<?php
/**
 * Rattrapage stored_files : enregistre dans stored_files tous les fichiers déjà sur disque
 * avant l'introduction de App\Core\FileStore, pour que le quota familial et la consommation par
 * module reflètent la réalité. Idempotent (une ligne déjà enregistrée — même `path` — n'est
 * jamais dupliquée) et sans effet destructif sur les données existantes.
 *
 * Deux groupes de sources :
 * - Groupe A (déjà hors webroot : documents, garanties, messages vocaux, coffre, deals,
 *   litiges, école, wishlist, liste de naissance) : simple enregistrement, aucun déplacement de
 *   fichier.
 * - Groupe B (legacy public/uploads/, servi directement par Apache sans contrôle d'accès :
 *   avatars, photos mur/album/grossesse) : en mode --apply, déplace physiquement le fichier vers
 *   storage/images/<module>/<family_id>/ et met à jour la colonne DB avec la nouvelle route
 *   "/files/{id}" (voir App\Core\FileStore::ROUTE_PREFIX) — en mode --dry-run (défaut), rien
 *   n'est déplacé ni modifié, seulement rapporté.
 *
 * Grandfathering : une famille déjà au-delà de storage_family_quota_gb n'est jamais bloquée a
 * posteriori par ce script — seulement signalée dans le rapport, pour alerte admin.
 *
 * Usage :
 *   php scripts/backfill_stored_files.php            # simulation (rien n'est modifié)
 *   php scripts/backfill_stored_files.php --apply     # applique réellement
 */

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../vendor/autoload.php';
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../src/';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require $file;
});

use App\Core\Database;
use App\Core\FileStore;
use App\Models\StoredFile;

$apply = in_array('--apply', $argv, true);
echo $apply ? "=== MODE APPLICATION (les fichiers legacy seront déplacés) ===\n\n" : "=== MODE SIMULATION (--dry-run, rien n'est modifié — passez --apply pour exécuter) ===\n\n";

$stats = ['registered' => 0, 'skipped_existing' => 0, 'skipped_missing_file' => 0, 'moved' => 0, 'bytes' => 0];
$perModule = [];
$perFamilyQuota = []; // modules exclus (avatars...) non comptés, comme FileStore::enforceQuota()
$excludedModulesForReport = FileStore::excludedModules();

function recordStat(string $module, int $familyId, int $sizeBytes): void
{
    global $perModule, $perFamilyQuota, $stats, $excludedModulesForReport;
    $perModule[$module] = ($perModule[$module] ?? 0) + $sizeBytes;
    if (!in_array($module, $excludedModulesForReport, true)) {
        $perFamilyQuota[$familyId] = ($perFamilyQuota[$familyId] ?? 0) + $sizeBytes;
    }
    $stats['bytes'] += $sizeBytes;
    $stats['registered']++;
}

/** Groupe A : chemin déjà relatif à BASE_PATH, déjà hors webroot — enregistrement direct. */
function registerGroupA(string $module, string $path, int $familyId, ?int $userId, ?string $originalName, ?string $mime, bool $apply): void
{
    global $stats;
    if (StoredFile::findByPath($path)) { $stats['skipped_existing']++; return; }
    $abs = BASE_PATH . $path;
    if (!is_file($abs)) { $stats['skipped_missing_file']++; echo "  ! fichier manquant sur disque, ignoré : $path\n"; return; }
    $size = (int)filesize($abs);
    if ($apply) {
        StoredFile::create($familyId, $userId, $module, $path, $originalName, $mime, $size);
    }
    recordStat($module, $familyId, $size);
}

/** Groupe B : chemin legacy public/uploads/ (ou déjà une route "/files/{id}" si un rattrapage
 *  partiel a déjà tourné — alors ignoré) — déplace le fichier hors webroot en mode --apply et
 *  retourne la nouvelle route à écrire dans $table.$col, ou null si rien à faire. */
