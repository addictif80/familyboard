<?php
$pageTitle = 'Abonnement';
ob_start();

use App\Models\Family;

$sub = $subscription;
$status = $sub['status'] ?? 'none';
$statusLabels = [
    'none'      => ['label' => 'Offre Gratuite', 'class' => ''],
    'trialing'  => ['label' => 'Essai gratuit en cours', 'class' => 'badge-success'],
    'active'    => ['label' => 'Abonné·e', 'class' => 'badge-success'],
    'past_due'  => ['label' => 'Paiement en échec', 'class' => 'badge-danger'],
    'canceled'  => ['label' => 'Abonnement résilié', 'class' => 'badge-danger'],
];
$statusInfo = $statusLabels[$status] ?? $statusLabels['none'];
?>
<div class="page-header">
    <h2>💳 Abonnement</h2>
</div>

<?php if ($upsellModule): ?>
<div class="card" style="border-left:4px solid var(--primary);margin-bottom:1rem">
    <p style="margin:0">🔒 Le module que vous venez d'ouvrir fait partie de l'offre <strong>Premium</strong>. Abonnez-vous pour en profiter, ainsi que de tous les autres modules avancés.</p>
</div>
<?php endif; ?>

<?php if (!$billingEnabled): ?>
<div class="card">
    <p>La facturation n'est pas encore activée sur cette instance — tous les modules sont accessibles librement pour le moment.</p>
</div>
<?php else: ?>

<div class="card" style="margin-bottom:1.5rem">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
        <div>
            <strong>Statut actuel : </strong>
            <span class="badge <?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span>
            <?php if ($sub && $sub['plan_name']): ?>
                <span class="badge"><?= htmlspecialchars($sub['plan_name']) ?> — <?= $sub['member_limit'] ? $sub['member_limit'] . ' membres max' : 'membres illimités' ?></span>
            <?php endif; ?>
            <?php if ($status === 'trialing' && !empty($sub['trial_ends_at'])): ?>
                <p style="color:var(--text-muted);font-size:.85rem;margin:.4rem 0 0">Votre essai se termine le <?= (new DateTime($sub['trial_ends_at']))->format('d/m/Y') ?>.</p>
            <?php elseif ($status === 'active' && !empty($sub['current_period_end'])): ?>
                <p style="color:var(--text-muted);font-size:.85rem;margin:.4rem 0 0">Prochain renouvellement le <?= (new DateTime($sub['current_period_end']))->format('d/m/Y') ?>.</p>
            <?php elseif (in_array($status, ['past_due', 'canceled'], true) && !empty($sub['grace_ends_at'])): ?>
                <p style="color:var(--danger);font-size:.85rem;margin:.4rem 0 0">Accès aux modules Premium maintenu jusqu'au <?= (new DateTime($sub['grace_ends_at']))->format('d/m/Y') ?>, le temps de régulariser.</p>
            <?php endif; ?>
        </div>
        <?php if ($isAdmin && $sub && !empty($sub['stripe_customer_id'])): ?>
        <form method="POST" action="<?= BASE_URL ?>/api/subscription/portal">
            <button type="submit" class="btn btn-secondary">Gérer mon abonnement</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!$isAdmin): ?>
<div class="card" style="margin-bottom:1.5rem">
    <p style="margin:0;color:var(--text-muted)">Seul l'administrateur de la famille peut souscrire ou modifier l'abonnement.</p>
</div>
<?php endif; ?>

<?php if (!$stripeConfigured): ?>
<div class="card"><p>La facturation est activée mais pas encore configurée par l'administrateur système. Revenez bientôt.</p></div>
<?php else: ?>

<div class="pricing-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.25rem">
    <?php foreach ($plans as $p): ?>
        <?php
        $monthly = $p['price_monthly_cents'] / 100;
        $yearly  = $p['price_yearly_cents'] / 100;
        $isCurrent = $sub && (int)($sub['plan_id'] ?? 0) === (int)$p['id'] && in_array($status, ['trialing', 'active'], true);
        ?>
        <div class="card" style="display:flex;flex-direction:column;gap:.75rem;<?= $isCurrent ? 'border:2px solid var(--primary)' : '' ?>">
            <h3 style="margin:0"><?= htmlspecialchars($p['name']) ?></h3>
            <p style="color:var(--text-muted);margin:0"><?= $p['member_limit'] ? "Jusqu'à {$p['member_limit']} membres" : 'Membres illimités' ?></p>
            <div>
                <strong style="font-size:1.5rem"><?= number_format($monthly, 2, ',', ' ') ?> €</strong>
                <span style="color:var(--text-muted)">/ mois</span>
            </div>
            <p style="color:var(--text-muted);font-size:.85rem;margin:0"><?= number_format($yearly, 2, ',', ' ') ?> € / an <?= $annualDiscount > 0 ? "(-{$annualDiscount}%)" : '' ?></p>
            <?php if ($memberCount > 0 && $p['member_limit'] && $memberCount > $p['member_limit']): ?>
                <p style="color:var(--danger);font-size:.8rem;margin:0">Votre famille compte déjà <?= $memberCount ?> membres.</p>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
            <form method="POST" action="<?= BASE_URL ?>/api/subscription/checkout" style="display:flex;gap:.4rem;margin-top:auto">
                <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                <input type="hidden" name="interval" value="monthly">
                <button type="submit" class="btn <?= $isCurrent ? 'btn-secondary' : 'btn-primary' ?> btn-sm" style="flex:1"><?= $isCurrent ? 'Palier actuel' : 'S\'abonner (mensuel)' ?></button>
            </form>
            <form method="POST" action="<?= BASE_URL ?>/api/subscription/checkout">
                <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                <input type="hidden" name="interval" value="yearly">
                <button type="submit" class="btn btn-secondary btn-sm" style="width:100%">S'abonner (annuel)</button>
            </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!$hasUsedTrial): ?>
<p style="color:var(--text-muted);font-size:.85rem;margin-top:1rem">✨ Essai gratuit de <?= $trialDays ?> jours sur le premier abonnement, résiliable à tout moment.</p>
<?php endif; ?>

<?php endif; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
require BASE_PATH . '/templates/layout.php';
