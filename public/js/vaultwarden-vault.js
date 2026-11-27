// ============================================
// Coffre-fort de mots de passe (Vaultwarden) géré directement dans FamilyBoard — voir bw-crypto.js
// pour la cryptographie et VaultwardenProxyController pour le relais serveur. Rien de ceci n'est
// jamais envoyé au serveur FamilyBoard en clair : seul le navigateur dérive la clé maître et
// (dé)chiffre le contenu.
// ============================================

let _bwState = null; // { accessToken, encKey, macKey, userId, items: [...] } — mémoire uniquement

function bwProxyUrl(path) {
    return `${BASE_URL}/api/vaultwarden/${path}`;
}

async function bwOpenUnlockModal() {
    document.getElementById('bw-unlock-error').textContent = '';
    document.getElementById('bw-unlock-password').value = '';
    document.getElementById('bw-unlock-kdf-warning').style.display = 'none';
    document.getElementById('bw-unlock-password-group').style.display = 'none';
    document.getElementById('bw-unlock-submit').style.display = 'none';
    openModal('bw-unlock-modal');

    // La vérification du type de KDF ne nécessite que l'adresse e-mail (pas le mot de passe) —
    // on peut donc prévenir l'utilisateur AVANT de lui demander son mot de passe maître si son
    // compte utilise Argon2id (non pris en charge ici pour l'instant).
    try {
        const pre = await fetch(bwProxyUrl('prelogin'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ email: BW_USER_EMAIL }),
        }).then(r => r.json());

        if (!BwCrypto.isKdfSupported(pre.kdf)) {
            document.getElementById('bw-unlock-kdf-warning').style.display = '';
            return;
        }
        document.getElementById('bw-unlock-password-group').style.display = '';
        document.getElementById('bw-unlock-submit').style.display = '';
        document.getElementById('bw-unlock-password').focus();
        bwOpenUnlockModal._iterations = pre.kdfIterations;
    } catch (e) {
        document.getElementById('bw-unlock-error').textContent = 'Instance Vaultwarden injoignable.';
    }
}

async function bwUnlock() {
    const password = document.getElementById('bw-unlock-password').value;
    const errEl = document.getElementById('bw-unlock-error');
    errEl.textContent = '';
    if (!password) return;
    const btn = document.getElementById('bw-unlock-submit');
    btn.disabled = true;
    btn.textContent = 'Déverrouillage…';
    try {
        const iterations = bwOpenUnlockModal._iterations;
        const masterKey = await BwCrypto.deriveMasterKey(password, BW_USER_EMAIL, iterations);
        const { encKey: stretchEnc, macKey: stretchMac } = await BwCrypto.stretchKey(masterKey);
        const serverHash = await BwCrypto.deriveServerHash(masterKey, password);

        const tokenBody = new URLSearchParams({
            grant_type: 'password', username: BW_USER_EMAIL, password: serverHash,
            scope: 'api offline_access', client_id: 'web',
            deviceType: '21', deviceIdentifier: bwDeviceId(), deviceName: 'familyboard',
        });
        const tokenRes = await fetch(bwProxyUrl('token'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: tokenBody.toString(),
        });
        const tokenJson = await tokenRes.json();
        if (!tokenRes.ok || !tokenJson.access_token) {
            errEl.textContent = 'Mot de passe maître incorrect.';
            console.error('Vaultwarden token error:', tokenJson);
            return;
        }
        if (!tokenJson.Key) {
            errEl.textContent = 'Ce compte nécessite la double authentification — non pris en charge ici, ouvrez le lien externe.';
            return;
        }

        const { encKey, macKey } = await BwCrypto.decryptUserKey(tokenJson.Key, stretchEnc, stretchMac);
        const payload = JSON.parse(atob(tokenJson.access_token.split('.')[1]));

        _bwState = { accessToken: tokenJson.access_token, encKey, macKey, userId: payload.sub, items: [] };
        closeModal('bw-unlock-modal');
        document.getElementById('bw-locked-panel').style.display = 'none';
        document.getElementById('bw-unlocked-panel').style.display = '';
        await bwSync();
        document.getElementById('bw-search')?.focus();
    } catch (e) {
        errEl.textContent = 'Erreur inattendue — réessayez.';
        console.error(e);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Déverrouiller';
    }
}

function bwDeviceId() {
    const key = 'fb_bw_device_id';
    let id = localStorage.getItem(key);
    if (!id) { id = crypto.randomUUID(); try { localStorage.setItem(key, id); } catch (_) {} }
    return id;
}

