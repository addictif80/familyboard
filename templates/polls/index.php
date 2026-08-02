<?php
$pageTitle = 'Sondages familiaux';
$extraJs   = ['polls.js'];
ob_start();
?>
<div class="content-header">
    <h2>🗳️ Sondages familiaux</h2>
    <button class="btn btn-primary" onclick="openPollModal()">+ Nouveau sondage</button>
</div>
<p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1.25rem">
    Proposez une question, chacun vote pour son option préférée — utile pour décider
    rapidement en famille (« on mange où ce soir ? », « destination des vacances ? »).
</p>

<?php if (empty($polls)): ?>
    <div class="empty-state-full">
        <div style="font-size:3rem">🗳️</div>
        <p>Aucun sondage pour le moment.</p>
        <button class="btn btn-primary" onclick="openPollModal()">Créer le premier sondage</button>
    </div>
<?php else: foreach ($polls as $poll): ?>
<div class="poll-card <?= $poll['is_closed'] ? 'wishlist-purchased' : '' ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem">
        <div>
            <h3 style="margin:0 0 .2rem"><?= htmlspecialchars($poll['question']) ?></h3>
            <span style="color:var(--text-muted);font-size:.78rem">
                Par <?= htmlspecialchars($poll['author_name']) ?> · <?= (int)$poll['vote_count'] ?> vote(s)
                <?= $poll['is_closed'] ? ' · Clos' : '' ?>
            </span>
        </div>
        <?php if ((int)$poll['user_id'] === (int)$user['id'] || $user['role'] === 'admin'): ?>
        <div style="display:flex;gap:.35rem;flex-shrink:0">
            <button class="btn btn-secondary btn-sm" onclick="togglePollClosed(<?= (int)$poll['id'] ?>)"><?= $poll['is_closed'] ? '🔓 Rouvrir' : '🔒 Clore' ?></button>
            <button class="btn btn-danger btn-sm" onclick="deletePoll(<?= (int)$poll['id'] ?>)">🗑️</button>
        </div>
        <?php endif; ?>
    </div>

    <div style="margin-top:.75rem">
        <?php foreach ($poll['options'] as $opt):
            $pct = $poll['vote_count'] > 0 ? round($opt['votes'] / $poll['vote_count'] * 100) : 0;
            $voted = $poll['my_vote'] === (int)$opt['id'];
        ?>
        <div class="poll-option <?= $voted ? 'poll-option-voted' : '' ?>"
             <?= $poll['is_closed'] ? '' : 'onclick="votePoll(' . (int)$poll['id'] . ',' . (int)$opt['id'] . ')"' ?>>
            <div class="poll-option-bar" style="width:<?= $pct ?>%"></div>
            <div class="poll-option-content">
                <span><?= $voted ? '✓ ' : '' ?><?= htmlspecialchars($opt['label']) ?></span>
                <span style="color:var(--text-muted);font-size:.82rem"><?= $pct ?>% (<?= (int)$opt['votes'] ?>)</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; endif; ?>

<!-- New poll modal -->
<div class="modal-overlay" id="poll-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Nouveau sondage</h3>
            <button onclick="closeModal('poll-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Question</label>
                <input type="text" id="poll-question" placeholder="On mange où ce soir ?">
            </div>
            <div class="form-group">
                <label>Options (au moins 2)</label>
                <div id="poll-options-wrap">
                    <input type="text" class="poll-option-input" placeholder="Option 1" style="margin-bottom:.4rem">
                    <input type="text" class="poll-option-input" placeholder="Option 2" style="margin-bottom:.4rem">
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addPollOptionField()">+ Ajouter une option</button>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="createPoll()">Créer</button>
            <button class="btn btn-secondary" onclick="closeModal('poll-modal')">Annuler</button>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
