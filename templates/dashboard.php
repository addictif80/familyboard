<?php
$pageTitle = 'Tableau de bord';
ob_start();
?>
<div class="dashboard-grid">
    <!-- Welcome card -->
    <div class="card card-welcome">
        <h2>Bonjour, <?= htmlspecialchars($user['name']) ?> 👋</h2>
        <p><?= date('l j F Y') ?></p>
    </div>

    <!-- Upcoming events -->
    <div class="card">
        <div class="card-header">
            <h3>📅 Prochains événements</h3>
            <a href="<?= BASE_URL ?>/calendar" class="btn-text">Voir tout</a>
        </div>
        <?php if (empty($upcomingEvents)): ?>
            <p class="empty-state">Aucun événement à venir.</p>
        <?php else: ?>
            <ul class="event-list">
                <?php foreach ($upcomingEvents as $event): ?>
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
        <?php endif; ?>
    </div>

    <!-- Custody today -->
    <?php if (!empty($custodyToday)): ?>
    <div class="card">
        <div class="card-header">
            <h3>👶 Garde aujourd'hui</h3>
            <a href="<?= BASE_URL ?>/custody" class="btn-text">Planning</a>
        </div>
        <ul class="custody-list">
            <?php foreach ($custodyToday as $c): ?>
                <li class="custody-item">
                    <span class="custody-dot" style="background:<?= htmlspecialchars($c['schedule_color']) ?>"></span>
                    <strong><?= htmlspecialchars($c['child_name']) ?></strong> chez <?= htmlspecialchars($c['parent_name']) ?>
                    <?php if ($c['arrival_time']): ?>
                        <small>(arrivée <?= substr($c['arrival_time'], 0, 5) ?>)</small>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Recent wall posts -->
    <div class="card">
        <div class="card-header">
            <h3>📸 Mur familial</h3>
            <a href="<?= BASE_URL ?>/wall" class="btn-text">Voir tout</a>
        </div>
        <?php if (empty($recentPosts)): ?>
            <p class="empty-state">Aucune publication récente.</p>
        <?php else: ?>
            <ul class="post-preview-list">
                <?php foreach ($recentPosts as $post): ?>
                    <li class="post-preview-item">
                        <div class="user-avatar-sm" style="background:<?= htmlspecialchars($post['user_color']) ?>">
                            <?= mb_substr($post['user_name'], 0, 1) ?>
                        </div>
                        <div>
                            <strong><?= htmlspecialchars($post['user_name']) ?></strong>
                            <p><?= htmlspecialchars(mb_substr($post['content'], 0, 80)) ?><?= mb_strlen($post['content']) > 80 ? '…' : '' ?></p>
                            <small><?= date('d/m à H:i', strtotime($post['created_at'])) ?></small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Quick links -->
    <div class="card">
        <h3>Accès rapide</h3>
        <div class="quick-links">
            <a href="<?= BASE_URL ?>/wall" class="quick-link">
                <span>📸</span><span>Mur</span>
            </a>
            <a href="<?= BASE_URL ?>/tasks" class="quick-link">
                <span>✅</span><span>Tâches</span>
            </a>
            <a href="<?= BASE_URL ?>/chat" class="quick-link">
                <span>💬</span><span>Chat</span>
            </a>
            <a href="<?= BASE_URL ?>/budget" class="quick-link">
                <span>💰</span><span>Budget</span>
            </a>
            <a href="<?= BASE_URL ?>/custody" class="quick-link">
                <span>👶</span><span>Garde</span>
            </a>
            <a href="<?= BASE_URL ?>/projects" class="quick-link">
                <span>📋</span><span>Projets</span>
            </a>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
