// ============================================
// Vue co-parent à accès restreint
// ============================================

let cpCurrentScheduleId = (typeof CP_SCHEDULES !== 'undefined' && CP_SCHEDULES.length) ? CP_SCHEDULES[0].id : null;
let cpJournalLastId = 0;
let cpJournalPolling = null;
let cpProposalMonth = new Date();
let cpProposalDays = {}; // 'YYYY-MM-DD' -> 1 | 2

function cpCurrentSchedule() {
    return CP_SCHEDULES.find(s => s.id == cpCurrentScheduleId) || CP_SCHEDULES[0];
}

function cpSwitchSchedule(id) {
    cpCurrentScheduleId = parseInt(id);
    cpJournalLastId = 0;
    cpLoadAll();
}

function cpShowTab(panelId) {
    document.querySelectorAll('.coparent-tab').forEach(t => t.classList.toggle('active', t.dataset.panel === panelId));
    document.querySelectorAll('.coparent-panel').forEach(p => p.classList.toggle('active', p.id === panelId));
}

function cpLoadAll() {
    cpLoadCustody();
    cpLoadJournal();
    cpLoadDocuments();
    cpLoadEvents();
}

// ── Calendrier de garde ──────────────────────────────────────────────────

async function cpLoadCustody() {
    const start = new Date();
    const end = new Date();
    end.setDate(end.getDate() + 90);
    const fmt = d => d.toISOString().slice(0, 10);
    const data = await apiFetch(`${BASE_URL}/api/coparent/custody-events?schedule_id=${cpCurrentScheduleId}&start=${fmt(start)}&end=${fmt(end)}`);
    const list = document.getElementById('cp-custody-list');
    const events = Array.isArray(data) ? data : [];
    if (!events.length) {
        list.innerHTML = '<p style="color:var(--text-muted);font-size:.85rem">Aucun jour de garde à venir.</p>';
        return;
    }
    list.innerHTML = events.map(e => `
        <div class="cp-custody-item">
            <strong>${escapeHtml(e.title)}</strong>
            <div style="color:var(--text-muted);font-size:.8rem">${cpFormatDateRange(e.start, e.end)}</div>
        </div>
    `).join('');
}

function cpFormatDateRange(start, end) {
    const opts = { day: '2-digit', month: 'short' };
    const s = new Date(start + 'T00:00:00').toLocaleDateString('fr-FR', opts);
    const endDate = new Date(end + 'T00:00:00');
    endDate.setDate(endDate.getDate() - 1);
    const e = endDate.toLocaleDateString('fr-FR', opts);
    return s === e ? s : `${s} → ${e}`;
}

// ── Proposition de garde ─────────────────────────────────────────────────

function cpOpenProposalModal() {
    cpProposalMonth = new Date();
    cpProposalMonth.setDate(1);
    cpProposalDays = {};
    const schedule = cpCurrentSchedule();
    document.getElementById('cp-p1-name').textContent = schedule.recurrence_parent1_label || schedule.parent1_name || 'Parent 1';
    document.getElementById('cp-p2-name').textContent = schedule.recurrence_parent2_label || schedule.parent2_name || 'Parent 2';
    document.getElementById('cp-legend-p1').style.background = schedule.recurrence_parent1_color || '#4A90D9';
    document.getElementById('cp-legend-p2').style.background = schedule.recurrence_parent2_color || '#E74C3C';
    cpRenderProposalGrid();
    openModal('cp-proposal-modal');
}

function cpProposalShiftMonth(delta) {
    cpProposalMonth.setMonth(cpProposalMonth.getMonth() + delta);
    cpRenderProposalGrid();
}

