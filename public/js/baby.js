/* Baby module */
var BabyApp = (() => {
    let state = {
        babyId: null,
        baby: null,
        pregnancy: null,
        editingEventId: null,
        editingSleepId: null,
        editingConsultId: null,
        imageCount: 0,
    };

    // ── Helpers ───────────────────────────────────────────────

    function now() {
        const d = new Date();
        return d.toISOString().slice(0, 16);
    }

    function toDatetimeLocal(str) {
        if (!str) return '';
        return str.slice(0, 16).replace(' ', 'T');
    }

    function formatDatetime(str) {
        if (!str) return '';
        const d = new Date(str.replace(' ', 'T'));
        return d.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
    }

    function formatTime(str) {
        if (!str) return '';
        const d = new Date(str.replace(' ', 'T'));
        return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    }

    function formatDate(str) {
        if (!str) return '';
        const d = new Date(str + 'T00:00:00');
        return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });
    }

    function durationMinutes(start, end) {
        const s = new Date(start.replace(' ', 'T'));
        const e = new Date(end.replace(' ', 'T'));
        return Math.round((e - s) / 60000);
    }

    function formatDuration(min) {
        if (min < 60) return `${min} min`;
        const h = Math.floor(min / 60), m = min % 60;
        return m ? `${h}h${String(m).padStart(2, '0')}` : `${h}h`;
    }

    function api(url, options = {}) {
        return fetch(BASE_URL + url, {
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            ...options,
        }).then(r => r.json());
    }

    function apiForm(url, formData) {
        return fetch(BASE_URL + url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
        }).then(r => r.json());
    }

    const EVENT_LABELS = {
        feeding: '🍼 Repas',
        stool:   '💩 Selles',
        urine:   '💧 Pipi',
        diaper:  '🧷 Couche',
        bath:    '🛁 Lavage',
    };

    // ── Modal helpers ─────────────────────────────────────────

    function openModal(id) {
        document.getElementById(id).style.display = 'flex';
    }

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    // ── Baby selection ────────────────────────────────────────

    function selectBaby(id) {
        state.babyId = id ? parseInt(id) : null;
        const emptyEl = document.getElementById('baby-empty');
        const hintEl  = document.getElementById('baby-select-hint');
        const dash    = document.getElementById('baby-dashboard');

        if (!state.babyId) {
            if (document.querySelector('#baby-select option:not([value=""])')) {
                emptyEl.style.display = 'none';
                hintEl.style.display = '';
            } else {
                emptyEl.style.display = '';
                hintEl.style.display = 'none';
            }
            dash.style.display = 'none';
            return;
        }

        emptyEl.style.display = 'none';
        hintEl.style.display = 'none';
        dash.style.display = '';

        const sel = document.getElementById('baby-select');
        const opt = sel.querySelector(`option[value="${state.babyId}"]`);
        state.baby = { id: state.babyId, name: opt?.textContent || '' };

        loadBabyDetails();
        initTrackingDate();
        loadTracking();
    }

    function loadBabyDetails() {
        // Re-fetch from DOM data (baby list was rendered server-side)
        // We refresh when saving edits, so just update UI from state.baby
        document.getElementById('baby-name-display').textContent = state.baby.name || '';
        updateBirthSummary(state.baby);
        updateAvatarDisplay(state.baby.avatar || null);
    }

    function updateBirthSummary(baby) {
        const el = document.getElementById('baby-birth-summary');
        if (!baby) { el.textContent = ''; return; }
        const parts = [];
        if (baby.birth_date) parts.push('Né(e) le ' + formatDate(baby.birth_date));
        if (baby.birth_weight) parts.push(baby.birth_weight + ' kg');
        if (baby.birth_height) parts.push(baby.birth_height + ' cm');
        el.textContent = parts.join(' · ');
    }

    function updateAvatarDisplay(path) {
        const img = document.getElementById('baby-avatar-img');
        const ph  = document.getElementById('baby-avatar-placeholder');
        if (path) {
            img.src = BASE_URL + path;
            img.style.display = '';
            ph.style.display = 'none';
        } else {
            img.style.display = 'none';
            ph.style.display = '';
        }
    }

    // ── Add / Edit baby ───────────────────────────────────────

    function openAddBaby() {
        document.getElementById('modal-baby-title').textContent = 'Nouveau bébé';
        document.getElementById('baby-form-name').value = '';
        state.editingBabyId = null;
        openModal('modal-baby');
    }

    function openEditBaby() {
        document.getElementById('modal-baby-title').textContent = 'Modifier le bébé';
        document.getElementById('baby-form-name').value = state.baby.name || '';
        state.editingBabyId = state.babyId;
        openModal('modal-baby');
    }

    function saveBaby(e) {
        if (e) e.preventDefault();
        const name = document.getElementById('baby-form-name').value.trim();
        if (!name) return;

        if (state.editingBabyId) {
            api(`/api/baby/${state.editingBabyId}`, {
                method: 'POST',
                body: JSON.stringify({ name, ...getBirthData() }),
            }).then(res => {
                if (!res.success) return;
                updateBabyInSelect(res.baby);
                state.baby = res.baby;
                loadBabyDetails();
                closeModal('modal-baby');
            });
        } else {
            api('/api/baby', { method: 'POST', body: JSON.stringify({ name }) }).then(res => {
                if (!res.success) return;
                addBabyToSelect(res.baby);
                state.baby = res.baby;
                closeModal('modal-baby');
                selectBaby(res.baby.id);
            });
        }
    }

    function getBirthData() {
        return {
            birth_date:    document.getElementById('birth-date')?.value || '',
            birth_time:    document.getElementById('birth-time')?.value || '',
            birth_weight:  document.getElementById('birth-weight')?.value || '',
            birth_height:  document.getElementById('birth-height')?.value || '',
            birth_details: document.getElementById('birth-details')?.value || '',
        };
    }

    function deleteBaby() {
        if (!state.babyId) return;
        if (!confirm(`Supprimer le bébé "${state.baby?.name}" ? Toutes les données seront perdues.`)) return;
        api(`/api/baby/${state.babyId}/delete`, { method: 'POST' }).then(res => {
            if (!res.success) return;
            removeBabyFromSelect(state.babyId);
            state.babyId = null;
            state.baby = null;
            document.getElementById('baby-select').value = '';
            selectBaby(null);
        });
    }

    function addBabyToSelect(baby) {
        const sel = document.getElementById('baby-select');
        const opt = document.createElement('option');
        opt.value = baby.id;
        opt.textContent = baby.name;
        opt.dataset.baby = JSON.stringify(baby);
        sel.appendChild(opt);
        document.getElementById('baby-empty').style.display = 'none';
    }

    function updateBabyInSelect(baby) {
        const opt = document.querySelector(`#baby-select option[value="${baby.id}"]`);
        if (opt) { opt.textContent = baby.name; opt.dataset.baby = JSON.stringify(baby); }
    }

    function removeBabyFromSelect(id) {
        const opt = document.querySelector(`#baby-select option[value="${id}"]`);
        if (opt) opt.remove();
        const sel = document.getElementById('baby-select');
        if (!sel.querySelector('option[value]:not([value=""])')) {
            document.getElementById('baby-empty').style.display = '';
        }
    }

    // ── Avatar ────────────────────────────────────────────────

    function uploadAvatar() {
        document.getElementById('avatar-file-input').click();
    }

    function doUploadAvatar(input) {
        if (!input.files[0]) return;
        const fd = new FormData();
        fd.append('avatar', input.files[0]);
        apiForm(`/api/baby/${state.babyId}/avatar`, fd).then(res => {
            if (res.success) {
                updateAvatarDisplay(res.path);
                if (state.baby) state.baby.avatar = res.path;
            }
        });
    }

    // ── Tabs ──────────────────────────────────────────────────

    function switchTab(tab, btn) {
        document.querySelectorAll('.baby-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.baby-tab-content').forEach(t => t.style.display = 'none');
        document.getElementById('tab-' + tab).style.display = '';

        if (tab === 'pregnancy') loadPregnancy();
        if (tab === 'birth') renderBirthTab();
        if (tab === 'consultations') loadConsultations();
    }

    // ── Tracking tab ──────────────────────────────────────────

    function initTrackingDate() {
        const inp = document.getElementById('tracking-date');
        inp.value = new Date().toISOString().slice(0, 10);
    }

    function prevDay() {
        const inp = document.getElementById('tracking-date');
        const d = new Date(inp.value + 'T00:00:00');
        d.setDate(d.getDate() - 1);
        inp.value = d.toISOString().slice(0, 10);
        loadTracking();
    }

    function nextDay() {
        const inp = document.getElementById('tracking-date');
        const d = new Date(inp.value + 'T00:00:00');
        d.setDate(d.getDate() + 1);
        inp.value = d.toISOString().slice(0, 10);
        loadTracking();
    }

    function loadTracking() {
        if (!state.babyId) return;
        const date = document.getElementById('tracking-date').value;
        api(`/api/baby/${state.babyId}/events?date=${date}`).then(data => {
            renderStats(data.stats, data.activeSleep);
            renderTimeline(data.events, data.sleeps, data.activeSleep);
            updateSleepButton(data.activeSleep);
        });
    }

    function renderStats(stats, activeSleep) {
        const el = document.getElementById('daily-stats');
        if (!stats) { el.innerHTML = ''; return; }
        el.innerHTML = `
            <div class="stat-chip feeding"><span class="stat-icon">🍼</span><span>${stats.feeding}</span><small>${stats.feeding_ml ? stats.feeding_ml + ' ml' : ''}</small></div>
            <div class="stat-chip stool"><span class="stat-icon">💩</span><span>${stats.stool}</span></div>
            <div class="stat-chip urine"><span class="stat-icon">💧</span><span>${stats.urine}</span></div>
            <div class="stat-chip diaper"><span class="stat-icon">🧷</span><span>${stats.diaper}</span></div>
            <div class="stat-chip bath"><span class="stat-icon">🛁</span><span>${stats.bath}</span></div>
            <div class="stat-chip sleep"><span class="stat-icon">😴</span><span>${formatDuration(stats.sleep_min)}${activeSleep ? ' 🔴' : ''}</span></div>
        `;
    }

    function renderTimeline(events, sleeps, activeSleep) {
        const all = [];
        events.forEach(e => all.push({ time: e.event_at, type: 'event', data: e }));
        sleeps.forEach(s => all.push({ time: s.start_at, type: 'sleep', data: s }));
        all.sort((a, b) => a.time > b.time ? -1 : 1);

        const el = document.getElementById('tracking-timeline');
        if (!all.length) {
            el.innerHTML = '<p class="text-muted text-center" style="padding:2rem">Aucun événement pour cette journée.</p>';
            return;
        }

        el.innerHTML = all.map(item => {
            if (item.type === 'event') {
                const e = item.data;
                const qty = e.quantity ? `<span class="event-qty">${e.quantity} ml</span>` : '';
                const notes = e.notes ? `<span class="event-notes">${escHtml(e.notes)}</span>` : '';
                return `<div class="timeline-item event-${e.type}">
                    <span class="timeline-time">${formatTime(e.event_at)}</span>
                    <span class="timeline-icon">${EVENT_LABELS[e.type]}</span>
                    ${qty}${notes}
                    <div class="timeline-actions">
                        <button class="btn-icon-sm" onclick="BabyApp.editEvent(${e.id})" title="Modifier">✏️</button>
                        <button class="btn-icon-sm" onclick="BabyApp.deleteEventItem(${e.id})" title="Supprimer">🗑️</button>
                    </div>
                </div>`;
            } else {
                const s = item.data;
                const active = !s.end_at;
                const dur = s.end_at ? ` · ${formatDuration(durationMinutes(s.start_at, s.end_at))}` : ' · en cours 🔴';
                const endTime = s.end_at ? ` → ${formatTime(s.end_at)}` : '';
                const notes = s.notes ? `<span class="event-notes">${escHtml(s.notes)}</span>` : '';
                return `<div class="timeline-item event-sleep ${active ? 'active-sleep' : ''}">
                    <span class="timeline-time">${formatTime(s.start_at)}${endTime}</span>
                    <span class="timeline-icon">😴 Sommeil${dur}</span>
                    ${notes}
                    <div class="timeline-actions">
                        ${active ? `<button class="btn btn-warning btn-xs" onclick="BabyApp.stopSleep(${s.id})">Arrêter</button>` : ''}
                        <button class="btn-icon-sm" onclick="BabyApp.editSleep(${s.id})" title="Modifier">✏️</button>
                        <button class="btn-icon-sm" onclick="BabyApp.deleteSleepItem(${s.id})" title="Supprimer">🗑️</button>
                    </div>
                </div>`;
            }
        }).join('');
    }

    function updateSleepButton(activeSleep) {
        const btn = document.getElementById('sleep-btn');
        if (activeSleep) {
            btn.textContent = '😴 Arrêter le sommeil';
            btn.classList.add('active-sleep-btn');
        } else {
            btn.textContent = '😴 Sommeil';
            btn.classList.remove('active-sleep-btn');
        }
    }

    // ── Quick add ─────────────────────────────────────────────

    function quickAdd(type) {
        state.editingEventId = null;
        document.getElementById('modal-event-title').textContent = 'Ajouter ' + (EVENT_LABELS[type] || type);
        document.getElementById('event-type').value = type;
        document.getElementById('event-at').value = now();
        document.getElementById('event-quantity').value = '';
        document.getElementById('event-notes').value = '';
        toggleQuantity();
        openModal('modal-event');
    }

    function quickAddSleep() {
        // Check for active sleep first
        api(`/api/baby/${state.babyId}/events?date=${document.getElementById('tracking-date').value}`)
            .then(data => {
                if (data.activeSleep) {
                    if (confirm('Un sommeil est en cours. Arrêter maintenant ?')) {
                        stopSleep(data.activeSleep.id);
                    }
                } else {
                    openSleepForm(null);
                }
            });
    }

    function openSleepForm(sleep) {
        state.editingSleepId = sleep ? sleep.id : null;
        document.getElementById('modal-sleep-title').textContent = sleep ? 'Modifier le sommeil' : 'Nouveau sommeil';
        document.getElementById('sleep-start').value = sleep ? toDatetimeLocal(sleep.start_at) : now();
        document.getElementById('sleep-end').value = sleep ? toDatetimeLocal(sleep.end_at) : '';
        document.getElementById('sleep-notes').value = sleep?.notes || '';
        openModal('modal-sleep');
    }

    function toggleQuantity() {
        const type = document.getElementById('event-type').value;
        const grp  = document.getElementById('event-quantity-group');
        grp.style.display = type === 'feeding' ? '' : 'none';
    }

    function saveEvent(e) {
        if (e) e.preventDefault();
        const payload = {
            type:     document.getElementById('event-type').value,
            event_at: document.getElementById('event-at').value.replace('T', ' '),
            quantity: document.getElementById('event-quantity').value,
            notes:    document.getElementById('event-notes').value,
        };

        const url = state.editingEventId
            ? `/api/baby/${state.babyId}/events/${state.editingEventId}`
            : `/api/baby/${state.babyId}/events`;

        api(url, { method: 'POST', body: JSON.stringify(payload) }).then(res => {
            if (res.success) { closeModal('modal-event'); loadTracking(); }
        });
    }

    function editEvent(id) {
        api(`/api/baby/${state.babyId}/events?date=${document.getElementById('tracking-date').value}`)
            .then(data => {
                const ev = data.events.find(e => e.id == id);
                if (!ev) return;
                state.editingEventId = id;
                document.getElementById('modal-event-title').textContent = 'Modifier l\'événement';
                document.getElementById('event-type').value = ev.type;
                document.getElementById('event-at').value = toDatetimeLocal(ev.event_at);
                document.getElementById('event-quantity').value = ev.quantity || '';
                document.getElementById('event-notes').value = ev.notes || '';
                toggleQuantity();
                openModal('modal-event');
            });
    }

    function deleteEventItem(id) {
        if (!confirm('Supprimer cet événement ?')) return;
        api(`/api/baby/${state.babyId}/events/${id}/delete`, { method: 'POST' })
            .then(res => { if (res.success) loadTracking(); });
    }

    function saveSleep(e) {
        if (e) e.preventDefault();
        const payload = {
            start_at: document.getElementById('sleep-start').value.replace('T', ' '),
            end_at:   document.getElementById('sleep-end').value.replace('T', ' ') || null,
            notes:    document.getElementById('sleep-notes').value,
        };

        const url = state.editingSleepId
            ? `/api/baby/${state.babyId}/sleeps/${state.editingSleepId}`
            : `/api/baby/${state.babyId}/sleeps`;

        api(url, { method: 'POST', body: JSON.stringify(payload) }).then(res => {
            if (res.success) { closeModal('modal-sleep'); loadTracking(); }
        });
    }

    function editSleep(id) {
        api(`/api/baby/${state.babyId}/events?date=${document.getElementById('tracking-date').value}`)
            .then(data => {
                const sl = data.sleeps.find(s => s.id == id);
                if (!sl) return;
                openSleepForm(sl);
            });
    }

    function stopSleep(id) {
        api(`/api/baby/${state.babyId}/sleeps/${id}/end`, {
            method: 'POST',
            body: JSON.stringify({ end_at: new Date().toISOString().slice(0, 19).replace('T', ' ') }),
        }).then(res => { if (res.success) loadTracking(); });
    }

    function deleteSleepItem(id) {
        if (!confirm('Supprimer ce sommeil ?')) return;
        api(`/api/baby/${state.babyId}/sleeps/${id}/delete`, { method: 'POST' })
            .then(res => { if (res.success) loadTracking(); });
    }

    // ── Pregnancy tab ─────────────────────────────────────────

    function loadPregnancy() {
        api(`/api/baby/${state.babyId}/pregnancy`).then(data => {
            state.pregnancy = data.pregnancy;
            renderPregnancyInfo(data.pregnancy);
            renderPregnancyConsultations(data.consultations);
        });
    }

    function renderPregnancyInfo(p) {
        const el = document.getElementById('pregnancy-info');
        if (!p) {
            el.innerHTML = '<p class="text-muted">Aucune information de grossesse enregistrée.</p>';
            return;
        }
        const weeksStr = p.lmp_date ? (() => {
            const days = Math.floor((Date.now() - new Date(p.lmp_date + 'T00:00:00')) / 86400000);
            const w = Math.floor(days / 7), d = days % 7;
            return `${w} SA + ${d} j`;
        })() : '';

        el.innerHTML = `
            <div class="info-grid">
                ${p.lmp_date ? `<div class="info-item"><label>DDR</label><span>${formatDate(p.lmp_date)}</span></div>` : ''}
                ${p.due_date ? `<div class="info-item"><label>DPA</label><span>${formatDate(p.due_date)}</span></div>` : ''}
                ${weeksStr ? `<div class="info-item"><label>Avancement</label><span>${weeksStr}</span></div>` : ''}
                ${p.notes ? `<div class="info-item full"><label>Notes</label><span>${escHtml(p.notes)}</span></div>` : ''}
            </div>`;
    }

    function renderPregnancyConsultations(list) {
        const el = document.getElementById('pregnancy-consultations-list');
        if (!list.length) {
            el.innerHTML = '<p class="text-muted">Aucune consultation enregistrée.</p>';
            return;
        }
        el.innerHTML = list.map(c => {
            const imgs = (c.images || []).map(img =>
                `<img src="${BASE_URL + img.path}" class="echo-thumb" onclick="BabyApp.openLightbox('${BASE_URL + img.path}')" title="${escHtml(img.name || '')}">
                 <button class="echo-delete" onclick="BabyApp.deletePregnancyImage(${img.id})" title="Supprimer">✕</button>`
            ).join('');
            return `<div class="consult-card">
                <div class="consult-header">
                    <strong>${formatDatetime(c.consult_date)}</strong>
                    ${c.practitioner ? `<span class="consult-practitioner">👨‍⚕️ ${escHtml(c.practitioner)}</span>` : ''}
                    <button class="btn-icon-sm" onclick="BabyApp.deletePregConsult(${c.id})" title="Supprimer">🗑️</button>
                </div>
                ${c.details ? `<p class="consult-details">${escHtml(c.details)}</p>` : ''}
                ${imgs ? `<div class="echo-gallery">${imgs}</div>` : ''}
            </div>`;
        }).join('');
    }

    function openPregnancyForm() {
        const p = state.pregnancy;
        document.getElementById('preg-lmp').value = p?.lmp_date || '';
        document.getElementById('preg-due').value = p?.due_date || '';
        document.getElementById('preg-notes').value = p?.notes || '';
        openModal('modal-pregnancy');
    }

    function calcDueDate() {
        const lmp = document.getElementById('preg-lmp').value;
        const due = document.getElementById('preg-due');
        if (lmp && !due.value) {
            const d = new Date(lmp + 'T00:00:00');
            d.setDate(d.getDate() + 280);
            due.value = d.toISOString().slice(0, 10);
        }
    }

    function savePregnancy(e) {
        if (e) e.preventDefault();
        const payload = {
            lmp_date: document.getElementById('preg-lmp').value,
            due_date: document.getElementById('preg-due').value,
            notes:    document.getElementById('preg-notes').value,
        };
        api(`/api/baby/${state.babyId}/pregnancy`, { method: 'POST', body: JSON.stringify(payload) })
            .then(res => {
                if (res.success) { closeModal('modal-pregnancy'); loadPregnancy(); }
            });
    }

    function openPregnancyConsultation() {
        state.imageCount = 0;
        document.getElementById('pc-date').value = now();
        document.getElementById('pc-practitioner').value = '';
        document.getElementById('pc-details').value = '';
        document.getElementById('pc-images-preview').innerHTML = '';
        document.getElementById('pc-image-inputs').innerHTML = '';
        openModal('modal-preg-consult');
    }

    function addImageField() {
        const idx = state.imageCount++;
        const wrap = document.getElementById('pc-image-inputs');
        const d = document.createElement('div');
        d.className = 'image-field';
        d.innerHTML = `<input type="file" name="image_${idx}" accept="image/*" onchange="BabyApp.previewImage(this, ${idx})">`;
        wrap.appendChild(d);
    }

    function previewImage(input, idx) {
        if (!input.files[0]) return;
        const reader = new FileReader();
        reader.onload = ev => {
            const preview = document.getElementById('pc-images-preview');
            const img = document.createElement('img');
            img.src = ev.target.result;
            img.className = 'echo-thumb-preview';
            img.dataset.idx = idx;
            preview.appendChild(img);
        };
        reader.readAsDataURL(input.files[0]);
    }

    function savePregnancyConsultation(e) {
        if (e) e.preventDefault();
        if (!state.pregnancy) {
            alert('Enregistrez d\'abord les informations de grossesse.');
            return;
        }
        const fd = new FormData();
        fd.append('consult_date', document.getElementById('pc-date').value.replace('T', ' '));
        fd.append('practitioner', document.getElementById('pc-practitioner').value);
        fd.append('details', document.getElementById('pc-details').value);

        const inputs = document.querySelectorAll('#pc-image-inputs input[type="file"]');
        inputs.forEach((inp, i) => { if (inp.files[0]) fd.append(`image_${i}`, inp.files[0]); });

        apiForm(`/api/baby/pregnancy/${state.pregnancy.id}/consultations`, fd).then(res => {
            if (res.success) { closeModal('modal-preg-consult'); loadPregnancy(); }
        });
    }

    function deletePregConsult(id) {
        if (!confirm('Supprimer cette consultation (et ses images) ?')) return;
        api(`/api/baby/pregnancy-consultations/${id}/delete`, { method: 'POST' })
            .then(res => { if (res.success) loadPregnancy(); });
    }

    function deletePregnancyImage(id) {
        if (!confirm('Supprimer cette image ?')) return;
        api(`/api/baby/pregnancy-images/${id}/delete`, { method: 'POST' })
            .then(res => { if (res.success) loadPregnancy(); });
    }

    function openLightbox(src) {
        document.getElementById('lightbox-img').src = src;
        openModal('modal-lightbox');
    }

    // ── Birth tab ─────────────────────────────────────────────

    function renderBirthTab() {
        const b = state.baby;
        const el = document.getElementById('birth-info');
        if (!b || (!b.birth_date && !b.birth_weight && !b.birth_height)) {
            el.innerHTML = '<p class="text-muted">Aucune information de naissance enregistrée.</p>';
            return;
        }
        el.innerHTML = `<div class="info-grid">
            ${b.birth_date ? `<div class="info-item"><label>Date</label><span>${formatDate(b.birth_date)}</span></div>` : ''}
            ${b.birth_time ? `<div class="info-item"><label>Heure</label><span>${b.birth_time.slice(0,5)}</span></div>` : ''}
            ${b.birth_weight ? `<div class="info-item"><label>Poids</label><span>${b.birth_weight} kg</span></div>` : ''}
            ${b.birth_height ? `<div class="info-item"><label>Taille</label><span>${b.birth_height} cm</span></div>` : ''}
            ${b.birth_details ? `<div class="info-item full"><label>Détails</label><span>${escHtml(b.birth_details)}</span></div>` : ''}
        </div>`;
    }

    function openBirthForm() {
        const b = state.baby || {};
        document.getElementById('birth-date').value = b.birth_date || '';
        document.getElementById('birth-time').value = b.birth_time ? b.birth_time.slice(0,5) : '';
        document.getElementById('birth-weight').value = b.birth_weight || '';
        document.getElementById('birth-height').value = b.birth_height || '';
        document.getElementById('birth-details').value = b.birth_details || '';
        openModal('modal-birth');
    }

    function saveBirth(e) {
        if (e) e.preventDefault();
        const payload = {
            name:          state.baby?.name || '',
            birth_date:    document.getElementById('birth-date').value,
            birth_time:    document.getElementById('birth-time').value,
            birth_weight:  document.getElementById('birth-weight').value,
            birth_height:  document.getElementById('birth-height').value,
            birth_details: document.getElementById('birth-details').value,
        };
        api(`/api/baby/${state.babyId}`, { method: 'POST', body: JSON.stringify(payload) })
            .then(res => {
                if (res.success) {
                    state.baby = res.baby;
                    updateBabyInSelect(res.baby);
                    loadBabyDetails();
                    renderBirthTab();
                    closeModal('modal-birth');
                }
            });
    }

    // ── Consultations tab ─────────────────────────────────────

    function loadConsultations() {
        api(`/api/baby/${state.babyId}/consultations`).then(list => {
            renderConsultations(list);
        });
    }

    function renderConsultations(list) {
        const el = document.getElementById('consultations-list');
        if (!list.length) {
            el.innerHTML = '<p class="text-muted">Aucune consultation enregistrée.</p>';
            return;
        }
        el.innerHTML = list.map(c => `
            <div class="consult-card">
                <div class="consult-header">
                    <strong>${formatDatetime(c.consult_at)}</strong>
                    ${c.practitioner ? `<span class="consult-practitioner">👨‍⚕️ ${escHtml(c.practitioner)}</span>` : ''}
                    <div class="consult-actions">
                        <button class="btn-icon-sm" onclick="BabyApp.editConsultation(${c.id})" title="Modifier">✏️</button>
                        <button class="btn-icon-sm" onclick="BabyApp.deleteConsultation(${c.id})" title="Supprimer">🗑️</button>
                    </div>
                </div>
                ${c.details ? `<p class="consult-details">${escHtml(c.details)}</p>` : ''}
            </div>`).join('');
    }

    function openConsultationForm(c) {
        state.editingConsultId = c ? c.id : null;
        document.getElementById('modal-consult-title').textContent = c ? 'Modifier la consultation' : 'Nouvelle consultation';
        document.getElementById('consult-at').value = c ? toDatetimeLocal(c.consult_at) : now();
        document.getElementById('consult-practitioner').value = c?.practitioner || '';
        document.getElementById('consult-details').value = c?.details || '';
        openModal('modal-consult');
    }

    function saveConsultation(e) {
        if (e) e.preventDefault();
        const payload = {
            consult_at:   document.getElementById('consult-at').value.replace('T', ' '),
            practitioner: document.getElementById('consult-practitioner').value,
            details:      document.getElementById('consult-details').value,
        };
        const url = state.editingConsultId
            ? `/api/baby/${state.babyId}/consultations/${state.editingConsultId}`
            : `/api/baby/${state.babyId}/consultations`;

        api(url, { method: 'POST', body: JSON.stringify(payload) }).then(res => {
            if (res.success) { closeModal('modal-consult'); loadConsultations(); }
        });
    }

    function editConsultation(id) {
        api(`/api/baby/${state.babyId}/consultations`).then(list => {
            const c = list.find(x => x.id == id);
            if (c) openConsultationForm(c);
        });
    }

    function deleteConsultation(id) {
        if (!confirm('Supprimer cette consultation ?')) return;
        api(`/api/baby/${state.babyId}/consultations/${id}/delete`, { method: 'POST' })
            .then(res => { if (res.success) loadConsultations(); });
    }

    // ── XSS helper ────────────────────────────────────────────

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Init ──────────────────────────────────────────────────

    function init() {
        toggleQuantity();
        // If babies exist, auto-select first one
        const sel = document.getElementById('baby-select');
        if (sel && sel.options.length > 1) {
            sel.selectedIndex = 1;
            selectBaby(sel.value);
        }
    }

    document.addEventListener('DOMContentLoaded', init);

    return {
        selectBaby, openAddBaby, openEditBaby, saveBaby, deleteBaby,
        uploadAvatar, doUploadAvatar,
        switchTab,
        prevDay, nextDay, loadTracking,
        quickAdd, quickAddSleep, toggleQuantity, saveEvent, editEvent, deleteEventItem,
        saveSleep, editSleep, stopSleep, deleteSleepItem,
        openPregnancyForm, calcDueDate, savePregnancy,
        openPregnancyConsultation, addImageField, previewImage, savePregnancyConsultation,
        deletePregConsult, deletePregnancyImage, openLightbox,
        openBirthForm, saveBirth,
        openConsultationForm, saveConsultation, editConsultation, deleteConsultation,
        closeModal,
    };
})();
