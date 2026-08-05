// ============================================
// Module Additions (partage de frais + cagnotte)
// ============================================

function openAdditionModal(isCagnotte) {
    document.getElementById('addition-modal-title').textContent = isCagnotte ? 'Nouvelle cagnotte' : 'Nouvelle addition';
    document.getElementById('addition-is-cagnotte').value = isCagnotte ? '1' : '0';
    document.getElementById('addition-title').value = '';
    document.getElementById('addition-description').value = '';
    document.getElementById('addition-total-amount').value = '';
    document.getElementById('addition-split-mode').value = 'percentage';
    document.getElementById('addition-cagnotte-goal').value = '';
    document.getElementById('addition-cagnotte-iban').value = '';
    document.getElementById('addition-cagnotte-wero').value = '';
    document.querySelectorAll('.addition-cagnotte-method').forEach(cb => { cb.checked = false; });

    document.getElementById('addition-amount-wrap').style.display = isCagnotte ? 'none' : '';
    document.getElementById('addition-split-wrap').style.display = isCagnotte ? 'none' : '';
    document.getElementById('addition-cagnotte-fields').style.display = isCagnotte ? '' : 'none';

    openModal('addition-modal');
}

async function saveAddition() {
    const title = document.getElementById('addition-title').value.trim();
    if (!title) { Dialog.toast('Le titre est requis.', 'error'); return; }
    const isCagnotte = document.getElementById('addition-is-cagnotte').value === '1';

    const data = {
        title,
        description: document.getElementById('addition-description').value.trim(),
        is_cagnotte: isCagnotte ? 1 : 0,
    };
    if (isCagnotte) {
        data.cagnotte_goal = document.getElementById('addition-cagnotte-goal').value || null;
        data.cagnotte_methods = Array.from(document.querySelectorAll('.addition-cagnotte-method:checked')).map(cb => cb.value);
        data.cagnotte_iban = document.getElementById('addition-cagnotte-iban').value.trim();
        data.cagnotte_wero_phone = document.getElementById('addition-cagnotte-wero').value.trim();
    } else {
        data.total_amount = document.getElementById('addition-total-amount').value;
        data.split_mode = document.getElementById('addition-split-mode').value;
    }

    const r = await apiFetch(BASE_URL + '/api/additions', { method: 'POST', body: JSON.stringify(data) });
    if (r.success) {
        closeModal('addition-modal');
        location.reload();
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function deleteAddition(id) {
    if (!confirm('Supprimer cette addition ? Cette action est irréversible.')) return;
    const r = await apiFetch(BASE_URL + '/api/additions/' + id + '/delete', { method: 'POST' });
    if (r.success) location.reload();
    else Dialog.toast('Erreur.', 'error');
}

function openInviteModal(additionId) {
    document.getElementById('invite-addition-id').value = additionId;
    document.getElementById('invite-user-select').value = '';
    document.getElementById('invite-email').value = '';
    document.getElementById('invite-name').value = '';
    document.getElementById('invite-share-value').value = '';
    document.getElementById('invite-modal-error').style.display = 'none';

    const card = document.querySelector('[data-addition-id="' + additionId + '"]');
    const splitMode = (card && card.dataset.splitMode) || 'percentage';
    const isCagnotte = card && card.dataset.isCagnotte === '1';
    document.getElementById('invite-share-unit').textContent = splitMode === 'percentage' ? '(%)' : '(€)';
    document.getElementById('invite-share-wrap').style.display = isCagnotte ? 'none' : '';

    openModal('invite-modal');
}

async function submitInvite() {
    const additionId = document.getElementById('invite-addition-id').value;
    const userId = document.getElementById('invite-user-select').value;
    const email = document.getElementById('invite-email').value.trim();
    const name = document.getElementById('invite-name').value.trim();
    const shareValue = document.getElementById('invite-share-value').value;
    const errorEl = document.getElementById('invite-modal-error');
    errorEl.style.display = 'none';

    const card = document.querySelector('[data-addition-id="' + additionId + '"]');
    const isCagnotte = card && card.dataset.isCagnotte === '1';

    if (!userId && !email) {
        errorEl.textContent = 'Sélectionnez un membre ou renseignez un e-mail.';
        errorEl.style.display = 'block';
        return;
    }
    if (!isCagnotte && (!shareValue || parseFloat(shareValue) <= 0)) {
        errorEl.textContent = 'Indiquez une part valide.';
        errorEl.style.display = 'block';
        return;
    }

    const data = { share_value: isCagnotte ? 0 : shareValue };
    if (userId) data.user_id = parseInt(userId, 10);
    else { data.email = email; data.name = name; }

    const r = await apiFetch(BASE_URL + '/api/additions/' + additionId + '/participants', { method: 'POST', body: JSON.stringify(data) });
    if (r.success) {
        closeModal('invite-modal');
        location.reload();
    } else {
        errorEl.textContent = r.error || 'Erreur.';
        errorEl.style.display = 'block';
    }
}

function openPaymentModal(additionId) {
    document.getElementById('payment-addition-id').value = additionId;
    document.getElementById('payment-payer-name').value = '';
    document.getElementById('payment-amount').value = '';
    document.getElementById('payment-method').value = 'cash';
    document.getElementById('payment-date').value = new Date().toISOString().slice(0, 10);
    document.getElementById('payment-note').value = '';
    openModal('payment-modal');
}

async function submitPayment() {
    const additionId = document.getElementById('payment-addition-id').value;
    const payerName = document.getElementById('payment-payer-name').value.trim();
    const amount = document.getElementById('payment-amount').value;
    if (!payerName || !amount || parseFloat(amount) <= 0) {
        Dialog.toast('Payeur et montant requis.', 'error');
        return;
    }
    const data = {
        payer_name: payerName,
        amount,
        method: document.getElementById('payment-method').value,
        paid_at: document.getElementById('payment-date').value,
        note: document.getElementById('payment-note').value.trim(),
    };
    const r = await apiFetch(BASE_URL + '/api/additions/' + additionId + '/payments', { method: 'POST', body: JSON.stringify(data) });
    if (r.success) {
        closeModal('payment-modal');
        location.reload();
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function deletePayment(additionId, paymentId) {
    if (!confirm('Supprimer cette perception ?')) return;
    const r = await apiFetch(BASE_URL + '/api/additions/' + additionId + '/payments/' + paymentId + '/delete', { method: 'POST' });
    if (r.success) location.reload();
    else Dialog.toast('Erreur.', 'error');
}
