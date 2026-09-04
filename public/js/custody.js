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

    const dayNames = typeof fmtDayNames === 'function' ? fmtDayNames() : ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];

    const firstDay = new Date(year, month, 1);
    let startDow = firstDay.getDay();
    if (startDow === 0) startDow = 7;
    const startDate = new Date(firstDay);
    startDate.setDate(startDate.getDate() - (startDow - 1));

    let html = `
    <div class="cal-wrapper">
        <div class="cal-nav">
            <button onclick="custodyPrevMonth()" class="btn btn-secondary btn-sm">‹</button>
            <h3>${typeof fmtMonthYear === 'function' ? fmtMonthYear(year, month) : month + ' ' + year}</h3>
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

        const dotsHtml = '<div class="cal-day-dots">' +
            dayEvents.slice(0, 4).map(e =>
                `<span class="cal-day-dot" style="background:${e.color || '#4A90D9'}"></span>`
            ).join('') + '</div>';

        html += `<div class="cal-day ${isCurrentMonth ? '' : 'cal-other-month'} ${isToday ? 'cal-today' : ''}"
                      style="${bgColor ? 'background:' + bgColor : ''}"
                      onclick="handleCustodyDayClick('${dateStr}')">
            <span class="cal-day-num">${d.getDate()}</span>
            ${dotsHtml}
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

    if (_custodySelectedDay) renderCustodyMobileAgenda(_custodySelectedDay);
}

let _custodySelectedDay = null;

function handleCustodyDayClick(dateStr) {
    if (window.innerWidth <= 768) {
        renderCustodyMobileAgenda(dateStr);
    } else {
        openCustodyEventModal(dateStr);
    }
}

function renderCustodyMobileAgenda(dateStr) {
    _custodySelectedDay = dateStr;

    document.querySelectorAll('#custody-calendar .cal-day.cal-selected').forEach(el => el.classList.remove('cal-selected'));
    document.querySelectorAll('#custody-calendar .cal-day').forEach(el => {
        if (el.getAttribute('onclick') === `handleCustodyDayClick('${dateStr}')`) {
            el.classList.add('cal-selected');
        }
    });

    const agenda = document.getElementById('custody-mobile-agenda');
    if (!agenda) return;

    const dayEvents = custodyEvents.filter(e => dateStr >= e.start && dateStr < e.end);

    const d = new Date(dateStr + 'T12:00:00');
    const label = d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });

    let inner = `<div class="cal-agenda-header">
        <span class="cal-agenda-title">${label.charAt(0).toUpperCase() + label.slice(1)}</span>
        <button class="btn btn-primary btn-sm" onclick="openCustodyEventModal('${dateStr}')">+ Ajouter</button>
    </div>`;

    if (dayEvents.length === 0) {
        inner += `<p class="cal-agenda-empty">Aucune garde ce jour.</p>`;
    } else {
        dayEvents.forEach(e => {
            const isRecurring = e.extendedProps?.is_recurring;
            const onClick = isRecurring
                ? `openCustodyEventModal('${dateStr}')`
                : `openEditCustodyEvent('${e.id}')`;
            inner += `<div class="cal-agenda-event" onclick="${onClick}" style="cursor:pointer">
                <span class="cal-agenda-dot" style="background:${e.color || '#4A90D9'}"></span>
                <div class="cal-agenda-info">
                    <div class="cal-agenda-name">${escapeHtml(e.title)}${isRecurring ? ' 🔄' : ''}</div>
                    <div class="cal-agenda-time">${isRecurring ? 'Récurrent' : 'Événement unique'}</div>
                </div>
            </div>`;
        });
    }

    agenda.innerHTML = inner;
    agenda.style.display = 'block';
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

    // Fetch the full 42-cell grid range actually rendered by renderCustodyCalendar()
    // (which starts on the Monday on/before the 1st and always shows 6 weeks),
    // not just the calendar month — otherwise the trailing days from the next
    // month shown in the grid have no data and render blank/stale.
    const firstDay = new Date(year, month, 1);
    let startDow = firstDay.getDay();
    if (startDow === 0) startDow = 7;
    const gridStart = new Date(firstDay);
    gridStart.setDate(gridStart.getDate() - (startDow - 1));
    const gridEnd = new Date(gridStart);
    gridEnd.setDate(gridEnd.getDate() + 41);

    const start = formatCustodyDate(gridStart);
    const end = formatCustodyDate(gridEnd);

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

function onScheduleChildSelect() {
    const select = document.getElementById('schedule-child-select');
    const isNew = select.value === '__new__';
    document.getElementById('schedule-child-name-group').style.display = isNew ? '' : 'none';
    if (!isNew) {
        const color = select.options[select.selectedIndex]?.dataset.color;
        if (color) document.getElementById('schedule-color').value = color;
    }
}