function migrateGroupB(string $table, int $rowId, string $col, string $module, string $value, int $familyId, ?int $userId, bool $apply): void
{
    global $stats;
    if ($value === '' || str_starts_with($value, FileStore::ROUTE_PREFIX)) return; // déjà migré

    $abs = BASE_PATH . $value;
    if (!is_file($abs)) { $stats['skipped_missing_file']++; echo "  ! fichier manquant sur disque, ignoré : $value ($table#$rowId)\n"; return; }
    if (StoredFile::findByPath($value)) { $stats['skipped_existing']++; return; }

    $size = (int)filesize($abs);
    recordStat($module, $familyId, $size);

    if (!$apply) { echo "  -> déplacerait $value vers storage/images/$module/$familyId/ ($table#$rowId)\n"; return; }

    $ext = pathinfo($value, PATHINFO_EXTENSION) ?: 'jpg';
    $dir = BASE_PATH . '/storage/images/' . $module . '/' . $familyId;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = $dir . '/' . $filename;
    if (!@rename($abs, $dest)) { echo "  ! échec du déplacement de $value\n"; return; }

    $relPath = '/storage/images/' . $module . '/' . $familyId . '/' . $filename;
    $mime = @mime_content_type($dest) ?: null;
    $id = StoredFile::create($familyId, $userId, $module, $relPath, basename($value), $mime, $size);
    $route = FileStore::ROUTE_PREFIX . $id;
    Database::execute("UPDATE `$table` SET `$col` = ? WHERE id = ?", [$route, $rowId]);
    $stats['moved']++;
    echo "  -> déplacé et migré : $table#$rowId ($value -> $route)\n";
}

// ── Groupe A ──────────────────────────────────────────────────────────────

echo "-- Groupe A (documents, garanties, voix, coffre, deals, litiges, école, wishlist, naissance) --\n";

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
        registerGroupA($module, $row['p'], (int)$row['fam'], $row['uid'] !== null ? (int)$row['uid'] : null, $row['orig'], $row['mime'], $apply);
    }
}

// Tables enfant sans family_id direct (jointure requise).
foreach (Database::fetchAll(
    'SELECT dd.id, dd.file_path AS p, dc.family_id AS fam, dd.uploaded_by AS uid, dd.file_original AS orig, dd.file_mime AS mime
     FROM dispute_documents dd JOIN dispute_cases dc ON dc.id = dd.dispute_id
     WHERE dd.file_path IS NOT NULL AND dd.file_path != \'\''
) as $row) {
    registerGroupA('disputes', $row['p'], (int)$row['fam'], $row['uid'] !== null ? (int)$row['uid'] : null, $row['orig'], $row['mime'], $apply);
}
foreach (Database::fetchAll(
    'SELECT sd.id, sd.file_path AS p, ss.family_id AS fam, sd.uploaded_by AS uid, sd.file_original AS orig, sd.file_mime AS mime
     FROM school_documents sd JOIN school_students ss ON ss.id = sd.student_id
     WHERE sd.file_path IS NOT NULL AND sd.file_path != \'\''
) as $row) {
    registerGroupA('school', $row['p'], (int)$row['fam'], $row['uid'] !== null ? (int)$row['uid'] : null, $row['orig'], $row['mime'], $apply);
}
foreach (Database::fetchAll(
    'SELECT bli.id, bli.image_path AS p, bl.family_id AS fam, bl.created_by AS uid
     FROM birth_list_items bli JOIN birth_lists bl ON bl.id = bli.birth_list_id
     WHERE bli.image_path IS NOT NULL AND bli.image_path != \'\''
) as $row) {
    // Un image_path de liste de naissance peut aussi provenir d'un aperçu de lien externe
    // (LinkPreview, écrit en legacy dans public/uploads/) plutôt que d'un envoi — traité en
    // Groupe A quand même : le fichier existe déjà hors webroot ou dans public/uploads, dans
    // les deux cas is_file()/filesize() fonctionnent, seule la sécurité d'accès diffère (non
    // traitée ici, ces aperçus restent hors périmètre, comme les images CMS admin).
    if (str_starts_with($row['p'], '/storage/')) {
        registerGroupA('birth-list', $row['p'], (int)$row['fam'], $row['uid'] !== null ? (int)$row['uid'] : null, null, null, $apply);
    }
}

// ── Groupe B ──────────────────────────────────────────────────────────────

