// ============================================
// FamilyBoard - Calendar JS (FullCalendar-free)
// ============================================

let currentDate = new Date();
let events = [];
let editingEventId = null;

// Intl formatters — use APP_TIMEZONE so display matches the server
function tzDate(dateStr) {
    // For date-only strings (YYYY-MM-DD), parse as local noon to avoid DST off-by-one
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
        return new Date(dateStr + 'T12:00:00');
    }
    return new Date(dateStr);
}

// fmtMonthYear / fmtDayNames now live in app.js (loaded on every page) so
// other month-grid views (custody, coparent) format headers correctly too.
const _intlTime  = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: APP_TIMEZONE });

function fmtEventTime(dateStr) {
    if (!dateStr || dateStr.length === 10) return '';
    return _intlTime.format(new Date(dateStr));
}

// Simple calendar renderer
function renderCalendar() {
    const container = document.getElementById('calendar');
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();

    const dayNames = fmtDayNames();

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);

    // Get start of week (Monday)
    let startDow = firstDay.getDay();
    if (startDow === 0) startDow = 7;
    const startDate = new Date(firstDay);
    startDate.setDate(startDate.getDate() - (startDow - 1));

    let html = `
    <div class="cal-wrapper">
        <div class="cal-nav">
            <button onclick="prevMonth()" class="btn btn-secondary btn-sm">‹</button>
            <h3>${fmtMonthYear(year, month)}</h3>
            <button onclick="nextMonth()" class="btn btn-secondary btn-sm">›</button>
            <button onclick="goToday()" class="btn btn-secondary btn-sm" style="margin-left:.5rem">Aujourd'hui</button>
        </div>
        <div class="cal-grid">
            ${dayNames.map(d => `<div class="cal-dayname">${d}</div>`).join('')}
    `;

    const today = new Date();
    today.setHours(0,0,0,0);

    let d = new Date(startDate);
    for (let i = 0; i < 42; i++) {
        const isCurrentMonth = d.getMonth() === month;
        const isToday = d.getTime() === today.getTime();
        const dateStr = formatDateISO(d);

        const dayEvents = events.filter(e => {
            const start = e.start.substring(0,10);
            const end = (e.end || e.start).substring(0,10);
            return dateStr >= start && dateStr <= end;
        });

        // Dots for mobile (max 4 shown)
        const dotsHtml = '<div class="cal-day-dots">' +
            dayEvents.slice(0, 4).map(e =>
                `<span class="cal-day-dot" style="background:${e.color || '#4A90D9'}"></span>`
            ).join('') + '</div>';

        html += `<div class="cal-day ${isCurrentMonth ? '' : 'cal-other-month'} ${isToday ? 'cal-today' : ''}"
                      onclick="handleDayClick('${dateStr}')">
            <span class="cal-day-num">${d.getDate()}</span>
            ${dotsHtml}
            <div class="cal-events">`;

        dayEvents.slice(0, 3).forEach(e => {
            const isCustody  = e.extendedProps?.type === 'custody';
            const isProject  = e.extendedProps?.type === 'project';
            const isBirthday = e.extendedProps?.type === 'birthday';
            const isVacation = e.extendedProps?.type === 'vacation';
            const isSchool   = e.extendedProps?.type === 'school_holiday';
            const onClick = isCustody
                ? `window.location='${BASE_URL}/custody'`
                : isProject
                    ? `window.location='${BASE_URL}/projects/${e.extendedProps.project_id}'`
                    : isBirthday
                        ? `window.location='${BASE_URL}/contacts'`
                        : isVacation
                            ? `deleteVacationPrompt(${e.extendedProps.vacation_id})`
                            : isSchool
                                ? ''
                                : `openEventDetails(${JSON.stringify(e.id)})`;
            const label  = isCustody ? '👶 ' : isProject ? '📋 ' : '';
            const suffix = isCustody ? ' (Garde alternée)' : isProject ? ' (Projet)' : isBirthday ? ' (Anniversaire)' : isVacation ? ' — cliquer pour supprimer' : '';
            html += `<div class="cal-event${isCustody ? ' cal-event-custody' : ''}"
                          style="background:${e.color || '#4A90D9'};${isSchool ? 'opacity:.85' : ''}"
                          onclick="event.stopPropagation();${onClick}"
                          title="${escapeHtml(e.title)}${suffix}"
                          ${isSchool ? '' : 'style="cursor:pointer"'}>
                ${label}${escapeHtml(e.title)}
            </div>`;
        });
        if (dayEvents.length > 3) html += `<div class="cal-more">+${dayEvents.length - 3}</div>`;

        html += `</div></div>`;
        d.setDate(d.getDate() + 1);
    }

    html += `</div></div>`;
    container.innerHTML = html;

    // Restore selected-day agenda if we're on mobile
    if (_selectedDay) renderMobileAgenda(_selectedDay);
}