function openScheduleModal() {
    document.getElementById('schedule-modal-title').textContent = 'Ajouter un enfant';
    document.getElementById('schedule-id').value = '';
    document.getElementById('schedule-child-select-group').style.display = '';
    document.getElementById('schedule-child-select').value = '__new__';
    document.getElementById('schedule-child-name').value = '';
    document.getElementById('schedule-color').value = '#E67E22';
    onScheduleChildSelect();
    document.getElementById('schedule-notes').value = '';
    document.getElementById('schedule-recurrence-type').value = 'none';
    document.getElementById('schedule-recurrence-start').value = '';
    document.getElementById('schedule-handover-time').value = '18:00';
    document.getElementById('schedule-handover-weekday').value = '';
    document.getElementById('schedule-extra-weekday').value = '';
    document.getElementById('schedule-parent1-label').value = '';
    document.getElementById('schedule-parent1-color').value = '#4A90D9';
    document.getElementById('schedule-parent1-id').value = '';
    document.getElementById('schedule-parent2-label').value = '';
    document.getElementById('schedule-parent2-color').value = '#E74C3C';
    document.getElementById('schedule-parent2-id').value = '';
    document.getElementById('recurrence-fields').style.display = 'none';
    openModal('schedule-modal');
}

function openEditScheduleModal(schedule) {
    document.getElementById('schedule-modal-title').textContent = 'Modifier le planning';
    document.getElementById('schedule-id').value = schedule.id;
    document.getElementById('schedule-child-select-group').style.display = 'none';
    document.getElementById('schedule-child-name-group').style.display = '';
    document.getElementById('schedule-child-name').value = schedule.child_name;
    document.getElementById('schedule-color').value = schedule.color;
    document.getElementById('schedule-notes').value = schedule.notes || '';

    const recType = schedule.recurrence_type || 'none';
    document.getElementById('schedule-recurrence-type').value = recType;
    document.getElementById('schedule-recurrence-start').value = schedule.recurrence_start || '';
    document.getElementById('schedule-handover-time').value = (schedule.handover_time || '18:00:00').slice(0, 5);
    document.getElementById('schedule-handover-weekday').value = schedule.handover_weekday || '';
    document.getElementById('schedule-extra-weekday').value = schedule.extra_weekday || '';

    // Parent 1
    document.getElementById('schedule-parent1-label').value = schedule.recurrence_parent1_label || schedule.parent1_name || '';
    document.getElementById('schedule-parent1-color').value = schedule.recurrence_parent1_color || '#4A90D9';
    document.getElementById('schedule-parent1-id').value = schedule.recurrence_parent1_id || '';

    // Parent 2
    document.getElementById('schedule-parent2-label').value = schedule.recurrence_parent2_label || schedule.parent2_name || '';
    document.getElementById('schedule-parent2-color').value = schedule.recurrence_parent2_color || '#E74C3C';
    document.getElementById('schedule-parent2-id').value = schedule.recurrence_parent2_id || '';

    toggleRecurrenceFields(recType);
    openModal('schedule-modal');
}

function fillParentFromMember(num, select) {
    const opt = select.options[select.selectedIndex];
    if (!opt.value) return;
    document.getElementById(`schedule-parent${num}-label`).value = opt.dataset.name;
    document.getElementById(`schedule-parent${num}-color`).value = opt.dataset.color;
    document.getElementById(`schedule-parent${num}-id`).value = opt.value;
    select.selectedIndex = 0; // reset dropdown
}

