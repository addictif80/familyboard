// ---- Theme (clair / sombre) ----
function toggleTheme() {
    const root = document.documentElement;
    const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('fb-theme', next); } catch {}
}

// Auto-dismiss alerts after 4s
document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .5s'; setTimeout(() => el.remove(), 500); }, 4000);
});

// ---- SMTP test (global config) ----
// La réponse brute d'un serveur SMTP distant (banner, EHLO, AUTH...) est affichée telle quelle
// dans cette page — un serveur malveillant ou une interception réseau pourrait y glisser du HTML.
function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderSmtpSteps(r) {
    const box = document.getElementById('smtp-test-result');
    let html = '<div style="border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-top:.5rem">';
    for (const step of (r.steps || [])) {
        html += `<div style="display:flex;align-items:center;gap:.6rem;padding:.45rem .75rem;border-bottom:1px solid var(--border)">
            <span style="font-size:1rem">${step.ok ? '✅' : '❌'}</span>
            <strong style="min-width:140px;flex-shrink:0">${escapeHtml(step.label)}</strong>
            <code style="font-size:.75rem;color:var(--text-muted)">${escapeHtml(step.detail)}</code>
        </div>`;
    }
    html += '</div>';
    if (r.error) {
        html += `<p style="color:var(--danger);margin-top:.5rem;font-size:.85rem">❌ ${escapeHtml(r.error)}</p>`;
    } else if (r.ok) {
        html += '<p style="color:var(--success);margin-top:.5rem;font-size:.85rem">✅ Succès.</p>';
    }
    box.innerHTML = html;
}

