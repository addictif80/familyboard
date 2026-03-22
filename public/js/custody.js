// ============================================
// FamilyBoard - Custody JS
// ============================================

let custodyEvents = [];
let custodyCurrentDate = new Date();
let editingCustodyEventId = null;
let editingScheduleId = null;

function renderCustodyCalendar() {
    const container = document.getElementById('custody-calendar');
    const year = custodyCurrentDate.getFullYear();
    const month = custodyCurrentDate.getMonth();

    const monthNames = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    const dayNames = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];

    const firstDay = new Date(year, month, 1);
    let startDow = firstDay.getDay();
    if (startDow === 0) startDow = 7;
    const startDate = new Date(firstDay);
    startDate.setDate(startDate.getDate() - (startDow - 1));

    let html = `
    <div class="cal-wrapper">
        <div class="cal-nav">
            <button onclick="custodyPrevMonth()" class="btn btn-secondary btn-sm">‹</button>
            <h3>${monthNames[month]} ${year}</h3>
            <button onclick="custodyNextMonth()" class="btn btn-secondary btn-sm">›</button>
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
        const dateStr = formatCustodyDate(d);

        const dayEvents = custodyEvents.filter(e => {
            return dateStr >= e.start && dateStr < e.end;
        });

        const bg = dayEvents.length === 1 ? dayEvents[0].backgroundColor + '40' : '';

        html += `<div class="cal-day custody-day ${isCurrentMonth ? '' : 'cal-other-month'} ${isToday ? 'cal-today' : ''}"
                      style="${bg ? 'background:' + bg : ''}"
                      onclick="openCustodyEventModal('${dateStr}')">
            <span class="cal-day-num">${d.getDate()}</span>
            <div class="cal-events">`;

        dayEvents.forEach(e => {
            html += `<div class="cal-event" style="background:${e.color}"
                          onclick="event.stopPropagation();openEditCustodyEvent(${e.custodyId})"
                          title="${escapeHtml(e.title)}">
                ${escapeHtml(e.title)}
            </div>`;
        });

        html += `</div></div>`;
        d.setDate(d.getDate() + 1);
    }

    html += `</div></div>`;
    container.innerHTML = html;
}

function loadCustodyEvents() {
    const year = custodyCurrentDate.getFullYear();
    const month = custodyCurrentDate.getMonth();
    const start = `${year}-${String(month+1).padStart(2,'0')}-01`;
    const end = `${year}-${String(month+1).padStart(2,'0')}-${new Date(year, month+1, 0).getDate()}`;

    fetch(`${BASE_URL}/api/custody/events?start=${start}&end=${end}`)
        .then(r => r.json())
        .then(data => {
            custodyEvents = data.map(e => ({
                ...e,
                custodyId: e.id.toString().replace('custody_', ''),
            }));
            renderCustodyCalendar();
        });
}

function custodyPrevMonth() { custodyCurrentDate.setMonth(custodyCurrentDate.getMonth() - 1); loadCustodyEvents(); }
function custodyNextMonth() { custodyCurrentDate.setMonth(custodyCurrentDate.getMonth() + 1); loadCustodyEvents(); }

function openScheduleModal() {
    editingScheduleId = null;
    document.getElementById('schedule-modal-title').textContent = 'Ajouter un enfant';
    document.getElementById('schedule-id').value = '';
    document.getElementById('schedule-child-name').value = '';
    document.getElementById('schedule-color').value = '#E67E22';
    document.getElementById('schedule-notes').value = '';
    openModal('schedule-modal');
}

function openEditScheduleModal(schedule) {
    editingScheduleId = schedule.id;
    document.getElementById('schedule-modal-title').textContent = 'Modifier';
    document.getElementById('schedule-id').value = schedule.id;
    document.getElementById('schedule-child-name').value = schedule.child_name;
    document.getElementById('schedule-color').value = schedule.color;
    document.getElementById('schedule-notes').value = schedule.notes || '';
    openModal('schedule-modal');
}

async function saveSchedule() {
    const childName = document.getElementById('schedule-child-name').value.trim();
    if (!childName) { alert('Prénom requis.'); return; }

    const data = {
        child_name: childName,
        color: document.getElementById('schedule-color').value,
        notes: document.getElementById('schedule-notes').value,
    };

    const id = document.getElementById('schedule-id').value;
    const url = id ? `${BASE_URL}/api/custody/schedule/${id}` : `${BASE_URL}/api/custody/schedule`;
    const result = await apiFetch(url, { method: 'POST', body: JSON.stringify(data) });
    if (result.success) { closeModal('schedule-modal'); location.reload(); }
}

async function deleteSchedule(id) {
    if (!confirm('Supprimer ce planning de garde ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/custody/schedule/${id}/delete`, { method: 'POST' });
    if (result.success) location.reload();
}