async function saveSchedule() {
    const id = document.getElementById('schedule-id').value;
    let childName = null, familyChildId = null, newChildName = null;
    if (id) {
        childName = document.getElementById('schedule-child-name').value.trim();
        if (!childName) { Dialog.toast('Prénom requis.', 'error'); return; }
    } else {
        const childSelect = document.getElementById('schedule-child-select').value;
        if (childSelect === '__new__') {
            newChildName = document.getElementById('schedule-child-name').value.trim();
            if (!newChildName) { Dialog.toast('Prénom requis.', 'error'); return; }
        } else {
            familyChildId = childSelect;
        }
    }

    const recType = document.getElementById('schedule-recurrence-type').value;
    const recStart = document.getElementById('schedule-recurrence-start').value;
    const p1label = document.getElementById('schedule-parent1-label').value.trim();
    const p2label = document.getElementById('schedule-parent2-label').value.trim();

    if (recType !== 'none') {
        if (!recStart) { Dialog.toast('Veuillez indiquer la date de début de la périodicité.', 'error'); return; }
        if (!p1label || !p2label) { Dialog.toast('Veuillez renseigner le nom des deux parents.', 'error'); return; }
        if (p1label === p2label) { Dialog.toast('Les deux parents doivent avoir des noms différents.', 'error'); return; }
    }

    const data = {
        child_name: childName,
        family_child_id: familyChildId,
        new_child_name: newChildName,
        color: document.getElementById('schedule-color').value,
        notes: document.getElementById('schedule-notes').value,
        recurrence_type: recType,
        recurrence_start: recType !== 'none' ? recStart : null,
        handover_time: document.getElementById('schedule-handover-time').value || null,
        handover_weekday: document.getElementById('schedule-handover-weekday').value || null,
        extra_weekday: document.getElementById('schedule-extra-weekday').value || null,
        recurrence_parent1_id: document.getElementById('schedule-parent1-id').value || null,
        recurrence_parent1_label: p1label || null,
        recurrence_parent1_color: document.getElementById('schedule-parent1-color').value,
        recurrence_parent2_id: document.getElementById('schedule-parent2-id').value || null,
        recurrence_parent2_label: p2label || null,
        recurrence_parent2_color: document.getElementById('schedule-parent2-color').value,
    };

    const url = id ? `${BASE_URL}/api/custody/schedule/${id}` : `${BASE_URL}/api/custody/schedule`;
    const result = await apiFetch(url, { method: 'POST', body: JSON.stringify(data) });
    if (result.success) { closeModal('schedule-modal'); location.reload(); }
}

