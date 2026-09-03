<?php
namespace App\Core;

use App\Models\DataExport;
use App\Models\FamilySubscription;

/**
 * Suppression DÉFINITIVE des données des modules premium d'une famille, à l'expiration du délai
 * de rétention suivant un impayé/essai non converti (voir FamilySubscription::isEntitled() pour
 * le blocage d'accès immédiat, distinct de cette purge qui n'intervient que grace_days plus
 * tard). Suit le même style que App\Models\AccountDeletion (collecte des chemins de fichiers
 * AVANT suppression des lignes, unlink un par un, puis nettoyage des dossiers storage/<sous-
 * dossier>/<familyId>/ le cas échéant).
 *
 * Ne couvre volontairement que les tables "racine" de chaque module — toutes les tables filles
 * (photos d'album, notes/absences école, documents de litige, journal de garde alternée...) sont
 * déclarées ON DELETE CASCADE en base sur ces racines, donc disparaissent automatiquement. Seule
 * exception : custody_activity_log, qui n'a jamais de FK vers custody_schedules par conception
 * (le journal doit survivre à la suppression d'un planning) — supprimé explicitement ici.
 *
 * additions/addition_guests est un cas à part volontairement absent : addition_guests est une
 * identité d'invité globale, réutilisable à travers plusieurs familles (un même lien magique
 * donne accès à tout son espace additions) — ne jamais la supprimer depuis un flux par famille.
 */
class PremiumDataPurge
{
    /** module slug (Family::MODULES) => tables directement filtrées par family_id à supprimer.
     *  'family-wall' est un simple écran d'agrégation en lecture d'autres modules : aucune table
     *  propre, volontairement absent de ce registre. */
    private const MODULE_ROOT_TABLES = [
        'albums'     => ['albums'],
        'custody'    => ['custody_schedules', 'custody_checklist_items', 'custody_activity_log'],
        'budget'     => ['budget_transactions', 'budget_recurring', 'budget_goals', 'budget_categories'],
        'projects'   => ['projects'],
        'warranties' => ['warranties'],
        'documents'  => ['documents'],
        'baby'       => ['babies', 'pregnancies'],
        'location'   => ['location_checkins', 'saved_places'],
        'emergency'  => ['emergency_cards'],
        'comm_log'   => ['comm_log_messages'],
        'meals'      => ['meal_plans', 'recipes'],
        'sitter'     => ['sitter_links'],
        'kiosk'      => ['kiosk_links'],
        'wishlist'   => ['wishlist_items'],
        'polls'      => ['polls'],
        'links'      => ['portal_links'],
        'additions'  => ['additions'],
        'letters'    => ['letters', 'letter_templates'],
        'disputes'   => ['dispute_cases'],
        'school'     => ['school_students'],
    ];

    /** Requêtes de collecte des chemins de fichiers À EXÉCUTER AVANT la suppression des lignes
     *  (certaines portent sur des tables filles qui disparaîtront en cascade). 'dir' marque un
     *  fichier rangé dans storage/<dir>/<familyId>/ (nettoyé en bloc ensuite) ; sans 'dir' le
     *  fichier est dans /public/uploads/ à la racine (nettoyé fichier par fichier uniquement). */
    private static function filePathQueries(): array
    {
        return [
            'albums' => [
                ['sql' => 'SELECT ap.image_path p FROM album_photos ap JOIN albums a ON a.id=ap.album_id WHERE a.family_id=?'],
            ],
            'warranties' => [
                ['sql' => 'SELECT file_path p FROM warranties WHERE family_id=?', 'dir' => 'warranties'],
            ],
            'documents' => [
                ['sql' => 'SELECT file_path p FROM documents WHERE family_id=?', 'dir' => 'documents'],
            ],
            'baby' => [
                ['sql' => 'SELECT avatar p FROM babies WHERE family_id=?'],
                ['sql' => 'SELECT file_path p FROM pregnancy_images WHERE family_id=?'],
            ],
            'comm_log' => [
                ['sql' => 'SELECT audio_path p FROM comm_log_messages WHERE family_id=? AND audio_path IS NOT NULL', 'dir' => 'voice'],
            ],
            'links' => [
                ['sql' => 'SELECT image_path p FROM portal_links WHERE family_id=?'],
            ],
            'disputes' => [
                ['sql' => 'SELECT dd.file_path p FROM dispute_documents dd JOIN dispute_cases dc ON dc.id=dd.dispute_id WHERE dc.family_id=?', 'dir' => 'disputes'],
            ],
            'school' => [
                ['sql' => 'SELECT sd.file_path p FROM school_documents sd JOIN school_students ss ON ss.id=sd.student_id WHERE ss.family_id=?', 'dir' => 'school'],
            ],
        ];
    }

    /** Modules actuellement "premium" (hors socle gratuit configuré) parmi ceux qui ont
     *  effectivement des données propres à purger. */
    public static function premiumModulesWithData(): array
    {
        $free = FamilySubscription::freeModules();
        return array_values(array_diff(array_keys(self::MODULE_ROOT_TABLES), $free));
    }

    /** Génère un instantané HTML des données premium d'une famille (avant suppression) — voir
     *  DataExport::buildHtmlPage(), déjà utilisé pour le même usage lors d'une suppression de
     *  compte utilisateur. */
    public static function exportHtml(int $familyId, array $moduleSlugs, string $familyName): string
    {
        $data = [];
        foreach ($moduleSlugs as $slug) {
            foreach (self::MODULE_ROOT_TABLES[$slug] ?? [] as $table) {
                $rows = Database::fetchAll("SELECT * FROM `$table` WHERE family_id=?", [$familyId]);
                if ($rows) $data[$slug . '_' . $table] = $rows;
            }
        }
        return DataExport::buildHtmlPage($data, $familyName . ' (modules premium)');
    }

    /** Purge définitive. Retourne la liste des modules effectivement purgés (pour le journal
     *  d'audit et l'email de confirmation). Idempotent au niveau de chaque requête (WHERE
     *  family_id=? sur une table déjà vidée ne fait simplement rien). */
    public static function purge(int $familyId): array
    {
        $modules = self::premiumModulesWithData();
        if (!$modules) return [];

        $fileQueries = self::filePathQueries();
        $dirsToRemove = [];

        foreach ($modules as $slug) {
            foreach ($fileQueries[$slug] ?? [] as $q) {
                foreach (Database::fetchAll($q['sql'], [$familyId]) as $row) {
                    if (!empty($row['p'])) {
                        $abs = BASE_PATH . $row['p'];
                        if (is_file($abs)) @unlink($abs);
                    }
                }
                if (!empty($q['dir'])) $dirsToRemove[$q['dir']] = true;
            }
        }

        foreach ($modules as $slug) {
            foreach (self::MODULE_ROOT_TABLES[$slug] as $table) {
                Database::execute("DELETE FROM `$table` WHERE family_id=?", [$familyId]);
            }
        }

        foreach (array_keys($dirsToRemove) as $dir) {
            self::removeDirRecursive(BASE_PATH . "/storage/$dir/$familyId");
        }

        return $modules;
    }

    private static function removeDirRecursive(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? self::removeDirRecursive($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
