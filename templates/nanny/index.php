<?php
$pageTitle = 'Suivi nounou';
$extraJs = ['nanny.js'];
ob_start();

$monthNames = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
    7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];
$monthNamesShort = [1 => 'Jan', 2 => 'Fév', 3 => 'Mar', 4 => 'Avr', 5 => 'Mai', 6 => 'Juin',
    7 => 'Juil', 8 => 'Août', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc'];
$pdfQuery = $childId ? ('?child_id=' . $childId) : '';
?>
<div class="tasks-main" style="width:100%">
    <div class="tasks-header">
        <h2>🕒 Suivi nounou</h2>
        <?php if (!$isCoparent): ?>
        <div style="display:flex;gap:.4rem;flex-wrap:wrap">
            <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/settings#family-children-list">👶 Enfants</a>
            <button class="btn btn-primary btn-sm" onclick="openNewEntryModal()">+ Nouvelle entrée</button>
        </div>
        <?php endif; ?>
    </div>

    <form method="GET" action="<?= BASE_URL ?>/nanny" class="card settings-section" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1rem">
        <div class="form-group" style="margin:0">
            <label>Année</label>
            <select name="year" onchange="this.form.submit()">
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0">
            <label>Mois</label>
            <select name="month" onchange="this.form.submit()">
                <?php foreach ($monthNames as $num => $label): ?>
                    <option value="<?= $num ?>" <?= $num === $month ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0">
            <label>Enfant</label>
            <select name="child_id" onchange="this.form.submit()">
                <option value="">— Tous les enfants —</option>
                <?php foreach ($children as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $childId === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;display:flex;gap:.4rem">
            <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/nanny/report/<?= $year ?>/<?= $month ?>/pdf<?= $pdfQuery ?>" target="_blank">📄 Rapport du mois</a>
            <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/nanny/report/<?= $year ?>/pdf<?= $pdfQuery ?>" target="_blank">📄 Rapport annuel</a>
        </div>
    </form>

    <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
        <div class="card settings-section" style="flex:1;min-width:200px;text-align:center">
            <div style="font-size:.8rem;color:var(--text-muted)">Total <?= htmlspecialchars($monthNames[$month]) ?> <?= $year ?></div>
            <div style="font-size:1.8rem;font-weight:700"><?= number_format($monthlyTotal, 2, ',', ' ') ?> h</div>
        </div>
        <div class="card settings-section" style="flex:1;min-width:200px;text-align:center">
            <div style="font-size:.8rem;color:var(--text-muted)">Total année <?= $year ?></div>
            <div style="font-size:1.8rem;font-weight:700"><?= number_format($annualTotal, 2, ',', ' ') ?> h</div>
        </div>
    </div>

    <div class="card settings-section" style="margin-bottom:1rem;overflow-x:auto">
        <div style="font-weight:600;margin-bottom:.5rem;font-size:.85rem">Répartition mensuelle <?= $year ?></div>
        <table class="admin-table">
            <thead><tr><?php foreach ($monthNamesShort as $label): ?><th><?= $label ?></th><?php endforeach; ?></tr></thead>
            <tbody><tr><?php foreach ($monthlyBreakdown as $h): ?><td><?= number_format($h, 1, ',', ' ') ?></td><?php endforeach; ?></tr></tbody>
        </table>
    </div>

    <div class="card settings-section" style="overflow-x:auto">
        <table class="admin-table">
            <thead><tr><th>Date</th><th>Enfant</th><th>Nounou</th><th>Heures</th><th>Notes</th><?php if (!$isCoparent): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($entries as $e): ?>
                <tr data-entry-id="<?= $e['id'] ?>">
                    <td><?= (new DateTime($e['entry_date']))->format('d/m/Y') ?></td>
                    <td><?php if ($e['child_name']): ?><span class="list-dot" style="background:<?= htmlspecialchars($e['child_color']) ?>"></span> <?= htmlspecialchars($e['child_name']) ?><?php else: ?>—<?php endif; ?></td>
                    <td><?= htmlspecialchars($e['nanny_name'] ?: '—') ?></td>
                    <td><?= number_format((float)$e['hours'], 2, ',', ' ') ?> h</td>
                    <td><?= htmlspecialchars($e['notes']) ?></td>
                    <?php if (!$isCoparent): ?>
                    <td style="white-space:nowrap">
                        <button class="btn-icon" title="Modifier" onclick='openEditEntryModal(<?= json_encode($e) ?>)'>✏️</button>
                        <button class="btn-icon" title="Supprimer" onclick="deleteEntry(<?= $e['id'] ?>)">🗑</button>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($entries)): ?>
                <tr><td colspan="<?= $isCoparent ? 5 : 6 ?>" class="empty-state">Aucune entrée sur cette période.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!$isCoparent): ?>
<!-- Nouvelle/modifier entrée -->
<div class="modal-overlay" id="entry-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="entry-modal-title">Nouvelle entrée</h3>
            <button onclick="closeModal('entry-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="entry-id">
            <div class="form-row">
                <div class="form-group">
                    <label>Date <span style="color:var(--danger)">*</span></label>
                    <input type="date" id="entry-date">
                </div>
                <div class="form-group">
                    <label>Heures <span style="color:var(--danger)">*</span></label>
                    <input type="number" id="entry-hours" min="0.25" max="24" step="0.25" placeholder="7.5">
                </div>
            </div>
            <div class="form-group">
                <label>Enfant</label>
                <select id="entry-child" onchange="toggleNewChildInput('entry')">
                    <option value="">— Non précisé —</option>
                    <?php foreach ($children as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                    <option value="__new__">+ Nouvel enfant…</option>
                </select>
                <input type="text" id="entry-child-new-name" placeholder="Nom du nouvel enfant" style="display:none;margin-top:.4rem">
            </div>
            <div class="form-group">
                <label>Nom de la nounou</label>
                <input type="text" id="entry-nanny-name">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <input type="text" id="entry-notes">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('entry-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveEntry()">Enregistrer</button>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
