<?php
// Application Configuration
define('APP_NAME', 'FamilyBoard');
define('BASE_PATH', dirname(__DIR__));
// Assets version — auto-updates whenever app.css is modified (no manual bump needed)
define('APP_VERSION', @filemtime(BASE_PATH . '/public/css/app.css') ?: '1');

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
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Session
define('SESSION_LIFETIME', 86400 * 30); // 30 days

// Admin credentials (override in config.local.php)
if (!defined('ADMIN_USER')) define('ADMIN_USER', getenv('ADMIN_USER') ?: 'admin');
if (!defined('ADMIN_PASS')) define('ADMIN_PASS', getenv('ADMIN_PASS') ?: 'changeme');
