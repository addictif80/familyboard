<?php
$pageTitle = 'Tableau de bord';
$family    = \App\Models\Family::findById($user['family_id']);
$firstName = trim(explode(' ', trim($user['name']))[0] ?? $user['name']);
ob_start();
?>
<!-- Salutation + météo/éphéméride + actions -->
<div class="dash-head">
    <div class="dash-greeting">
        <h1>Bonjour <?= htmlspecialchars($firstName) ?> <span class="dash-wave" aria-hidden="true">👋</span></h1>
        <p><?= ucfirst(\App\Core\DateHelper::format(date('Y-m-d'), 'l j F Y')) ?></p>
    </div>
    <div class="dash-head-actions">
        <div class="dash-infopill" id="dashboard-hero">
            <div class="dash-clock" id="dash-clock" aria-hidden="true">🕐 --:--</div>
            <div class="dash-infopill-sep"></div>
            <div class="hero-weather" id="hero-weather">
                <span class="weather-loading">…</span>
            </div>
            <?php if ($todayNameDay): ?>
            <div class="dash-infopill-sep"></div>
            <div class="dash-nameday">
                <span>🎉 Fête : <?= htmlspecialchars($todayNameDay) ?></span>
                <small>Éphéméride du jour</small>
            </div>
            <?php endif; ?>
        </div>
        <button class="dash-chip dash-chip-ghost" onclick="Dashboard.openWidgetManager()">✨ Personnaliser</button>
    </div>
    <div class="hero-alert" id="hero-alert" style="display:none"></div>
</div>

<!-- Sur PC, cette même mise en avant s'affiche dans la sidebar (voir abhd_spotlight_sidebar.php)
     — .dashboard-spotlight-wrap masque celle-ci au-delà de 768px pour ne pas la dupliquer. -->
<div class="dashboard-spotlight-wrap">
    <?php $placement = 'dashboard'; require BASE_PATH . '/templates/partials/abhd_spotlight.php'; ?>
</div>

<!-- Chiffres clés du jour -->
<div class="dash-stats">
    <div class="dash-stat-card">
        <div class="dash-stat-head"><span>Tâches du jour</span><span aria-hidden="true">✅</span></div>
        <div class="dash-stat-val"><?= (int)$headerStats['tasks']['remaining'] ?></div>
        <?php if ($headerStats['tasks']['total'] > 0): ?>
            <div class="dash-stat-bar"><div style="width:<?= round($headerStats['tasks']['done'] / $headerStats['tasks']['total'] * 100) ?>%;background:linear-gradient(90deg,var(--success),var(--info))"></div></div>
        <?php else: ?>
            <div class="dash-stat-sub">Aucune échéance aujourd'hui</div>
        <?php endif; ?>
    </div>
    <div class="dash-stat-card">
        <div class="dash-stat-head"><span>Événements à venir</span><span aria-hidden="true">📅</span></div>
        <div class="dash-stat-val"><?= (int)$headerStats['events']['count'] ?></div>
        <div class="dash-stat-sub">
            <?php if ($headerStats['events']['next']): $n = $headerStats['events']['next']; ?>
                Prochain : <?= $n['is_all_day'] ? 'journée' : date('d/m à H\hi', strtotime($n['start_datetime'])) ?>
            <?php else: ?>
                Rien de prévu cette semaine
            <?php endif; ?>
        </div>
    </div>
    <?php if ($headerStats['budget']): ?>
    <div class="dash-stat-card">
        <div class="dash-stat-head"><span>Budget du mois</span><span aria-hidden="true">💰</span></div>
        <div class="dash-stat-val"><?= $headerStats['budget']['percent'] !== null ? $headerStats['budget']['percent'] . '%' : '—' ?></div>
        <?php if ($headerStats['budget']['percent'] !== null): ?>
            <div class="dash-stat-bar"><div style="width:<?= $headerStats['budget']['percent'] ?>%;background:linear-gradient(90deg,var(--primary),#FF5CA8)"></div></div>
        <?php else: ?>
            <div class="dash-stat-sub">Aucun revenu renseigné ce mois-ci</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($headerStats['unread'] !== null): ?>
    <div class="dash-stat-card">
        <div class="dash-stat-head"><span>Messages non lus</span><span aria-hidden="true">💬</span></div>
        <div class="dash-stat-val"><?= (int)$headerStats['unread'] ?></div>
        <div class="dash-stat-sub">Messages privés</div>
    </div>
    <?php endif; ?>
