<?php
$pageTitle = 'À rattacher';
$extraJs = ['nextcloud-inbox.js'];
ob_start();
?>
<div class="tasks-main" style="width:100%">
    <div class="tasks-header">
        <h2>📂 À rattacher</h2>
    </div>
    <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
        Fichiers déposés directement dans Nextcloud (hors FamilyBoard), dans le dossier
        <code>a-rattacher</code> de l'espace familial partagé. Choisissez où rattacher chacun, ou ignorez-le
        pour le laisser tel quel dans Nextcloud.
    </p>

    <?php if (empty($pending)): ?>
        <div class="empty-state-full">
            <div style="font-size:3rem">📭</div>
            <p>Rien à rattacher pour le moment.</p>
        </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:1rem">
        <?php foreach ($pending as $f): ?>
        <div class="card" style="padding:1.25rem" id="ncinbox-item-<?= (int)$f['id'] ?>">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.5rem">
                <div>
                    <strong>📄 <?= htmlspecialchars($f['original_name']) ?></strong>
                    <div style="font-size:.8rem;color:var(--text-muted)">
                        <?= \App\Core\PlatformHealth::formatBytes((int)$f['size_bytes']) ?>
                        · déposé par <?= htmlspecialchars($f['uploader_name'] ?? 'quelqu\'un (non identifié)') ?>
                        · <?= \App\Core\DateHelper::fromUtc($f['discovered_at'], 'd/m/Y H:i') ?>
                    </div>
                </div>
                <button class="btn btn-secondary btn-sm" onclick="ncinboxDismiss(<?= (int)$f['id'] ?>)">Ignorer</button>
            </div>

            <div class="form-row" style="margin-top:.75rem">
                <div class="form-group">
                    <label>Rattacher à</label>
                    <select id="ncinbox-module-<?= (int)$f['id'] ?>" onchange="ncinboxToggleFields(<?= (int)$f['id'] ?>)">
                        <option value="documents">🗂️ Documents</option>
                        <option value="warranties">🧾 Garanties</option>
                        <option value="deals">🏷️ Bons plans</option>
                        <option value="school">🎓 Scolaire</option>
                        <option value="disputes">⚖️ Litiges</option>
                    </select>
                </div>
                <div class="form-group flex-2 ncinbox-field-title">
                    <label>Titre</label>
                    <input type="text" id="ncinbox-title-<?= (int)$f['id'] ?>" value="<?= htmlspecialchars(pathinfo($f['original_name'], PATHINFO_FILENAME)) ?>">
                </div>
                <div class="form-group ncinbox-field-school" style="display:none">
                    <label>Élève</label>
                    <select id="ncinbox-student-<?= (int)$f['id'] ?>">
                        <?php foreach ($students as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group ncinbox-field-disputes" style="display:none">
                    <label>Litige</label>
                    <select id="ncinbox-dispute-<?= (int)$f['id'] ?>">
                        <?php foreach ($disputes as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php if (empty($students)): ?><p class="form-hint ncinbox-field-school" style="display:none;color:var(--danger)">Aucun élève enregistré dans le module Scolaire.</p><?php endif; ?>
            <?php if (empty($disputes)): ?><p class="form-hint ncinbox-field-disputes" style="display:none;color:var(--danger)">Aucun litige enregistré.</p><?php endif; ?>

            <button class="btn btn-primary btn-sm ncinbox-attach-btn" style="margin-top:.5rem" onclick="ncinboxAttach(<?= (int)$f['id'] ?>)">Rattacher</button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