async function deleteSchedule(id) {
    if (!await Dialog.confirm('Supprimer ce planning de garde et tous ses événements ?')) return;
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
        Dialog.toast('Enfant, parent et dates requis.', 'error');
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
    if (!id || !await Dialog.confirm('Supprimer cette période de garde ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/custody/event/${id}/delete`, { method: 'POST' });
    if (result.success) { closeModal('custody-event-modal'); loadCustodyEvents(); }
}

function formatCustodyDate(d) {
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}

// ============================================================
// PROPOSAL TOOL
// ============================================================

let proposalDays   = {};  // { 'YYYY-MM-DD': { parentNum: 1|2, parentId, color, initial } }
let proposalP1     = { id: 0, label: 'Parent 1', color: '#4A90D9' };
let proposalP2     = { id: 0, label: 'Parent 2', color: '#E74C3C' };
let proposalParentActive = 1; // which parent we assign on click

function openProposalModal() {
    proposalDays = {};
    document.getElementById('proposal-calendar').innerHTML = '';
    document.getElementById('proposal-legend').style.display = 'none';
    document.getElementById('proposal-export-btn').style.display = 'none';
    document.getElementById('proposal-apply-btn').style.display = 'none';
    // Default range: 1st of next month to end of next month
    const now = new Date();
    const y = now.getFullYear(), m = now.getMonth() + 1;
    const nextM = m === 12 ? 1 : m + 1;
    const nextY = m === 12 ? y + 1 : y;
    const lastDay = new Date(nextY, nextM, 0).getDate();
    document.getElementById('proposal-start').value = `${nextY}-${String(nextM).padStart(2,'0')}-01`;
    document.getElementById('proposal-end').value   = `${nextY}-${String(nextM).padStart(2,'0')}-${lastDay}`;
    openModal('proposal-modal');
}

function proposalUpdateParentInfo() {
    const sel = document.getElementById('proposal-schedule-id');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;

    proposalP1 = {
        id:    parseInt(opt.dataset.p1Id) || 0,
        label: opt.dataset.p1Label || 'Parent 1',
        color: opt.dataset.p1Color || '#4A90D9',
    };
    proposalP2 = {
        id:    parseInt(opt.dataset.p2Id) || 0,
        label: opt.dataset.p2Label || 'Parent 2',
        color: opt.dataset.p2Color || '#E74C3C',
    };
}

function generateProposal() {
    const scheduleId = document.getElementById('proposal-schedule-id').value;
    const start      = document.getElementById('proposal-start').value;
    const end        = document.getElementById('proposal-end').value;

    if (!scheduleId) { Dialog.toast('Veuillez choisir un enfant.', 'error'); return; }
    if (!start || !end || start > end) { Dialog.toast('Période invalide.', 'error'); return; }

    proposalUpdateParentInfo();
    proposalDays = {};

    // Update legend
    document.getElementById('proposal-legend').style.display = 'flex';
    document.getElementById('proposal-export-btn').style.display = '';
    document.getElementById('proposal-apply-btn').style.display = '';

    document.getElementById('proposal-p1-name').textContent  = proposalP1.label;
    document.getElementById('proposal-p1-dot').style.background = proposalP1.color;
    document.getElementById('proposal-p2-name').textContent  = proposalP2.label;
    document.getElementById('proposal-p2-dot').style.background = proposalP2.color;

    renderProposalCalendar(start, end);
}

function renderProposalCalendar(startStr, endStr) {
    const cal = document.getElementById('proposal-calendar');
    const start = new Date(startStr + 'T12:00:00');
    const end   = new Date(endStr   + 'T12:00:00');

    // Group by month
    let html = '';
    let cur = new Date(start.getFullYear(), start.getMonth(), 1);

    while (cur <= end) {
        const year  = cur.getFullYear();
        const month = cur.getMonth();
        const monthLabel = cur.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });

        html += `<div class="proposal-month" data-month="${year}-${month}">`;
        html += `<div class="proposal-month-title">${monthLabel.charAt(0).toUpperCase() + monthLabel.slice(1)}</div>`;

        // Day names header
        html += `<div class="proposal-week" style="margin-bottom:.15rem">`;
        ['L','M','M','J','V','S','D'].forEach(d => {
            html += `<div style="text-align:center;font-size:.65rem;font-weight:600;color:var(--text-muted);padding:.2rem 0">${d}</div>`;
        });
        html += `</div>`;

        // Build weeks
        const firstDay = new Date(year, month, 1);
        let dow = firstDay.getDay(); // 0=Sun
        if (dow === 0) dow = 7;
        const startOfGrid = new Date(firstDay);
        startOfGrid.setDate(startOfGrid.getDate() - (dow - 1));

        const lastDay = new Date(year, month + 1, 0);
        let gridEnd = new Date(lastDay);
        let dowEnd = gridEnd.getDay(); if (dowEnd === 0) dowEnd = 7;
        gridEnd.setDate(gridEnd.getDate() + (7 - dowEnd));

        let d = new Date(startOfGrid);
        while (d <= gridEnd) {
            html += `<div class="proposal-week">`;
            for (let i = 0; i < 7; i++) {
                const dateStr  = formatCustodyDate(d);
                const inRange  = d >= start && d <= end;
                const inMonth  = d.getMonth() === month;
                const isWe     = d.getDay() === 0 || d.getDay() === 6;

                let cls = 'proposal-day';
                if (!inRange) cls += ' pd-empty';
                else if (!inMonth) cls += ' pd-other-month';
                if (isWe && inRange) cls += ' pd-weekend';

                const day = proposalDays[dateStr];
                let bgStyle = '';
                let initial = '';
                if (day && inRange) {
                    bgStyle = `background:${day.color};border-color:${day.color}`;
                    cls += day.parentNum === 1 ? ' pd-p1' : ' pd-p2';
                    initial = day.initial;
                }

                html += `<div class="${cls}" style="${bgStyle}" data-date="${dateStr}" onclick="proposalToggleDay('${dateStr}')">
                    <span class="pd-num">${d.getDate()}</span>
                    <span class="pd-initial">${initial}</span>
                </div>`;
                d.setDate(d.getDate() + 1);
            }
            html += `</div>`;
        }

        html += `</div>`;
        cur.setMonth(cur.getMonth() + 1);
    }

    cal.innerHTML = html;
    proposalUpdateCounts();
}

function proposalToggleDay(dateStr) {
    const cur = proposalDays[dateStr];
    if (!cur) {
        // Assign to current active parent
        const p = proposalParentActive === 1 ? proposalP1 : proposalP2;
        proposalDays[dateStr] = { parentNum: proposalParentActive, parentId: p.id, color: p.color, initial: p.label.charAt(0).toUpperCase() };
    } else if (cur.parentNum === 1) {
        // Switch to P2
        proposalDays[dateStr] = { parentNum: 2, parentId: proposalP2.id, color: proposalP2.color, initial: proposalP2.label.charAt(0).toUpperCase() };
    } else {
        // Clear
        delete proposalDays[dateStr];
    }
    proposalRefreshDay(dateStr);
    proposalUpdateCounts();
}

function proposalRefreshDay(dateStr) {
    const el = document.querySelector(`.proposal-day[data-date="${dateStr}"]`);
    if (!el) return;
    const day = proposalDays[dateStr];
    el.classList.remove('pd-p1', 'pd-p2');
    el.style.background = '';
    el.style.borderColor = '';
    const initial = el.querySelector('.pd-initial');
    if (day) {
        el.style.background = day.color;
        el.style.borderColor = day.color;
        el.classList.add(day.parentNum === 1 ? 'pd-p1' : 'pd-p2');
        if (initial) initial.textContent = day.initial;
    } else {
        if (initial) initial.textContent = '';
    }
}

