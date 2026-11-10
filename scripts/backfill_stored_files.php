<?php
/**
 * Enveloppe CLI autour de App\Core\StorageBackfill (logique partagée avec le déclenchement
 * depuis le panneau admin, voir AdminController::runStorageBackfill — aucun accès SSH requis
 * pour exécuter ce rattrapage, seulement pratique pour qui préfère la ligne de commande).
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

$apply = in_array('--apply', $argv, true);
$result = (new \App\Core\StorageBackfill($apply))->run();

foreach ($result['log'] as $line) {
    echo $line . "\n";
}

echo "\n=== Rapport ===\n";
echo "Fichiers enregistrés : {$result['stats']['registered']}\n";
echo "Déjà enregistrés (ignorés) : {$result['stats']['skipped_existing']}\n";
echo "Fichiers manquants sur disque (ignorés) : {$result['stats']['skipped_missing_file']}\n";
echo "Fichiers déplacés hors webroot : {$result['stats']['moved']}\n";
echo "Volume total : " . round($result['stats']['bytes'] / 1024 / 1024, 1) . " Mo\n";

echo "\nPar module :\n";
foreach ($result['per_module'] as $module => $bytes) {
    echo "  - $module : " . round($bytes / 1024 / 1024, 1) . " Mo\n";
}

$excluded = \App\Core\FileStore::excludedModules();
echo "\nFamilles dépassant le quota familial (" . \App\Core\StorageQuota::familyQuotaGb() . " Go, modules exclus : " . implode(',', $excluded) . ") — non bloquées (grandfathering), à surveiller :\n";
if (empty($result['over_quota'])) {
    echo "  (aucune)\n";
} else {
    foreach ($result['over_quota'] as $fam) {
        echo "  ! famille #{$fam['family_id']} : " . round($fam['bytes'] / 1024 / 1024 / 1024, 2) . " Go\n";
    }
}

echo "\n" . ($apply ? "Terminé." : "Simulation terminée — relancez avec --apply pour appliquer.") . "\n";
