<?php
$pageTitle = 'Tableau de bord';
$family    = \App\Models\Family::findById($user['family_id']);
ob_start();
?>
<!-- Weather banner -->
<div class="dashboard-hero" id="dashboard-hero">
    <div class="hero-identity">
        <div class="hero-greeting">
            <span class="hero-name">Bonjour, <?= htmlspecialchars($user['name']) ?> 👋</span>
            <span class="hero-date"><?= ucfirst(\App\Core\DateHelper::format(date('Y-m-d'), 'l j F Y')) ?></span>
        </div>
        <div class="hero-weather" id="hero-weather">
            <span class="weather-loading">…</span>
        </div>
    </div>
    <div class="hero-alert" id="hero-alert" style="display:none"></div>
</div>

<!-- Widget toolbar -->
<div class="widget-toolbar">
    <span class="widget-toolbar-title">Widgets</span>
    <button class="btn btn-sm" onclick="Dashboard.openWidgetManager()">Personnaliser</button>
</div>

<!-- Widget grid -->
<div class="widget-grid" id="widget-grid">
<?php foreach ($widgetConfig as $w):
    $slug    = $w['slug'];
    $meta    = \App\Controllers\DashboardController::AVAILABLE_WIDGETS[$slug] ?? null;
    if (!$meta) continue;
    $enabled = $w['enabled'];
    $data    = $widgetData[$slug] ?? [];