function proposalUpdateCounts() {
    let c1 = 0, c2 = 0;
    Object.values(proposalDays).forEach(d => {
        if (d.parentNum === 1) c1++;
        else c2++;
    });
    document.getElementById('proposal-p1-count').textContent = c1 + ' jour' + (c1 > 1 ? 's' : '');
    document.getElementById('proposal-p2-count').textContent = c2 + ' jour' + (c2 > 1 ? 's' : '');
}

function proposalFillAll(parentNum) {
    const p = parentNum === 1 ? proposalP1 : proposalP2;
    document.querySelectorAll('.proposal-day:not(.pd-empty):not(.pd-other-month)').forEach(el => {
        const dateStr = el.dataset.date;
        if (!dateStr) return;
        proposalDays[dateStr] = { parentNum, parentId: p.id, color: p.color, initial: p.label.charAt(0).toUpperCase() };
        proposalRefreshDay(dateStr);
    });
    proposalUpdateCounts();
}

function proposalFillWeekdays(parentNum) {
    const p = parentNum === 1 ? proposalP1 : proposalP2;
    document.querySelectorAll('.proposal-day:not(.pd-empty):not(.pd-other-month)').forEach(el => {
        const dateStr = el.dataset.date;
        if (!dateStr) return;
        const d = new Date(dateStr + 'T12:00:00');
        const dow = d.getDay();
        if (dow === 0 || dow === 6) return;
        proposalDays[dateStr] = { parentNum, parentId: p.id, color: p.color, initial: p.label.charAt(0).toUpperCase() };
        proposalRefreshDay(dateStr);
    });
    proposalUpdateCounts();
}

function proposalFillWeekends(parentNum) {
    const p = parentNum === 1 ? proposalP1 : proposalP2;
    document.querySelectorAll('.proposal-day:not(.pd-empty):not(.pd-other-month)').forEach(el => {
        const dateStr = el.dataset.date;
        if (!dateStr) return;
        const d = new Date(dateStr + 'T12:00:00');
        const dow = d.getDay();
        if (dow !== 0 && dow !== 6) return;
        proposalDays[dateStr] = { parentNum, parentId: p.id, color: p.color, initial: p.label.charAt(0).toUpperCase() };
        proposalRefreshDay(dateStr);
    });
    proposalUpdateCounts();
}

function proposalClearAll() {
    proposalDays = {};
    document.querySelectorAll('.proposal-day').forEach(el => {
        const dateStr = el.dataset.date;
        if (dateStr) proposalRefreshDay(dateStr);
    });
    proposalUpdateCounts();
}

async function applyProposal() {
    const scheduleId = document.getElementById('proposal-schedule-id').value;
    if (!scheduleId) { Dialog.toast('Veuillez choisir un enfant.', 'error'); return; }

    const daysList = Object.entries(proposalDays).map(([date, d]) => ({
        date,
        parent_user_id: d.parentId,
    })).filter(d => d.parent_user_id);

    if (!daysList.length) { Dialog.toast('Aucun jour attribué.', 'error'); return; }

    if (!await Dialog.confirm(`Appliquer ${daysList.length} jour(s) au calendrier de garde ? Les événements existants ne seront pas supprimés.`)) return;

    const result = await apiFetch(`${BASE_URL}/api/custody/proposal/apply`, {
        method: 'POST',
        body: JSON.stringify({ schedule_id: scheduleId, days: daysList }),
    });

    if (result.success) {
        Dialog.toast(`${result.created} bloc(s) de garde ajouté(s) au calendrier.`, 'success');
        closeModal('proposal-modal');
        loadCustodyEvents();
    }
}

