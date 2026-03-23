<?php
// Application Configuration
define('APP_NAME', 'FamilyBoard');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', ''); // e.g. /familyboard or empty if at root

// Load local overrides FIRST so they take priority
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

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
