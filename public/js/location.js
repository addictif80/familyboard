// ---- Position (check-in ponctuel) ----

function getPosition() {
    return new Promise((resolve, reject) => {
        if (!('geolocation' in navigator)) {
            reject(new Error("La géolocalisation n'est pas supportée par ce navigateur."));
            return;
        }
        navigator.geolocation.getCurrentPosition(
            pos => resolve(pos.coords),
            err => reject(new Error('Impossible d\'obtenir votre position : ' + err.message)),
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 }
        );
    });
}

async function shareMyLocation() {
    const status = document.getElementById('loc-share-status');
    status.textContent = '⏳ Localisation en cours…';
    try {
        const coords = await getPosition();
        const duration = parseInt(document.getElementById('loc-duration').value, 10);
        const note = document.getElementById('loc-note').value.trim();
        const r = await apiFetch(BASE_URL + '/api/location/checkin', {
            method: 'POST',
            body: JSON.stringify({ lat: coords.latitude, lng: coords.longitude, duration_minutes: duration, note }),
        });
        if (r.success) {
            status.textContent = r.place ? `✅ Position partagée (près de ${r.place.name}).` : '✅ Position partagée.';
            loadCurrentLocations();
        } else {
            status.textContent = '❌ ' + (r.error || 'Erreur.');
        }
    } catch (e) {
        status.textContent = '❌ ' + e.message;
    }
}

async function clearMyLocation() {
    const status = document.getElementById('loc-share-status');
    await apiFetch(BASE_URL + '/api/location/clear', { method: 'POST' });
    status.textContent = 'Partage arrêté.';
    loadCurrentLocations();
}

async function loadCurrentLocations() {
    const list = document.getElementById('loc-current-list');
    const r = await apiFetch(BASE_URL + '/api/location/current');
    const current = r.current || [];
    if (!current.length) {
        list.innerHTML = '<p style="color:var(--text-muted);font-size:.85rem">Personne ne partage sa position actuellement.</p>';
        return;
    }
    list.innerHTML = current.map(c => `
        <div class="member-item">
            ${avatarHtml(c.user_avatar, c.user_color, c.user_name)}
            <div class="member-info">
                <strong>${escapeHtml(c.user_name)}</strong>
                <small>${c.place_name ? '📍 ' + escapeHtml(c.place_name) : 'Position partagée'} · ${formatTime(c.created_at)}${c.note ? ' · ' + escapeHtml(c.note) : ''}</small>
            </div>
        </div>
    `).join('');
}

async function addPlaceHere() {
    const input = document.getElementById('loc-place-name');
    const name = input.value.trim();
    if (!name) { Dialog.toast('Entrez un nom de lieu.', 'error'); return; }
    try {
        const coords = await getPosition();
        const r = await apiFetch(BASE_URL + '/api/location/places', {
            method: 'POST',
            body: JSON.stringify({ name, lat: coords.latitude, lng: coords.longitude }),
        });
        if (r.success) {
            input.value = '';
            const list = document.getElementById('loc-places-list');
            const empty = list.querySelector('p');
            if (empty) empty.remove();
            const div = document.createElement('div');
            div.className = 'member-item';
            div.dataset.placeId = r.place.id;
            div.innerHTML = `
                <div class="member-info">
                    <strong>${escapeHtml(r.place.name)}</strong>
                    <small>Rayon : ${r.place.radius_m} m</small>
                </div>
                <button class="btn btn-danger btn-sm" onclick="deletePlace(${r.place.id})">Retirer</button>
            `;
            list.appendChild(div);
            Dialog.toast('Lieu ajouté !');
        } else {
            Dialog.toast(r.error || 'Erreur.', 'error');
        }
    } catch (e) {
        Dialog.toast(e.message, 'error');
    }
}

async function deletePlace(id) {
    const ok = await Dialog.confirm('Retirer ce lieu favori ?');
    if (!ok) return;
    await apiFetch(BASE_URL + '/api/location/places/' + id + '/delete', { method: 'POST' });
    document.querySelector(`[data-place-id="${id}"]`)?.remove();
}

loadCurrentLocations();
setInterval(loadCurrentLocations, 60000);
