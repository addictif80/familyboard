<?php
$pageTitle = 'Fiche urgence';
ob_start();
?>
<div class="card" style="max-width:480px;margin:2rem auto;padding:1.5rem">
<?php if (!$card): ?>
    <h2>🚑 Fiche introuvable</h2>
    <p style="color:var(--text-muted)">Ce lien est invalide ou la fiche a été supprimée.</p>
<?php else: ?>
    <h2>🚑 Fiche urgence — <?= htmlspecialchars($subjectName) ?></h2>
    <div style="display:flex;flex-direction:column;gap:.9rem;margin-top:1rem">
        <?php if ($card['blood_type']): ?>
        <div><strong>Groupe sanguin :</strong> <?= htmlspecialchars($card['blood_type']) ?></div>
        <?php endif; ?>
        <?php if ($card['allergies']): ?>
        <div><strong>⚠️ Allergies :</strong> <?= htmlspecialchars($card['allergies']) ?></div>
        <?php endif; ?>
        <?php if ($card['medications']): ?>
        <div><strong>Traitements en cours :</strong> <?= htmlspecialchars($card['medications']) ?></div>
        <?php endif; ?>
        <?php if ($card['conditions']): ?>
        <div><strong>Conditions médicales :</strong> <?= htmlspecialchars($card['conditions']) ?></div>
        <?php endif; ?>
        <?php if ($card['doctor_name'] || $card['doctor_phone']): ?>
        <div><strong>Médecin traitant :</strong> <?= htmlspecialchars($card['doctor_name'] ?? '') ?>
            <?php if ($card['doctor_phone']): ?> — <a href="tel:<?= htmlspecialchars($card['doctor_phone']) ?>"><?= htmlspecialchars($card['doctor_phone']) ?></a><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($card['emergency_contact_name'] || $card['emergency_contact_phone']): ?>
        <div><strong>Contact d'urgence :</strong> <?= htmlspecialchars($card['emergency_contact_name'] ?? '') ?>
            <?php if ($card['emergency_contact_phone']): ?> — <a href="tel:<?= htmlspecialchars($card['emergency_contact_phone']) ?>"><?= htmlspecialchars($card['emergency_contact_phone']) ?></a><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($card['notes']): ?>
        <div><strong>Notes :</strong> <?= nl2br(htmlspecialchars($card['notes'])) ?></div>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
