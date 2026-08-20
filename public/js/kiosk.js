// ============================================
// Kiosk mode — polls /kiosk/:token/data periodically and re-renders
// in place (no page reload), toggling to a privacy screen whenever a
// babysitter access is currently active for the family.
// ============================================

const KIOSK_DAY_NAMES = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
const KIOSK_MONTH_NAMES = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const KIOSK_MEAL_LABELS = { breakfast: 'Petit-déj', lunch: 'Midi', dinner: 'Soir', snack: 'Goûter' };

function kioskEscape(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function kioskFormatDay(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    return KIOSK_DAY_NAMES[d.getDay()].slice(0, 3) + ' ' + d.getDate();
}

function updateKioskClock() {
    const clockEl = document.getElementById('kiosk-clock');
    const dateEl = document.getElementById('kiosk-date');
    if (!clockEl) return;
    const now = new Date();
    clockEl.textContent = now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    if (dateEl) {
        dateEl.textContent = KIOSK_DAY_NAMES[now.getDay()] + ' ' + now.getDate() + ' ' + KIOSK_MONTH_NAMES[now.getMonth()];
    }
}

async function fetchKioskData() {
    try {
        const res = await fetch(BASE_URL + '/kiosk/' + KIOSK_TOKEN + '/data');
        const data = await res.json();
        if (!data.success) return;
        if (data.sitter_active) {
            renderKioskPrivacy(data.sitter_url);
        } else {
            renderKioskBoard(data);
        }
    } catch (e) { /* offline — keep showing the last render, retry next tick */ }
}

function renderKioskPrivacy(sitterUrl) {
    document.getElementById('kiosk-board').style.display = 'none';
    document.getElementById('kiosk-privacy').style.display = 'flex';
    closeFamilyInfo();

    const container = document.getElementById('kiosk-qr');
    if (container.dataset.url !== sitterUrl) {
        container.innerHTML = '';
        const qr = qrcode(0, 'M');
        qr.addData(sitterUrl);
        qr.make();
        container.innerHTML = qr.createSvgTag({ cellSize: 6, margin: 4 });
        container.dataset.url = sitterUrl;
    }
    window._kioskSitterUrl = sitterUrl;
}

function showFamilyInfo() {
    if (!window._kioskSitterUrl) return;
    document.getElementById('kiosk-iframe').src = window._kioskSitterUrl;
    document.getElementById('kiosk-iframe-overlay').style.display = 'block';
}

function closeFamilyInfo() {
    document.getElementById('kiosk-iframe-overlay').style.display = 'none';
    document.getElementById('kiosk-iframe').src = 'about:blank';
}

function renderKioskBoard(data) {
    document.getElementById('kiosk-privacy').style.display = 'none';
    document.getElementById('kiosk-board').style.display = 'grid';

    // Preserve any in-progress "add task" input text across refreshes.
    const pendingInputs = {};
    document.querySelectorAll('.kiosk-add-row input').forEach(inp => {
        if (inp.value) pendingInputs[inp.dataset.listId] = inp.value;
    });

    const board = document.getElementById('kiosk-board');
    board.innerHTML = `
        <div class="kiosk-header">
            <div>
                <h1>🏠 ${kioskEscape(data.family_name)}</h1>
                <div class="kiosk-date" id="kiosk-date"></div>
            </div>
            ${data.name_day ? `<div class="kiosk-nameday">🎉 Fête : ${kioskEscape(data.name_day)}</div>` : ''}
            <div class="kiosk-clock" id="kiosk-clock"></div>
        </div>

        <div class="kiosk-section">
            <h2>✅ Tâches &amp; Courses</h2>
            ${renderKioskTaskLists([...data.task_lists, ...data.shopping_lists])}
        </div>

        <div class="kiosk-section">
            <h2>📅 Événements</h2>
            ${renderKioskEvents(data.events)}
        </div>

        <div class="kiosk-section">
            <h2>🍽️ Repas</h2>
            ${renderKioskMeals(data.meals)}
        </div>

        <div class="kiosk-section">
            <h2>📒 Contacts</h2>
            ${renderKioskContacts(data.contacts)}
        </div>

        ${data.timers && data.timers.length ? `
        <div class="kiosk-section kiosk-timers-section">
            <h2>⏰ Minuteurs</h2>
            ${renderKioskTimers(data.timers)}
        </div>` : ''}
    `;

    updateKioskClock();
    _kioskTimersTick();

    Object.entries(pendingInputs).forEach(([listId, val]) => {
        const inp = board.querySelector(`.kiosk-add-row input[data-list-id="${listId}"]`);
        if (inp) inp.value = val;
    });
}

function renderKioskTaskLists(lists) {
    if (!lists.length) return '<p class="kiosk-empty">Aucune liste.</p>';
    return lists.map(list => `
        <div class="kiosk-list-group">
            <div class="kiosk-list-name">${kioskEscape(list.name)}</div>
            ${list.pending.length
                ? list.pending.map(t => `
                    <div class="kiosk-task-item" onclick="kioskToggleTask(${t.id})">
                        <span class="kiosk-task-check"></span>
                        <span>${kioskEscape(t.title)}</span>
                    </div>
                `).join('')
                : '<p class="kiosk-empty">Rien en attente.</p>'
            }
            <div class="kiosk-add-row">
                <input type="text" data-list-id="${list.id}" placeholder="Ajouter…"
                       onkeydown="if(event.key==='Enter') kioskAddTask(${list.id}, this)">
                <button type="button" onclick="kioskAddTask(${list.id}, this.previousElementSibling)">+</button>
            </div>
        </div>
    `).join('');
}

function renderKioskEvents(events) {
    if (!events.length) return '<p class="kiosk-empty">Aucun événement à venir.</p>';
    return events.slice(0, 12).map(e => `
        <div class="kiosk-event-item">
            <span class="kiosk-event-date">${kioskFormatDay(e.start_datetime.slice(0, 10))}</span>
            <span>${kioskEscape(e.title)}</span>
        </div>
    `).join('');
}

function renderKioskMeals(meals) {
    if (!meals.length) return '<p class="kiosk-empty">Aucun repas planifié.</p>';
    return meals.slice(0, 10).map(m => `
        <div class="kiosk-meal-item">
            <span class="kiosk-meal-date">${kioskFormatDay(m.date)}</span>
            <span>${KIOSK_MEAL_LABELS[m.type] || m.type} — ${kioskEscape(m.title) || '—'}</span>
        </div>
    `).join('');
}

function renderKioskContacts(contacts) {
    if (!contacts.length) return '<p class="kiosk-empty">Aucun contact.</p>';
    return contacts.slice(0, 15).map(c => `
        <div class="kiosk-contact-item">
            <span class="kiosk-contact-name">${kioskEscape(c.first_name)} ${kioskEscape(c.last_name)}</span>
            <span class="kiosk-contact-phone">${kioskEscape(c.phone || '')}</span>
        </div>
    `).join('');
}

function renderKioskTimers(timers) {
    return `<div class="kiosk-timers">` + timers.map(t => `
        <div class="kiosk-timer${t.run_id ? ' running' : ''}" id="kiosk-timer-${t.id}"
             data-timer-id="${t.id}" data-duration-min="${t.duration_minutes}"
             ${t.run_id ? `data-run-id="${t.run_id}" data-ends-at="${kioskEscape(t.ends_at)}"` : ''}>
            <span class="kiosk-timer-label">${kioskEscape(t.label)}</span>
            <span class="kiosk-timer-value">${t.run_id ? '--:--' : t.duration_minutes + ' min'}</span>
            <button type="button" class="kiosk-timer-btn kiosk-timer-start" onclick="kioskStartTimer(${t.id})" ${t.run_id ? 'style="display:none"' : ''}>▶</button>
            <button type="button" class="kiosk-timer-btn kiosk-timer-stop" onclick="kioskStopTimer(${t.id})" ${t.run_id ? '' : 'style="display:none"'}>⏹</button>
        </div>
    `).join('') + `</div>`;
}

// L'échéance ("alarming") se déduit localement de data-ends-at à chaque tick — jamais un état
// serveur —, ce qui garde écran mural et kiosque cohérents sans synchronisation particulière.
let _kioskAlarmCtx = null;
let _kioskAlarmInterval = null;

function _kioskBeep() {
    try {
        _kioskAlarmCtx = _kioskAlarmCtx || new (window.AudioContext || window.webkitAudioContext)();
        const osc = _kioskAlarmCtx.createOscillator();
        const gain = _kioskAlarmCtx.createGain();
        osc.type = 'sine';
        osc.frequency.value = 880;
        gain.gain.setValueAtTime(0.25, _kioskAlarmCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, _kioskAlarmCtx.currentTime + 0.4);
        osc.connect(gain).connect(_kioskAlarmCtx.destination);
        osc.start();
        osc.stop(_kioskAlarmCtx.currentTime + 0.4);
    } catch (_) {}
}

function _kioskTimersTick() {
    const els = document.querySelectorAll('.kiosk-timer[data-run-id]');
    let anyAlarming = false;
    els.forEach(el => {
        const endsAt = new Date(el.dataset.endsAt.replace(' ', 'T') + 'Z').getTime();
        const remaining = Math.round((endsAt - Date.now()) / 1000);
        const valueEl = el.querySelector('.kiosk-timer-value');
        if (remaining > 0) {
            const m = String(Math.floor(remaining / 60)).padStart(2, '0');
            const s = String(remaining % 60).padStart(2, '0');
            if (valueEl) valueEl.textContent = `${m}:${s}`;
            el.classList.remove('alarming');
        } else {
            if (valueEl) valueEl.textContent = '00:00';
            el.classList.add('alarming');
            anyAlarming = true;
        }
    });
    if (anyAlarming) {
        if (!_kioskAlarmInterval) {
            _kioskBeep();
            _kioskAlarmInterval = setInterval(_kioskBeep, 1200);
        }
    } else {
        clearInterval(_kioskAlarmInterval);
        _kioskAlarmInterval = null;
    }
}

async function kioskStartTimer(id) {
    const res = await fetch(BASE_URL + '/kiosk/' + KIOSK_TOKEN + '/timers/' + id + '/start', { method: 'POST' });
    const data = await res.json();
    if (!data.success) return;
    const el = document.getElementById('kiosk-timer-' + id);
    if (!el) return;
    el.dataset.runId = data.run_id;
    el.dataset.endsAt = data.ends_at;
    el.classList.add('running');
    el.querySelector('.kiosk-timer-start').style.display = 'none';
    el.querySelector('.kiosk-timer-stop').style.display = '';
    _kioskTimersTick();
}

async function kioskStopTimer(id) {
    await fetch(BASE_URL + '/kiosk/' + KIOSK_TOKEN + '/timers/' + id + '/stop', { method: 'POST' });
    const el = document.getElementById('kiosk-timer-' + id);
    if (!el) return;
    delete el.dataset.runId;
    delete el.dataset.endsAt;
    el.classList.remove('running', 'alarming');
    el.querySelector('.kiosk-timer-value').textContent = el.dataset.durationMin + ' min';
    el.querySelector('.kiosk-timer-start').style.display = '';
    el.querySelector('.kiosk-timer-stop').style.display = 'none';
    _kioskTimersTick();
}

async function kioskAddTask(listId, input) {
    const title = input.value.trim();
    if (!title) return;
    input.value = '';
    await fetch(BASE_URL + '/kiosk/' + KIOSK_TOKEN + '/tasks', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ list_id: listId, title }),
    });
    fetchKioskData();
}

async function kioskToggleTask(taskId) {
    await fetch(BASE_URL + '/kiosk/' + KIOSK_TOKEN + '/tasks/' + taskId + '/toggle', { method: 'POST' });
    fetchKioskData();
}

fetchKioskData();
setInterval(fetchKioskData, KIOSK_REFRESH_SECONDS * 1000);
setInterval(updateKioskClock, 1000);
setInterval(_kioskTimersTick, 1000);
