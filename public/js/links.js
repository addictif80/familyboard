// ---- Portail de liens ----

function openLinkModal() {
    document.getElementById('link-url').value = '';
    document.getElementById('link-title').value = '';
    document.getElementById('link-description').value = '';
    document.getElementById('link-coparent').checked = false;
    document.getElementById('link-modal-error').style.display = 'none';
    openModal('link-modal');
}

async function submitLink() {
    const url = document.getElementById('link-url').value.trim();
    const errorEl = document.getElementById('link-modal-error');
    errorEl.style.display = 'none';
    if (!url) {
        errorEl.textContent = 'Le lien est requis.';
        errorEl.style.display = 'block';
        return;
    }

    const btn = document.getElementById('link-modal-submit');
    const originalLabel = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Vérification du lien…';

    const payload = {
        url,
        title: document.getElementById('link-title').value.trim(),
        description: document.getElementById('link-description').value.trim(),
        visible_to_coparent: document.getElementById('link-coparent').checked,
    };
    const r = await apiFetch(BASE_URL + '/api/links', { method: 'POST', body: JSON.stringify(payload) });

    btn.disabled = false;
    btn.textContent = originalLabel;

    if (r.success) {
        closeModal('link-modal');
        Dialog.toast(r.status === 'approved' ? 'Lien ajouté !' : 'Proposition envoyée, en attente de validation.');
        setTimeout(() => window.location.reload(), 600);
    } else {
        errorEl.textContent = r.error || 'Erreur.';
        errorEl.style.display = 'block';
        btn.textContent = 'Réessayer';
    }
}

async function approveLink(id) {
    const r = await apiFetch(BASE_URL + '/api/links/' + id + '/approve', { method: 'POST', body: '{}' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function rejectLink(id) {
    const ok = await Dialog.confirm('Refuser ce lien proposé ?');
    if (!ok) return;
    // window.prompt() reste le seul moyen simple de saisir un texte libre optionnel ici —
    // Dialog (composant maison) n'a pas d'équivalent "prompt", seulement alert/confirm/toast.
    const reason = window.prompt('Motif du refus (optionnel, visible par la personne qui a proposé le lien) :') || '';
    const r = await apiFetch(BASE_URL + '/api/links/' + id + '/reject', { method: 'POST', body: JSON.stringify({ reason }) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deleteLink(id) {
    const ok = await Dialog.confirm('Supprimer ce lien du portail ?');
    if (!ok) return;
    const r = await apiFetch(BASE_URL + '/api/links/' + id + '/delete', { method: 'POST', body: '{}' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}
