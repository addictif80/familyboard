<?php
$pageTitle = 'Support';
ob_start();
?>
<div class="support-page">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.5rem">
        <div>
            <h2 style="margin:0">🎫 Tickets de support</h2>
            <p style="color:var(--text-muted);font-size:.85rem;margin:.25rem 0 0">Contactez l'administrateur pour toute question ou problème.</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('new-ticket-modal')">+ Nouveau ticket</button>
    </div>

    <?php if (empty($tickets)): ?>
        <div class="empty-state-full">
            <div style="font-size:3rem">🎫</div>
            <p>Aucun ticket de support pour le moment.</p>
            <button class="btn btn-primary" onclick="openModal('new-ticket-modal')">Ouvrir un ticket</button>
        </div>
    <?php else: ?>
    <div class="card">
        <table class="admin-table">
            <thead><tr><th>#</th><th>Sujet</th><th>Statut</th><th>Messages</th><th>Mis à jour</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tickets as $t): ?>
            <tr>
                <td><?= $t['id'] ?></td>
                <td><a href="<?= BASE_URL ?>/support/<?= $t['id'] ?>"><?= htmlspecialchars($t['subject']) ?></a></td>
                <td><span class="badge badge-<?= $t['status'] === 'closed' ? 'ok' : ($t['status'] === 'in_progress' ? 'warn' : 'new') ?>">
                    <?= match($t['status']) { 'open'=>'🆕 Ouvert','in_progress'=>'💬 En cours','closed'=>'✅ Fermé',default=>$t['status'] } ?>
                </span></td>
                <td><?= $t['msg_count'] ?></td>
                <td><?= \App\Core\DateHelper::fromUtc($t['updated_at'], 'd/m/Y H:i') ?></td>
                <td><a href="<?= BASE_URL ?>/support/<?= $t['id'] ?>" class="btn btn-secondary btn-sm">Voir</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- New ticket modal -->
<div class="modal-overlay" id="new-ticket-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Nouveau ticket de support</h3>
            <button onclick="closeModal('new-ticket-modal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/support">
            <div class="modal-body">
                <div class="form-group">
                    <label>Sujet *</label>
                    <input type="text" name="subject" required placeholder="Décrivez brièvement votre problème…">
                </div>
                <div class="form-group">
                    <label>Message *</label>
                    <textarea name="message" rows="5" required placeholder="Décrivez en détail votre problème ou question…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('new-ticket-modal')">Annuler</button>
                <button type="submit" class="btn btn-primary">Envoyer</button>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
