// ============================================
// FamilyBoard - Calendar JS (FullCalendar-free)
// ============================================

let currentDate = new Date();
let events = [];
let editingEventId = null;

// ── Robustesse réseau ────────────────────────────────────────────
// Erreur typée : distingue un problème réseau (serveur injoignable, hors-ligne, délai dépassé)
// d'une erreur serveur, pour afficher un message utile au lieu du « Load failed » brut du
// navigateur (libellé Safari/WebKit pour un échec de fetch non typé).
class CalendarLoadError extends Error {
    constructor(message, kind) {
        super(message);
        this.name = 'CalendarLoadError';
        this.kind = kind || 'network';
    }
}

// Identifie la requête d'événements la plus récente : si l'utilisateur change de mois pendant
// qu'une requête est en vol, la réponse périmée est ignorée (sinon un mois lointain pouvait
// écraser l'affichage du mois courant — cause d'« erreurs de navigation » sur réseau lent).
let _eventsReqSeq = 0;

// Filtres par membre (cases à cocher de la barre d'outils). Renvoie null si aucun filtre n'est
// actif (tous les membres cochés), auquel cas tout est affiché.
function checkedMemberIds() {
    const boxes = document.querySelectorAll('.calendar-filters input[data-member]');
    if (!boxes.length) return null;
    const ids = new Set();
    let all = true;
    boxes.forEach(b => {
        if (b.checked) ids.add(Number(b.dataset.member));
        else all = false;
    });
    return all ? null : ids;
}

// Un événement sans user_id (garde alternée, vacances scolaires, anniversaires…) n'est jamais
// masqué par le filtre par membre : seuls les événements rattachés à une personne le sont.
function isEventVisible(e, memberIds) {
    if (!memberIds) return true;
    const uid = e.extendedProps && e.extendedProps.user_id;
    if (uid === null || uid === undefined) return true;
    return memberIds.has(Number(uid));
}

// Tous les événements d'une date (filtre par membre appliqué), dans l'ordre du serveur.
function eventsForDate(dateStr, memberIds) {
    if (memberIds === undefined) memberIds = checkedMemberIds();
    return events.filter(e => {
        if (!isEventVisible(e, memberIds)) return false;
        const start = e.start.substring(0, 10);
        const end = (e.end || e.start).substring(0, 10);
        return dateStr >= start && dateStr <= end;
    });
}

// Action déclenchée au clic sur un événement, selon son type.
function eventClickAction(e) {
    const type = e.extendedProps && e.extendedProps.type;
    if (type === 'custody')  return `window.location='${BASE_URL}/custody'`;
    if (type === 'project')  return `window.location='${BASE_URL}/projects/${e.extendedProps.project_id}'`;
    if (type === 'birthday') return `window.location='${BASE_URL}/contacts'`;
    if (type === 'vacation') return `deleteVacationPrompt(${e.extendedProps.vacation_id})`;
    if (type === 'school_holiday') return '';
    return `openEventDetails(${JSON.stringify(e.id)})`;
}

// Préfixe d'icône d'un événement (garde alternée / projet) + indicateurs de partage.
function eventIconPrefix(e) {
    const type = e.extendedProps && e.extendedProps.type;
    return (type === 'custody' ? '👶 ' : type === 'project' ? '📋 ' : '') + eventShareIcon(e);
}

// Ligne d'événement réutilisée par l'agenda mobile et la fenêtre « journée » (desktop).
// `prefix` est un fragment JS optionnel exécuté avant l'action (ex. fermer la fenêtre).
function eventRowHtml(e, prefix) {
    const onClick = eventClickAction(e);
    const time = e.start && e.start.length > 10 ? fmtEventTime(e.start) : 'Toute la journée';
    return `<div class="cal-agenda-event" onclick="${prefix || ''}${onClick}" style="cursor:${onClick ? 'pointer' : 'default'}">` +
        `<span class="cal-agenda-dot" style="background:${safeColor(e.color)}"></span>` +
        `<div class="cal-agenda-info">` +
            `<div class="cal-agenda-name">${eventIconPrefix(e)}${escapeHtml(e.title)}</div>` +
            `<div class="cal-agenda-time">${escapeHtml(time)}${escapeHtml(eventShareTitleSuffix(e))}</div>` +
        `</div></div>`;
}

// fetch() avec délai maximal : sur réseau mobile une requête peut rester en suspens très
// longtemps sans jamais échouer, laissant le calendrier bloqué sur un état de chargement.
async function fetchEventsWithTimeout(url, ms) {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), ms);
    try {
        return await fetch(url, {
            signal: ctrl.signal,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        });
    } finally {
        clearTimeout(timer);
    }
}

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

