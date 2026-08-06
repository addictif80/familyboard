<?php
// Application Configuration
define('APP_NAME', 'FamilyBoard');
define('BASE_PATH', dirname(__DIR__));
// Dépôt GitHub public utilisé pour distribuer l'APK Android (release "android-latest",
// republiée à chaque build par .github/workflows/android-release.yml).
define('GITHUB_REPO', 'addictif80/familyboard');
// Assets version — auto-updates whenever any CSS/JS asset changes (no manual
// bump needed). Must cover every file under public/css and public/js, not
// just app.css: the service worker caches all /public/ assets together under
// one version-tagged bucket (see sw.js), so a JS-only fix that doesn't bump
// this value would silently never reach browsers with an already-primed cache.
$__assetsVersion = 0;
foreach ([glob(BASE_PATH . '/public/css/*.css') ?: [], glob(BASE_PATH . '/public/js/*.js') ?: []] as $__group) {
    foreach ($__group as $__file) {
        $__assetsVersion = max($__assetsVersion, @filemtime($__file) ?: 0);
    }
}
define('APP_VERSION', $__assetsVersion ?: 1);
unset($__assetsVersion, $__group, $__file);

// Load local overrides FIRST so they take priority
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// BASE_URL: path prefix only (no protocol/domain), e.g. '' or '/familyboard'
// Can be overridden in config.local.php with: define('BASE_URL', '/subdir');
if (!defined('BASE_URL')) define('BASE_URL', '');

// Database defaults (used only if not defined in config.local.php)
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: '3306');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'familyboard');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Assets URL (chemin vers le dossier public/)
define('ASSETS_URL', BASE_URL . '/public');

// Upload settings
define('UPLOAD_DIR', BASE_PATH . '/public/uploads/');
define('UPLOAD_MAX_SIZE', 20 * 1024 * 1024); // 20MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Ensure uploads directory exists and is writable by the web server
if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0777, true);
} elseif (!is_writable(UPLOAD_DIR)) {
    @chmod(UPLOAD_DIR, 0777);
}

// Session
define('SESSION_LIFETIME', 86400 * 30); // 30 days

// Admin credentials (override in config.local.php)
if (!defined('ADMIN_USER')) define('ADMIN_USER', getenv('ADMIN_USER') ?: 'admin');
if (!defined('ADMIN_PASS')) define('ADMIN_PASS', getenv('ADMIN_PASS') ?: 'changeme');

// Clé de chiffrement applicative (secrets 2FA au repos, voir src/Core/Crypto.php). Recommandée
// en production (config.local.php ou variable d'environnement APP_KEY) : sans elle une clé est
// générée automatiquement dans storage/app_key.bin, ce qui fonctionne mais ne survit pas à une
// suppression du dossier storage/ (les secrets TOTP déjà chiffrés deviendraient illisibles).
if (!defined('APP_ENCRYPTION_KEY')) define('APP_ENCRYPTION_KEY', getenv('APP_KEY') ?: '');

// Désactivé par défaut : le détail d'une exception (souvent des fragments de requête SQL, des
// chemins serveur...) ne doit jamais atteindre le navigateur d'un visiteur en production.
if (!defined('APP_DEBUG')) define('APP_DEBUG', getenv('APP_DEBUG') === '1');
