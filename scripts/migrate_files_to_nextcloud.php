<?php
/**
 * Enveloppe CLI autour de App\Core\NextcloudFileMigration (logique partagée avec le
 * déclenchement depuis le panneau admin, voir AdminController::runNextcloudFileMigration —
 * aucun accès SSH requis pour exécuter cette migration, seulement pratique pour qui préfère la
 * ligne de commande).
 *
 * Usage :
 *   php scripts/migrate_files_to_nextcloud.php            # simulation (rien n'est modifié)
 *   php scripts/migrate_files_to_nextcloud.php --apply     # transfère réellement le contenu
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

if (!\App\Core\FileStore::nextcloudFilesEnabled()) {
    echo "storage_nextcloud_files_enabled est désactivé — activez-le depuis le panneau admin (onglet Stockage) avant de lancer cette migration.\n";
    exit(1);
}

$result = (new \App\Core\NextcloudFileMigration($apply))->run();

foreach ($result['log'] as $line) {
    echo $line . "\n";
}

echo "\n=== Rapport ===\n";
echo "Fichiers migrés : {$result['stats']['migrated']}\n";
echo "Introuvables sur disque (ignorés) : {$result['stats']['skipped_missing']}\n";
echo "Échecs : {$result['stats']['failed']}\n";
echo "Volume total : " . round($result['stats']['bytes'] / 1024 / 1024, 1) . " Mo\n";

if (!empty($result['failures'])) {
    echo "\nDétail des échecs :\n";
    foreach ($result['failures'] as $f) echo "  ! $f\n";
}

echo "\n" . ($apply ? "Terminé." : "Simulation terminée — relancez avec --apply pour transférer réellement le contenu.") . "\n";
