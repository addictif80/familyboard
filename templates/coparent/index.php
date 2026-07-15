<?php
$pageTitle = 'Garde partagée';
$extraJs = ['coparent.js'];
ob_start();
?>
<?php if (empty($schedules)): ?>
    <div class="card" style="padding:1.5rem">
        <h3>Aucun accès configuré</h3>
        <p style="color:var(--text-muted)">Vous n'avez pour l'instant accès à aucun planning de garde partagée.</p>
    </div>
<?php else: ?>

<?php if ((\App\Core\Session::user()['role'] ?? null) === 'coparent'): ?>
<div class="card" style="padding:1.25rem;margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
    <div>
        <strong>Vous avez aussi votre propre famille ?</strong>
        <p style="color:var(--text-muted);font-size:.85rem;margin:.2rem 0 0">
            Créez votre espace FamilyBoard complet — vous garderez cet accès de garde partagée en plus.
        </p>
    </div>
    <button class="btn btn-secondary btn-sm" onclick="cpOpenCreateFamilyModal()">Créer ma propre famille</button>
</div>
<?php endif; ?>

<?php if (count($schedules) > 1): ?>
<div class="form-group coparent-child-picker">
    <label>Enfant</label>
    <select id="cp-schedule-select" onchange="cpSwitchSchedule(this.value)">
        <?php foreach ($schedules as $s): ?>
            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['child_name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>

<div class="coparent-tabs">
    <button class="coparent-tab active" data-panel="cp-panel-calendar" onclick="cpShowTab('cp-panel-calendar')">📅 Calendrier de garde</button>
    <button class="coparent-tab" data-panel="cp-panel-journal" onclick="cpShowTab('cp-panel-journal')">📝 Journal parental</button>
    <button class="coparent-tab" data-panel="cp-panel-documents" onclick="cpShowTab('cp-panel-documents')">🗂️ Documents</button>
    <button class="coparent-tab" data-panel="cp-panel-events" onclick="cpShowTab('cp-panel-events')">📆 Évènements</button>
</div>

<div class="coparent-panel active" id="cp-panel-calendar">
    <div class="card" style="padding:1.25rem;margin-bottom:1rem">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
            <h3 style="margin:0">Prochains jours de garde</h3>
            <button class="btn btn-primary btn-sm" onclick="cpOpenProposalModal()">Proposer des jours</button>
        </div>
        <div id="cp-custody-list"><p style="color:var(--text-muted);font-size:.85rem">Chargement…</p></div>
    </div>
</div>

<div class="coparent-panel" id="cp-panel-journal">
    <div class="card" style="padding:1.25rem">
        <div id="cp-journal-list" style="display:flex;flex-direction:column;gap:.6rem;max-height:400px;overflow-y:auto;margin-bottom:1rem"></div>
        <div style="display:flex;gap:.5rem;align-items:center">
            <input type="text" id="cp-journal-input" placeholder="Écrire un message…" style="flex:1" onkeydown="if(event.key==='Enter')cpSendJournal()">
            <span class="voice-record-timer" id="cp-voice-timer"></span>
            <button type="button" class="voice-record-btn" id="cp-voice-btn" title="Message vocal">🎤</button>
            <button class="btn btn-primary" onclick="cpSendJournal()">Envoyer</button>
        </div>
    </div>
</div>

<div class="coparent-panel" id="cp-panel-documents">
    <div class="card" style="padding:1.25rem;margin-bottom:1rem">
        <h3 style="margin-top:0">Ajouter un document</h3>
        <div class="form-row">
            <div class="form-group flex-2">
                <label>Titre</label>
                <input type="text" id="cp-doc-title" placeholder="Carnet de santé, ordonnance…">
            </div>
            <div class="form-group">
                <label>Fichier</label>
                <input type="file" id="cp-doc-file">
            </div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="cpUploadDocument()">Envoyer</button>
    </div>
    <div class="card" style="padding:1.25rem">
        <h3 style="margin-top:0">Documents</h3>
        <div id="cp-documents-list"><p style="color:var(--text-muted);font-size:.85rem">Chargement…</p></div>
    </div>
</div>

<div class="coparent-panel" id="cp-panel-events">
    <div class="card" style="padding:1.25rem;margin-bottom:1rem">
        <h3 style="margin-top:0">Ajouter un évènement</h3>
        <div class="form-group">
            <label>Titre</label>
            <input type="text" id="cp-event-title" placeholder="Rendez-vous médecin, sortie scolaire…">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Début</label>
                <input type="datetime-local" id="cp-event-start">
            </div>
            <div class="form-group">
                <label>Fin</label>
                <input type="datetime-local" id="cp-event-end">
            </div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="cpCreateEvent()">Ajouter</button>
    </div>
    <div class="card" style="padding:1.25rem">
        <h3 style="margin-top:0">Évènements à venir</h3>
        <div id="cp-events-list"><p style="color:var(--text-muted);font-size:.85rem">Chargement…</p></div>
    </div>
</div>

<!-- Proposal Modal -->
<div class="modal-overlay" id="cp-proposal-modal" style="display:none">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>Proposer des jours de garde</h3>
            <button onclick="closeModal('cp-proposal-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
                <button class="btn btn-secondary btn-sm" onclick="cpProposalShiftMonth(-1)">← Mois préc.</button>
                <strong id="cp-proposal-month-label"></strong>
                <button class="btn btn-secondary btn-sm" onclick="cpProposalShiftMonth(1)">Mois suiv. →</button>
            </div>
            <div style="display:flex;gap:1rem;align-items:center;margin-bottom:.75rem;font-size:.85rem">
                <span><span class="cp-legend-dot" id="cp-legend-p1"></span> <span id="cp-p1-name">Parent 1</span></span>
                <span><span class="cp-legend-dot" id="cp-legend-p2"></span> <span id="cp-p2-name">Parent 2</span></span>
                <span style="color:var(--text-muted)">Cliquez un jour pour alterner : vide → Parent 1 → Parent 2 → vide</span>
            </div>
            <div id="cp-proposal-grid" class="cp-proposal-grid"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('cp-proposal-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="cpSubmitProposal()">Envoyer la proposition</button>
        </div>
    </div>
</div>

<!-- Create family modal -->
<div class="modal-overlay" id="cp-create-family-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Créer ma propre famille</h3>
            <button onclick="closeModal('cp-create-family-modal')">✕</button>
        </div>
        <div class="modal-body">
            <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:1rem">
                Vous garderez votre accès de garde partagée actuel après la création de votre famille.
            </p>
            <div class="form-group">
                <label>Nom de la famille</label>
                <input type="text" id="cp-family-name" placeholder="Famille Martin…">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('cp-create-family-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="cpCreateFamily()">Créer</button>
        </div>
    </div>
</div>

<style>
.cp-proposal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
.cp-proposal-day {
    aspect-ratio: 1; border-radius: 6px; border: 2px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: .8rem; font-weight: 600; background: var(--card-bg); color: var(--text);
    min-height: 38px; user-select: none;
}
.cp-proposal-day.cp-empty { visibility: hidden; }
.cp-legend-dot { display:inline-block; width:.7rem; height:.7rem; border-radius:50%; margin-right:.25rem; vertical-align:middle; }
.cp-doc-item, .cp-event-item, .cp-custody-item { padding:.6rem 0; border-bottom:1px solid var(--border); font-size:.88rem; }
.cp-doc-item:last-child, .cp-event-item:last-child, .cp-custody-item:last-child { border-bottom:none; }
.cp-journal-msg { background:var(--bg); border-radius:10px; padding:.5rem .75rem; font-size:.88rem; }
.cp-journal-msg .cp-journal-meta { font-size:.72rem; color:var(--text-muted); margin-bottom:.15rem; }
</style>

<script>
const CP_SCHEDULES = <?= json_encode($schedules) ?>;
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
