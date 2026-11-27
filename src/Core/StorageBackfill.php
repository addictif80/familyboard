<?php
namespace App\Core;

use App\Models\StoredFile;

/**
 * Rattrapage stored_files : enregistre dans stored_files tous les fichiers déjà sur disque
 * avant l'introduction de App\Core\FileStore, pour que le quota familial et la consommation par
 * module reflètent la réalité. Idempotent (une ligne déjà enregistrée — même `path` — n'est
 * jamais dupliquée) et sans effet destructif sur les données existantes.
 *
 * Logique extraite de scripts/backfill_stored_files.php (toujours utilisable en CLI, qui
 * délègue maintenant ici) pour être aussi déclenchable depuis le panneau admin
 * (AdminController::runStorageBackfill) — aucun accès SSH requis pour l'exécuter.
 *
 * Deux groupes de sources :
 * - Groupe A (déjà hors webroot : documents, garanties, messages vocaux, coffre, deals,
 *   litiges, école, wishlist, liste de naissance) : simple enregistrement, aucun déplacement de
 *   fichier.
 * - Groupe B (legacy public/uploads/, servi directement par Apache sans contrôle d'accès :
 *   avatars, photos mur/album/grossesse) : en mode $apply=true, déplace physiquement le fichier
 *   vers storage/images/<module>/<family_id>/ et met à jour la colonne DB avec la nouvelle
 *   route "/files/{id}" — en simulation, rien n'est déplacé ni modifié, seulement rapporté.
 *
 * Grandfathering : une famille déjà au-delà de storage_family_quota_gb n'est jamais bloquée a
 * posteriori — seulement signalée dans le rapport, pour alerte admin.
 */
class StorageBackfill
{
    private array $stats = ['registered' => 0, 'skipped_existing' => 0, 'skipped_missing_file' => 0, 'moved' => 0, 'bytes' => 0];
    private array $perModule = [];
    private array $perFamilyQuota = [];
    private array $log = [];
    private array $excludedModules;
    private bool $apply;

    public function __construct(bool $apply)
    {
        $this->apply = $apply;
        $this->excludedModules = FileStore::excludedModules();
    }

    /** @return array{apply: bool, log: string[], stats: array, per_module: array, over_quota: array} */
    public function run(): array
    {
        $this->log[] = $this->apply
            ? '=== MODE APPLICATION (les fichiers legacy seront déplacés) ==='
            : "=== MODE SIMULATION (rien n'est modifié) ===";

        $this->log[] = '';
        $this->log[] = '-- Groupe A (documents, garanties, voix, coffre, deals, litiges, école, wishlist, naissance) --';
        $this->runGroupA();

        $this->log[] = '';
        $this->log[] = '-- Groupe B (avatars, photos mur/album/grossesse — legacy public/uploads/) --';
        $this->runGroupB();

        return [
            'apply'      => $this->apply,
            'log'        => $this->log,
            'stats'      => $this->stats,
            'per_module' => $this->perModuleSorted(),
            'over_quota' => $this->familiesOverQuota(),
        ];
    }

    private function perModuleSorted(): array
    {
        $m = $this->perModule;
        arsort($m);
        return $m;
    }

    private function familiesOverQuota(): array
    {
        $familyQuotaBytes = StorageQuota::familyQuotaGb() * 1024 * 1024 * 1024;
        $over = [];
        foreach ($this->perFamilyQuota as $familyId => $bytes) {
            if ($bytes > $familyQuotaBytes) {
                $over[] = ['family_id' => $familyId, 'bytes' => $bytes];
            }
        }
        usort($over, fn($a, $b) => $b['bytes'] <=> $a['bytes']);
        return $over;
    }

    private function recordStat(string $module, int $familyId, int $sizeBytes): void
    {
        $this->perModule[$module] = ($this->perModule[$module] ?? 0) + $sizeBytes;
        if (!in_array($module, $this->excludedModules, true)) {
            $this->perFamilyQuota[$familyId] = ($this->perFamilyQuota[$familyId] ?? 0) + $sizeBytes;
        }
        $this->stats['bytes'] += $sizeBytes;
        $this->stats['registered']++;
    }

