<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></title>
    <!-- Apply saved theme before first paint to avoid a flash of the wrong theme -->
    <script>
    (function () {
        try {
            var stored = localStorage.getItem('fb-theme');
            var theme = stored || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        } catch (e) {}
    })();
    </script>
    <!-- Favicon -->
    <link rel="icon" href="<?= BASE_URL ?>/public/icons/icon.svg" type="image/svg+xml">
    <link rel="icon" href="<?= BASE_URL ?>/public/icons/icon-192.png" type="image/png">
    <!-- PWA -->
    <link rel="manifest" href="<?= BASE_URL ?>/public/manifest.json">
    <meta name="theme-color" content="#232A3D">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="FamilyBoard">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/public/icons/icon-192.png">
    <!-- Styles -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/app.css?v=<?= APP_VERSION ?>">
    <?php if (isset($extraCss)): ?>
        <?php foreach ((array)$extraCss as $css): ?>
            <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/<?= $css ?>?v=<?= APP_VERSION ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap">
</head>
<body>

<div id="top-banners">
<?php if (\App\Core\Session::isLoggedIn() && !empty($_SESSION['impersonation'])): ?>
<div class="impersonation-banner">
    🕵️ Connecté en tant que <strong><?= htmlspecialchars(\App\Core\Session::user()['name'] ?? '') ?></strong> (accès support admin)
    <a href="<?= BASE_URL ?>/admin/stop-impersonating" class="btn btn-sm">Revenir à l'admin</a>
</div>
<?php endif; ?>

<?php if (\App\Core\Session::isLoggedIn()):
    $_tfaStatus = \App\Models\TwoFactorAuth::getPolicyStatus((int)\App\Core\Session::user()['id']);
    if (!empty($_tfaStatus['enforced']) && empty($_tfaStatus['blocked'])): ?>
<div class="impersonation-banner" style="background:#B8860B">
    🔐 La double authentification devient obligatoire dans <strong><?= (int)$_tfaStatus['days_left'] ?> jour<?= $_tfaStatus['days_left'] > 1 ? 's' : '' ?></strong>.
    <a href="<?= BASE_URL ?>/settings" class="btn btn-sm">Activer maintenant</a>
</div>
<?php endif; endif; ?>

<?php if (\App\Core\Session::isLoggedIn() && (\App\Core\Session::user()['role'] ?? null) !== 'coparent'): ?>
<div class="impersonation-banner" id="pwa-onboarding-banner" style="background:#2E6E4A;display:none">
    <span id="pwa-onboarding-text"></span>
    <a href="<?= BASE_URL ?>/settings#pwa-install-section" id="pwa-onboarding-link" class="btn btn-sm">Voir comment faire</a>
</div>
<?php endif; ?>
</div>

<?php if (\App\Core\Session::isLoggedIn() && (\App\Core\Session::user()['role'] ?? null) === 'coparent'): ?>
<!-- Compte à accès restreint : pas de sidebar complète, seulement l'essentiel. -->
<div class="coparent-shell">
    <header class="coparent-topbar">
        <div class="coparent-brand">
            <span class="logo-icon">🔒</span>
            <span>Garde partagée</span>
        </div>
        <div style="display:flex;gap:.5rem;align-items:center">
            <button class="btn btn-secondary btn-sm" onclick="openReportIssueModal()" title="Signaler un problème">🐞 Signaler un problème</button>
            <a href="<?= BASE_URL ?>/logout" class="btn btn-secondary btn-sm">Déconnexion</a>
        </div>
    </header>
    <?php $success = \App\Core\Session::getFlash('success'); $error = \App\Core\Session::getFlash('error'); ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="coparent-content">
        <?= $content ?? '' ?>
    </div>