echo "\n-- Groupe B (avatars, photos mur/album/grossesse — legacy public/uploads/) --\n";

foreach (Database::fetchAll("SELECT id, avatar AS p, family_id AS fam FROM users WHERE avatar IS NOT NULL AND avatar != ''") as $row) {
    migrateGroupB('users', (int)$row['id'], 'avatar', 'avatar', $row['p'], (int)$row['fam'], (int)$row['id'], $apply);
}
foreach (Database::fetchAll("SELECT id, avatar AS p, family_id AS fam FROM babies WHERE avatar IS NOT NULL AND avatar != ''") as $row) {
    migrateGroupB('babies', (int)$row['id'], 'avatar', 'avatar', $row['p'], (int)$row['fam'], null, $apply);
}
foreach (Database::fetchAll("SELECT id, avatar AS p, family_id AS fam FROM contacts WHERE avatar IS NOT NULL AND avatar != ''") as $row) {
    migrateGroupB('contacts', (int)$row['id'], 'avatar', 'avatar', $row['p'], (int)$row['fam'], null, $apply);
}
foreach (Database::fetchAll("SELECT id, image_path AS p, family_id AS fam, user_id AS uid FROM posts WHERE image_path IS NOT NULL AND image_path != ''") as $row) {
    migrateGroupB('posts', (int)$row['id'], 'image_path', 'wall', $row['p'], (int)$row['fam'], $row['uid'] !== null ? (int)$row['uid'] : null, $apply);
}
foreach (Database::fetchAll(
    "SELECT ap.id, ap.image_path AS p, al.family_id AS fam, ap.user_id AS uid
     FROM album_photos ap JOIN albums al ON al.id = ap.album_id
     WHERE ap.image_path IS NOT NULL AND ap.image_path != ''"
) as $row) {
    migrateGroupB('album_photos', (int)$row['id'], 'image_path', 'album', $row['p'], (int)$row['fam'], $row['uid'] !== null ? (int)$row['uid'] : null, $apply);
}
foreach (Database::fetchAll("SELECT id, file_path AS p, family_id AS fam FROM pregnancy_images WHERE file_path IS NOT NULL AND file_path != ''") as $row) {
    if (str_starts_with($row['p'], '/public/')) {
        migrateGroupB('pregnancy_images', (int)$row['id'], 'file_path', 'baby', $row['p'], (int)$row['fam'], null, $apply);
    } else {
        registerGroupA('baby', $row['p'], (int)$row['fam'], null, null, null, $apply);
    }
}

// ── Rapport ───────────────────────────────────────────────────────────────

echo "\n=== Rapport ===\n";
echo "Fichiers enregistrés : {$stats['registered']}\n";
echo "Déjà enregistrés (ignorés) : {$stats['skipped_existing']}\n";
echo "Fichiers manquants sur disque (ignorés) : {$stats['skipped_missing_file']}\n";
echo "Fichiers déplacés hors webroot : {$stats['moved']}\n";
echo "Volume total : " . round($stats['bytes'] / 1024 / 1024, 1) . " Mo\n";

echo "\nPar module :\n";
arsort($perModule);
foreach ($perModule as $module => $bytes) {
    echo "  - $module : " . round($bytes / 1024 / 1024, 1) . " Mo\n";
}

$familyQuotaBytes = \App\Core\StorageQuota::familyQuotaGb() * 1024 * 1024 * 1024;
echo "\nFamilles dépassant le quota familial (" . \App\Core\StorageQuota::familyQuotaGb() . " Go, modules exclus : " . implode(',', $excludedModulesForReport) . ") — non bloquées (grandfathering), à surveiller :\n";
$overQuotaCount = 0;
foreach ($perFamilyQuota as $familyId => $bytes) {
    if ($bytes > $familyQuotaBytes) {
        $overQuotaCount++;
        echo "  ! famille #$familyId : " . round($bytes / 1024 / 1024 / 1024, 2) . " Go\n";
    }
}
if ($overQuotaCount === 0) echo "  (aucune)\n";

echo "\n" . ($apply ? "Terminé." : "Simulation terminée — relancez avec --apply pour appliquer.") . "\n";
