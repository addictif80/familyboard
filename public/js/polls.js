// ---- Sondages familiaux ----

function openPollModal() {
    document.getElementById('poll-question').value = '';
    document.getElementById('poll-options-wrap').innerHTML =
        '<input type="text" class="poll-option-input" placeholder="Option 1" style="margin-bottom:.4rem">' +
        '<input type="text" class="poll-option-input" placeholder="Option 2" style="margin-bottom:.4rem">';
    openModal('poll-modal');
}

function addPollOptionField() {
    const wrap = document.getElementById('poll-options-wrap');
    const n = wrap.querySelectorAll('.poll-option-input').length + 1;
    if (n > 10) return;
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'poll-option-input';
    input.placeholder = 'Option ' + n;
    input.style.marginBottom = '.4rem';
    wrap.appendChild(input);
}

async function createPoll() {
    const question = document.getElementById('poll-question').value.trim();
    const options = Array.from(document.querySelectorAll('.poll-option-input'))
        .map(i => i.value.trim()).filter(Boolean);
    if (!question || options.length < 2) {
        Dialog.toast('Une question et au moins deux options sont requises.', 'error');
        return;
    }
    const r = await apiFetch(BASE_URL + '/api/polls', { method: 'POST', body: JSON.stringify({ question, options }) });
    if (r.success) {
        closeModal('poll-modal');
        window.location.reload();
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function votePoll(pollId, optionId) {
    const r = await apiFetch(BASE_URL + '/api/polls/' + pollId + '/vote', { method: 'POST', body: JSON.stringify({ option_id: optionId }) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function togglePollClosed(pollId) {
    const r = await apiFetch(BASE_URL + '/api/polls/' + pollId + '/close', { method: 'POST', body: '{}' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deletePoll(pollId) {
    const ok = await Dialog.confirm('Supprimer ce sondage et tous ses votes ?');
    if (!ok) return;
    const r = await apiFetch(BASE_URL + '/api/polls/' + pollId + '/delete', { method: 'POST', body: '{}' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}