function cpRenderProposalGrid() {
    const schedule = cpCurrentSchedule();
    const year = cpProposalMonth.getFullYear();
    const month = cpProposalMonth.getMonth();
    document.getElementById('cp-proposal-month-label').textContent =
        cpProposalMonth.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });

    const firstDay = new Date(year, month, 1);
    const startOffset = (firstDay.getDay() + 6) % 7; // Monday = 0
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const p1Color = schedule.recurrence_parent1_color || '#4A90D9';
    const p2Color = schedule.recurrence_parent2_color || '#E74C3C';

    let html = '';
    for (let i = 0; i < startOffset; i++) html += '<div class="cp-proposal-day cp-empty"></div>';
    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const assigned = cpProposalDays[dateStr];
        const bg = assigned === 1 ? p1Color : (assigned === 2 ? p2Color : 'var(--card-bg)');
        const color = assigned ? '#fff' : 'var(--text)';
        html += `<div class="cp-proposal-day" style="background:${bg};color:${color}" onclick="cpToggleProposalDay('${dateStr}')">${d}</div>`;
    }
    document.getElementById('cp-proposal-grid').innerHTML = html;
}

function cpToggleProposalDay(dateStr) {
    const current = cpProposalDays[dateStr];
    if (!current) cpProposalDays[dateStr] = 1;
    else if (current === 1) cpProposalDays[dateStr] = 2;
    else delete cpProposalDays[dateStr];
    cpRenderProposalGrid();
}

async function cpSubmitProposal() {
    const schedule = cpCurrentSchedule();
    const days = Object.keys(cpProposalDays).map(date => {
        const slot = cpProposalDays[date];
        const parentId = slot === 1 ? schedule.recurrence_parent1_id : schedule.recurrence_parent2_id;
        return { date, parent_user_id: parentId };
    }).filter(d => d.parent_user_id);

    if (!days.length) { Dialog.toast('Sélectionnez au moins un jour attribué à un parent avec compte FamilyBoard.', 'error'); return; }

    const result = await apiFetch(`${BASE_URL}/api/coparent/custody-proposal`, {
        method: 'POST',
        body: JSON.stringify({ schedule_id: cpCurrentScheduleId, days }),
    });
    if (result.success) {
        Dialog.toast('Proposition envoyée.', 'success');
        closeModal('cp-proposal-modal');
        cpLoadCustody();
    } else {
        Dialog.toast(result.error || 'Erreur lors de l\'envoi.', 'error');
    }
}

// ── Journal parental ──────────────────────────────────────────────────────

async function cpLoadJournal() {
    const data = await apiFetch(`${BASE_URL}/api/coparent/journal?schedule_id=${cpCurrentScheduleId}`);
    const list = document.getElementById('cp-journal-list');
    list.innerHTML = '';
    (data.messages || []).forEach(m => cpAppendJournalMessage(m));
    if (data.messages && data.messages.length) {
        cpJournalLastId = Math.max(...data.messages.map(m => m.id));
    }
    list.scrollTop = list.scrollHeight;
}

function cpAppendJournalMessage(m) {
    const list = document.getElementById('cp-journal-list');
    const div = document.createElement('div');
    div.className = 'cp-journal-msg';
    div.innerHTML = `
        <div class="cp-journal-meta" style="color:${escapeHtml(m.user_color || '#888')}">${escapeHtml(m.user_name)} · ${cpFormatDateTime(m.created_at)}</div>
        <div>${escapeHtml(m.content).replace(/\n/g, '<br>')}</div>
    `;
    list.appendChild(div);
}

