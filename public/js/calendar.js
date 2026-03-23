// ============================================
// FamilyBoard - Calendar JS (FullCalendar-free)
// ============================================

let currentDate = new Date();
let events = [];
let editingEventId = null;

// Simple calendar renderer
function renderCalendar() {
    const container = document.getElementById('calendar');
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();

    const monthNames = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    const dayNames = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];

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
            <h3>${monthNames[month]} ${year}</h3>
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

        html += `<div class="cal-day ${isCurrentMonth ? '' : 'cal-other-month'} ${isToday ? 'cal-today' : ''}"
                      onclick="handleDayClick('${dateStr}')">
            <span class="cal-day-num">${d.getDate()}</span>
            <div class="cal-events">`;

        dayEvents.slice(0, 3).forEach(e => {
            const isCustody = e.extendedProps?.type === 'custody';
            const onClick = isCustody
                ? `window.location='${BASE_URL}/custody'`
                : `openEventDetails(${JSON.stringify(e.id)})`;
            html += `<div class="cal-event${isCustody ? ' cal-event-custody' : ''}"
                          style="background:${e.color || '#4A90D9'}"
                          onclick="event.stopPropagation();${onClick}"
                          title="${escapeHtml(e.title)}${isCustody ? ' (Garde alternée)' : ''}">
                ${isCustody ? '👶 ' : ''}${escapeHtml(e.title)}
            </div>`;
        });
        if (dayEvents.length > 3) html += `<div class="cal-more">+${dayEvents.length - 3}</div>`;

        html += `</div></div>`;
        d.setDate(d.getDate() + 1);
    }

    html += `</div></div>`;
    container.innerHTML = html;
}

function loadEvents() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const start = `${year}-${String(month+1).padStart(2,'0')}-01`;
    const end = `${year}-${String(month+1).padStart(2,'0')}-${new Date(year, month+1, 0).getDate()}`;
    const custodyToggle = document.getElementById('custody-toggle');
    const custody = custodyToggle && custodyToggle.checked ? 1 : 0;

    fetch(`${BASE_URL}/api/calendar/events?start=${start}&end=${end}&custody=${custody}`)
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
    openEventModal(dateStr);
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
    if (!title || !start || !end) { alert('Remplissez les champs obligatoires.'); return; }

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
    if (!id || !confirm('Supprimer cet événement ?')) return;
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
    if (!data.name || !data.url) { alert('Nom et URL requis.'); return; }
    const result = await apiFetch(`${BASE_URL}/api/calendar/caldav`, { method: 'POST', body: JSON.stringify(data) });
    if (result.success) { closeModal('caldav-modal'); location.reload(); }
    else alert('Erreur lors de l\'ajout.');
}

async function syncCalDAV(id) {
    const btn = event.currentTarget;
    btn.textContent = '⏳';
    const result = await apiFetch(`${BASE_URL}/api/calendar/caldav/${id}/sync`, { method: 'POST' });
    if (result.success) { loadEvents(); btn.textContent = '🔄'; }
    else { btn.textContent = '❌'; }
}

async function deleteCalDAV(id) {
    if (!confirm('Supprimer ce calendrier CalDAV et ses événements ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/calendar/caldav/${id}/delete`, { method: 'POST' });
    if (result.success) location.reload();
}

function filterCalendar() {
    loadEvents(); // Simple reload — could filter client-side
}

function formatDateISO(d) {
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}

// Init
loadEvents();