function exportProposalAsImage() {
    const start = document.getElementById('proposal-start').value;
    const end   = document.getElementById('proposal-end').value;
    const childSel = document.getElementById('proposal-schedule-id');
    const childName = childSel.options[childSel.selectedIndex]?.text || 'Enfant';

    // Count days
    let c1 = 0, c2 = 0;
    Object.values(proposalDays).forEach(d => { if (d.parentNum === 1) c1++; else c2++; });

    const fmtDate = str => {
        if (!str) return '';
        const d = new Date(str + 'T12:00:00');
        return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
    };

    // Build months list
    const startD = new Date(start + 'T12:00:00');
    const endD   = new Date(end   + 'T12:00:00');

    const CELL   = 44;
    const PAD    = 20;
    const COL_W  = CELL + 3;
    const gridW  = COL_W * 7 - 3;
    const canvasW = gridW + PAD * 2;

    // Pre-compute months and their rows
    const months = [];
    let cur = new Date(startD.getFullYear(), startD.getMonth(), 1);
    while (cur <= endD) {
        const year  = cur.getFullYear();
        const month = cur.getMonth();
        const firstDay = new Date(year, month, 1);
        let dow = firstDay.getDay(); if (dow === 0) dow = 7;
        const startOfGrid = new Date(firstDay); startOfGrid.setDate(startOfGrid.getDate() - (dow - 1));
        const lastDay = new Date(year, month + 1, 0);
        let gridEnd = new Date(lastDay); let de = gridEnd.getDay(); if (de === 0) de = 7; gridEnd.setDate(gridEnd.getDate() + (7 - de));
        const weeks = Math.ceil(((gridEnd - startOfGrid) / 86400000 + 1) / 7);
        months.push({ year, month, startOfGrid, weeks });
        cur.setMonth(cur.getMonth() + 1);
    }

    const HEADER_H = 80;
    const FOOTER_H = 50;
    const MONTH_TITLE_H = 26;
    const DAYNAMES_H = 22;
    const WEEK_H = CELL + 3;
    let totalH = HEADER_H + PAD;
    months.forEach(m => { totalH += MONTH_TITLE_H + DAYNAMES_H + m.weeks * WEEK_H + PAD; });
    totalH += FOOTER_H + PAD;

    const canvas = document.createElement('canvas');
    const dpr = window.devicePixelRatio || 1;
    canvas.width  = canvasW  * dpr;
    canvas.height = totalH   * dpr;
    canvas.style.width  = canvasW  + 'px';
    canvas.style.height = totalH   + 'px';
    const ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);

    // Background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvasW, totalH);

    // Header
    ctx.fillStyle = '#1e293b';
    ctx.font = 'bold 18px system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(`Proposition de garde — ${childName}`, canvasW / 2, 28);
    ctx.font = '13px system-ui, sans-serif';
    ctx.fillStyle = '#64748b';
    ctx.fillText(`Du ${fmtDate(start)} au ${fmtDate(end)}`, canvasW / 2, 50);

    // Parent indicators in header
    const p1x = canvasW / 2 - 120;
    const p2x = canvasW / 2 + 20;
    ctx.beginPath(); ctx.arc(p1x - 8, 66, 6, 0, Math.PI*2); ctx.fillStyle = proposalP1.color; ctx.fill();
    ctx.fillStyle = '#1e293b'; ctx.font = '12px system-ui, sans-serif'; ctx.textAlign = 'left';
    ctx.fillText(`${proposalP1.label} : ${c1} jour${c1>1?'s':''}`, p1x, 70);
    ctx.beginPath(); ctx.arc(p2x - 8, 66, 6, 0, Math.PI*2); ctx.fillStyle = proposalP2.color; ctx.fill();
    ctx.fillText(`${proposalP2.label} : ${c2} jour${c2>1?'s':''}`, p2x, 70);

    let y = HEADER_H + PAD;
    const dayNamesShort = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];

    months.forEach(({ year, month, startOfGrid, weeks }) => {
        // Month title
        const monthLabel = new Date(year, month, 1).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
        ctx.fillStyle = '#475569';
        ctx.font = 'bold 13px system-ui, sans-serif';
        ctx.textAlign = 'left';
        ctx.fillText(monthLabel.charAt(0).toUpperCase() + monthLabel.slice(1), PAD, y + 16);
        y += MONTH_TITLE_H;

        // Day names
        dayNamesShort.forEach((dn, i) => {
            ctx.fillStyle = '#94a3b8';
            ctx.font = '11px system-ui, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(dn, PAD + i * COL_W + CELL / 2, y + 14);
        });
        y += DAYNAMES_H;

        // Weeks
        let d = new Date(startOfGrid);
        for (let w = 0; w < weeks; w++) {
            for (let i = 0; i < 7; i++) {
                const dateStr  = formatCustodyDate(d);
                const inRange  = d >= startD && d <= endD;
                const inMonth  = d.getMonth() === month;
                const isWe     = d.getDay() === 0 || d.getDay() === 6;
                const x        = PAD + i * COL_W;
                const dy       = proposalDays[dateStr];

                if (inRange) {
                    if (dy) {
                        // Colored cell
                        const hex = dy.color;
                        ctx.fillStyle = hex;
                        roundRect(ctx, x, y, CELL, CELL, 7);
                        ctx.fill();
                        // Initial
                        ctx.fillStyle = 'rgba(255,255,255,0.9)';
                        ctx.font = 'bold 10px system-ui, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.fillText(dy.initial, x + CELL/2, y + CELL - 7);
                    } else if (isWe) {
                        ctx.fillStyle = '#f1f5f9';
                        roundRect(ctx, x, y, CELL, CELL, 7);
                        ctx.fill();
                    } else {
                        ctx.fillStyle = '#f8fafc';
                        roundRect(ctx, x, y, CELL, CELL, 7);
                        ctx.fill();
                    }
                    // Date number
                    ctx.fillStyle = dy ? 'rgba(255,255,255,0.95)' : (inMonth ? '#334155' : '#94a3b8');
                    ctx.font = (inMonth ? '500' : '400') + ' 12px system-ui, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText(d.getDate(), x + CELL/2, y + 16);
                }

                d.setDate(d.getDate() + 1);
            }
            y += WEEK_H;
        }
        y += PAD;
    });

    // Footer
    ctx.fillStyle = '#94a3b8';
    ctx.font = '11px system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('FamilyBoard — Proposition de garde', canvasW / 2, y + 20);

    // Download
    const link = document.createElement('a');
    link.download = `garde-${childName.toLowerCase().replace(/\s+/g,'-')}-${start}-${end}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
}

function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + w - r, y);
    ctx.quadraticCurveTo(x + w, y, x + w, y + r);
    ctx.lineTo(x + w, y + h - r);
    ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
    ctx.lineTo(x + r, y + h);
    ctx.quadraticCurveTo(x, y + h, x, y + h - r);
    ctx.lineTo(x, y + r);
    ctx.quadraticCurveTo(x, y, x + r, y);
    ctx.closePath();
}

// ---- Vacation periods ----

let vacationPeriodsCache = [];

async function openVacationModal(scheduleId, childName) {
    document.getElementById('vacation-modal-title').textContent = `Périodes de vacances — ${childName}`;
    document.getElementById('vacation-schedule-id').value = scheduleId;
    resetVacationForm();
    await loadVacationPeriods(scheduleId);
    openModal('vacation-modal');
}

async function loadVacationPeriods(scheduleId) {
    const list = document.getElementById('vacation-list');
    list.innerHTML = '<p style="color:var(--text-muted);font-size:.85rem">Chargement…</p>';
    const schedule = SCHEDULES.find(s => s.id == scheduleId);
    const data = await apiFetch(`${BASE_URL}/api/custody/schedule/${scheduleId}/vacation-list`);
    vacationPeriodsCache = Array.isArray(data) ? data : (data.periods || []);
    if (!vacationPeriodsCache.length) {
        list.innerHTML = '<p style="color:var(--text-muted);font-size:.85rem">Aucune période configurée.</p>';
        return;
    }
    const distLabels = { '1week_2': '1 semaine sur 2', '2weeks_4': '2 semaines sur 4', 'odd_even_weeks': 'Semaines paires/impaires' };
    list.innerHTML = vacationPeriodsCache.map(v => `
        <div class="cp-custody-item" style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid var(--border)">
            <div>
                <strong>${escapeHtml(v.label || 'Vacances')}</strong>
                <div style="font-size:.78rem;color:var(--text-muted)">${v.start_date} → ${v.end_date} · ${distLabels[v.distribution_type] || v.distribution_type}</div>
            </div>
            <div>
                <button class="btn-chip" onclick="editVacationPeriod(${v.id})">✏️</button>
                <button class="btn-chip" onclick="deleteVacationPeriod(${v.id}, ${scheduleId})">✕</button>
            </div>
        </div>
    `).join('');
}

function resetVacationForm() {
    document.getElementById('vacation-id').value = '';
    document.getElementById('vacation-label').value = '';
    document.getElementById('vacation-start').value = '';
    document.getElementById('vacation-end').value = '';
    document.getElementById('vacation-distribution').value = '1week_2';
    document.getElementById('vacation-starting-parent').value = '1';
}

function editVacationPeriod(id) {
    const v = vacationPeriodsCache.find(p => p.id == id);
    if (!v) return;
    document.getElementById('vacation-id').value = v.id;
    document.getElementById('vacation-label').value = v.label || '';
    document.getElementById('vacation-start').value = v.start_date;
    document.getElementById('vacation-end').value = v.end_date;
    document.getElementById('vacation-distribution').value = v.distribution_type;
    document.getElementById('vacation-starting-parent').value = v.starting_parent;
}

async function saveVacationPeriod() {
    const scheduleId = document.getElementById('vacation-schedule-id').value;
    const start = document.getElementById('vacation-start').value;
    const end = document.getElementById('vacation-end').value;
    if (!start || !end) { Dialog.toast('Renseignez les deux dates.', 'error'); return; }

    const data = {
        label: document.getElementById('vacation-label').value.trim(),
        start_date: start,
        end_date: end,
        distribution_type: document.getElementById('vacation-distribution').value,
        starting_parent: document.getElementById('vacation-starting-parent').value,
    };

    const id = document.getElementById('vacation-id').value;
    const url = id ? `${BASE_URL}/api/custody/vacation/${id}` : `${BASE_URL}/api/custody/schedule/${scheduleId}/vacation`;
    const result = await apiFetch(url, { method: 'POST', body: JSON.stringify(data) });
    if (result.success) {
        resetVacationForm();
        loadVacationPeriods(scheduleId);
        loadCustodyEvents();
    } else {
        Dialog.toast(result.error || 'Erreur.', 'error');
    }
}

async function deleteVacationPeriod(id, scheduleId) {
    if (!await Dialog.confirm('Supprimer cette période de vacances ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/custody/vacation/${id}/delete`, { method: 'POST' });
    if (result.success) {
        loadVacationPeriods(scheduleId);
        loadCustodyEvents();
    }
}