</div>

<!-- Bento : agenda / famille / alertes & actus -->
<div class="dash-bento">
    <div class="dash-card">
        <div class="dash-card-head">
            <h2>Aujourd'hui &amp; demain</h2>
            <a href="<?= BASE_URL ?>/calendar">Voir le calendrier →</a>
        </div>
        <?php if (empty($timelineDays['items'])): ?>
            <p class="empty-state">Rien de prévu aujourd'hui ni demain.</p>
        <?php else: ?>
            <div class="dash-timeline">
                <?php foreach ($timelineDays['items'] as $it): ?>
                <div class="dash-timeline-item">
                    <div class="dash-timeline-bar" style="background:<?= htmlspecialchars($it['color']) ?>"></div>
                    <div>
                        <div class="dash-timeline-label"><?= htmlspecialchars($it['label']) ?></div>
                        <div class="dash-timeline-date"><?= $it['date'] === $timelineDays['today'] ? "Aujourd'hui" : 'Demain' ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="dash-card">
        <div class="dash-card-head"><h2>Votre famille</h2></div>
        <div class="dash-family-list">
            <?php foreach ($familyMembers as $m): ?>
            <div class="dash-family-item">
                <?= \App\Core\Avatar::html($m['avatar'] ?? null, $m['color'], $m['name'], 'user-avatar-sm') ?>
                <span><?= htmlspecialchars($m['name']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <a href="<?= BASE_URL ?>/settings#members" class="dash-chip dash-chip-light">+ Inviter un membre</a>
    </div>

    <div class="dash-card">
        <?php if (!empty($officialAlerts)): ?>
        <div class="dash-card-head"><h2>🚨 Alertes officielles</h2></div>
        <div class="dash-alert-list">
            <?php foreach ($officialAlerts as $a):
                $meta = \App\Models\OfficialAlert::CATEGORIES[$a['category']] ?? ['label' => $a['category'], 'icon' => '⚠️'];
            ?>
            <div class="dash-alert-item">
                <span class="dash-alert-icon" aria-hidden="true"><?= $meta['icon'] ?></span>
                <div>
                    <div class="dash-alert-title"><?= htmlspecialchars($a['title']) ?></div>
                    <div class="dash-alert-sub"><?= htmlspecialchars($a['summary'] ?? '') ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($officialAlerts) && !empty($announcements)): ?><hr class="dash-sep"><?php endif; ?>
        <?php if (!empty($announcements)): ?>
        <div class="dash-card-head"><h2>📣 Actualités</h2></div>
        <div class="dash-alert-list">
            <?php foreach ($announcements as $ann):
                $tmeta = \App\Models\Announcement::TYPES[$ann['type']] ?? ['icon' => 'ℹ️'];
            ?>
            <div class="dash-news-item">
                <div class="dash-alert-title"><?= $tmeta['icon'] ?> <?= htmlspecialchars($ann['title']) ?></div>
                <div class="dash-alert-sub"><?= htmlspecialchars(mb_substr(strip_tags($ann['content']), 0, 110)) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (empty($officialAlerts) && empty($announcements)): ?>
            <div class="dash-card-head"><h2>📣 Actualités</h2></div>
            <p class="empty-state">Aucune alerte ni actualité pour le moment.</p>
        <?php endif; ?>
    </div>
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
                            <li class="event-item" style="cursor:pointer" onclick="openRecordPreview('event', <?= (int)$event['id'] ?>)">
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
                            <li class="post-preview-item" style="cursor:pointer" onclick="openRecordPreview('post', <?= (int)$post['id'] ?>)">
                                <?= \App\Core\Avatar::html($post['user_avatar'] ?? null, $post['user_color'], $post['user_name'], 'user-avatar-sm') ?>
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
                                <a href="<?= BASE_URL ?>/wall#post-<?= $post['id'] ?>" class="btn-text" style="flex-shrink:0;align-self:center" onclick="event.stopPropagation()">Lire</a>
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
                            <li class="task-widget-item" data-task-id="<?= (int)$t['id'] ?>">
                                <input type="checkbox" class="task-widget-check" onclick="widgetToggleTask(event, <?= (int)$t['id'] ?>)">
                                <span class="task-widget-name" style="cursor:pointer" onclick="openRecordPreview('task', <?= (int)$t['id'] ?>)"><?= htmlspecialchars($t['title']) ?></span>
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
                            <?php $clickable = is_numeric($c['id']); ?>
                            <li class="custody-item" <?= $clickable ? 'style="cursor:pointer" onclick="openRecordPreview(\'custody_event\', ' . (int)$c['id'] . ')"' : '' ?>>
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

            case 'birthdays': ?>
                <div class="card-header">
                    <h3><?= $meta['icon'] ?> <?= $meta['label'] ?></h3>
                </div>
                <?php $birthdays = $data['birthdays'] ?? []; ?>
                <?php if (empty($birthdays)): ?>
                    <p class="empty-state">Aucun anniversaire dans les 60 prochains jours.</p>
                <?php else: ?>
                    <ul class="event-list">
                        <?php foreach (array_slice($birthdays, 0, 6) as $b): ?>
                            <li class="event-item" style="cursor:pointer" onclick="openRecordPreview('<?= htmlspecialchars($b['type']) ?>', <?= (int)$b['id'] ?>)">
                                <span class="event-dot" style="background:<?= htmlspecialchars($b['color']) ?>"></span>
                                <div class="event-info">
                                    <strong><?= htmlspecialchars($b['name']) ?></strong>
                                    <span class="event-date">
                                        <?php if ($b['days_until'] === 0): ?>
                                            🎉 Aujourd'hui — <?= $b['age'] ?> ans
                                        <?php else: ?>
                                            dans <?= $b['days_until'] ?> j (<?= date('d/m', strtotime($b['date'])) ?>) — <?= $b['age'] ?> ans
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; break;

            case 'chat': ?>
                <div class="card-header">
                    <h3><?= $meta['icon'] ?> <?= $meta['label'] ?></h3>
                    <a href="<?= BASE_URL ?>/chat" class="btn-text">Ouvrir</a>
                </div>
                <?php $messages = $data['messages'] ?? []; ?>
                <?php if (empty($messages)): ?>
                    <p class="empty-state">Aucun message récent.</p>
                <?php else: ?>
                    <ul class="post-preview-list">
                        <?php foreach ($messages as $msg): ?>
                            <li class="post-preview-item">
                                <?= \App\Core\Avatar::html($msg['user_avatar'] ?? null, $msg['user_color'] ?? null, $msg['user_name'] ?? '?', 'user-avatar-sm') ?>
                                <div style="flex:1;min-width:0">
                                    <strong><?= htmlspecialchars($msg['user_name'] ?? '') ?></strong>
                                    <p><?= htmlspecialchars(mb_substr($msg['content'] ?? '', 0, 80)) ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; break;

            case 'budget': ?>
                <div class="card-header">
                    <h3><?= $meta['icon'] ?> <?= $meta['label'] ?></h3>
                    <a href="<?= BASE_URL ?>/budget" class="btn-text">Voir tout</a>
                </div>
                <?php $summary = $data['summary'] ?? ['income' => 0, 'expenses' => 0, 'balance' => 0]; ?>
                <div class="budget-widget-row"><span>Revenus</span><strong class="amount-income">+<?= number_format($summary['income'], 0, ',', ' ') ?> €</strong></div>
                <div class="budget-widget-row"><span>Dépenses</span><strong class="amount-expense">−<?= number_format($summary['expenses'], 0, ',', ' ') ?> €</strong></div>
                <div class="budget-widget-row budget-widget-balance"><span>Solde</span><strong class="<?= $summary['balance'] >= 0 ? 'amount-income' : 'amount-expense' ?>"><?= $summary['balance'] >= 0 ? '+' : '−' ?><?= number_format(abs($summary['balance']), 0, ',', ' ') ?> €</strong></div>
                <?php break; ?>

            case 'projects': ?>
                <div class="card-header">
                    <h3><?= $meta['icon'] ?> <?= $meta['label'] ?></h3>
                    <a href="<?= BASE_URL ?>/projects" class="btn-text">Voir tout</a>
                </div>
                <?php $projects = $data['projects'] ?? []; ?>
                <?php if (empty($projects)): ?>
                    <p class="empty-state">Aucun projet en cours.</p>
                <?php else: ?>
                    <ul class="event-list">
                        <?php foreach ($projects as $p): ?>
                            <li class="event-item" style="cursor:pointer" onclick="window.location.href='<?= BASE_URL ?>/projects?id=<?= (int)$p['id'] ?>'">
                                <span class="event-dot" style="background:var(--primary)"></span>
                                <div class="event-info">
                                    <strong><?= htmlspecialchars($p['title'] ?? $p['name'] ?? '') ?></strong>
                                    <span class="event-date"><?= htmlspecialchars($p['status'] ?? '') ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; break;

            case 'stock': ?>
                <div class="card-header">
                    <h3><?= $meta['icon'] ?> <?= $meta['label'] ?></h3>
                    <a href="<?= BASE_URL ?>/stock" class="btn-text">Voir tout</a>
                </div>
                <?php $expiring = $data['expiring'] ?? []; ?>
                <?php if (empty($expiring)): ?>
                    <p class="empty-state">Rien n'expire bientôt.</p>
                <?php else: ?>
                    <ul class="event-list">
                        <?php foreach ($expiring as $s): ?>
                            <li class="event-item">
                                <span class="event-dot" style="background:var(--warning)"></span>
                                <div class="event-info">
                                    <strong><?= htmlspecialchars($s['name']) ?></strong>
                                    <span class="event-date">Péremption <?= date('d/m', strtotime($s['expiry_date'])) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; break;

            case 'polls': ?>
                <div class="card-header">
                    <h3><?= $meta['icon'] ?> <?= $meta['label'] ?></h3>
                    <a href="<?= BASE_URL ?>/polls" class="btn-text">Voir tout</a>
                </div>
                <?php $polls = $data['polls'] ?? []; ?>
                <?php if (empty($polls)): ?>
                    <p class="empty-state">Aucun sondage en cours.</p>
                <?php else: ?>
                    <ul class="event-list">
                        <?php foreach ($polls as $p): ?>
                            <li class="event-item" style="cursor:pointer" onclick="window.location.href='<?= BASE_URL ?>/polls'">
                                <span class="event-dot" style="background:var(--info)"></span>
                                <div class="event-info">
                                    <strong><?= htmlspecialchars($p['question']) ?></strong>
                                    <span class="event-date"><?= (int)$p['vote_count'] ?> vote<?= $p['vote_count'] > 1 ? 's' : '' ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; break;

            case 'wishlist': ?>
                <div class="card-header">
                    <h3><?= $meta['icon'] ?> <?= $meta['label'] ?></h3>
                    <a href="<?= BASE_URL ?>/wishlist" class="btn-text">Voir tout</a>
                </div>
                <?php $items = $data['items'] ?? []; ?>
                <?php if (empty($items)): ?>
                    <p class="empty-state">Aucun souhait en attente.</p>
                <?php else: ?>
                    <ul class="event-list">
                        <?php foreach ($items as $it): ?>
                            <li class="event-item">
                                <span class="event-dot" style="background:#FF5CA8"></span>
                                <div class="event-info"><strong><?= htmlspecialchars($it['title']) ?></strong></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; break;

            case 'medication': ?>
                <div class="card-header">
                    <h3><?= $meta['icon'] ?> <?= $meta['label'] ?></h3>
                    <a href="<?= BASE_URL ?>/medication" class="btn-text">Voir tout</a>
                </div>
                <?php $treatments = $data['treatments'] ?? []; ?>
                <?php if (empty($treatments)): ?>
                    <p class="empty-state">Aucun traitement en cours.</p>
                <?php else: ?>
                    <ul class="event-list">
                        <?php foreach ($treatments as $t): ?>
                            <li class="event-item">
                                <span class="event-dot" style="background:var(--success)"></span>
                                <div class="event-info"><strong><?= htmlspecialchars($t['name']) ?></strong></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; break;

            case 'vehicles': ?>
                <div class="card-header">
                    <h3><?= $meta['icon'] ?> <?= $meta['label'] ?></h3>
                    <a href="<?= BASE_URL ?>/vehicles" class="btn-text">Voir tout</a>
                </div>
                <?php $vehicles = $data['vehicles'] ?? []; ?>
                <?php if (empty($vehicles)): ?>
                    <p class="empty-state">Aucun véhicule enregistré.</p>
                <?php else: ?>
                    <ul class="event-list">
                        <?php foreach ($vehicles as $v): ?>
                            <li class="event-item" style="cursor:pointer" onclick="window.location.href='<?= BASE_URL ?>/vehicles?id=<?= (int)$v['id'] ?>'">
                                <span class="event-dot" style="background:var(--primary)"></span>
                                <div class="event-info"><strong><?= htmlspecialchars($v['name']) ?></strong></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; break;

            case 'pets': ?>
                <div class="card-header">
                    <h3><?= $meta['icon'] ?> <?= $meta['label'] ?></h3>
                    <a href="<?= BASE_URL ?>/pets" class="btn-text">Voir tout</a>
                </div>
                <?php $pets = $data['pets'] ?? []; ?>
                <?php if (empty($pets)): ?>
                    <p class="empty-state">Aucun animal enregistré.</p>
                <?php else: ?>
                    <ul class="event-list">
                        <?php foreach ($pets as $p): ?>
                            <li class="event-item" style="cursor:pointer" onclick="window.location.href='<?= BASE_URL ?>/pets?id=<?= (int)$p['id'] ?>'">
                                <span class="event-dot" style="background:var(--accent)"></span>
                                <div class="event-info"><strong><?= htmlspecialchars($p['name']) ?></strong></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; break;

            case 'travels': ?>
                <div class="card-header">
                    <h3><?= $meta['icon'] ?> <?= $meta['label'] ?></h3>
                    <a href="<?= BASE_URL ?>/travels" class="btn-text">Voir tout</a>
                </div>
                <?php $travels = $data['travels'] ?? []; ?>
                <?php if (empty($travels)): ?>
                    <p class="empty-state">Aucun voyage prévu.</p>
                <?php else: ?>
                    <ul class="event-list">
                        <?php foreach ($travels as $t): ?>
                            <li class="event-item" style="cursor:pointer" onclick="window.location.href='<?= BASE_URL ?>/travels?id=<?= (int)$t['id'] ?>'">
                                <span class="event-dot" style="background:var(--info)"></span>
                                <div class="event-info">
                                    <strong><?= htmlspecialchars($t['title']) ?></strong>
                                    <?php if ($t['destination']): ?><span class="event-date"><?= htmlspecialchars($t['destination']) ?></span><?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; break;

            case 'meters': ?>
                <div class="card-header">
                    <h3><?= $meta['icon'] ?> <?= $meta['label'] ?></h3>
                    <a href="<?= BASE_URL ?>/meters" class="btn-text">Voir tout</a>
                </div>
                <?php $meters = $data['meters'] ?? []; ?>
                <?php if (empty($meters)): ?>
                    <p class="empty-state">Aucun compteur enregistré.</p>
                <?php else: ?>
                    <ul class="event-list">
                        <?php foreach ($meters as $m):
                            $mt = \App\Models\Meter::TYPES[$m['meter_type']] ?? ['icon' => '📊'];
                        ?>
                            <li class="event-item" style="cursor:pointer" onclick="window.location.href='<?= BASE_URL ?>/meters?id=<?= (int)$m['id'] ?>'">
                                <span class="event-dot" style="background:var(--warning)"></span>
                                <div class="event-info"><strong><?= $mt['icon'] ?> <?= htmlspecialchars($m['name']) ?></strong></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; break;

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
    <div class="modal modal-xl">
        <div class="modal-header">
            <h2>✨ Personnaliser le tableau de bord</h2>
            <button class="modal-close" onclick="Dashboard.closeWidgetManager()">✕</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-muted);font-size:.88rem;margin-bottom:1rem">
                Activez ou désactivez les widgets. Faites glisser les cartes sur le tableau de bord pour les réordonner.
            </p>
            <?php foreach (\App\Controllers\DashboardController::WIDGET_CATEGORIES as $catKey => $catLabel): ?>
                <?php $catWidgets = array_filter($widgetConfig, fn($w) => (\App\Controllers\DashboardController::AVAILABLE_WIDGETS[$w['slug']]['cat'] ?? null) === $catKey); ?>
                <?php if (empty($catWidgets)) continue; ?>
                <div class="widget-cat-label"><?= htmlspecialchars($catLabel) ?></div>
                <ul class="widget-manager-list widget-manager-grid" id="widget-manager-list-<?= $catKey ?>">
                    <?php foreach ($catWidgets as $w):
                        $slug = $w['slug'];
                        $meta = \App\Controllers\DashboardController::AVAILABLE_WIDGETS[$slug] ?? null;
                        if (!$meta) continue;
                    ?>
                        <li class="widget-manager-item <?= $w['enabled'] ? 'widget-manager-item-on' : '' ?>" data-slug="<?= $slug ?>">
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
            <?php endforeach; ?>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="Dashboard.saveWidgets()">Enregistrer ✨</button>
            <button class="btn btn-secondary" onclick="Dashboard.closeWidgetManager()">Fermer</button>
        </div>
    </div>