function openCustodyEventModal(date = null) {
    editingCustodyEventId = null;
    document.getElementById('custody-event-modal-title').textContent = 'Période de garde';
    document.getElementById('custody-event-id').value = '';
    document.getElementById('custody-event-delete-btn').style.display = 'none';
    document.getElementById('custody-notes').value = '';
    document.getElementById('custody-arrival-time').value = '';
    document.getElementById('custody-departure-time').value = '';
    if (date) {
        document.getElementById('custody-start').value = date;
        document.getElementById('custody-end').value = date;
    }
    openModal('custody-event-modal');
}

function openEditCustodyEvent(rawId) {
    const id = rawId.toString().replace('custody_', '');
    const e = custodyEvents.find(ev => ev.custodyId == id);
    if (!e) return;
    editingCustodyEventId = id;
    document.getElementById('custody-event-id').value = id;
    document.getElementById('custody-event-delete-btn').style.display = '';
    document.getElementById('custody-schedule-id').value = e.extendedProps?.schedule_id || '';
    document.getElementById('custody-parent').value = e.extendedProps?.parent_id || '';
    document.getElementById('custody-start').value = e.start;
    document.getElementById('custody-end').value = e.start;
    document.getElementById('custody-arrival-time').value = e.extendedProps?.arrival_time || '';
    document.getElementById('custody-departure-time').value = e.extendedProps?.departure_time || '';
    document.getElementById('custody-notes').value = e.extendedProps?.notes || '';
    openModal('custody-event-modal');
}

async function saveCustodyEvent() {
    const scheduleId = document.getElementById('custody-schedule-id').value;
    const parentId = document.getElementById('custody-parent').value;
    const start = document.getElementById('custody-start').value;
    const end = document.getElementById('custody-end').value;

    if (!scheduleId || !parentId || !start || !end) { alert('Tous les champs obligatoires doivent être remplis.'); return; }

    const data = {
        schedule_id: scheduleId,
        parent_user_id: parentId,
        start_date: start,
        end_date: end,
        arrival_time: document.getElementById('custody-arrival-time').value || null,
        departure_time: document.getElementById('custody-departure-time').value || null,
        notes: document.getElementById('custody-notes').value,
    };

    const id = document.getElementById('custody-event-id').value;
    const url = id ? `${BASE_URL}/api/custody/event/${id}` : `${BASE_URL}/api/custody/event`;
    const result = await apiFetch(url, { method: 'POST', body: JSON.stringify(data) });
    if (result.success) { closeModal('custody-event-modal'); loadCustodyEvents(); }
}

async function deleteCustodyEvent() {
    const id = document.getElementById('custody-event-id').value;
    if (!id || !confirm('Supprimer cette période de garde ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/custody/event/${id}/delete`, { method: 'POST' });
    if (result.success) { closeModal('custody-event-modal'); loadCustodyEvents(); }
}

function formatCustodyDate(d) {
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}

// Init
loadCustodyEvents();
