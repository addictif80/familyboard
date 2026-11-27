<?php
$pageTitle = 'Carnet de santé partagé';
ob_start();
?>
<div class="card" style="max-width:720px;margin:2rem auto;padding:1.5rem">
<?php if (!$active): ?>
    <h2>⛔ Lien invalide ou expiré</h2>
    <p style="color:var(--text-muted)">Ce lien de partage n'est plus valable. Demandez-en un nouveau à la famille.</p>
<?php else: ?>
    <h2 style="margin-bottom:.25rem">🏥 Carnet de santé — <?= htmlspecialchars($subjectName) ?></h2>
    <p style="color:var(--text-muted);font-size:.85rem">
        Partagé par <?= htmlspecialchars($family['name'] ?? 'une famille') ?> ·
        lien valable jusqu'au <?= (new \DateTime($link['expires_at']))->format('d/m/Y à H:i') ?>
    </p>
    <div style="background:color-mix(in srgb, var(--accent) 10%, transparent);border-left:3px solid var(--accent);border-radius:6px;padding:.75rem 1rem;margin:1rem 0;font-size:.85rem">
        Lecture seule — ce lien ne permet aucune modification. Il expirera automatiquement à la date indiquée ci-dessus.
    </div>

    <?php if (!empty($doctors)): ?>
    <h3 style="margin-top:1.5rem">🩺 Médecin(s) suivi(s)</h3>
    <ul style="padding-left:1.2rem">
        <?php foreach ($doctors as $doc): ?>
        <li style="margin-bottom:.4rem">
            <strong><?= htmlspecialchars($doc['name']) ?></strong><?= $doc['specialty'] ? ' — ' . htmlspecialchars($doc['specialty']) : '' ?>
            <?php if ($doc['phone']): ?><br>📞 <?= htmlspecialchars($doc['phone']) ?><?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($link['subject_type'] === 'child'): ?>
    <h3 style="margin-top:1.5rem">📈 Croissance</h3>
    <?php if (empty($growth)): ?>
        <p class="empty-state">Aucune mesure enregistrée.</p>
    <?php else: ?>
    <table class="admin-table">
        <thead><tr><th>Date</th><th>Taille</th><th>Poids</th></tr></thead>
        <tbody>
        <?php foreach ($growth as $g): ?>
            <tr>
                <td><?= (new \DateTime($g['measured_at']))->format('d/m/Y') ?></td>
                <td><?= $g['height_cm'] !== null ? number_format((float)$g['height_cm'], 1, ',', ' ') . ' cm' : '—' ?></td>
                <td><?= $g['weight_kg'] !== null ? number_format((float)$g['weight_kg'], 2, ',', ' ') . ' kg' : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php endif; ?>

    <h3 style="margin-top:1.5rem">📋 Carnet de santé</h3>
    <?php if (empty($entries)): ?>
        <p class="empty-state">Aucune entrée enregistrée.</p>
    <?php else: ?>
    <ul style="padding-left:1.2rem">
        <?php foreach ($entries as $e): ?>
        <?php $t = \App\Models\Health::ENTRY_TYPES[$e['entry_type']] ?? ['icon' => '🏥', 'label' => $e['entry_type']]; ?>
        <li style="margin-bottom:.6rem">
            <strong><?= $t['icon'] ?> <?= htmlspecialchars($e['title']) ?></strong>
            <span style="color:var(--text-muted);font-size:.82rem"> — <?= htmlspecialchars($t['label']) ?><?= $e['entry_date'] ? ' · ' . (new \DateTime($e['entry_date']))->format('d/m/Y') : '' ?></span>
            <?php if ($e['description']): ?><div style="white-space:pre-wrap;font-size:.9rem"><?= htmlspecialchars($e['description']) ?></div><?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
<?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
