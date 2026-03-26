// ============================================
// FamilyBoard - Contacts JS
// ============================================

let _editingContactId = null;
let _pendingAvatarFile = null;

// ── Search ─────────────────────────────────
function filterContacts() {
    const q = document.getElementById('contact-search').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.contact-card');
    let visible = 0;
    cards.forEach(card => {
        const match = !q || card.dataset.search.includes(q);
        card.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    const empty = document.getElementById('contacts-empty-search');
    if (empty) empty.style.display = visible === 0 && q ? '' : 'none';
}

// ── View modal ──────────────────────────────
function openViewModal(contact) {
    const body   = document.getElementById('contact-view-body');
    const footer = document.getElementById('contact-view-footer');

    const initial = (contact.first_name || '?')[0].toUpperCase();
    const fullName = [contact.first_name, contact.last_name].filter(Boolean).join(' ');
    const mapsUrl  = contact.address
        ? 'https://maps.google.com/maps?q=' + encodeURIComponent(contact.address)
        : null;

    let birthdayFmt = '';
    if (contact.birthday) {
        const [y, m, d] = contact.birthday.split('-');
        birthdayFmt = `${d}/${m}/${y}`;
    }

    const avatarHtml = contact.avatar
        ? `<img src="${BASE_URL}${contact.avatar}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`
        : initial;

    body.innerHTML = `
        <div style="display:flex;align-items:center;gap:1.2rem;margin-bottom:1.2rem">
            <div class="contact-avatar contact-avatar-lg" style="background:${contact.color}">${avatarHtml}</div>
            <div>
                <div style="font-size:1.3rem;font-weight:700">${esc(fullName)}</div>
            </div>
        </div>
        <div class="contact-detail-list">
            ${contact.phone  ? `<div class="contact-detail-row"><span class="cd-icon">📞</span><a href="tel:${esc(contact.phone)}">${esc(contact.phone)}</a></div>` : ''}
            ${contact.phone2 ? `<div class="contact-detail-row"><span class="cd-icon">📞</span><a href="tel:${esc(contact.phone2)}">${esc(contact.phone2)}</a><span class="cd-label">Tél. 2</span></div>` : ''}
            ${contact.email  ? `<div class="contact-detail-row"><span class="cd-icon">✉️</span><a href="mailto:${esc(contact.email)}">${esc(contact.email)}</a></div>` : ''}
            ${contact.address ? `<div class="contact-detail-row"><span class="cd-icon">📍</span>${mapsUrl ? `<a href="${mapsUrl}" target="_blank" rel="noopener">${esc(contact.address).replace(/\n/g,'<br>')}</a>` : esc(contact.address).replace(/\n/g,'<br>')}</div>` : ''}
            ${contact.birthday ? `<div class="contact-detail-row"><span class="cd-icon">🎂</span>${esc(birthdayFmt)}</div>` : ''}
            ${contact.notes  ? `<div class="contact-detail-row"><span class="cd-icon">📝</span><span style="white-space:pre-wrap">${esc(contact.notes)}</span></div>` : ''}
        </div>
    `;

    footer.innerHTML = contact.is_system
        ? `<a href="tel:${esc(contact.phone)}" class="btn btn-primary">📞 Appeler</a>`
        : `<a href="${BASE_URL}/contacts/${contact.id}/vcard" class="btn btn-secondary btn-sm" title="Télécharger la fiche vCard">📲 vCard</a>
           <button class="btn btn-secondary" onclick="closeModal('contact-view-modal');openContactModal(${JSON.stringify(contact).replace(/</g,'\\u003c')})">✏️ Modifier</button>
           <button class="btn btn-danger"    onclick="closeModal('contact-view-modal');deleteContact(${contact.id})">🗑 Supprimer</button>`;

    openModal('contact-view-modal');
}

// ── Edit / Create modal ─────────────────────
function openContactModal(contact) {
    _editingContactId = contact ? contact.id : null;
    _pendingAvatarFile = null;

    document.getElementById('contact-edit-title').textContent = contact ? 'Modifier le contact' : 'Nouveau contact';
    document.getElementById('ce-id').value        = contact ? contact.id : '';
    document.getElementById('ce-firstname').value = contact ? contact.first_name : '';
    document.getElementById('ce-lastname').value  = contact ? contact.last_name  : '';
    document.getElementById('ce-phone').value     = contact ? (contact.phone  || '') : '';
    document.getElementById('ce-phone2').value    = contact ? (contact.phone2 || '') : '';
    document.getElementById('ce-email').value     = contact ? (contact.email  || '') : '';
    document.getElementById('ce-address').value   = contact ? (contact.address || '') : '';
    document.getElementById('ce-birthday').value  = contact ? (contact.birthday || '') : '';
    document.getElementById('ce-notes').value     = contact ? (contact.notes  || '') : '';
    document.getElementById('ce-color').value     = contact ? contact.color : '#4A90D9';
    document.getElementById('ce-avatar-file').value = '';

    const preview = document.getElementById('ce-avatar-preview');
    preview.style.background = contact ? contact.color : '#4A90D9';

    const initial = document.getElementById('ce-avatar-initial');
    if (contact && contact.avatar) {
        preview.innerHTML = `<img src="${BASE_URL}${contact.avatar}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
    } else {
        const letter = (contact ? contact.first_name : '') || '?';
        preview.innerHTML = `<span id="ce-avatar-initial">${letter[0].toUpperCase()}</span>`;
    }

    openModal('contact-edit-modal');
}

function updateAvatarInitial() {
    const v = document.getElementById('ce-firstname').value;
    const el = document.getElementById('ce-avatar-initial');
    if (el) el.textContent = v ? v[0].toUpperCase() : '?';
}

function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    _pendingAvatarFile = input.files[0];
    const reader = new FileReader();
    reader.onload = e => {
        const preview = document.getElementById('ce-avatar-preview');
        preview.innerHTML = `<img src="${e.target.result}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
    };
    reader.readAsDataURL(_pendingAvatarFile);
}

async function saveContact() {
    const firstName = document.getElementById('ce-firstname').value.trim();
    if (!firstName) { Dialog.toast('Prénom requis.', 'error'); return; }

    const id   = document.getElementById('ce-id').value;
    const data = {
        first_name: firstName,
        last_name:  document.getElementById('ce-lastname').value.trim(),
        phone:      document.getElementById('ce-phone').value.trim()   || null,
        phone2:     document.getElementById('ce-phone2').value.trim()  || null,
        email:      document.getElementById('ce-email').value.trim()   || null,
        address:    document.getElementById('ce-address').value.trim() || null,
        birthday:   document.getElementById('ce-birthday').value       || null,
        notes:      document.getElementById('ce-notes').value.trim()   || null,
        color:      document.getElementById('ce-color').value,
    };

    let result;
    if (id) {
        result = await apiFetch(`${BASE_URL}/api/contacts/${id}`, { method: 'POST', body: JSON.stringify(data) });
    } else {
        result = await apiFetch(`${BASE_URL}/api/contacts`, { method: 'POST', body: JSON.stringify(data) });
    }

    if (!result.success) { Dialog.toast(result.error || 'Erreur', 'error'); return; }

    // Upload avatar if chosen
    if (_pendingAvatarFile && result.contact) {
        const fd = new FormData();
        fd.append('avatar', _pendingAvatarFile);
        const avRes = await fetch(`${BASE_URL}/api/contacts/${result.contact.id}/avatar`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
        }).then(r => r.json());
        if (avRes.success) result.contact.avatar = avRes.avatar;
    }

    closeModal('contact-edit-modal');
    _pendingAvatarFile = null;

    // Update DOM
    if (id) {
        updateContactInDom(result.contact);
    } else {
        appendContactToGrid(result.contact);
    }
}

async function deleteContact(id) {
    if (!await Dialog.confirm('Supprimer ce contact ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/contacts/${id}/delete`, { method: 'POST' });
    if (result.success) {
        const el = document.querySelector(`.contact-card[data-id="${id}"]`);
        if (el) el.remove();
        checkGridEmpty();
    }
}

// ── DOM helpers ─────────────────────────────
function buildContactCard(c) {
    const initial  = (c.first_name || '?')[0].toUpperCase();
    const fullName = [c.first_name, c.last_name].filter(Boolean).join(' ');
    const searchStr = (fullName + ' ' + (c.email || '') + ' ' + (c.phone || '')).toLowerCase();

    const avatarInner = c.avatar
        ? `<img src="${BASE_URL}${c.avatar}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`
        : initial;

    const div = document.createElement('div');
    div.className = 'contact-card';
    div.dataset.id     = c.id;
    div.dataset.search = searchStr;
    div.onclick = () => openViewModal(c);

    div.innerHTML = `
        <div class="contact-avatar" style="background:${c.color}">${avatarInner}</div>
        <div class="contact-info">
            <div class="contact-name">${esc(fullName)}</div>
            ${c.phone ? `<div class="contact-line">📞 <a href="tel:${esc(c.phone)}" onclick="event.stopPropagation()">${esc(c.phone)}</a></div>` : ''}
            ${c.email ? `<div class="contact-line">✉️ <a href="mailto:${esc(c.email)}" onclick="event.stopPropagation()">${esc(c.email)}</a></div>` : ''}
        </div>
    `;
    return div;
}

function appendContactToGrid(contact) {
    let grid = document.getElementById('contacts-grid');
    if (!grid) {
        // First contact: remove empty state and create grid
        document.querySelector('.empty-state-full')?.remove();
        const page = document.querySelector('.contacts-page');
        grid = document.createElement('div');
        grid.className = 'contacts-grid';
        grid.id = 'contacts-grid';
        page.appendChild(grid);
        // Add empty search message if it doesn't exist
        if (!document.getElementById('contacts-empty-search')) {
            const p = document.createElement('p');
            p.id = 'contacts-empty-search';
            p.style.cssText = 'display:none;text-align:center;color:var(--text-muted);margin-top:2rem';
            p.textContent = 'Aucun résultat.';
            page.appendChild(p);
        }
    }
    grid.appendChild(buildContactCard(contact));
}

function updateContactInDom(contact) {
    const old = document.querySelector(`.contact-card[data-id="${contact.id}"]`);
    if (old) old.replaceWith(buildContactCard(contact));
}

function checkGridEmpty() {
    const grid = document.getElementById('contacts-grid');
    if (grid && grid.children.length === 0) location.reload();
}

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