// ---- Invite co-parent ----

function openInviteCoparentModal(scheduleId) {
    document.getElementById('invite-coparent-email').value = '';
    const container = document.getElementById('invite-coparent-children');
    container.innerHTML = SCHEDULES.map(s => `
        <label class="radio-option" style="display:flex;align-items:center;gap:.4rem;margin-bottom:.3rem">
            <input type="checkbox" class="invite-coparent-child-cb" value="${s.id}" ${s.id == scheduleId ? 'checked' : ''}>
            <span>${escapeHtml(s.child_name)}</span>
        </label>
    `).join('');
    document.getElementById('invite-coparent-modal').dataset.anchorSchedule = scheduleId;
    openModal('invite-coparent-modal');
}

async function sendCoparentInvite() {
    const email = document.getElementById('invite-coparent-email').value.trim();
    if (!email) { Dialog.toast('Adresse email requise.', 'error'); return; }
    const scheduleIds = Array.from(document.querySelectorAll('.invite-coparent-child-cb:checked')).map(cb => parseInt(cb.value));
    if (!scheduleIds.length) { Dialog.toast('Sélectionnez au moins un enfant.', 'error'); return; }

    const anchorSchedule = document.getElementById('invite-coparent-modal').dataset.anchorSchedule;
    const result = await apiFetch(`${BASE_URL}/api/custody/schedule/${anchorSchedule}/invite-coparent`, {
        method: 'POST',
        body: JSON.stringify({ email, schedule_ids: scheduleIds }),
    });
    if (result.success) {
        Dialog.toast('Invitation envoyée.', 'success');
        closeModal('invite-coparent-modal');
    } else {
        Dialog.toast(result.error || 'Erreur lors de l\'envoi.', 'error');
    }
}

