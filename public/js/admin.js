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
function renderSmtpSteps(r) {
    const box = document.getElementById('smtp-test-result');
    let html = '<div style="border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-top:.5rem">';
    for (const step of (r.steps || [])) {
        html += `<div style="display:flex;align-items:center;gap:.6rem;padding:.45rem .75rem;border-bottom:1px solid var(--border)">
            <span style="font-size:1rem">${step.ok ? '✅' : '❌'}</span>
            <strong style="min-width:140px;flex-shrink:0">${step.label}</strong>
            <code style="font-size:.75rem;color:var(--text-muted)">${step.detail}</code>
        </div>`;
    }
    html += '</div>';
    if (r.error) {
        html += `<p style="color:var(--danger);margin-top:.5rem;font-size:.85rem">❌ ${r.error}</p>`;
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
            : '<p style="color:var(--danger)">❌ ' + (data.error || 'Échec du test.') + '</p>';
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
