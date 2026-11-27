<?php
namespace App\Core;

use App\Models\StoredFile;

/**
 * Rattrapage ponctuel des références par chemin brut (documents.file_path, warranties.file_path,
 * ...) cassées par le bug corrigé dans FileStore::repointReference() : avant ce correctif, un
 * renommage ou une migration vers Nextcloud mettait à jour stored_files.path SANS répercuter le
 * changement dans la colonne du module, qui gardait l'ancienne valeur — resolveLocalPath()
 * devenait alors incapable de retrouver la ligne stored_files, bien que le fichier existe
 * toujours réellement sur Nextcloud.
 *
 * Pour chaque ligne dont la colonne ne correspond plus à aucune ligne stored_files (référence
 * cassée), cherche une ligne stored_files candidate sur la même famille + le même module, non
 * déjà réclamée par une autre ligne de la même table dans cette passe — en comparant le nom
 * original quand la table en garde un (file_original/audio_original). Quand la table n'en garde
 * pas (wishlist_items/birth_list_items, qui ne stockent que l'image) : rapprochement PAR ORDRE
 * d'identifiant au sein de la même famille (la ligne et son fichier sont créés dans la même
 * requête, donc dans le même ordre chronologique) — uniquement quand le nombre de lignes cassées
 * égale exactement le nombre de fichiers orphelins disponibles pour cette famille, sinon jamais
 * deviné. Une correspondance ambiguë (plusieurs candidats par nom, ou comptes différents par
 * ordre) n'est JAMAIS corrigée automatiquement — elle est seulement signalée, pour une
 * vérification manuelle. Simulation par défaut (rien n'est modifié) ; --apply pour appliquer.
 */
class RawPathRepair
{
    private const TARGETS = [
        ['module' => 'documents',  'table' => 'documents',         'pathCol' => 'file_path',  'origCol' => 'file_original',  'join' => '', 'familyExpr' => 't.family_id'],
        ['module' => 'warranties', 'table' => 'warranties',        'pathCol' => 'file_path',  'origCol' => 'file_original',  'join' => '', 'familyExpr' => 't.family_id'],
        ['module' => 'deals',      'table' => 'deals',             'pathCol' => 'file_path',  'origCol' => 'file_original',  'join' => '', 'familyExpr' => 't.family_id'],
        ['module' => 'vault',      'table' => 'vault_entries',     'pathCol' => 'file_path',  'origCol' => 'file_original',  'join' => '', 'familyExpr' => 't.family_id'],
        ['module' => 'wishlist',   'table' => 'wishlist_items',    'pathCol' => 'image_path', 'origCol' => null,             'join' => '', 'familyExpr' => 't.family_id'],
        ['module' => 'voice',      'table' => 'messages',          'pathCol' => 'audio_path', 'origCol' => 'audio_original', 'join' => '', 'familyExpr' => 't.family_id'],
        ['module' => 'voice',      'table' => 'comm_log_messages', 'pathCol' => 'audio_path', 'origCol' => 'audio_original', 'join' => '', 'familyExpr' => 't.family_id'],
        ['module' => 'school',     'table' => 'school_documents',  'pathCol' => 'file_path',  'origCol' => 'file_original',
            'join' => 'JOIN school_students ss ON ss.id = t.student_id', 'familyExpr' => 'ss.family_id'],
        ['module' => 'disputes',   'table' => 'dispute_documents', 'pathCol' => 'file_path',  'origCol' => 'file_original',
            'join' => 'JOIN dispute_cases dc ON dc.id = t.dispute_id', 'familyExpr' => 'dc.family_id'],
        ['module' => 'birth-list', 'table' => 'birth_list_items',  'pathCol' => 'image_path', 'origCol' => null,
            'join' => 'JOIN birth_lists bl ON bl.id = t.birth_list_id', 'familyExpr' => 'bl.family_id'],
    ];

    public function __construct(private bool $apply) {}

