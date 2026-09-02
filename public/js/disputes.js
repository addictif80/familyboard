// ============================================
// FamilyBoard - Dossiers de litige
// ============================================

function openNewDisputeModal() {
    document.getElementById('dispute-modal-title').textContent = 'Nouveau dossier';
    document.getElementById('dispute-id').value = '';
    document.getElementById('dispute-title').value = '';
    document.getElementById('dispute-opposing-party').value = '';
    document.getElementById('dispute-start-date').value = '';
    document.getElementById('dispute-details').value = '';
    openModal('dispute-modal');
}

function openEditDisputeModal() {
    if (!SELECTED_DISPUTE) return;
    document.getElementById('dispute-modal-title').textContent = 'Modifier le dossier';
    document.getElementById('dispute-id').value = SELECTED_DISPUTE.id;
    document.getElementById('dispute-title').value = SELECTED_DISPUTE.title;
    document.getElementById('dispute-opposing-party').value = SELECTED_DISPUTE.opposing_party;
    document.getElementById('dispute-start-date').value = SELECTED_DISPUTE.start_date;
    document.getElementById('dispute-details').value = SELECTED_DISPUTE.details || '';
    openModal('dispute-modal');
}

async function saveDispute() {
    const title = document.getElementById('dispute-title').value.trim();
    const opposingParty = document.getElementById('dispute-opposing-party').value.trim();
    const startDate = document.getElementById('dispute-start-date').value;
    if (!title || !opposingParty || !startDate) {
        Dialog.toast('Titre, partie adverse et date de début sont requis.', 'error');
        return;
    }
    const payload = {
        title,
        opposing_party: opposingParty,
        start_date: startDate,
        details: document.getElementById('dispute-details').value,
    };
    const id = document.getElementById('dispute-id').value;
    const url = id ? `${BASE_URL}/api/disputes/${id}` : `${BASE_URL}/api/disputes`;
    const r = await apiFetch(url, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) {
        window.location.href = `${BASE_URL}/disputes?id=${id || r.id}`;
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function toggleDisputeStatus() {
    if (!SELECTED_DISPUTE) return;
    const newStatus = SELECTED_DISPUTE.status === 'open' ? 'closed' : 'open';
    const r = await apiFetch(`${BASE_URL}/api/disputes/${DISPUTE_ID}/status`, { method: 'POST', body: JSON.stringify({ status: newStatus }) });
    if (r.success) window.location.reload();
}

async function deleteDispute() {
    const ok = await Dialog.confirm('Supprimer ce dossier et toutes ses pièces jointes ? Cette action est irréversible.');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/disputes/${DISPUTE_ID}/delete`, { method: 'POST' });
    if (r.success) window.location.href = `${BASE_URL}/disputes`;
}

// ---- Pièces jointes ----

async function uploadDisputeDocument() {
    const input = document.getElementById('dispute-doc-file');
    const file = input.files[0];
    if (!file) { Dialog.toast('Choisissez un fichier.', 'error'); return; }
    const formData = new FormData();
    formData.append('file', file);
    const res = await fetch(`${BASE_URL}/api/disputes/${DISPUTE_ID}/documents`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
    });
    const r = await res.json();
    if (r.success) {
        window.location.reload();
    } else {
        Dialog.toast(r.error || 'Erreur lors de l\'envoi.', 'error');
    }
}

async function deleteDisputeDocument(docId) {
    const ok = await Dialog.confirm('Supprimer cette pièce jointe ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/disputes/${DISPUTE_ID}/documents/${docId}/delete`, { method: 'POST' });
    if (r.success) {
        const el = document.querySelector(`[data-doc-id="${docId}"]`);
        if (el) el.remove();
    }
}

// ---- Traçabilité des échanges ----

document.addEventListener('DOMContentLoaded', () => {
    const typeSelect = document.getElementById('exchange-type');
    if (!typeSelect) return;
    typeSelect.addEventListener('change', () => {
        document.getElementById('exchange-contact-label').textContent = EXCHANGE_CONTACT_LABELS[typeSelect.value];
        document.getElementById('exchange-contact').placeholder = {
            telephone: '06 12 34 56 78',
            email: 'contact@exemple.fr',
            courrier: '12 rue de la Mairie, 75000 Paris',
        }[typeSelect.value];
    });
});

async function addDisputeExchange() {
    const type = document.getElementById('exchange-type').value;
    const contactInfo = document.getElementById('exchange-contact').value.trim();
    const exchangeDate = document.getElementById('exchange-date').value;
    if (!contactInfo || !exchangeDate) {
        Dialog.toast('Coordonnée et date sont requises.', 'error');
        return;
    }
    const payload = {
        type,
        contact_info: contactInfo,
        exchange_date: exchangeDate,
        notes: document.getElementById('exchange-notes').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/disputes/${DISPUTE_ID}/exchanges`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) {
        window.location.reload();
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function deleteDisputeExchange(exchangeId) {
    const ok = await Dialog.confirm('Supprimer cet échange ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/disputes/${DISPUTE_ID}/exchanges/${exchangeId}/delete`, { method: 'POST' });
    if (r.success) {
        const el = document.querySelector(`[data-exchange-id="${exchangeId}"]`);
        if (el) el.remove();
    }
}

// ---- Partage public ----

function showDisputeShareLink(share) {
    const container = document.getElementById('dispute-share-qr-container');
    container.innerHTML = '';
    const qr = qrcode(0, 'M');
    qr.addData(share.url);
    qr.make();
    container.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 4 });
    document.getElementById('dispute-share-link-text').textContent = share.url;
    document.getElementById('dispute-share-open-link').href = share.url;
    document.getElementById('dispute-share-copy-btn').onclick = () => copyCode(share.url);
    openModal('share-dispute-modal');
}

async function openShareModal() {
    const r = await apiFetch(`${BASE_URL}/api/disputes/${DISPUTE_ID}/share`, { method: 'POST', body: '{}' });
    if (r.success) showDisputeShareLink(r.share);
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function regenerateDisputeShare() {
    const ok = await Dialog.confirm('Régénérer le lien ? L\'ancien lien cessera immédiatement de fonctionner.');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/disputes/${DISPUTE_ID}/share/regenerate`, { method: 'POST', body: '{}' });
    if (r.success) showDisputeShareLink(r.share);
}

async function revokeDisputeShare() {
    const ok = await Dialog.confirm('Révoquer ce lien de partage ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/disputes/${DISPUTE_ID}/share/revoke`, { method: 'POST' });
    if (r.success) {
        closeModal('share-dispute-modal');
        Dialog.toast('Lien révoqué.');
    }
}