// Icônes de partage cohérentes avec les autres modules : 🔒 co-parent, 🔗 invitation famille
// amie en attente, 🤝 famille amie ayant accepté (ou copie reçue d'une famille amie).
function eventShareIcon(e) {
    const props = e.extendedProps || {};
    let icon = '';
    if (props.custody_schedule_ids) icon += '🔒';
    if (props.shared_from_family_name) icon += '🤝';
    else if (props.share_status === 'pending') icon += '🔗';
    else if (props.share_status === 'accepted') icon += '🤝';
    return icon ? icon + ' ' : '';
}

function eventShareTitleSuffix(e) {
    const props = e.extendedProps || {};
    let parts = [];
    if (props.custody_schedule_ids) parts.push('partagé avec le co-parent');
    if (props.shared_from_family_name) parts.push(`reçu de la famille ${props.shared_from_family_name}`);
    else if (props.share_status === 'pending') parts.push('invitation famille amie en attente');
    else if (props.share_status === 'accepted') parts.push('partagé avec une famille amie');
    return parts.length ? ' — ' + parts.join(', ') : '';
}

// Simple calendar renderer
function renderCalendar() {
    const container = document.getElementById('calendar');
    if (!container) return; // la page courante n'affiche pas de calendrier
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();

    const dayNames = fmtDayNames();
    const memberIds = checkedMemberIds();

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

        const dayEvents = eventsForDate(dateStr, memberIds);

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
            const isCustody = e.extendedProps?.type === 'custody';
            const isSchool  = e.extendedProps?.type === 'school_holiday';
            const onClick = eventClickAction(e);
            const typeLabel = isCustody ? ' (Garde alternée)'
                : e.extendedProps?.type === 'project'  ? ' (Projet)'
                : e.extendedProps?.type === 'birthday' ? ' (Anniversaire)'
                : e.extendedProps?.type === 'vacation' ? ' — cliquer pour supprimer'
                : '';
            // Un seul attribut style (un doublon était ignoré par le parseur, laissant les
            // puces non cliquables) ; titre échappé dans son intégralité.
            html += `<div class="cal-event${isCustody ? ' cal-event-custody' : ''}"
                          style="background:${safeColor(e.color)};${isSchool ? 'opacity:.85;cursor:default' : 'cursor:pointer'}"
                          onclick="event.stopPropagation();${onClick}"
                          title="${escapeHtml(e.title + typeLabel + eventShareTitleSuffix(e))}">
                ${eventIconPrefix(e)}${escapeHtml(e.title)}
            </div>`;
        });
        // Au-delà de 3 événements, la puce « +N » ouvre la vue « journée » qui les liste tous
        // (auparavant elle était purement décorative : impossible de voir les événements cachés).
        if (dayEvents.length > 3) {
            html += `<button type="button" class="cal-more"
                          onclick="event.stopPropagation();openDayModal('${dateStr}')"
                          title="Voir les ${dayEvents.length} événements de cette journée">+${dayEvents.length - 3}</button>`;
        }

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

    const dayEvents = eventsForDate(dateStr);

    const d = new Date(dateStr + 'T12:00:00');
    const label = d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });

    let inner = `<div class="cal-agenda-header">
        <span class="cal-agenda-title">${label.charAt(0).toUpperCase() + label.slice(1)}</span>
        <button class="btn btn-primary btn-sm" onclick="openEventModal('${dateStr}')">+ Ajouter</button>
    </div>`;

    inner += dayEvents.length
        ? dayEvents.map(e => eventRowHtml(e)).join('')
        : `<p class="cal-agenda-empty">Aucun événement ce jour.</p>`;

    agenda.innerHTML = inner;
    agenda.style.display = 'block';
}

// Fenêtre « journée » (desktop) : liste TOUS les événements de la date, y compris ceux qui ne
// tiennent pas dans les 3 puces de la case, et permet d'en ajouter un.
function openDayModal(dateStr) {
    const list = document.getElementById('day-modal-list');
    if (!list) { openEventModal(dateStr); return; } // repli si le markup est absent

    const dayEvents = eventsForDate(dateStr);
    const d = new Date(dateStr + 'T12:00:00');
    const label = d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
    const title = document.getElementById('day-modal-title');
    if (title) title.textContent = label.charAt(0).toUpperCase() + label.slice(1);

    list.innerHTML = dayEvents.length
        ? dayEvents.map(e => eventRowHtml(e, "closeModal('day-modal');")).join('')
        : '<p class="cal-agenda-empty">Aucun événement ce jour.</p>';

    const addBtn = document.getElementById('day-modal-add');
    if (addBtn) {
        addBtn.onclick = function () { closeModal('day-modal'); openEventModal(dateStr); };
    }

    openModal('day-modal');
}

