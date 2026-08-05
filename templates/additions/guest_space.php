<?php
$pageTitle = 'Mon espace additions';
$guestToken = $params['token'] ?? '';
$extraJs = ['additions-guest.js'];
ob_start();
?>
<div class="card" style="padding:1.5rem;max-width:720px;margin:0 auto 1.25rem">
    <h2 style="margin:0 0 .3rem">🧾 Mon espace additions</h2>
    <p style="color:var(--text-muted);font-size:.85rem;margin:0">
        Bonjour <?= htmlspecialchars($guest['name'] ?: $guest['email']) ?>, voici les additions et
        cagnottes partagées avec vous. Créez un compte FamilyBoard avec la même adresse e-mail
        (<?= htmlspecialchars($guest['email']) ?>) pour les retrouver directement dans votre compte.
    </p>
</div>

<?php if (empty($participations)): ?>
    <p class="empty-state" style="max-width:720px;margin:0 auto">Rien pour l'instant.</p>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:1rem;max-width:720px;margin:0 auto">
    <?php foreach ($participations as $p): ?>
    <div class="card" style="padding:1.25rem">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
            <div>
                <h3 style="margin:0"><?= $p['is_cagnotte'] ? '🪙 ' : '' ?><?= htmlspecialchars($p['title']) ?></h3>
                <?php if ($p['description']): ?><p style="color:var(--text-muted);font-size:.85rem;margin:.3rem 0"><?= nl2br(htmlspecialchars($p['description'])) ?></p><?php endif; ?>
                <p style="font-size:.82rem;color:var(--text-muted);margin:.2rem 0">Famille <?= htmlspecialchars($p['family_name']) ?></p>
            </div>
            <?php if ($p['status'] === 'pending'): ?>
                <div style="display:flex;gap:.4rem">
                    <button class="btn btn-primary btn-sm" onclick="guestRespond(<?= (int)$p['id'] ?>, 'accept')">Accepter</button>
                    <button class="btn btn-secondary btn-sm" onclick="guestRespond(<?= (int)$p['id'] ?>, 'decline')">Refuser</button>
                </div>
            <?php else: ?>
                <span class="badge-custom"><?= ['accepted' => 'Accepté', 'declined' => 'Refusé'][$p['status']] ?? $p['status'] ?></span>
            <?php endif; ?>
        </div>
        <?php if (!$p['is_cagnotte']): ?>
            <p style="margin:.5rem 0">Total : <strong><?= number_format((float)$p['total_amount'], 2, ',', ' ') ?> €</strong>
                — votre part : <strong><?= number_format((float)$p['share_value'], 2, ',', ' ') ?><?= $p['split_mode'] === 'percentage' ? ' %' : ' €' ?></strong></p>
        <?php else: ?>
            <?php $methods = $p['cagnotte_methods'] ? explode(',', $p['cagnotte_methods']) : []; ?>
            <?php if ($methods): ?>
            <p style="font-size:.85rem;margin:.5rem 0">
                Moyens acceptés :
                <?php foreach ($methods as $m): ?><span class="badge-custom"><?= ['cash' => 'Espèces', 'transfer' => 'Virement', 'wero' => 'Wero'][$m] ?? $m ?></span><?php endforeach; ?>
                <?php if ($p['cagnotte_iban']): ?> · IBAN : <code><?= htmlspecialchars($p['cagnotte_iban']) ?></code><?php endif; ?>
                <?php if ($p['cagnotte_wero_phone']): ?> · Wero : <code><?= htmlspecialchars($p['cagnotte_wero_phone']) ?></code><?php endif; ?>
            </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>var GUEST_TOKEN = <?= json_encode($guestToken) ?>;</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
