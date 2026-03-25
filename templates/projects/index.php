<?php
$pageTitle = 'Projets';
$extraJs = ['projects.js'];
ob_start();
?>
<div class="projects-container">
    <div class="projects-header">
        <h2>Projets familiaux</h2>
        <div style="display:flex;gap:.5rem">
            <button class="btn btn-secondary btn-sm" id="view-list-btn" onclick="setProjectView('list')" style="font-weight:bold">≡ Liste</button>
            <button class="btn btn-secondary btn-sm" id="view-cal-btn" onclick="setProjectView('calendar')">📅 Calendrier</button>
            <button class="btn btn-primary" onclick="openProjectModal()">+ Nouveau projet</button>
        </div>
    </div>

    <div id="proj-calendar-view" style="display:none;margin-bottom:1.5rem">
        <div class="cal-wrapper">
            <div class="cal-nav">
                <button onclick="projPrevMonth()" class="btn btn-secondary btn-sm">‹</button>
                <h3 id="proj-cal-title"></h3>
                <button onclick="projNextMonth()" class="btn btn-secondary btn-sm">›</button>
                <button onclick="projGoToday()" class="btn btn-secondary btn-sm" style="margin-left:.5rem">Aujourd'hui</button>
            </div>
            <div class="cal-grid" id="proj-cal-grid"></div>
        </div>
    </div>

    <div id="proj-list-view">

    <?php
    $active = array_filter($projects, fn($p) => $p['status'] === 'active');
    $completed = array_filter($projects, fn($p) => $p['status'] === 'completed');
    $archived = array_filter($projects, fn($p) => $p['status'] === 'archived');
    ?>

    <?php foreach ([['Actifs', $active], ['Terminés', $completed], ['Archivés', $archived]] as [$label, $group]): ?>
        <?php if (!empty($group)): ?>
        <h3 class="projects-section-title"><?= $label ?></h3>
        <div class="projects-grid">
            <?php foreach ($group as $p): ?>
                <a href="<?= BASE_URL ?>/projects/<?= $p['id'] ?>" class="project-card">
                    <div class="project-color-bar" style="background:<?= htmlspecialchars($p['color']) ?>"></div>
                    <div class="project-body">
                        <h4><?= htmlspecialchars($p['name']) ?></h4>
                        <?php if ($p['description']): ?>
                            <p><?= htmlspecialchars(mb_substr($p['description'], 0, 80)) ?>…</p>
                        <?php endif; ?>
                        <div class="project-stats">
                            <span>✅ <?= $p['done_count'] ?>/<?= $p['task_count'] ?> tâches</span>
                            <?php if ($p['budget']): ?>
                                <span>💰 <?= number_format($p['spent'], 0, ',', ' ') ?>/<?= number_format($p['budget'], 0, ',', ' ') ?> €</span>
                            <?php endif; ?>
                            <?php if ($p['deadline']): ?>
                                <span>📅 <?= date('d/m/Y', strtotime($p['deadline'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if (empty($projects)): ?>
        <div class="empty-state-card">
            <p>Aucun projet pour l'instant.</p>
            <button class="btn btn-primary" onclick="openProjectModal()">Créer le premier projet</button>
        </div>
    <?php endif; ?>
    </div><!-- end #proj-list-view -->
</div>

<!-- Project Modal -->
<div class="modal-overlay" id="project-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Nouveau projet</h3>
            <button onclick="closeModal('project-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Nom du projet *</label>
                <input type="text" id="proj-name" placeholder="Rénovation cuisine, Voyage…">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea id="proj-desc" rows="3"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Budget (€)</label>
                    <input type="number" id="proj-budget" step="0.01" placeholder="Optionnel">
                </div>
                <div class="form-group">
                    <label>Échéance</label>
                    <input type="date" id="proj-deadline">
                </div>
            </div>
            <div class="form-group">
                <label>Couleur</label>
                <input type="color" id="proj-color" value="#4A90D9">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('project-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="createProject()">Créer</button>
        </div>
    </div>
</div>

<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;

// ---- Project mini-calendar ----
let _projDate = new Date();
let _projEvents = [];

function setProjectView(view) {
    const listView = document.getElementById('proj-list-view');
    const calView  = document.getElementById('proj-calendar-view');
    const listBtn  = document.getElementById('view-list-btn');
    const calBtn   = document.getElementById('view-cal-btn');
    if (view === 'calendar') {
        listView.style.display = 'none';
        calView.style.display  = '';
        calBtn.style.fontWeight  = 'bold';
        listBtn.style.fontWeight = '';
        loadProjEvents();
    } else {
        calView.style.display  = 'none';
        listView.style.display = '';
        listBtn.style.fontWeight = 'bold';
        calBtn.style.fontWeight  = '';
    }
}

function loadProjEvents() {
    const y = _projDate.getFullYear();
    const m = _projDate.getMonth();
    const start = `${y}-${String(m+1).padStart(2,'0')}-01`;
    const end   = `${y}-${String(m+1).padStart(2,'0')}-${new Date(y, m+1, 0).getDate()}`;
    fetch(`${BASE_URL}/api/projects/calendar?start=${start}&end=${end}`)
        .then(r => r.json())
        .then(data => { _projEvents = data; renderProjCalendar(); });
}

function projPrevMonth() { _projDate.setMonth(_projDate.getMonth() - 1); loadProjEvents(); }
function projNextMonth() { _projDate.setMonth(_projDate.getMonth() + 1); loadProjEvents(); }
function projGoToday()   { _projDate = new Date(); loadProjEvents(); }

function renderProjCalendar() {
    const y = _projDate.getFullYear();
    const m = _projDate.getMonth();
    const monthName = new Intl.DateTimeFormat('fr-FR', { month: 'long' }).format(new Date(y, m, 1));
    document.getElementById('proj-cal-title').textContent =
        monthName.charAt(0).toUpperCase() + monthName.slice(1) + ' ' + y;

    const dayNames = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
    const firstDay = new Date(y, m, 1);
    let startDow = firstDay.getDay();
    if (startDow === 0) startDow = 7;
    const startDate = new Date(firstDay);
    startDate.setDate(startDate.getDate() - (startDow - 1));

    const today = new Date();
    today.setHours(0,0,0,0);

    let html = dayNames.map(d => `<div class="cal-dayname">${d}</div>`).join('');
    let d = new Date(startDate);
    for (let i = 0; i < 42; i++) {
        const isCurrentMonth = d.getMonth() === m;
        const isToday = d.getTime() === today.getTime();
        const dateStr = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
        const dayEvts = _projEvents.filter(e => e.start === dateStr);

        html += `<div class="cal-day ${isCurrentMonth ? '' : 'cal-other-month'} ${isToday ? 'cal-today' : ''}">
            <span class="cal-day-num">${d.getDate()}</span>
            <div class="cal-day-dots">${dayEvts.slice(0,4).map(e =>
                `<span class="cal-day-dot" style="background:${e.color||'#4A90D9'}"></span>`
            ).join('')}</div>
            <div class="cal-events">`;
        dayEvts.slice(0,3).forEach(e => {
            html += `<div class="cal-event" style="background:${e.color||'#4A90D9'}"
                onclick="window.location='${BASE_URL}/projects/${e.extendedProps.project_id}'"
                title="${e.title.replace(/"/g,'&quot;')}">📋 ${e.title}</div>`;
        });
        if (dayEvts.length > 3) html += `<div class="cal-more">+${dayEvts.length-3}</div>`;
        html += `</div></div>`;
        d.setDate(d.getDate() + 1);
    }
    document.getElementById('proj-cal-grid').innerHTML = html;
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