</div>

<!-- Digest hebdomadaire IA -->
<?php if ($weeklyDigest): ?>
<div class="modal-overlay" id="weekly-digest-modal">
    <div class="modal">
        <div class="modal-header">
            <h3>✨ Le point de la semaine</h3>
            <button class="modal-close" onclick="Dashboard.dismissWeeklyDigest()">✕</button>
        </div>
        <div class="modal-body">
            <p style="white-space:pre-wrap;line-height:1.5"><?= nl2br(htmlspecialchars($weeklyDigest['content'])) ?></p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="Dashboard.dismissWeeklyDigest()">Compris, merci !</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Bouton d'ajout rapide (mobile/PWA) -->
<button type="button" class="dash-fab" id="dash-fab" onclick="Dashboard.openQuickAdd()" aria-label="Ajouter rapidement">+</button>
<div class="dash-sheet-overlay" id="dash-sheet-overlay" style="display:none" onclick="Dashboard.closeQuickAdd()"></div>
<div class="dash-sheet" id="dash-sheet" style="display:none">
    <div class="dash-sheet-handle"></div>
    <h2>Ajouter rapidement</h2>
    <div class="dash-sheet-grid">
        <a href="<?= BASE_URL ?>/tasks" class="dash-sheet-btn"><span aria-hidden="true">✅</span>Tâche</a>
        <a href="<?= BASE_URL ?>/calendar" class="dash-sheet-btn"><span aria-hidden="true">📅</span>Événement</a>
        <a href="<?= BASE_URL ?>/documents" class="dash-sheet-btn"><span aria-hidden="true">🗂️</span>Document</a>
        <a href="<?= BASE_URL ?>/wall" class="dash-sheet-btn"><span aria-hidden="true">📸</span>Photo</a>
    </div>
</div>

<script>
var _weatherCity = <?= json_encode($family['weather_city'] ?? '') ?>;
var _widgetConfig = <?= json_encode($widgetConfig) ?>;
var _baseUrl = <?= json_encode(BASE_URL) ?>;
var _hasWeeklyDigest = <?= $weeklyDigest ? 'true' : 'false' ?>;
</script>
<script src="<?= ASSETS_URL ?>/js/dashboard.js?v=<?= APP_VERSION ?>"></script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