let _selectedDay = null;

function renderMobileAgenda(dateStr) {
    _selectedDay = dateStr;

    // Remove previous selection highlight
    document.querySelectorAll('.cal-day.cal-selected').forEach(el => el.classList.remove('cal-selected'));

    // Highlight selected cell (find by onclick attribute value)
    document.querySelectorAll('.cal-day').forEach(el => {
        if (el.getAttribute('onclick') === `handleDayClick('${dateStr}')`) {
            el.classList.add('cal-selected');
        }
    });

    const agenda = document.getElementById('cal-mobile-agenda');
    if (!agenda) return;

    const dayEvents = events.filter(e => {
        const start = e.start.substring(0, 10);
        const end = (e.end || e.start).substring(0, 10);
        return dateStr >= start && dateStr <= end;
    });

    const d = new Date(dateStr + 'T12:00:00');
    const label = d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });

    let inner = `<div class="cal-agenda-header">
        <span class="cal-agenda-title">${label.charAt(0).toUpperCase() + label.slice(1)}</span>
        <button class="btn btn-primary btn-sm" onclick="openEventModal('${dateStr}')">+ Ajouter</button>
    </div>`;

    if (dayEvents.length === 0) {
        inner += `<p class="cal-agenda-empty">Aucun événement ce jour.</p>`;
    } else {
        dayEvents.forEach(e => {
            const isCustody  = e.extendedProps?.type === 'custody';
            const isProject  = e.extendedProps?.type === 'project';
            const isBirthday = e.extendedProps?.type === 'birthday';
            const isVacation = e.extendedProps?.type === 'vacation';
            const isSchool   = e.extendedProps?.type === 'school_holiday';
            const timeStr = e.start && e.start.length > 10 ? fmtEventTime(e.start) : 'Toute la journée';
            const onClick = isCustody
                ? `window.location='${BASE_URL}/custody'`
                : isProject
                    ? `window.location='${BASE_URL}/projects/${e.extendedProps.project_id}'`
                    : isBirthday
                        ? `window.location='${BASE_URL}/contacts'`
                        : isVacation
                            ? `deleteVacationPrompt(${e.extendedProps.vacation_id})`
                            : isSchool ? '' : `openEventDetails(${JSON.stringify(e.id)})`;
            const label = isCustody ? '👶 ' : isProject ? '📋 ' : '';
            inner += `<div class="cal-agenda-event" onclick="${onClick}" style="cursor:pointer">
                <span class="cal-agenda-dot" style="background:${e.color || '#4A90D9'}"></span>
                <div class="cal-agenda-info">
                    <div class="cal-agenda-name">${label}${escapeHtml(e.title)}</div>
                    <div class="cal-agenda-time">${timeStr}</div>
                </div>
            </div>`;
        });
    }

    agenda.innerHTML = inner;
    agenda.style.display = 'block';
}