function bwLock() {
    _bwState = null;
    document.getElementById('bw-locked-panel').style.display = '';
    document.getElementById('bw-unlocked-panel').style.display = 'none';
    document.getElementById('bw-items-list').innerHTML = '';
    const search = document.getElementById('bw-search');
    if (search) search.value = '';
}

async function bwSync() {
    if (!_bwState) return;
    const res = await fetch(bwProxyUrl('sync'), { headers: { Authorization: 'Bearer ' + _bwState.accessToken, 'X-Requested-With': 'XMLHttpRequest' } });
    if (!res.ok) { bwLock(); return; }
    const data = await res.json();
    const ciphers = (data.ciphers || data.Ciphers || []).filter(c => (c.type ?? c.Type) === 1 && !(c.deletedDate ?? c.DeletedDate));

    const items = [];
    for (const c of ciphers) {
        let itemEnc = _bwState.encKey, itemMac = _bwState.macKey;
        const cipherKey = c.key ?? c.Key;
        if (cipherKey) {
            const unwrapped = await BwCrypto.decryptUserKey(cipherKey, _bwState.encKey, _bwState.macKey);
            itemEnc = unwrapped.encKey; itemMac = unwrapped.macKey;
        }
        const login = c.login ?? c.Login ?? {};
        items.push({
            id: c.id ?? c.Id,
            name: await BwCrypto.decryptEncStringUtf8(c.name ?? c.Name, itemEnc, itemMac),
            username: await BwCrypto.decryptEncStringUtf8(login.username ?? login.Username, itemEnc, itemMac),
            password: await BwCrypto.decryptEncStringUtf8(login.password ?? login.Password, itemEnc, itemMac),
        });
    }
    items.sort((a, b) => a.name.localeCompare(b.name));
    _bwState.items = items;
    // Conserve la recherche en cours après un ajout/modification/suppression (bwSync() est aussi
    // appelé depuis ces actions) plutôt que de revenir silencieusement à la liste complète.
    bwRenderItems(document.getElementById('bw-search')?.value || '');
}

/** Minuscules + sans accents, pour qu'une recherche "impots" trouve "impôts.gouv.fr" — le contenu
 *  n'existant qu'en mémoire du navigateur (jamais indexé côté serveur, voir plus bas), ce filtrage
 *  est aussi la seule recherche possible sur ce contenu : la recherche globale de FamilyBoard
 *  (barre du haut) ne peut pas et ne pourra jamais porter sur le coffre, par construction —
 *  l'inclure impliquerait d'envoyer le contenu déchiffré au serveur. */