    /** Groupe A : chemin déjà relatif à BASE_PATH, déjà hors webroot — enregistrement direct. */
    private function registerGroupA(string $module, string $path, int $familyId, ?int $userId, ?string $originalName, ?string $mime): void
    {
        if (StoredFile::findByPath($path)) { $this->stats['skipped_existing']++; return; }
        $abs = BASE_PATH . $path;
        if (!is_file($abs)) { $this->stats['skipped_missing_file']++; $this->log[] = "  ! fichier manquant sur disque, ignoré : $path"; return; }
        $size = (int)filesize($abs);
        if ($this->apply) {
            StoredFile::create($familyId, $userId, $module, $path, $originalName, $mime, $size);
        }
        $this->recordStat($module, $familyId, $size);
    }

    /** Groupe B : chemin legacy public/uploads/ (ou déjà une route "/files/{id}" si un
     *  rattrapage partiel a déjà tourné — alors ignoré). */
    private function migrateGroupB(string $table, int $rowId, string $col, string $module, string $value, int $familyId, ?int $userId): void
    {
        if ($value === '' || str_starts_with($value, FileStore::ROUTE_PREFIX)) return; // déjà migré

        $abs = BASE_PATH . $value;
        if (!is_file($abs)) { $this->stats['skipped_missing_file']++; $this->log[] = "  ! fichier manquant sur disque, ignoré : $value ($table#$rowId)"; return; }
        if (StoredFile::findByPath($value)) { $this->stats['skipped_existing']++; return; }

        $size = (int)filesize($abs);
        $this->recordStat($module, $familyId, $size);

        if (!$this->apply) { $this->log[] = "  -> déplacerait $value vers storage/images/$module/$familyId/ ($table#$rowId)"; return; }

        $ext = pathinfo($value, PATHINFO_EXTENSION) ?: 'jpg';
        $dir = BASE_PATH . '/storage/images/' . $module . '/' . $familyId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $filename;
        if (!@rename($abs, $dest)) { $this->log[] = "  ! échec du déplacement de $value"; return; }

        $relPath = '/storage/images/' . $module . '/' . $familyId . '/' . $filename;
        $mime = @mime_content_type($dest) ?: null;
        $id = StoredFile::create($familyId, $userId, $module, $relPath, basename($value), $mime, $size);
        $route = FileStore::ROUTE_PREFIX . $id;
        Database::execute("UPDATE `$table` SET `$col` = ? WHERE id = ?", [$route, $rowId]);
        $this->stats['moved']++;
        $this->log[] = "  -> déplacé et migré : $table#$rowId ($value -> $route)";
    }

