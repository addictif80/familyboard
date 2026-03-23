// ============================================
// FamilyBoard - Custody JS
// ============================================

let custodyEvents = [];
let custodyCurrentDate = new Date();
let editingCustodyEventId = null;

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

        const dayEvents = custodyEvents.filter(e => dateStr >= e.start && dateStr < e.end);

        // Background tint from first event's schedule color
        const bgColor = dayEvents.length > 0 ? hexToRgba(dayEvents[0].backgroundColor, 0.25) : '';

        html += `<div class="cal-day ${isCurrentMonth ? '' : 'cal-other-month'} ${isToday ? 'cal-today' : ''}"
                      style="${bgColor ? 'background:' + bgColor : ''}"
                      onclick="openCustodyEventModal('${dateStr}')">
            <span class="cal-day-num">${d.getDate()}</span>
            <div class="cal-events">`;

        dayEvents.forEach(e => {
            const isRecurring = e.extendedProps?.is_recurring;
            html += `<div class="cal-event ${isRecurring ? 'cal-event-recurring' : ''}"
                          style="background:${e.color};${isRecurring ? 'border-left:3px solid rgba(255,255,255,.7)' : ''}"
                          onclick="event.stopPropagation();${isRecurring ? 'openCustodyEventModal(\'' + dateStr + '\')' : 'openEditCustodyEvent(\'' + e.id + '\')'}"
                          title="${escapeHtml(e.title)}${isRecurring ? ' (récurrent)' : ''}">
                ${escapeHtml(e.title)}${isRecurring ? ' 🔄' : ''}
            </div>`;
        });

        html += `</div></div>`;
        d.setDate(d.getDate() + 1);
    }

    html += `</div></div>`;
    container.innerHTML = html;
}

function hexToRgba(hex, alpha) {
    if (!hex) return '';
    const r = parseInt(hex.slice(1,3),16);
    const g = parseInt(hex.slice(3,5),16);
    const b = parseInt(hex.slice(5,7),16);
    return `rgba(${r},${g},${b},${alpha})`;
}

function loadCustodyEvents() {
    const year = custodyCurrentDate.getFullYear();
    const month = custodyCurrentDate.getMonth();
    const start = `${year}-${String(month+1).padStart(2,'0')}-01`;
    const end = `${year}-${String(month+1).padStart(2,'0')}-${new Date(year, month+1, 0).getDate()}`;

    fetch(`${BASE_URL}/api/custody/events?start=${start}&end=${end}`)
        .then(r => r.json())
        .then(data => {
            custodyEvents = data;
            renderCustodyCalendar();
        });
}

function custodyPrevMonth() { custodyCurrentDate.setMonth(custodyCurrentDate.getMonth() - 1); loadCustodyEvents(); }
function custodyNextMonth() { custodyCurrentDate.setMonth(custodyCurrentDate.getMonth() + 1); loadCustodyEvents(); }

// ---- Schedule modal ----

function openScheduleModal() {
    document.getElementById('schedule-modal-title').textContent = 'Ajouter un enfant';
    document.getElementById('schedule-id').value = '';
    document.getElementById('schedule-child-name').value = '';
    document.getElementById('schedule-color').value = '#E67E22';
    document.getElementById('schedule-notes').value = '';
    document.getElementById('schedule-recurrence-type').value = 'none';
    document.getElementById('schedule-recurrence-start').value = '';
    document.getElementById('schedule-parent1').value = '';
    document.getElementById('schedule-parent2').value = '';
    document.getElementById('recurrence-fields').style.display = 'none';
    openModal('schedule-modal');
}

function openEditScheduleModal(schedule) {
    document.getElementById('schedule-modal-title').textContent = 'Modifier le planning';
    document.getElementById('schedule-id').value = schedule.id;
    document.getElementById('schedule-child-name').value = schedule.child_name;
    document.getElementById('schedule-color').value = schedule.color;
    document.getElementById('schedule-notes').value = schedule.notes || '';

    const recType = schedule.recurrence_type || 'none';
    document.getElementById('schedule-recurrence-type').value = recType;
    document.getElementById('schedule-recurrence-start').value = schedule.recurrence_start || '';
    document.getElementById('schedule-parent1').value = schedule.recurrence_parent1_id || '';
    document.getElementById('schedule-parent2').value = schedule.recurrence_parent2_id || '';
    document.getElementById('recurrence-fields').style.display = recType === 'none' ? 'none' : 'block';
    openModal('schedule-modal');
}

async function saveSchedule() {
    const childName = document.getElementById('schedule-child-name').value.trim();
    if (!childName) { alert('Prénom requis.'); return; }

    const recType = document.getElementById('schedule-recurrence-type').value;
    const recStart = document.getElementById('schedule-recurrence-start').value;
    const parent1 = document.getElementById('schedule-parent1').value;
    const parent2 = document.getElementById('schedule-parent2').value;

    if (recType !== 'none') {
        if (!recStart) { alert('Veuillez indiquer la date de début de la périodicité.'); return; }
        if (!parent1 || !parent2) { alert('Veuillez sélectionner les deux parents.'); return; }
        if (parent1 === parent2) { alert('Les deux parents doivent être différents.'); return; }
    }

    const data = {
        child_name: childName,
        color: document.getElementById('schedule-color').value,
        notes: document.getElementById('schedule-notes').value,
        recurrence_type: recType,
        recurrence_start: recType !== 'none' ? recStart : null,
        recurrence_parent1_id: recType !== 'none' ? parent1 : null,
        recurrence_parent2_id: recType !== 'none' ? parent2 : null,
    };

    const id = document.getElementById('schedule-id').value;
    const url = id ? `${BASE_URL}/api/custody/schedule/${id}` : `${BASE_URL}/api/custody/schedule`;
    const result = await apiFetch(url, { method: 'POST', body: JSON.stringify(data) });
    if (result.success) { closeModal('schedule-modal'); location.reload(); }
}

async function deleteSchedule(id) {
    if (!confirm('Supprimer ce planning de garde et tous ses événements ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/custody/schedule/${id}/delete`, { method: 'POST' });
    if (result.success) location.reload();
}

// ---- Event modal (exceptions / manual) ----

function openCustodyEventModal(date = null) {
    editingCustodyEventId = null;
    document.getElementById('custody-event-modal-title').textContent = 'Exception / période manuelle';
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
    // rawId format: "custody_123"
    const id = rawId.toString().replace('custody_', '');
    // Only manual events are editable (recurring ones open the "new exception" modal)
    const e = custodyEvents.find(ev => ev.id === rawId);
    if (!e || e.extendedProps?.is_recurring) return;

    editingCustodyEventId = id;
    document.getElementById('custody-event-id').value = id;
    document.getElementById('custody-event-delete-btn').style.display = '';
    document.getElementById('custody-event-modal-title').textContent = 'Modifier la période';

    // Find schedule
    const scheduleId = e.extendedProps?.schedule_id;
    if (scheduleId) document.getElementById('custody-schedule-id').value = scheduleId;

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

    if (!scheduleId || !parentId || !start || !end) {
        alert('Enfant, parent et dates requis.');
        return;
    }

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