</div>
<?php elseif (\App\Core\Session::isLoggedIn()): ?>
<?php
$currentUser = \App\Core\Session::user();
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$unreadCount = \App\Models\Notification::getUnreadCount($currentUser['id']);
$family = \App\Models\Family::findById($currentUser['family_id']);
$_disabledModules = \App\Models\Family::getDisabledModules($family ?? []);
$_hasCustodyAccess = \App\Models\Custody::getSchedulesForUser($currentUser['id']);
$_navEnabled = fn (string $module) => !in_array($module, $_disabledModules);
?>
<div class="app-wrapper">
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <span class="logo-icon">🏠</span>
                <span class="logo-text"><?= htmlspecialchars($family['name'] ?? APP_NAME) ?></span>
            </div>
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
        </div>

        <ul class="nav-menu">
            <li class="nav-item <?= $currentPath === BASE_URL . '/' || $currentPath === BASE_URL ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/" class="nav-link">
                    <span class="nav-icon">🏠</span>
                    <span class="nav-label">Tableau de bord</span>
                </a>
            </li>
            <?php if ($_navEnabled('family-wall')): ?>
            <li class="nav-item <?= str_contains($currentPath, '/family-wall') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/family-wall" class="nav-link">
                    <span class="nav-icon">📺</span>
                    <span class="nav-label">Écran mural</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($_navEnabled('wall') || $_navEnabled('albums') || $_navEnabled('chat') || $_navEnabled('calendar') || $_navEnabled('contacts') || $_navEnabled('meals')): ?>
            <li class="nav-section-label">Vie de famille</li>
            <?php endif; ?>
            <?php if ($_navEnabled('wall')): ?>
            <li class="nav-item <?= str_contains($currentPath, '/wall') && !str_contains($currentPath, '/family-wall') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/wall" class="nav-link">
                    <span class="nav-icon">📸</span>
                    <span class="nav-label">Mur familial</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($_navEnabled('albums')): ?>
            <li class="nav-item <?= str_contains($currentPath, '/albums') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/albums" class="nav-link">
                    <span class="nav-icon">🖼️</span>
                    <span class="nav-label">Albums photo</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($_navEnabled('chat')): ?>
            <li class="nav-item <?= str_contains($currentPath, '/chat') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/chat" class="nav-link">
                    <span class="nav-icon">💬</span>
                    <span class="nav-label">Chat familial</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($_navEnabled('calendar')): ?>
            <li class="nav-item <?= str_contains($currentPath, '/calendar') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/calendar" class="nav-link">
                    <span class="nav-icon">📅</span>
                    <span class="nav-label">Calendrier</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($_navEnabled('contacts')): ?>
            <li class="nav-item <?= str_contains($currentPath, '/contacts') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/contacts" class="nav-link">
                    <span class="nav-icon">📒</span>
                    <span class="nav-label">Répertoire</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($_navEnabled('meals')): ?>
            <li class="nav-item <?= str_contains($currentPath, '/meals') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/meals" class="nav-link">
                    <span class="nav-icon">🍽️</span>
                    <span class="nav-label">Repas</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($_navEnabled('custody') || $_hasCustodyAccess || $_navEnabled('comm_log')): ?>
            <li class="nav-section-label">Garde partagée</li>
            <?php endif; ?>
            <?php if ($_navEnabled('custody')): ?>
            <li class="nav-item <?= str_contains($currentPath, '/custody') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/custody" class="nav-link">
                    <span class="nav-icon">👶</span>
                    <span class="nav-label">Garde alternée</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($_hasCustodyAccess): ?>
            <li class="nav-item <?= str_contains($currentPath, '/coparent') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/coparent" class="nav-link">
                    <span class="nav-icon">🔒</span>
                    <span class="nav-label">Garde partagée</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($_navEnabled('comm_log')): ?>
            <li class="nav-item <?= str_contains($currentPath, '/comm-log') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/comm-log" class="nav-link">
                    <span class="nav-icon">📝</span>
                    <span class="nav-label">Journal parental</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($_navEnabled('tasks') || $_navEnabled('projects') || $_navEnabled('budget') || $_navEnabled('documents') || $_navEnabled('warranties')): ?>
            <li class="nav-section-label">Organisation</li>
            <?php endif; ?>
            <?php if ($_navEnabled('tasks')): ?>
            <li class="nav-item <?= str_contains($currentPath, '/tasks') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/tasks" class="nav-link">
                    <span class="nav-icon">✅</span>
                    <span class="nav-label">Tâches & Courses</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($_navEnabled('projects')): ?>
            <li class="nav-item <?= str_contains($currentPath, '/projects') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/projects" class="nav-link">
                    <span class="nav-icon">📋</span>
                    <span class="nav-label">Projets</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($_navEnabled('budget')): ?>
            <li class="nav-item <?= str_contains($currentPath, '/budget') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/budget" class="nav-link">
                    <span class="nav-icon">💰</span>
                    <span class="nav-label">Budget</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($_navEnabled('documents')): ?>
            <li class="nav-item <?= str_contains($currentPath, '/documents') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/documents" class="nav-link">
                    <span class="nav-icon">🗂️</span>
                    <span class="nav-label">Documents</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($_navEnabled('warranties')): ?>
            <li class="nav-item <?= str_contains($currentPath, '/warranties') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/warranties" class="nav-link">
                    <span class="nav-icon">🛡️</span>
                    <span class="nav-label">Garanties</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($_navEnabled('baby') || $_navEnabled('location') || $_navEnabled('emergency') || $_navEnabled('cameras')): ?>
            <li class="nav-section-label">Suivi & sécurité</li>
            <?php endif; ?>
            <?php if ($_navEnabled('baby')): ?>
            <li class="nav-item <?= str_contains($currentPath, '/baby') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/baby" class="nav-link">
                    <span class="nav-icon">🍼</span>
                    <span class="nav-label">Bébé</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($_navEnabled('location')): ?>
            <li class="nav-item <?= str_contains($currentPath, '/location') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/location" class="nav-link">
                    <span class="nav-icon">📍</span>
                    <span class="nav-label">Position</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($_navEnabled('emergency')): ?>
            <li class="nav-item <?= str_contains($currentPath, '/emergency') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/emergency" class="nav-link">
                    <span class="nav-icon">🚑</span>
                    <span class="nav-label">Fiches urgence</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($_navEnabled('cameras')): ?>
            <li class="nav-item <?= str_contains($currentPath, '/cameras') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/cameras" class="nav-link">
                    <span class="nav-icon">🎥</span>
                    <span class="nav-label">Caméras</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <div class="sidebar-footer">
            <div class="user-menu" id="user-menu">
                <button type="button" class="user-menu-trigger" id="user-menu-trigger" onclick="toggleUserMenu()">
                    <div class="user-avatar" style="background:<?= htmlspecialchars($currentUser['color']) ?>">
                        <?php if ($currentUser['avatar']): ?>
                            <img src="<?= BASE_URL . htmlspecialchars($currentUser['avatar']) ?>" alt="">
                        <?php else: ?>
                            <?= mb_substr($currentUser['name'], 0, 1) ?>
                        <?php endif; ?>
                    </div>
                    <span class="user-menu-identity">
                        <span class="user-menu-name"><?= htmlspecialchars($currentUser['name']) ?></span>
                        <span class="user-menu-role"><?= ['admin' => 'Administrateur', 'coparent' => 'Co-parent'][$currentUser['role']] ?? 'Membre' ?></span>
                    </span>
                    <span class="user-menu-caret">▾</span>
                </button>

                <div class="user-menu-popover" id="user-menu-popover" style="display:none">
                    <a href="<?= BASE_URL ?>/settings" class="user-menu-item">
                        <span class="user-menu-item-icon">⚙️</span> Paramètres
                    </a>
                    <button type="button" id="pwa-install-btn" onclick="installPWA()" class="user-menu-item" style="display:none">
                        <span class="user-menu-item-icon">📲</span> Installer l'app
                    </button>
                    <a href="<?= BASE_URL ?>/support" class="user-menu-item">
                        <span class="user-menu-item-icon">🎫</span> Support
                    </a>
                    <div class="user-menu-divider"></div>
                    <a href="<?= BASE_URL ?>/logout" class="user-menu-item user-menu-item-danger">
                        <span class="user-menu-item-icon">🚪</span> Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <div class="main-content">
        <!-- Top bar -->
        <header class="topbar">
            <button class="mobile-menu-btn" onclick="toggleSidebar()">☰</button>
            <h1 class="page-title"><?= htmlspecialchars($pageTitle ?? '') ?></h1>
            <div class="alert-banner" id="alert-banner" style="display:none">
                <div class="alert-banner-track" id="alert-banner-track"></div>
            </div>
            <div class="topbar-actions">
                <button class="btn-icon" onclick="openReportIssueModal()" title="Signaler un problème">🐞</button>
                <button class="theme-toggle-btn" onclick="toggleTheme()" title="Changer de thème">
                    <span class="theme-icon-sun">☀️</span>
                    <span class="theme-icon-moon">🌙</span>
                </button>
                <button class="btn-icon" onclick="toggleNotifications()" title="Notifications">
                    🔔
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge" id="notif-badge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </button>
            </div>
        </header>

        <!-- Notifications panel -->
        <div class="notifications-panel" id="notifications-panel" style="display:none">
            <div class="notif-header">
                <span>Notifications</span>
                <button onclick="markAllRead()" class="btn-text">Tout lire</button>
            </div>
            <div id="notif-list"></div>
        </div>

        <!-- Flash messages -->
        <?php $success = \App\Core\Session::getFlash('success'); $error = \App\Core\Session::getFlash('error'); ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Page content -->
        <div class="content-area">
            <?= $content ?? '' ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="auth-wrapper">
    <?= $content ?? '' ?>