function bwNormalize(s) {
    return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

function bwHighlight(text, query) {
    const safe = escapeHtml(text || '');
    if (!query) return safe;
    const normText = bwNormalize(text || '');
    const normQuery = bwNormalize(query);
    const idx = normText.indexOf(normQuery);
    if (idx === -1) return safe;
    // Les indices sont calculés sur la version normalisée mais découpent le texte ORIGINAL — valide
    // ici car les diacritiques retirés par normalize('NFD') ne changent pas la longueur en code units
    // pour le français (lettres accentuées latines, pas d'émoji/combinants multi-unités dans ces champs).
    return escapeHtml(text.slice(0, idx)) + '<mark>' + escapeHtml(text.slice(idx, idx + query.length)) + '</mark>' + escapeHtml(text.slice(idx + query.length));
}

function bwFilterItems() {
    bwRenderItems(document.getElementById('bw-search').value);
}

function bwRenderItems(query = '') {
    const list = document.getElementById('bw-items-list');
    const searchBox = document.getElementById('bw-search-wrap');
    if (searchBox) searchBox.style.display = _bwState.items.length ? '' : 'none';

    if (!_bwState.items.length) {
        list.innerHTML = '<p class="empty-state">Aucun identifiant enregistré.</p>';
        return;
    }

    const normQuery = bwNormalize(query);
    const matches = _bwState.items
        .map((it, idx) => ({ it, idx }))
        .filter(({ it }) => !normQuery || bwNormalize(it.name).includes(normQuery) || bwNormalize(it.username).includes(normQuery));

    if (!matches.length) {
        list.innerHTML = `<p class="empty-state">Aucun identifiant ne correspond à « ${escapeHtml(query)} ».</p>`;
        return;
    }

    list.innerHTML = matches.map(({ it, idx }) => `
        <div class="card" style="padding:.75rem 1rem;margin-bottom:.5rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
            <div style="flex:1;min-width:160px">
                <strong>${bwHighlight(it.name || '(sans nom)', query)}</strong>
                ${it.username ? `<div style="font-size:.82rem;color:var(--text-muted)">${bwHighlight(it.username, query)}</div>` : ''}
            </div>
            <div style="display:flex;align-items:center;gap:.4rem">
                <input type="password" readonly value="${escapeHtml(it.password)}" id="bw-pw-${idx}" style="width:160px;font-family:monospace" onclick="this.select()">
                <button class="btn-icon-sm" title="Afficher/masquer" onclick="bwTogglePw(${idx})">👁️</button>
                <button class="btn-icon-sm" title="Copier le mot de passe" onclick="bwCopy('${idx}')">📋</button>
            </div>
            <div>
                <button class="btn-icon-sm" title="Modifier" onclick="bwOpenEditModal(${idx})">✏️</button>
                <button class="btn-icon-sm" title="Supprimer" onclick="bwDeleteItem(${idx})">🗑</button>
            </div>
        </div>
    `).join('');
}

function bwTogglePw(idx) {
    const el = document.getElementById(`bw-pw-${idx}`);
    el.type = el.type === 'password' ? 'text' : 'password';
}
function bwCopy(idx) {
    const el = document.getElementById(`bw-pw-${idx}`);
    navigator.clipboard.writeText(el.value).then(() => Dialog.toast('Copié.', 'success'));
}

function bwOpenNewItemModal() {
    document.getElementById('bw-item-modal-title').textContent = 'Nouvel identifiant';
    document.getElementById('bw-item-idx').value = '';
    document.getElementById('bw-item-name').value = '';
    document.getElementById('bw-item-username').value = '';
    document.getElementById('bw-item-password').value = '';
    openModal('bw-item-modal');
}

function bwOpenEditModal(idx) {
    const it = _bwState.items[idx];
    document.getElementById('bw-item-modal-title').textContent = 'Modifier l\'identifiant';
    document.getElementById('bw-item-idx').value = idx;
    document.getElementById('bw-item-name').value = it.name;
    document.getElementById('bw-item-username').value = it.username;
    document.getElementById('bw-item-password').value = it.password;
    openModal('bw-item-modal');
}

async function bwSaveItem() {
    const name = document.getElementById('bw-item-name').value.trim();
    if (!name) { Dialog.toast('Le nom est requis.', 'error'); return; }
    const username = document.getElementById('bw-item-username').value;
    const password = document.getElementById('bw-item-password').value;
    const idxStr = document.getElementById('bw-item-idx').value;

    const encName = await BwCrypto.encryptEncStringUtf8(name, _bwState.encKey, _bwState.macKey);
    const encUser = await BwCrypto.encryptEncStringUtf8(username, _bwState.encKey, _bwState.macKey);
    const encPass = await BwCrypto.encryptEncStringUtf8(password, _bwState.encKey, _bwState.macKey);
    const body = JSON.stringify({
        type: 1, name: encName, notes: null, favorite: false, encryptedFor: _bwState.userId,
        login: { username: encUser, password: encPass, uris: [] },
    });

    let res;
    if (idxStr === '') {
        res = await fetch(bwProxyUrl('ciphers'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + _bwState.accessToken, 'X-Requested-With': 'XMLHttpRequest' },
            body,
        });
    } else {
        const id = _bwState.items[parseInt(idxStr, 10)].id;
        res = await fetch(bwProxyUrl(`ciphers/${id}/update`), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + _bwState.accessToken, 'X-Requested-With': 'XMLHttpRequest' },
            body,
        });
    }
    if (!res.ok) { Dialog.toast('Erreur lors de l\'enregistrement.', 'error'); return; }
    closeModal('bw-item-modal');
    await bwSync();
}

async function bwDeleteItem(idx) {
    const ok = await Dialog.confirm('Supprimer cet identifiant du coffre-fort ?');
    if (!ok) return;
    const id = _bwState.items[idx].id;
    const res = await fetch(bwProxyUrl(`ciphers/${id}/delete`), {
        method: 'POST', headers: { Authorization: 'Bearer ' + _bwState.accessToken, 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!res.ok) { Dialog.toast('Erreur lors de la suppression.', 'error'); return; }
    await bwSync();
}