    private function runGroupA(): void
    {
        foreach ([
            ['documents', 'file_path', 'file_original', 'file_mime', 'family_id', 'user_id', 'documents'],
            ['warranties', 'file_path', 'file_original', 'file_mime', 'family_id', 'user_id', 'warranties'],
            ['messages', 'audio_path', 'audio_original', 'audio_mime', 'family_id', 'user_id', 'voice'],
            ['comm_log_messages', 'audio_path', 'audio_original', 'audio_mime', 'family_id', 'user_id', 'voice'],
            ['vault_entries', 'file_path', 'file_original', 'file_mime', 'family_id', 'created_by', 'vault'],
            ['deals', 'file_path', 'file_original', 'file_mime', 'family_id', 'created_by', 'deals'],
            ['wishlist_items', 'image_path', null, null, 'family_id', 'user_id', 'wishlist'],
        ] as [$table, $col, $origCol, $mimeCol, $familyCol, $userCol, $module]) {
            $select = "id, `$col` AS p, `$familyCol` AS fam, `$userCol` AS uid"
                . ($origCol ? ", `$origCol` AS orig" : ", NULL AS orig")
                . ($mimeCol ? ", `$mimeCol` AS mime" : ", NULL AS mime");
            foreach (Database::fetchAll("SELECT $select FROM `$table` WHERE `$col` IS NOT NULL AND `$col` != ''") as $row) {
                $this->registerGroupA($module, $row['p'], (int)$row['fam'], $row['uid'] !== null ? (int)$row['uid'] : null, $row['orig'], $row['mime']);
            }
        }

        foreach (Database::fetchAll(
            'SELECT dd.id, dd.file_path AS p, dc.family_id AS fam, dd.uploaded_by AS uid, dd.file_original AS orig, dd.file_mime AS mime
             FROM dispute_documents dd JOIN dispute_cases dc ON dc.id = dd.dispute_id
             WHERE dd.file_path IS NOT NULL AND dd.file_path != \'\''
        ) as $row) {
            $this->registerGroupA('disputes', $row['p'], (int)$row['fam'], $row['uid'] !== null ? (int)$row['uid'] : null, $row['orig'], $row['mime']);
        }
        foreach (Database::fetchAll(
            'SELECT sd.id, sd.file_path AS p, ss.family_id AS fam, sd.uploaded_by AS uid, sd.file_original AS orig, sd.file_mime AS mime
             FROM school_documents sd JOIN school_students ss ON ss.id = sd.student_id
             WHERE sd.file_path IS NOT NULL AND sd.file_path != \'\''
        ) as $row) {
            $this->registerGroupA('school', $row['p'], (int)$row['fam'], $row['uid'] !== null ? (int)$row['uid'] : null, $row['orig'], $row['mime']);
        }
        foreach (Database::fetchAll(
            'SELECT bli.id, bli.image_path AS p, bl.family_id AS fam, bl.created_by AS uid
             FROM birth_list_items bli JOIN birth_lists bl ON bl.id = bli.birth_list_id
             WHERE bli.image_path IS NOT NULL AND bli.image_path != \'\''
        ) as $row) {
            if (str_starts_with($row['p'], '/storage/')) {
                $this->registerGroupA('birth-list', $row['p'], (int)$row['fam'], $row['uid'] !== null ? (int)$row['uid'] : null, null, null);
            }
        }
    }

    private function runGroupB(): void
    {
        foreach (Database::fetchAll("SELECT id, avatar AS p, family_id AS fam FROM users WHERE avatar IS NOT NULL AND avatar != ''") as $row) {
            $this->migrateGroupB('users', (int)$row['id'], 'avatar', 'avatar', $row['p'], (int)$row['fam'], (int)$row['id']);
        }
        foreach (Database::fetchAll("SELECT id, avatar AS p, family_id AS fam FROM babies WHERE avatar IS NOT NULL AND avatar != ''") as $row) {
            $this->migrateGroupB('babies', (int)$row['id'], 'avatar', 'avatar', $row['p'], (int)$row['fam'], null);
        }
        foreach (Database::fetchAll("SELECT id, avatar AS p, family_id AS fam FROM contacts WHERE avatar IS NOT NULL AND avatar != ''") as $row) {
            $this->migrateGroupB('contacts', (int)$row['id'], 'avatar', 'avatar', $row['p'], (int)$row['fam'], null);
        }
        foreach (Database::fetchAll("SELECT id, image_path AS p, family_id AS fam, user_id AS uid FROM posts WHERE image_path IS NOT NULL AND image_path != ''") as $row) {
            $this->migrateGroupB('posts', (int)$row['id'], 'image_path', 'wall', $row['p'], (int)$row['fam'], $row['uid'] !== null ? (int)$row['uid'] : null);
        }
        foreach (Database::fetchAll(
            "SELECT ap.id, ap.image_path AS p, al.family_id AS fam, ap.user_id AS uid
             FROM album_photos ap JOIN albums al ON al.id = ap.album_id
             WHERE ap.image_path IS NOT NULL AND ap.image_path != ''"
        ) as $row) {
            $this->migrateGroupB('album_photos', (int)$row['id'], 'image_path', 'album', $row['p'], (int)$row['fam'], $row['uid'] !== null ? (int)$row['uid'] : null);
        }
        foreach (Database::fetchAll("SELECT id, file_path AS p, family_id AS fam FROM pregnancy_images WHERE file_path IS NOT NULL AND file_path != ''") as $row) {
            if (str_starts_with($row['p'], '/public/')) {
                $this->migrateGroupB('pregnancy_images', (int)$row['id'], 'file_path', 'baby', $row['p'], (int)$row['fam'], null);
            } else {
                $this->registerGroupA('baby', $row['p'], (int)$row['fam'], null, null, null);
            }
        }
    }
}