function eventsUrl() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const start = `${year}-${String(month+1).padStart(2,'0')}-01`;
    const end = `${year}-${String(month+1).padStart(2,'0')}-${new Date(year, month+1, 0).getDate()}`;
    const custody   = document.getElementById('custody-toggle')?.checked ? 1 : 0;
    const projects  = document.getElementById('projects-toggle')?.checked ? 1 : 0;
    const vacations = document.getElementById('vacations-toggle')?.checked ? 1 : 0;
    const school    = document.getElementById('school-toggle')?.checked ? 1 : 0;
    return `${BASE_URL}/api/calendar/events?start=${start}&end=${end}&custody=${custody}&projects=${projects}&vacations=${vacations}&school=${school}`;
}

function calendarSetLoading() {
    const container = document.getElementById('calendar');
    if (!container) return;
    container.innerHTML =
        '<div class="cal-wrapper cal-loading">' +
        '<span class="cal-spinner" aria-hidden="true"></span>' +
        '<span>Chargement du calendrier…</span>' +
        '</div>';
}

function calendarShowError(message) {
    const container = document.getElementById('calendar');
    if (!container) return;
    container.innerHTML =
        '<div class="empty-state-card cal-error">' +
        '<div class="empty-icon">⚠️</div>' +
        '<h3>Calendrier indisponible</h3>' +
        '<p>' + escapeHtml(message) + '</p>' +
        '<button class="btn btn-primary btn-sm" onclick="loadEvents()">Réessayer</button>' +
        '</div>';
}

async function loadEvents() {
    const container = document.getElementById('calendar');
    if (!container) return; // hors page calendrier (ex. autre module réutilisant ce script)

    const seq = ++_eventsReqSeq;
    const url = eventsUrl();

    // On ne montre le squelette qu'au premier chargement : lors d'un changement de mois, la
    // grille précédente reste affichée jusqu'à l'arrivée des nouvelles données (pas de flash).
    if (!container.querySelector('.cal-wrapper')) calendarSetLoading();

    for (let attempt = 0; attempt < 2; attempt++) {
        try {
            const r = await fetchEventsWithTimeout(url, 20000);

            // Une session expirée renvoie la page de connexion en HTML avec un code 200 :
            // sans ce garde-fou, r.json() échouerait avec une erreur incompréhensible.
            const contentType = r.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                throw new CalendarLoadError('Réponse inattendue du serveur.', 'server');
            }

            const data = await r.json();

            if (!r.ok || (data && !Array.isArray(data) && data.error)) {
                const detail = data && (data.message || data.error);
                const kind = r.status === 503 ? 'offline' : 'server';
                throw new CalendarLoadError(detail || ('Erreur serveur ' + r.status), kind);
            }
            if (!Array.isArray(data)) {
                throw new CalendarLoadError('Réponse invalide du calendrier.', 'server');
            }

            if (seq !== _eventsReqSeq) return; // un chargement plus récent a pris la main

            events = data;
            renderCalendar();
            return;
        } catch (err) {
            // Chargement remplacé par une navigation plus récente : on abandonne sans bruit.
            if (seq !== _eventsReqSeq) return;

            const aborted = err && err.name === 'AbortError';
            const kind = aborted ? 'network' : (err && err.kind) || 'network';
            const message = err && err.message ? err.message : 'Erreur inconnue';

            // Un seul réessai automatique : réseau instable ou délai dépassé (transitoires).
            if (attempt === 0 && (kind === 'network')) continue;

            if (kind === 'offline') {
                calendarShowError("Vous semblez hors ligne : les événements n'ont pas pu être récupérés.");
            } else if (kind === 'network') {
                calendarShowError(aborted
                    ? 'Le serveur met trop de temps à répondre. Vérifiez votre connexion puis réessayez.'
                    : 'Impossible de joindre le serveur. Vérifiez votre connexion puis réessayez.');
            } else {
                calendarShowError("Le calendrier n'a pas pu être chargé. Réessayez ; si le problème persiste, un signalement a été envoyé au support.");
            }
            reportClientError('Échec du chargement du calendrier : ' + message, { file: 'calendar.js', kind });
            return;
        }
    }
}

// Navigation mensuelle : on repart toujours du 1er du mois. Sans cela, `setMonth` conservait
// le jour courant (ex. le 31) et débordait sur le mois suivant (31 janv. → 3 mars), faisant
// sauter un mois entier — cause classique d'« erreur de navigation » en fin de mois.
function prevMonth() { currentDate = new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1); loadEvents(); }
function nextMonth() { currentDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 1); loadEvents(); }
function goToday() {
    const now = new Date();
    currentDate = new Date(now.getFullYear(), now.getMonth(), 1);
    loadEvents();
}

