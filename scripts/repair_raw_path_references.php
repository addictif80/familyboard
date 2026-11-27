<?php
/**
 * Enveloppe CLI autour de App\Core\RawPathRepair — rattrape les références par chemin brut
 * (documents.file_path, warranties.file_path, ...) cassées par un renommage/migration Nextcloud
 * antérieur au correctif de FileStore::repointReference() (voir ce fichier pour le détail du bug).
 *
 * Usage :
 *   php scripts/repair_raw_path_references.php            # simulation (rien n'est modifié)
 *   php scripts/repair_raw_path_references.php --apply     # corrige réellement les références
 *
 * Vérifiez toujours la simulation avant --apply : une correspondance ambiguë ou absente est
 * signalée mais jamais devinée — ces lignes-là nécessitent une vérification manuelle (comparer le
 * fichier sur Nextcloud au contenu attendu avant de corriger la colonne à la main en base).
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
$result = (new \App\Core\RawPathRepair($apply))->run();

foreach ($result['log'] as $line) {
    echo $line . "\n";
}

echo "\n=== Rapport ===\n";
echo "Références vérifiées : {$result['stats']['checked']}\n";
echo "Déjà correctes : {$result['stats']['already_ok']}\n";
echo "Corrigées : {$result['stats']['repaired']}\n";
echo "Sans candidat (fichier probablement perdu) : {$result['stats']['no_candidate']}\n";
echo "Ambiguës (plusieurs candidats, non corrigées) : {$result['stats']['ambiguous']}\n";

echo "\n" . ($apply ? "Terminé." : "Simulation terminée — relancez avec --apply pour corriger réellement.") . "\n";
