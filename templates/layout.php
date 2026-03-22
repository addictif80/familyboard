<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/app.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
</head>
<body>

<?php if (\App\Core\Session::isLoggedIn()): ?>
<?php
$currentUser = \App\Core\Session::user();
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$unreadCount = \App\Models\Notification::getUnreadCount($currentUser['id']);
$family = \App\Models\Family::findById($currentUser['family_id']);
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
            <li class="nav-item <?= str_contains($currentPath, '/wall') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/wall" class="nav-link">
                    <span class="nav-icon">📸</span>
                    <span class="nav-label">Mur familial</span>
                </a>
            </li>
            <li class="nav-item <?= str_contains($currentPath, '/calendar') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/calendar" class="nav-link">
                    <span class="nav-icon">📅</span>
                    <span class="nav-label">Calendrier</span>
                </a>
            </li>
            <li class="nav-item <?= str_contains($currentPath, '/custody') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/custody" class="nav-link">
                    <span class="nav-icon">👶</span>
                    <span class="nav-label">Garde alternée</span>
                </a>
            </li>
            <li class="nav-item <?= str_contains($currentPath, '/tasks') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/tasks" class="nav-link">
                    <span class="nav-icon">✅</span>
                    <span class="nav-label">Tâches & Courses</span>
                </a>
            </li>
            <li class="nav-item <?= str_contains($currentPath, '/chat') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/chat" class="nav-link">
                    <span class="nav-icon">💬</span>
                    <span class="nav-label">Chat familial</span>
                </a>
            </li>
            <li class="nav-item <?= str_contains($currentPath, '/budget') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/budget" class="nav-link">
                    <span class="nav-icon">💰</span>
                    <span class="nav-label">Budget</span>
                </a>
            </li>
            <li class="nav-item <?= str_contains($currentPath, '/projects') ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/projects" class="nav-link">
                    <span class="nav-icon">📋</span>
                    <span class="nav-label">Projets</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <a href="<?= BASE_URL ?>/settings" class="nav-link <?= str_contains($currentPath, '/settings') ? 'active' : '' ?>">
                <div class="user-avatar" style="background:<?= htmlspecialchars($currentUser['color']) ?>">
                    <?php if ($currentUser['avatar']): ?>
                        <img src="<?= BASE_URL . htmlspecialchars($currentUser['avatar']) ?>" alt="">
                    <?php else: ?>
                        <?= mb_substr($currentUser['name'], 0, 1) ?>
                    <?php endif; ?>
                </div>
                <span class="nav-label"><?= htmlspecialchars($currentUser['name']) ?></span>
            </a>
        </div>
    </nav>

    <!-- Main content -->
    <div class="main-content">
        <!-- Top bar -->
        <header class="topbar">
            <button class="mobile-menu-btn" onclick="toggleSidebar()">☰</button>
            <h1 class="page-title"><?= htmlspecialchars($pageTitle ?? '') ?></h1>
            <div class="topbar-actions">
                <button class="btn-icon" onclick="toggleNotifications()" title="Notifications">
                    🔔
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge" id="notif-badge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </button>
                <a href="<?= BASE_URL ?>/logout" class="btn-icon" title="Déconnexion">🚪</a>
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

<script>const BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
<script src="<?= BASE_URL ?>/js/app.js"></script>
<?php if (isset($extraJs)): ?>
    <?php foreach ((array)$extraJs as $js): ?>
        <script src="<?= BASE_URL ?>/js/<?= $js ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