function handleDayClick(dateStr) {
    if (window.innerWidth <= 768) {
        renderMobileAgenda(dateStr);
        return;
    }
    // Desktop : un jour qui contient déjà des événements ouvre la vue « journée » (on y voit
    // tout, même au-delà des 3 puces affichées, et on peut en ajouter) ; un jour vide ouvre
    // directement le formulaire d'ajout (création rapide préservée).
    if (eventsForDate(dateStr).length) {
        openDayModal(dateStr);
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
    const employmentToggle = document.getElementById('event-employment-toggle');
    if (employmentToggle) {
        employmentToggle.checked = false;
        document.getElementById('event-employment-select-wrap').style.display = 'none';
        document.getElementById('event-employment-leave-type').value = 'paid_leave';
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
        if (employmentToggle) {
            const profId = eventData.extendedProps?.employment_profile_id;
            employmentToggle.checked = !!profId;
            document.getElementById('event-employment-select-wrap').style.display = profId ? '' : 'none';
            if (profId) {
                document.getElementById('event-employment-profile').value = profId;
                document.getElementById('event-employment-leave-type').value = eventData.extendedProps?.employment_leave_type || 'paid_leave';
            }
        }
    }
    const statusEl = document.getElementById('event-share-status');
    if (statusEl) {
        const suffix = eventData ? eventShareTitleSuffix(eventData) : '';
        if (suffix) {
            statusEl.textContent = eventShareIcon(eventData).trim() + suffix.replace(' — ', ' ');
            statusEl.style.display = '';
        } else {
            statusEl.style.display = 'none';
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
    const employmentToggle = document.getElementById('event-employment-toggle');
    if (employmentToggle) {
        data.employment_profile_id = employmentToggle.checked ? parseInt(document.getElementById('event-employment-profile').value, 10) : null;
        data.employment_leave_type = employmentToggle.checked ? document.getElementById('event-employment-leave-type').value : null;
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
        // loadEvents() ne recharge que le mois actuellement affiché : sans ceci, un événement
        // ajouté pour un mois futur/passé semble "disparaître" (enregistré en base, mais hors
        // de la vue courante) jusqu'à ce qu'on navigue manuellement jusque-là.
        const newEventDate = tzDate(data.start_datetime);
        currentDate = new Date(newEventDate.getFullYear(), newEventDate.getMonth(), 1);
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

async function syncCalDAV(id, btn) {
    // L'ancien code lisait `event.currentTarget` (variable globale `window.event`, obsolète et
    // non fiable). On reçoit désormais le bouton explicitement depuis l'attribut onclick.
    btn = btn || (window.event && window.event.currentTarget) || null;
    const original = btn ? btn.textContent : '';
    if (btn) btn.textContent = '⏳';
    try {
        const result = await apiFetch(`${BASE_URL}/api/calendar/caldav/${id}/sync`, { method: 'POST' });
        if (result.success) {
            loadEvents();
            if (btn) btn.textContent = '🔄';
            if (result.count === 0) {
                Dialog.alert('Synchronisation effectuée mais aucun événement trouvé.\n\nVérifiez :\n• L\'URL (doit être une URL .ics publique ou avec identifiants)\n• Les identifiants si requis\n• Que le calendrier contient des événements', 'Aucun événement');
            }
        } else {
            if (btn) btn.textContent = '❌';
            Dialog.alert('Échec de la synchronisation : ' + (result.error || 'Erreur inconnue'), 'Erreur');
        }
    } catch (err) {
        if (btn) btn.textContent = original || '🔄';
        reportClientError('Échec de la synchronisation CalDAV : ' + (err && err.message ? err.message : 'erreur réseau'), { file: 'calendar.js', kind: 'network' });
        Dialog.alert('Impossible de joindre le serveur pour synchroniser ce calendrier.', 'Erreur');
    }
}

async function deleteCalDAV(id) {
    if (!await Dialog.confirm('Supprimer ce calendrier CalDAV et ses événements ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/calendar/caldav/${id}/delete`, { method: 'POST' });
    if (result.success) location.reload();
}

function filterCalendar() {
    // Filtrage purement côté client (les événements du mois sont déjà chargés) : réafficher
    // la grille immédiatement, sans nouvelle requête réseau.
    renderCalendar();
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
    let r;
    try {
        r = await apiFetch(BASE_URL + '/api/calendar/share-invitations');
    } catch (err) {
        // Hors-ligne ou session expirée : le panneau d'invitations est optionnel.
        return;
    }
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
