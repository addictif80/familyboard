<?php
/**
 * Reset admin credentials stored in app_settings.
 * Run once from CLI: php tools/reset-admin-credentials.php
 * DELETE THIS FILE afterwards.
 */

// Garde de défense en profondeur : ce script ne doit jamais être exécutable via le web (un
// .htaccess bloque déjà l'accès direct au dossier tools/, mais toutes les configurations
// serveur ne respectent pas .htaccess — ex. Nginx sans traduction équivalente).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Accès refusé : ce script ne peut être exécuté qu\'en ligne de commande.');
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config/config.php';
require BASE_PATH . '/src/Core/Database.php';

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("DELETE FROM app_settings WHERE `key` IN ('admin_username','admin_password_hash')");

echo "✅ Credentials réinitialisés.\n";
echo "Connectez-vous maintenant avec :\n";
echo "  Utilisateur : " . ADMIN_USER . "\n";
echo "  Mot de passe : " . ADMIN_PASS . "\n";
echo "\n⚠️  Supprimez ce fichier après utilisation : rm tools/reset-admin-credentials.php\n";
