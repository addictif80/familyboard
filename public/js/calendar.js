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
                `<span class="cal-day-dot" style="background:${safeColor(e.color)}"></span>`
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
                          style="background:${safeColor(e.color)};${isSchool ? 'opacity:.85' : ''}"
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
                <span class="cal-agenda-dot" style="background:${safeColor(e.color)}"></span>
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
        .then(r => {
            if (!r.ok) throw new Error('Réponse serveur ' + r.status + ' lors du chargement du calendrier');
            return r.json();
        })
        .then(data => {
            if (!Array.isArray(data)) throw new Error('Réponse invalide du calendrier (pas un tableau)');
            events = data;
            renderCalendar();
        })
        .catch(err => {
            reportClientError('Échec du chargement du calendrier : ' + err.message, { file: 'calendar.js', line: 199 });
            const container = document.getElementById('calendar');
            if (container) {
                container.innerHTML = '<div class="empty-state-card"><p>⚠️ Le calendrier n\'a pas pu être chargé. Rechargez la page ; si le problème persiste, un signalement a été envoyé au support.</p></div>';
            }
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
    document.getElementById('event-professional').value = '';
    document.getElementById('event-location-input').value = '';
    document.getElementById('event-location-lat').value = '';
    document.getElementById('event-location-lng').value = '';
    document.getElementById('event-more-info').open = false;
    hideLocationPreview();
    const custodyToggle = document.getElementById('event-custody-toggle');
    if (custodyToggle) {
        custodyToggle.checked = false;
        document.getElementById('event-custody-select-wrap').style.display = 'none';
        document.querySelectorAll('.event-custody-child-cb').forEach(cb => { cb.checked = false; });
    }
    const shareWrap = document.getElementById('event-share-wrap');
    if (shareWrap) {
        // Uniquement à la création — un événement déjà partagé ne peut plus être ré-invité
        // depuis ce formulaire (voir la note sous la liste des familles).
        shareWrap.style.display = eventData ? 'none' : '';
        document.getElementById('event-share-toggle').checked = false;
        document.getElementById('event-share-select-wrap').style.display = 'none';
        document.querySelectorAll('.event-share-family-cb').forEach(cb => { cb.checked = false; });
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
        const professionalName = eventData.extendedProps?.professional_name || '';
        const location = eventData.extendedProps?.location || '';
        const locationLat = eventData.extendedProps?.location_lat || '';
        const locationLng = eventData.extendedProps?.location_lng || '';
        document.getElementById('event-professional').value = professionalName;
        document.getElementById('event-location-input').value = location;
        document.getElementById('event-location-lat').value = locationLat;
        document.getElementById('event-location-lng').value = locationLng;
        document.getElementById('event-more-info').open = !!(professionalName || location);
        if (location && locationLat && locationLng) {
            updateLocationPreview(location, locationLat, locationLng);
        } else if (location) {
            geocodeAndPreview(location);
        } else {
            hideLocationPreview();
        }
        if (custodyToggle) {
            const csIds = (eventData.extendedProps?.custody_schedule_ids || '').toString().split(',').filter(Boolean).map(Number);
            custodyToggle.checked = csIds.length > 0;
            document.getElementById('event-custody-select-wrap').style.display = csIds.length ? '' : 'none';
            document.querySelectorAll('.event-custody-child-cb').forEach(cb => {
                cb.checked = csIds.includes(parseInt(cb.value, 10));
            });
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
        professional_name: document.getElementById('event-professional').value.trim() || null,
        location: document.getElementById('event-location-input').value.trim() || null,
        location_lat: document.getElementById('event-location-lat').value || null,
        location_lng: document.getElementById('event-location-lng').value || null,
    };
    const custodyToggle = document.getElementById('event-custody-toggle');
    if (custodyToggle && custodyToggle.checked) {
        data.custody_schedule_ids = Array.from(document.querySelectorAll('.event-custody-child-cb:checked')).map(cb => parseInt(cb.value, 10));
    }
    const shareToggle = document.getElementById('event-share-toggle');
    if (shareToggle && shareToggle.checked) {
        data.share_family_ids = Array.from(document.querySelectorAll('.event-share-family-cb:checked')).map(cb => parseInt(cb.value, 10));
    }

    const id = document.getElementById('event-id').value;
    const url = id ? `${BASE_URL}/api/calendar/events/${id}` : `${BASE_URL}/api/calendar/events`;
    const result = await apiFetch(url, { method: 'POST', body: JSON.stringify(data) });

    if (result.success) {
        closeModal('event-modal');
        loadEvents();
    } else {
        Dialog.toast(result.error || 'Erreur lors de l\'enregistrement.', 'error');
    }
}

async function deleteEvent() {
    const id = document.getElementById('event-id').value;
    if (!id || !await Dialog.confirm('Supprimer cet événement ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/calendar/events/${id}/delete`, { method: 'POST' });
    if (result.success) {
        closeModal('event-modal');
        loadEvents();
    } else {
        Dialog.toast(result.error || 'Erreur lors de la suppression.', 'error');
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

// ── Event location: map preview + Google Maps / Waze links ─────
function hideLocationPreview() {
    const preview = document.getElementById('event-location-preview');
    if (preview) preview.style.display = 'none';
}

function updateLocationPreview(address, lat, lng) {
    const preview = document.getElementById('event-location-preview');
    if (!preview) return;
    lat = parseFloat(lat);
    lng = parseFloat(lng);
    if (isNaN(lat) || isNaN(lng)) { hideLocationPreview(); return; }

    const delta = 0.004;
    const bbox = [lng - delta, lat - delta, lng + delta, lat + delta].join(',');
    document.getElementById('event-location-map').src =
        `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&marker=${lat},${lng}&layer=mapnik`;
    document.getElementById('event-location-gmaps').href =
        `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
    document.getElementById('event-location-waze').href =
        `https://waze.com/ul?ll=${lat},${lng}&navigate=yes`;
    preview.style.display = '';
}

async function geocodeAndPreview(address) {
    try {
        const url = 'https://nominatim.openstreetmap.org/search?format=json&countrycodes=fr&limit=1&q=' + encodeURIComponent(address);
        const res = await fetch(url);
        const data = await res.json();
        if (data && data[0]) {
            document.getElementById('event-location-lat').value = data[0].lat;
            document.getElementById('event-location-lng').value = data[0].lon;
            updateLocationPreview(address, data[0].lat, data[0].lon);
        } else {
            hideLocationPreview();
        }
    } catch (_) { hideLocationPreview(); }
}

// ── Address autocomplete (event location, France uniquement) ───
(function () {
    const input = document.getElementById('event-location-input');
    const list  = document.getElementById('event-location-list');
    if (!input) return;
    const latInput = document.getElementById('event-location-lat');
    const lngInput = document.getElementById('event-location-lng');

    let timer = null;
    let activeIdx = -1;

    input.addEventListener('input', () => {
        clearTimeout(timer);
        // L'adresse a changé manuellement : les coordonnées mémorisées ne sont plus valables.
        latInput.value = '';
        lngInput.value = '';
        hideLocationPreview();
        const q = input.value.trim();
        if (q.length < 3) { hide(); return; }
        timer = setTimeout(() => fetchAddresses(q), 350);
    });

    input.addEventListener('keydown', e => {
        const items = list.querySelectorAll('li');
        if (!items.length) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive(activeIdx + 1, items); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(activeIdx - 1, items); }
        else if (e.key === 'Enter' && activeIdx >= 0) { e.preventDefault(); items[activeIdx].click(); }
        else if (e.key === 'Escape') { hide(); }
    });

    document.addEventListener('click', e => {
        if (!e.target.closest('#event-location-wrap')) hide();
    });

    async function fetchAddresses(q) {
        try {
            const url = 'https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=6&countrycodes=fr&q=' + encodeURIComponent(q);
            const res  = await fetch(url);
            const data = await res.json();
            render(data || []);
        } catch (_) { hide(); }
    }

    // Construit un libellé lisible : "12 Rue de la Paix" / "75002 Paris"
    // plutôt que d'afficher tel quel le display_name brut de Nominatim.
    function formatResult(r) {
        const a = r.address || {};
        const street = [a.house_number, a.road].filter(Boolean).join(' ');
        const main = street || a.neighbourhood || a.suburb || a.village || r.display_name.split(',')[0].trim();
        const city = a.city || a.town || a.village || a.municipality || '';
        const sub = [a.postcode, city].filter(Boolean).join(' ');
        return { main, sub: sub || a.state || '' };
    }

    function render(results) {
        if (!results.length) { hide(); return; }
        activeIdx = -1;
        list.innerHTML = '';
        results.forEach(r => {
            const { main, sub } = formatResult(r);
            const li = document.createElement('li');
            li.className = 'city-ac-item';
            li.innerHTML = '<span class="city-ac-name">' + esc(main) + '</span>' +
                           '<span class="city-ac-sub">' + esc(sub) + '</span>';
            li.addEventListener('mousedown', e => {
                e.preventDefault();
                select(r.display_name, r.lat, r.lon);
            });
            list.appendChild(li);
        });
        list.style.display = 'block';
    }

    function select(address, lat, lon) {
        input.value = address;
        latInput.value = lat;
        lngInput.value = lon;
        updateLocationPreview(address, lat, lon);
        hide();
    }

    function setActive(idx, items) {
        items.forEach(i => i.classList.remove('active'));
        activeIdx = Math.max(0, Math.min(idx, items.length - 1));
        items[activeIdx].classList.add('active');
    }

    function hide() { list.style.display = 'none'; activeIdx = -1; }

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
})();

// ── Partage inter-familles ───────────────────────────────────────
async function loadShareInvitations() {
    const panel = document.getElementById('share-invitations-panel');
    if (!panel) return;
    const r = await apiFetch(BASE_URL + '/api/calendar/share-invitations');
    if (!r || (!r.invitations && !r.changes)) return;

    const parts = [];

    (r.invitations || []).forEach(inv => {
        const when = inv.is_all_day
            ? new Date(inv.start_datetime).toLocaleDateString('fr-FR')
            : new Date(inv.start_datetime.replace(' ', 'T')).toLocaleString('fr-FR');
        parts.push(
            '<div class="card share-alert-card" style="padding:.85rem 1rem;margin-bottom:.5rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">' +
            '<span style="flex:1"><strong>' + esc(inv.origin_family_name) + '</strong> vous invite à « ' + esc(inv.title) + ' » — ' + esc(when) + '</span>' +
            '<button class="btn btn-primary btn-sm" onclick="respondShareInvitation(' + inv.id + `, 'accept')">Accepter</button>` +
            '<button class="btn btn-secondary btn-sm" onclick="respondShareInvitation(' + inv.id + `, 'decline')">Refuser</button>` +
            '</div>'
        );
    });

    (r.changes || []).forEach(ch => {
        const label = ch.change_type === 'delete' ? 'a supprimé' : 'a modifié';
        const payload = ch.payload ? JSON.parse(ch.payload) : {};
        const title = payload.title || '';
        parts.push(
            '<div class="card share-alert-card" style="padding:.85rem 1rem;margin-bottom:.5rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">' +
            '<span style="flex:1"><strong>' + esc(ch.origin_family_name) + '</strong> ' + label + ' un événement partagé' + (title ? (' « ' + esc(title) + ' »') : '') + '</span>' +
            '<button class="btn btn-primary btn-sm" onclick="resolveShareChange(' + ch.id + `, 'accept')">Accepter</button>` +
            '<button class="btn btn-secondary btn-sm" onclick="resolveShareChange(' + ch.id + `, 'decline')">Refuser</button>` +
            '</div>'
        );
    });

    panel.innerHTML = parts.join('');
}

async function respondShareInvitation(id, decision) {
    const r = await apiFetch(BASE_URL + '/api/calendar/share-invitations/' + id + '/' + decision, { method: 'POST' });
    if (r.success) {
        Dialog.toast(decision === 'accept' ? 'Invitation acceptée.' : 'Invitation refusée.');
        loadShareInvitations();
        loadEvents();
    } else {
        Dialog.toast('Erreur.', 'error');
    }
}

async function resolveShareChange(id, decision) {
    let reason = null;
    if (decision === 'decline') {
        reason = prompt('Motif du refus (optionnel) :') || '';
    }
    const r = await apiFetch(BASE_URL + '/api/calendar/share-changes/' + id + '/resolve', {
        method: 'POST', body: JSON.stringify({ decision, reason }),
    });
    if (r.success) {
        Dialog.toast('Réponse enregistrée.');
        loadShareInvitations();
        loadEvents();
    } else {
        Dialog.toast('Erreur.', 'error');
    }
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Init
loadEvents();
loadShareInvitations();