</div>
<?php endif; ?>

<!-- Signaler un problème (disponible sur toutes les pages, y compris l'accès co-parent restreint) -->
<div class="modal-overlay" id="report-issue-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>🐞 Signaler un problème</h3>
            <button onclick="closeModal('report-issue-modal')">✕</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-muted);font-size:.85rem">
                Décrivez brièvement ce qui ne va pas (facultatif) : un diagnostic technique (navigateur, page, cache…) sera joint automatiquement pour le support.
            </p>
            <div class="form-group">
                <textarea id="report-issue-description" rows="3" placeholder="Ex : le calendrier ne s'affiche pas…"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('report-issue-modal')">Annuler</button>
            <button class="btn btn-primary" id="report-issue-submit-btn" onclick="submitReportIssue()">Envoyer</button>
        </div>
    </div>
</div>

<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;
const APP_TIMEZONE = <?= json_encode(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Europe/Paris') ?>;
const APP_VERSION = <?= json_encode((string)APP_VERSION) ?>;
</script>
<script src="<?= ASSETS_URL ?>/js/app.js?v=<?= APP_VERSION ?>"></script>
<script>
if ('serviceWorker' in navigator) {
    // Register as early as possible so .ready resolves before initPushSubscription runs
    navigator.serviceWorker.register('<?= BASE_URL ?>/sw.js?v=<?= APP_VERSION ?>')
        .then(() => initPushSubscription());
}
// PWA install prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault();
    deferredPrompt = e;
    ['pwa-install-btn', 'pwa-install-btn-settings'].forEach(id => {
        const btn = document.getElementById(id);
        if (btn) btn.style.display = id === 'pwa-install-btn' ? 'flex' : 'inline-block';
    });
});
function installPWA() {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then(() => {
        deferredPrompt = null;
        ['pwa-install-btn', 'pwa-install-btn-settings'].forEach(id => {
            const btn = document.getElementById(id);
            if (btn) btn.style.display = 'none';
        });
    });
}
if (typeof isStandalonePWA === 'function' && isStandalonePWA()) {
    const statusEl = document.getElementById('pwa-install-status');
    if (statusEl) statusEl.style.display = 'block';
}
</script>
<?php if (isset($extraJs)): ?>
    <?php foreach ((array)$extraJs as $js): ?>
        <script src="<?= ASSETS_URL ?>/js/<?= $js ?>?v=<?= APP_VERSION ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