async function testMeteoFranceKey() {
    const box = document.getElementById('meteofrance-test-result');
    box.innerHTML = '<p style="color:var(--text-muted)">⏳ Test en cours…</p>';
    try {
        const res = await fetch(BASE_URL + '/admin/meteofrance-key/test', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        box.innerHTML = data.ok
            ? '<p style="color:var(--success)">✅ Clé valide, l\'API Vigilance a répondu.</p>'
            : '<p style="color:var(--danger)">❌ ' + escapeHtml(data.error || 'Échec du test.') + '</p>';
    } catch {
        box.innerHTML = '<p style="color:var(--danger)">Erreur réseau.</p>';
    }
}

function confirmUserDelete(form, name) {
    const purgeAll = form.querySelector('input[name="purge_all"]').checked;
    const msg = purgeAll
        ? `Supprimer DÉFINITIVEMENT le compte de ${name} ET toutes ses données, y compris celles normalement conservées (documents, événements, photos…) ? Une copie de ses données lui sera envoyée par e-mail avant suppression, avec le motif. Cette action est totalement irréversible.`
        : `Supprimer le compte de ${name} ? Un e-mail avec le motif et une copie de ses données lui sera envoyé. Le contenu créé est conservé, seul le compte disparaît.`;
    return confirm(msg);
}

async function testVaultwarden() {
    const box = document.getElementById('vaultwarden-test-result');
    box.innerHTML = '<p style="color:var(--text-muted)">⏳ Test en cours…</p>';
    try {
        const res = await fetch(BASE_URL + '/admin/vaultwarden/test', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        box.innerHTML = data.ok
            ? '<p style="color:var(--success)">✅ Connexion réussie.</p>'
            : '<p style="color:var(--danger)">❌ ' + escapeHtml(data.error || 'Échec du test.') + '</p>';
    } catch {
        box.innerHTML = '<p style="color:var(--danger)">Erreur réseau.</p>';
    }
}

async function testOllama() {
    const box = document.getElementById('ollama-test-result');
    box.innerHTML = '<p style="color:var(--text-muted)">⏳ Test en cours…</p>';
    try {
        const res = await fetch(BASE_URL + '/admin/ollama/test', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        box.innerHTML = data.ok
            ? '<p style="color:var(--success)">✅ Connexion réussie.</p>'
            : '<p style="color:var(--danger)">❌ ' + escapeHtml(data.error || 'Échec du test.') + '</p>';
    } catch {
        box.innerHTML = '<p style="color:var(--danger)">Erreur réseau.</p>';
    }
}

async function testOneMinAi() {
    const box = document.getElementById('onemin-test-result');
    box.innerHTML = '<p style="color:var(--text-muted)">⏳ Test en cours…</p>';
    try {
        const res = await fetch(BASE_URL + '/admin/onemin/test', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        box.innerHTML = data.ok
            ? '<p style="color:var(--success)">✅ Connexion réussie.</p>'
            : '<p style="color:var(--danger)">❌ ' + escapeHtml(data.error || 'Échec du test.') + '</p>';
    } catch {
        box.innerHTML = '<p style="color:var(--danger)">Erreur réseau.</p>';
    }
}

async function testGithub() {
    const box = document.getElementById('github-test-result');
    box.innerHTML = '<p style="color:var(--text-muted)">⏳ Test en cours…</p>';
    try {
        const res = await fetch(BASE_URL + '/admin/github/test', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        box.innerHTML = data.ok
            ? '<p style="color:var(--success)">✅ Connexion réussie, droits d\'écriture confirmés.</p>'
            : '<p style="color:var(--danger)">❌ ' + escapeHtml(data.error || 'Échec du test.') + '</p>';
    } catch {
        box.innerHTML = '<p style="color:var(--danger)">Erreur réseau.</p>';
    }
}

async function checkPlatformUpdate() {
    const box = document.getElementById('platform-update-result');
    const btn = document.getElementById('platform-update-btn');
    btn.style.display = 'none';
    box.innerHTML = '<p style="color:var(--text-muted)">⏳ Vérification en cours…</p>';
    try {
        const res = await fetch(BASE_URL + '/admin/platform/update-status', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        if (data.fetch_error) {
            box.innerHTML = '<p style="color:var(--danger)">❌ Impossible de contacter le dépôt distant : ' + escapeHtml(data.fetch_error) + '</p>';
            return;
        }
        let html = '<p style="font-size:.85rem;color:var(--text-muted)">Branche <code>' + escapeHtml(data.branch || '?') + '</code> — ' + escapeHtml(data.commit || '?') + '</p>';
        if (data.dirty) {
            html += '<p style="color:var(--danger)">⚠️ Des modifications non commitées existent sur le serveur — la mise à jour sera refusée tant qu\'elles ne sont pas résolues.</p>';
        } else if (data.behind > 0) {
            html += '<p style="color:var(--warn,#b45309)">🔄 ' + data.behind + ' commit(s) en attente.</p>';
            btn.style.display = '';
        } else {
            html += '<p style="color:var(--success)">✅ À jour.</p>';
        }
        box.innerHTML = html;
    } catch {
        box.innerHTML = '<p style="color:var(--danger)">Erreur réseau.</p>';
    }
}

async function applyPlatformUpdate() {
    if (!confirm('Mettre à jour le serveur maintenant (git pull) ? La page va potentiellement afficher du code différent après coup — relancez un test après coup.')) return;
    const box = document.getElementById('platform-update-result');
    const btn = document.getElementById('platform-update-btn');
    btn.disabled = true;
    box.innerHTML = '<p style="color:var(--text-muted)">⏳ Mise à jour en cours… (peut prendre jusqu\'à une minute si composer doit réinstaller des dépendances)</p>';
    try {
        const res = await fetch(BASE_URL + '/admin/platform/update', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        box.innerHTML = (data.success
            ? '<p style="color:var(--success)">✅ Mise à jour appliquée.</p>'
            : '<p style="color:var(--danger)">❌ Échec de la mise à jour.</p>')
            + '<pre style="white-space:pre-wrap;background:var(--bg-alt,#1e1e1e);color:#ddd;padding:.75rem;border-radius:6px;font-size:.78rem;max-height:300px;overflow:auto">' + escapeHtml(data.output || '') + '</pre>';
    } catch {
        box.innerHTML = '<p style="color:var(--danger)">Erreur réseau.</p>';
    } finally {
        btn.disabled = false;
    }
}

async function testStorageConnection(kind) {
    const box = document.getElementById('storage-' + kind + '-test-result');
    box.innerHTML = '<p style="color:var(--text-muted)">⏳ Test en cours…</p>';
    try {
        const res = await fetch(BASE_URL + '/admin/storage/' + kind + '/test', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        box.innerHTML = data.ok
            ? '<p style="color:var(--success)">✅ Connexion réussie.</p>'
            : '<p style="color:var(--danger)">❌ ' + escapeHtml(data.error || 'Échec du test.') + '</p>';
    } catch {
        box.innerHTML = '<p style="color:var(--danger)">Erreur réseau.</p>';
    }
}

async function testStorageLdapAccount() {
    const username = document.getElementById('storage-ldap-account-username').value.trim();
    const box = document.getElementById('storage-ldap-account-test-result');
    if (!username) { box.innerHTML = '<p style="color:var(--danger)">Nom de compte requis.</p>'; return; }
    box.innerHTML = '<p style="color:var(--text-muted)">⏳ Recherche en cours…</p>';
    try {
        const res = await fetch(BASE_URL + '/admin/storage/ldap/test-account', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'username=' + encodeURIComponent(username),
        });
        const data = await res.json();
        if (!data.ok) {
            box.innerHTML = '<p style="color:var(--danger)">❌ ' + escapeHtml(data.error || 'Échec du test.') + '</p>';
        } else if (!data.found) {
            box.innerHTML = '<p style="color:var(--text-muted)">Aucun compte LDAP "' + escapeHtml(username) + '" trouvé.</p>';
        } else {
            box.innerHTML = '<p style="color:var(--success)">✅ Compte trouvé — uidNumber ' + escapeHtml(String(data.uidNumber)) +
                ', e-mail ' + escapeHtml(data.mail || '—') +
                ', membre du groupe : ' + (data.inGroup ? 'oui' : 'non') + '.</p>';
        }
    } catch {
        box.innerHTML = '<p style="color:var(--danger)">Erreur réseau.</p>';
    }
}

async function testSmtp() {
    const box = document.getElementById('smtp-test-result');
    box.innerHTML = '<p style="color:var(--text-muted)">⏳ Test en cours…</p>';
    try {
        const res = await fetch(BASE_URL + '/admin/smtp/test', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        renderSmtpSteps(await res.json());
    } catch {
        box.innerHTML = '<p style="color:var(--danger)">Erreur réseau.</p>';
    }
}

async function sendTestSmtpEmail() {
    const email = document.getElementById('smtp-test-email').value.trim();
    const box = document.getElementById('smtp-test-result');
    if (!email) { box.innerHTML = '<p style="color:var(--danger)">Entrez une adresse email.</p>'; return; }
    box.innerHTML = '<p style="color:var(--text-muted)">⏳ Envoi en cours…</p>';
    try {
        const res = await fetch(BASE_URL + '/admin/smtp/send-test', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ email }),
        });
        renderSmtpSteps(await res.json());
    } catch {
        box.innerHTML = '<p style="color:var(--danger)">Erreur réseau.</p>';
    }
}
