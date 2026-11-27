// Espace additions — invité externe sans compte (accès via lien magique).

async function guestRespond(participantId, decision) {
    const r = await apiFetch(BASE_URL + '/additions/espace/' + GUEST_TOKEN + '/participants/' + participantId + '/respond', {
        method: 'POST', body: JSON.stringify({ decision }),
    });
    if (r.success) location.reload();
    else Dialog.toast('Erreur.', 'error');
}