function cpFormatDateTime(datetime) {
    const iso = datetime.includes('T') ? datetime : datetime.replace(' ', 'T') + 'Z';
    return new Date(iso).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

async function cpSendJournal() {
    const input = document.getElementById('cp-journal-input');
    const content = input.value.trim();
    if (!content) return;
    input.value = '';
    const result = await apiFetch(`${BASE_URL}/api/coparent/journal`, {
        method: 'POST',
        body: JSON.stringify({ schedule_id: cpCurrentScheduleId, content }),
    });
    if (result.success) {
        cpAppendJournalMessage(result.message);
        cpJournalLastId = result.message.id;
        const list = document.getElementById('cp-journal-list');
        list.scrollTop = list.scrollHeight;
    }
}

async function cpPollJournal() {
    if (!cpCurrentScheduleId) return;
    const data = await apiFetch(`${BASE_URL}/api/coparent/journal?schedule_id=${cpCurrentScheduleId}&after=${cpJournalLastId}`);
    (data.messages || []).forEach(m => {
        cpAppendJournalMessage(m);
        cpJournalLastId = Math.max(cpJournalLastId, m.id);
    });
    if (data.messages && data.messages.length) {
        const list = document.getElementById('cp-journal-list');
        list.scrollTop = list.scrollHeight;
    }
}

// ── Documents ─────────────────────────────────────────────────────────────

async function cpLoadDocuments() {
    const docs = await apiFetch(`${BASE_URL}/api/coparent/documents?schedule_id=${cpCurrentScheduleId}`);
    const list = document.getElementById('cp-documents-list');
    if (!Array.isArray(docs) || !docs.length) {
        list.innerHTML = '<p style="color:var(--text-muted);font-size:.85rem">Aucun document.</p>';
        return;
    }
    list.innerHTML = docs.map(d => `
        <div class="cp-doc-item">
            <strong>${escapeHtml(d.title)}</strong>
            ${d.file_path ? `<a href="${BASE_URL}/documents/file/${d.id}" target="_blank" class="btn-text" style="margin-left:.5rem">Voir</a>` : ''}
        </div>
    `).join('');
}

async function cpUploadDocument() {
    const title = document.getElementById('cp-doc-title').value.trim();
    const file = document.getElementById('cp-doc-file').files[0];
    if (!title) { Dialog.toast('Le titre est obligatoire.', 'error'); return; }

    const fd = new FormData();
    fd.append('title', title);
    fd.append('schedule_id', cpCurrentScheduleId);
    if (file) fd.append('file', file);

    const res = await fetch(`${BASE_URL}/api/coparent/documents`, { method: 'POST', body: fd });
    const result = await res.json();
    if (result.success) {
        document.getElementById('cp-doc-title').value = '';
        document.getElementById('cp-doc-file').value = '';
        cpLoadDocuments();
    } else {
        Dialog.toast(result.error || 'Erreur lors de l\'envoi.', 'error');
    }
}

// ── Évènements calendrier tagués ──────────────────────────────────────────

async function cpLoadEvents() {
    const start = new Date();
    const end = new Date();
    end.setDate(end.getDate() + 90);
    const data = await apiFetch(`${BASE_URL}/api/coparent/events?schedule_id=${cpCurrentScheduleId}&start=${start.toISOString()}&end=${end.toISOString()}`);
    const list = document.getElementById('cp-events-list');
    if (!Array.isArray(data) || !data.length) {
        list.innerHTML = '<p style="color:var(--text-muted);font-size:.85rem">Aucun évènement à venir.</p>';
        return;
    }
    list.innerHTML = data.map(e => `
        <div class="cp-event-item">
            <strong>${escapeHtml(e.title)}</strong>
            <div style="color:var(--text-muted);font-size:.8rem">${cpFormatDateTime(e.start_datetime)}</div>
        </div>
    `).join('');
}

async function cpCreateEvent() {
    const title = document.getElementById('cp-event-title').value.trim();
    const start = document.getElementById('cp-event-start').value;
    const end = document.getElementById('cp-event-end').value;
    if (!title || !start || !end) { Dialog.toast('Remplissez tous les champs.', 'error'); return; }

    const result = await apiFetch(`${BASE_URL}/api/coparent/events`, {
        method: 'POST',
        body: JSON.stringify({
            schedule_id: cpCurrentScheduleId,
            title,
            start_datetime: start.replace('T', ' ') + ':00',
            end_datetime: end.replace('T', ' ') + ':00',
            is_all_day: 0,
        }),
    });
    if (result.success) {
        document.getElementById('cp-event-title').value = '';
        document.getElementById('cp-event-start').value = '';
        document.getElementById('cp-event-end').value = '';
        cpLoadEvents();
    } else {
        Dialog.toast(result.error || 'Erreur lors de l\'ajout.', 'error');
    }
}

// ── Init ──────────────────────────────────────────────────────────────────

if (cpCurrentScheduleId) {
    cpLoadAll();
    cpJournalPolling = setInterval(cpPollJournal, 5000);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearInterval(cpJournalPolling);
        } else {
            cpJournalPolling = setInterval(cpPollJournal, 5000);
        }
    });
}