    public function run(): array
    {
        $stats = ['checked' => 0, 'already_ok' => 0, 'repaired' => 0, 'no_candidate' => 0, 'ambiguous' => 0];
        $log = [];

        foreach (self::TARGETS as $target) {
            $sql = "SELECT t.id AS row_id, {$target['familyExpr']} AS family_id, t.`{$target['pathCol']}` AS path"
                . ($target['origCol'] ? ", t.`{$target['origCol']}` AS orig_name" : '')
                . " FROM `{$target['table']}` t {$target['join']} WHERE t.`{$target['pathCol']}` IS NOT NULL AND t.`{$target['pathCol']}` != ''";
            $rows = Database::fetchAll($sql);

            // Première passe : identifie les lignes stored_files déjà correctement référencées
            // par CETTE table, pour ne jamais les proposer comme candidat à une autre ligne.
            $claimedStoredFileIds = [];
            $broken = [];
            foreach ($rows as $row) {
                $stats['checked']++;
                $match = StoredFile::findByPath($row['path']);
                if ($match) {
                    $claimedStoredFileIds[(int)$match['id']] = true;
                    $stats['already_ok']++;
                } else {
                    $broken[] = $row;
                }
            }

            if ($target['origCol']) {
                foreach ($broken as $row) {
                    $candidates = array_values(array_filter(
                        Database::fetchAll(
                            'SELECT * FROM stored_files WHERE family_id = ? AND module = ? AND original_name <=> ?',
                            [(int)$row['family_id'], $target['module'], $row['orig_name']]
                        ),
                        fn($c) => !isset($claimedStoredFileIds[(int)$c['id']])
                    ));

                    if (count($candidates) === 0) {
                        $stats['no_candidate']++;
                        $log[] = "AUCUNE CORRESPONDANCE : {$target['table']}#{$row['row_id']} (famille {$row['family_id']}) : {$row['path']}";
                        continue;
                    }
                    if (count($candidates) > 1) {
                        $stats['ambiguous']++;
                        $log[] = "AMBIGU (" . count($candidates) . " candidats, non corrigé) : {$target['table']}#{$row['row_id']} : {$row['path']}";
                        continue;
                    }

                    $newPath = $candidates[0]['path'];
                    $claimedStoredFileIds[(int)$candidates[0]['id']] = true;
                    if ($this->apply) {
                        Database::execute("UPDATE `{$target['table']}` SET `{$target['pathCol']}` = ? WHERE id = ?", [$newPath, $row['row_id']]);
                    }
                    $stats['repaired']++;
                    $log[] = ($this->apply ? 'OK' : '[simulation] corrigerait') . " {$target['table']}#{$row['row_id']} : {$row['path']} -> $newPath";
                }
                continue;
            }

            // Pas de nom original à comparer (wishlist_items/birth_list_items) : rapprochement
            // par ordre d'identifiant, séparément par famille, uniquement quand les deux comptes
            // correspondent exactement pour cette famille.
            $brokenByFamily = [];
            foreach ($broken as $row) $brokenByFamily[(int)$row['family_id']][] = $row;

            foreach ($brokenByFamily as $familyId => $familyBroken) {
                $pool = array_values(array_filter(
                    Database::fetchAll('SELECT * FROM stored_files WHERE family_id = ? AND module = ?', [$familyId, $target['module']]),
                    fn($c) => !isset($claimedStoredFileIds[(int)$c['id']])
                ));

                if (count($pool) !== count($familyBroken)) {
                    foreach ($familyBroken as $row) {
                        $stats['ambiguous']++;
                        $log[] = "AMBIGU (" . count($pool) . " candidats pour " . count($familyBroken) . " lignes, comptes différents, non corrigé) : {$target['table']}#{$row['row_id']} : {$row['path']}";
                    }
                    continue;
                }

                usort($familyBroken, fn($a, $b) => $a['row_id'] <=> $b['row_id']);
                usort($pool, fn($a, $b) => (int)$a['id'] <=> (int)$b['id']);

                foreach ($familyBroken as $i => $row) {
                    $newPath = $pool[$i]['path'];
                    $claimedStoredFileIds[(int)$pool[$i]['id']] = true;
                    if ($this->apply) {
                        Database::execute("UPDATE `{$target['table']}` SET `{$target['pathCol']}` = ? WHERE id = ?", [$newPath, $row['row_id']]);
                    }
                    $stats['repaired']++;
                    $log[] = ($this->apply ? 'OK (ordre)' : '[simulation, ordre] corrigerait') . " {$target['table']}#{$row['row_id']} : {$row['path']} -> $newPath";
                }
            }
        }

        return ['stats' => $stats, 'log' => $log, 'apply' => $this->apply];
    }
}
