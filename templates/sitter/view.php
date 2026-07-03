<?php
$pageTitle = 'Accès baby-sitter';
ob_start();
?>
<div class="card" style="max-width:640px;margin:2rem auto;padding:1.5rem">
<?php if (!$link): ?>
    <h2>⛔ Lien invalide ou expiré</h2>
    <p style="color:var(--text-muted)">Ce lien d'accès n'est plus valable. Demandez-en un nouveau à la famille.</p>
<?php else: ?>
    <h2>👶 <?= htmlspecialchars($family['name'] ?? 'Famille') ?> — <?= htmlspecialchars($link['label']) ?></h2>
    <p style="color:var(--text-muted);font-size:.85rem">
        Accès valable jusqu'au <?= \App\Core\DateHelper::fromUtc($link['expires_at'], 'd/m/Y à H:i') ?>.
        Vue lecture seule du <?= \App\Core\DateHelper::long($today) ?>.
    </p>

    <h3 style="margin-top:1.5rem">📅 Aujourd'hui</h3>
    <?php if (empty($events)): ?>
        <p style="color:var(--text-muted);font-size:.85rem">Aucun événement aujourd'hui.</p>
    <?php else: ?>
        <ul style="padding-left:1.2rem">
            <?php foreach ($events as $e): ?>
                <li>
                    <strong><?= htmlspecialchars($e['title']) ?></strong>
                    <?php if (!$e['is_all_day']): ?>
                        — <?= date('H\hi', strtotime($e['start_datetime'])) ?>
                    <?php else: ?>
                        — Toute la journée
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h3 style="margin-top:1.5rem">✅ Tâches en cours</h3>
    <?php if (empty($tasks)): ?>
        <p style="color:var(--text-muted);font-size:.85rem">Aucune tâche ouverte.</p>
    <?php else: ?>
        <ul style="padding-left:1.2rem">
            <?php foreach ($tasks as $t): ?>
                <li>
                    <strong><?= htmlspecialchars($t['title']) ?></strong>
                    <small style="color:var(--text-muted)"> (<?= htmlspecialchars($t['list_name']) ?>)</small>
                    <?php if ($t['notes']): ?><br><small><?= htmlspecialchars($t['notes']) ?></small><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h3 style="margin-top:1.5rem">🚑 Fiches urgence</h3>
    <?php if (empty($cards)): ?>
        <p style="color:var(--text-muted);font-size:.85rem">Aucune fiche urgence enregistrée.</p>
    <?php else: ?>
        <?php foreach ($cards as $c): ?>
        <div style="border:1px solid var(--border);border-radius:8px;padding:.75rem 1rem;margin-bottom:.75rem">
            <strong><?= htmlspecialchars($c['subject_name']) ?></strong>
            <div style="font-size:.85rem;margin-top:.3rem;display:flex;flex-direction:column;gap:.25rem">
                <?php if ($c['allergies']): ?><div>⚠️ Allergies : <?= htmlspecialchars($c['allergies']) ?></div><?php endif; ?>
                <?php if ($c['medications']): ?><div>Traitements : <?= htmlspecialchars($c['medications']) ?></div><?php endif; ?>
                <?php if ($c['emergency_contact_name'] || $c['emergency_contact_phone']): ?>
                <div>Contact d'urgence : <?= htmlspecialchars($c['emergency_contact_name'] ?? '') ?>
                    <?php if ($c['emergency_contact_phone']): ?> — <a href="tel:<?= htmlspecialchars($c['emergency_contact_phone']) ?>"><?= htmlspecialchars($c['emergency_contact_phone']) ?></a><?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($c['doctor_name'] || $c['doctor_phone']): ?>
                <div>Médecin : <?= htmlspecialchars($c['doctor_name'] ?? '') ?>
                    <?php if ($c['doctor_phone']): ?> — <a href="tel:<?= htmlspecialchars($c['doctor_phone']) ?>"><?= htmlspecialchars($c['doctor_phone']) ?></a><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