?>
    <div class="widget-card <?= $enabled ? '' : 'widget-hidden' ?>"
         data-slug="<?= $slug ?>"
         draggable="false"
         id="widget-<?= $slug ?>">
        <div class="widget-drag-handle" title="Déplacer">⠿</div>
        <div class="widget-body">
        <?php switch ($slug):
            case 'calendar': ?>
                <div class="card-header">
                    <h3><?= $meta['icon'] ?> <?= $meta['label'] ?></h3>
                    <a href="<?= BASE_URL ?>/calendar" class="btn-text">Voir tout</a>
                </div>
                <?php $events = $data['events'] ?? []; ?>
                <?php if (empty($events)): ?>
                    <p class="empty-state">Aucun événement à venir.</p>
                <?php else: ?>
                    <ul class="event-list">
                        <?php foreach ($events as $event): ?>
                            <li class="event-item">
                                <span class="event-dot" style="background:<?= htmlspecialchars($event['color']) ?>"></span>
                                <div class="event-info">
                                    <strong><?= htmlspecialchars($event['title']) ?></strong>
                                    <span class="event-date">
                                        <?php if ($event['is_all_day']): ?>
                                            <?= date('d/m', strtotime($event['start_datetime'])) ?>
                                        <?php else: ?>
                                            <?= date('d/m à H\hi', strtotime($event['start_datetime'])) ?>
                                        <?php endif; ?>
                                        — <?= htmlspecialchars($event['user_name']) ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; break;

            case 'wall': ?>
                <div class="card-header">
                    <h3><?= $meta['icon'] ?> <?= $meta['label'] ?></h3>
                    <a href="<?= BASE_URL ?>/wall" class="btn-text">Voir tout</a>
                </div>
                <?php $posts = $data['posts'] ?? []; ?>
                <?php if (empty($posts)): ?>
                    <p class="empty-state">Aucune publication récente.</p>
                <?php else: ?>
                    <ul class="post-preview-list">
                        <?php foreach ($posts as $post): ?>
                            <li class="post-preview-item">
                                <div class="user-avatar-sm" style="background:<?= htmlspecialchars($post['user_color']) ?>">
                                    <?= mb_substr($post['user_name'], 0, 1) ?>
                                </div>
                                <div style="flex:1;min-width:0">
                                    <strong><?= htmlspecialchars($post['user_name']) ?></strong>
                                    <?php $plain = mb_substr(strip_tags($post['content']), 0, 100); ?>
                                    <?php if ($plain): ?>
                                        <p><?= htmlspecialchars($plain) ?><?= mb_strlen(strip_tags($post['content'])) > 100 ? '…' : '' ?></p>
                                    <?php elseif ($post['image_path']): ?>
                                        <p style="color:var(--text-muted);font-style:italic">📷 Photo</p>
                                    <?php endif; ?>
                                    <small><?= \App\Core\DateHelper::fromUtc($post['created_at'], 'd/m \à H:i') ?></small>
                                </div>
                                <a href="<?= BASE_URL ?>/wall#post-<?= $post['id'] ?>" class="btn-text" style="flex-shrink:0;align-self:center">Lire</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; break;

            case 'tasks': ?>
                <div class="card-header">
                    <h3><?= $meta['icon'] ?> <?= $meta['label'] ?></h3>
                    <a href="<?= BASE_URL ?>/tasks" class="btn-text">Voir tout</a>
                </div>
                <?php $tasks = $data['tasks'] ?? []; ?>
                <?php if (empty($tasks)): ?>
                    <p class="empty-state">Aucune tâche en cours.</p>
                <?php else: ?>
                    <ul class="task-widget-list">
                        <?php foreach ($tasks as $t): ?>
                            <li class="task-widget-item">
                                <span class="task-widget-check">○</span>
                                <span class="task-widget-name"><?= htmlspecialchars($t['title']) ?></span>
                                <span class="task-widget-list-name"><?= htmlspecialchars($t['list_name']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; break;

            case 'custody': ?>
                <div class="card-header">
                    <h3><?= $meta['icon'] ?> <?= $meta['label'] ?></h3>
                    <a href="<?= BASE_URL ?>/custody" class="btn-text">Planning</a>
                </div>
                <?php $custody = $data['custody'] ?? []; ?>
                <?php if (empty($custody)): ?>
                    <p class="empty-state">Aucune garde aujourd'hui.</p>
                <?php else: ?>
                    <ul class="custody-list">
                        <?php foreach ($custody as $c): ?>
                            <li class="custody-item">
                                <span class="custody-dot" style="background:<?= htmlspecialchars($c['schedule_color']) ?>"></span>
                                <strong><?= htmlspecialchars($c['child_name']) ?></strong> chez <?= htmlspecialchars($c['parent_name']) ?>
                                <?php if ($c['arrival_time']): ?>
                                    <small>(arrivée <?= substr($c['arrival_time'], 0, 5) ?>)</small>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; break;

            case 'baby': ?>
                <div class="card-header">
                    <h3><?= $meta['icon'] ?> <?= $meta['label'] ?></h3>
                    <a href="<?= BASE_URL ?>/baby" class="btn-text">Détails</a>
                </div>
                <?php $babies = $data['babies'] ?? []; ?>
                <?php if (empty($babies)): ?>
                    <p class="empty-state">Aucun bébé enregistré.</p>
                <?php else: foreach ($babies as $bd):
                    $stats = $bd['stats']; $baby = $bd['baby']; $sleep = $bd['sleep']; ?>
                    <div class="baby-widget-name"><?= htmlspecialchars($baby['name']) ?></div>
                    <div class="baby-widget-stats">
                        <span title="Tétées/biberons">🍼 <?= $stats['feeding'] ?></span>
                        <span title="Couches">🩲 <?= $stats['diaper'] ?></span>
                        <span title="Selles">💩 <?= $stats['stool'] ?></span>
                        <span title="Sommeil"><?= $sleep ? '😴 en cours' : '💤 ' . round($stats['sleep_min'] / 60, 1) . 'h' ?></span>
                    </div>
                <?php endforeach; endif; break;

            default: ?>
                <div class="card-header">
                    <h3><?= $meta['icon'] ?> <?= $meta['label'] ?></h3>
                </div>
                <p class="empty-state">Ouvrez le module pour plus de détails.</p>
        <?php endswitch; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- Widget manager modal -->
<div class="modal-overlay" id="modal-widget-manager" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h2>Personnaliser les widgets</h2>
            <button class="modal-close" onclick="Dashboard.closeWidgetManager()">✕</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-muted);font-size:.88rem;margin-bottom:1rem">
                Activez ou désactivez les widgets. Faites glisser les cartes sur le tableau de bord pour les réordonner.
            </p>
            <ul class="widget-manager-list" id="widget-manager-list">
                <?php foreach ($widgetConfig as $w):
                    $slug = $w['slug'];
                    $meta = \App\Controllers\DashboardController::AVAILABLE_WIDGETS[$slug] ?? null;
                    if (!$meta) continue;
                ?>
                    <li class="widget-manager-item" data-slug="<?= $slug ?>">
                        <span class="widget-manager-icon"><?= $meta['icon'] ?></span>
                        <span class="widget-manager-label"><?= $meta['label'] ?></span>
                        <label class="toggle-switch">
                            <input type="checkbox" <?= $w['enabled'] ? 'checked' : '' ?>
                                   onchange="Dashboard.toggleWidget('<?= $slug ?>', this.checked)">
                            <span class="toggle-slider"></span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="Dashboard.saveWidgets()">Enregistrer</button>
            <button class="btn btn-secondary" onclick="Dashboard.closeWidgetManager()">Fermer</button>
        </div>
    </div>
</div>

<script>
var _weatherCity = <?= json_encode($family['weather_city'] ?? '') ?>;
var _widgetConfig = <?= json_encode($widgetConfig) ?>;
var _baseUrl = <?= json_encode(BASE_URL) ?>;
</script>
<script src="<?= ASSETS_URL ?>/js/dashboard.js?v=<?= APP_VERSION ?>"></script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