// ---- Activity log ----

async function openActivityLogModal(scheduleId, childName) {
    document.getElementById('activity-log-title').textContent = `Journal d'activité — ${childName}`;
    document.getElementById('activity-log-content').innerHTML = '<p style="color:var(--text-muted);font-size:.85rem">Chargement…</p>';
    openModal('activity-log-modal');
    const data = await apiFetch(`${BASE_URL}/api/custody/schedule/${scheduleId}/activity-log`);
    document.getElementById('activity-log-content').innerHTML = renderActivityLogHtml(data);
}

// ---- Checklist "à ne pas oublier" ----

async function toggleCustodyChecklistItem(id, checked) {
    await apiFetch(`${BASE_URL}/api/custody/checklist/${id}/toggle`, {
        method: 'POST',
        body: JSON.stringify({ checked }),
    });
}

async function deleteCustodyChecklistItem(id) {
    const ok = await Dialog.confirm('Retirer cet élément de la checklist (pour tous les prochains transferts) ?');
    if (!ok) return;
    await apiFetch(`${BASE_URL}/api/custody/checklist/${id}/delete`, { method: 'POST', body: '{}' });
    window.location.reload();
}

async function addCustodyChecklistItem() {
    const input = document.getElementById('custody-checklist-new-label');
    const label = input.value.trim();
    if (!label) return;
    const scheduleId = document.getElementById('custody-checklist-new-schedule').value;
    const r = await apiFetch(`${BASE_URL}/api/custody/checklist`, {
        method: 'POST',
        body: JSON.stringify({ label, schedule_id: scheduleId || null }),
    });
    if (r.success) {
        window.location.reload();
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

// Init
loadCustodyEvents();