function loadEvents() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const start = `${year}-${String(month+1).padStart(2,'0')}-01`;
    const end = `${year}-${String(month+1).padStart(2,'0')}-${new Date(year, month+1, 0).getDate()}`;
    const custody   = document.getElementById('custody-toggle')?.checked ? 1 : 0;
    const projects  = document.getElementById('projects-toggle')?.checked ? 1 : 0;
    const vacations = document.getElementById('vacations-toggle')?.checked ? 1 : 0;
    const school    = document.getElementById('school-toggle')?.checked ? 1 : 0;

    fetch(`${BASE_URL}/api/calendar/events?start=${start}&end=${end}&custody=${custody}&projects=${projects}&vacations=${vacations}&school=${school}`)
        .then(r => r.json())
        .then(data => {
            events = data;
            renderCalendar();
        });
}

function prevMonth() { currentDate.setMonth(currentDate.getMonth() - 1); loadEvents(); }
function nextMonth() { currentDate.setMonth(currentDate.getMonth() + 1); loadEvents(); }
function goToday() { currentDate = new Date(); loadEvents(); }

function handleDayClick(dateStr) {
    if (window.innerWidth <= 768) {
        renderMobileAgenda(dateStr);
    } else {
        openEventModal(dateStr);
    }
}

function openEventModal(date = null, eventData = null) {
    editingEventId = null;
    document.getElementById('event-modal-title').textContent = 'Nouvel événement';
    document.getElementById('event-delete-btn').style.display = 'none';
    document.getElementById('event-id').value = '';
    document.getElementById('event-title').value = '';
    document.getElementById('event-desc').value = '';
    document.getElementById('event-color').value = '#4A90D9';
    document.getElementById('event-recurrence').value = '';
    const custodyToggle = document.getElementById('event-custody-toggle');
    if (custodyToggle) {
        custodyToggle.checked = false;
        document.getElementById('event-custody-select-wrap').style.display = 'none';
        document.getElementById('event-custody-schedule').value = '';
    }

    if (date) {
        document.getElementById('event-start').value = date + 'T09:00';
        document.getElementById('event-end').value = date + 'T10:00';
    }
    if (eventData) {
        editingEventId = eventData.id;
        document.getElementById('event-modal-title').textContent = 'Modifier l\'événement';
        document.getElementById('event-delete-btn').style.display = '';
        document.getElementById('event-id').value = eventData.id;
        document.getElementById('event-title').value = eventData.title;
        document.getElementById('event-desc').value = eventData.extendedProps?.description || '';
        document.getElementById('event-start').value = eventData.start?.replace(' ', 'T') || '';
        document.getElementById('event-end').value = (eventData.end || eventData.start)?.replace(' ', 'T') || '';
        document.getElementById('event-color').value = eventData.color || '#4A90D9';
        if (custodyToggle) {
            const csId = eventData.extendedProps?.custody_schedule_id || '';
            custodyToggle.checked = !!csId;
            document.getElementById('event-custody-select-wrap').style.display = csId ? '' : 'none';
            document.getElementById('event-custody-schedule').value = csId;
        }
    }
    openModal('event-modal');
}

function openEventDetails(eventId) {
    const e = events.find(ev => ev.id === eventId);
    if (e) openEventModal(null, e);
}

async function saveEvent() {
    const title = document.getElementById('event-title').value.trim();
    const start = document.getElementById('event-start').value;
    const end = document.getElementById('event-end').value;
    if (!title || !start || !end) { Dialog.toast('Remplissez les champs obligatoires.', 'error'); return; }

    const allDay = document.getElementById('event-allday').checked;
    const data = {
        title,
        description: document.getElementById('event-desc').value,
        start_datetime: start.replace('T', ' ') + ':00',
        end_datetime: end.replace('T', ' ') + ':00',
        is_all_day: allDay ? 1 : 0,
        color: document.getElementById('event-color').value,
        recurrence: document.getElementById('event-recurrence').value || null,
    };
    const custodyToggle = document.getElementById('event-custody-toggle');
    if (custodyToggle && custodyToggle.checked) {
        data.custody_schedule_id = document.getElementById('event-custody-schedule').value;
    }

    const id = document.getElementById('event-id').value;
    const url = id ? `${BASE_URL}/api/calendar/events/${id}` : `${BASE_URL}/api/calendar/events`;
    const result = await apiFetch(url, { method: 'POST', body: JSON.stringify(data) });

    if (result.success) {
        closeModal('event-modal');
        loadEvents();
    }
}

