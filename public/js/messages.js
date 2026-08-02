// ---- Messagerie privée (DM) ----

let dmLastId = (typeof DM_LAST_ID !== 'undefined') ? DM_LAST_ID : 0;
let dmPolling = null;

function dmScrollToBottom() {
    const c = document.getElementById('dm-messages');
    if (c) c.scrollTop = c.scrollHeight;
}

function dmAppendMessage(msg, isOwn) {
    const container = document.getElementById('dm-messages');
    if (!container) return;
    const row = document.createElement('div');
    row.className = 'message-row' + (isOwn ? ' own' : '');
    row.dataset.id = msg.id;
    row.innerHTML = `
        ${!isOwn ? `<div class="user-avatar-sm" style="background:${escapeHtml(msg.sender_color || '#4A90D9')}">${msg.sender_avatar ? `<img src="${BASE_URL}${msg.sender_avatar}" alt="">` : escapeHtml((msg.sender_name || '?').charAt(0))}</div>` : ''}
        <div class="message-bubble ${isOwn ? 'bubble-own' : 'bubble-other'}">
            <div class="message-text">${escapeHtml(msg.content).replace(/\n/g, '<br>')}</div>
            <div class="message-time">${new Date((msg.created_at || '').replace(' ', 'T') + 'Z').toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}</div>
        </div>`;
    container.appendChild(row);
}

async function sendDirectMessage() {
    const input = document.getElementById('dm-input');
    const content = input.value.trim();
    if (!content) return;
    input.value = '';

    const r = await apiFetch(BASE_URL + '/api/messages/' + DM_OTHER_ID + '/send', { method: 'POST', body: JSON.stringify({ content }) });
    if (r.success) {
        dmAppendMessage(r.message, true);
        dmLastId = Math.max(dmLastId, r.message.id);
        dmScrollToBottom();
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function dmPoll() {
    const data = await apiFetch(BASE_URL + '/api/messages/' + DM_OTHER_ID + '/poll?after=' + dmLastId);
    if (data.messages && data.messages.length > 0) {
        const container = document.getElementById('dm-messages');
        const wasAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 50;
        data.messages.forEach(msg => {
            if (msg.sender_id !== CURRENT_USER_ID) dmAppendMessage(msg, false);
            dmLastId = Math.max(dmLastId, msg.id);
        });
        if (wasAtBottom) dmScrollToBottom();
    }
}

dmScrollToBottom();
dmPolling = setInterval(dmPoll, 3000);

document.addEventListener('visibilitychange', () => {
    clearInterval(dmPolling);
    if (!document.hidden) dmPolling = setInterval(dmPoll, 3000);
});