async function deleteEvent() {
    const id = document.getElementById('event-id').value;
    if (!id || !await Dialog.confirm('Supprimer cet événement ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/calendar/events/${id}/delete`, { method: 'POST' });
    if (result.success) {
        closeModal('event-modal');
        loadEvents();
    }
}

function toggleAllDay(cb) {
    const startEl = document.getElementById('event-start');
    const endEl = document.getElementById('event-end');
    startEl.type = cb.checked ? 'date' : 'datetime-local';
    endEl.type = cb.checked ? 'date' : 'datetime-local';
}

function openCalDAVModal() {
    openModal('caldav-modal');
}

async function addCalDAV() {
    const data = {
        name: document.getElementById('caldav-name').value,
        url: document.getElementById('caldav-url').value,
        username: document.getElementById('caldav-user').value,
        password: document.getElementById('caldav-pass').value,
        color: document.getElementById('caldav-color').value,
    };
    if (!data.name || !data.url) { Dialog.toast('Nom et URL requis.', 'error'); return; }
    const result = await apiFetch(`${BASE_URL}/api/calendar/caldav`, { method: 'POST', body: JSON.stringify(data) });
    if (result.success) { closeModal('caldav-modal'); location.reload(); }
    else Dialog.toast('Erreur lors de l\'ajout.', 'error');
}

async function syncCalDAV(id) {
    const btn = event.currentTarget;
    btn.textContent = '⏳';
    const result = await apiFetch(`${BASE_URL}/api/calendar/caldav/${id}/sync`, { method: 'POST' });
    if (result.success) {
        loadEvents();
        btn.textContent = '🔄';
        if (result.count === 0) {
            Dialog.alert('Synchronisation effectuée mais aucun événement trouvé.\n\nVérifiez :\n• L\'URL (doit être une URL .ics publique ou avec identifiants)\n• Les identifiants si requis\n• Que le calendrier contient des événements', 'Aucun événement');
        }
    } else {
        btn.textContent = '❌';
        Dialog.alert('Échec de la synchronisation : ' + (result.error || 'Erreur inconnue'), 'Erreur');
    }
}

async function deleteCalDAV(id) {
    if (!await Dialog.confirm('Supprimer ce calendrier CalDAV et ses événements ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/calendar/caldav/${id}/delete`, { method: 'POST' });
    if (result.success) location.reload();
}

function filterCalendar() {
    loadEvents(); // Simple reload — could filter client-side
}

function formatDateISO(d) {
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}

// ── Vacations ────────────────────────────────────────────────

function openVacationModal(dateStr) {
    const today = dateStr || formatDateISO(new Date());
    document.getElementById('vac-title').value = 'Congé';
    document.getElementById('vac-start').value = today;
    document.getElementById('vac-end').value   = today;
    openModal('vacation-modal');
}

async function saveVacation() {
    const title = document.getElementById('vac-title').value.trim() || 'Congé';
    const start = document.getElementById('vac-start').value;
    const end   = document.getElementById('vac-end').value;
    if (!start || !end) { Dialog.toast('Dates requises.', 'error'); return; }
    if (end < start)    { Dialog.toast('La fin doit être après le début.', 'error'); return; }

    const result = await apiFetch(`${BASE_URL}/api/calendar/vacations`, {
        method: 'POST',
        body: JSON.stringify({ title, start_date: start, end_date: end }),
    });
    if (result?.success) {
        closeModal('vacation-modal');
        loadEvents();
    } else {
        Dialog.toast('Erreur : ' + (result?.error || 'inconnue'), 'error');
    }
}

async function deleteVacationPrompt(id) {
    if (!await Dialog.confirm('Supprimer ce congé ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/calendar/vacations/${id}/delete`, { method: 'POST' });
    if (result?.success) loadEvents();
    else Dialog.toast('Erreur lors de la suppression.', 'error');
}

// Init
loadEvents();
