// ============================================
// FamilyBoard - Chat JS
// ============================================

let lastMsgId = LAST_MSG_ID;
let polling = null;

function scrollToBottom() {
    const msgs = document.getElementById('chat-messages');
    msgs.scrollTop = msgs.scrollHeight;
}

async function sendMessage() {
    const input = document.getElementById('chat-input');
    const content = input.value.trim();
    if (!content) return;

    input.value = '';
    const data = await apiFetch(BASE_URL + '/api/chat/send', {
        method: 'POST',
        body: JSON.stringify({ content })
    });

    if (data.success) {
        appendMessage(data.message, true);
        lastMsgId = data.message.id;
        scrollToBottom();
    }
}

function appendMessage(msg, own) {
    const container = document.getElementById('chat-messages');
    const isOwn = own || msg.user_id === CURRENT_USER_ID;
    const div = document.createElement('div');
    div.className = 'message-row ' + (isOwn ? 'own' : '');
    div.setAttribute('data-id', msg.id);

    const avatar = !isOwn ? `
        <div class="user-avatar-sm" style="background:${escapeHtml(msg.user_color)}" title="${escapeHtml(msg.user_name)}">
            ${escapeHtml(msg.user_name[0])}
        </div>` : '';

    const author = !isOwn ? `<div class="message-author" style="color:${escapeHtml(msg.user_color)}">${escapeHtml(msg.user_name)}</div>` : '';

    div.innerHTML = `
        ${avatar}
        <div class="message-bubble ${isOwn ? 'bubble-own' : 'bubble-other'}">
            ${author}
            <div class="message-text">${escapeHtml(msg.content).replace(/\n/g,'<br>')}</div>
            <div class="message-time">${formatMsgTime(msg.created_at)}</div>
        </div>
        ${isOwn ? `<button class="msg-delete-btn" onclick="deleteMessage(${msg.id})" title="Supprimer">✕</button>` : ''}
    `;

    container.appendChild(div);
}

function formatMsgTime(datetime) {
    const d = new Date(datetime);
    return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

async function deleteMessage(id) {
    const result = await apiFetch(BASE_URL + '/api/chat/' + id + '/delete', { method: 'POST' });
    if (result.success) {
        const el = document.querySelector(`[data-id="${id}"]`);
        if (el) el.remove();
    }
}

async function pollMessages() {
    const data = await apiFetch(BASE_URL + '/api/chat/poll?after=' + lastMsgId);
    if (data.messages && data.messages.length > 0) {
        const container = document.getElementById('chat-messages');
        const wasAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 50;

        data.messages.forEach(msg => {
            if (msg.user_id !== CURRENT_USER_ID) {
                appendMessage(msg, false);
                lastMsgId = msg.id;
            } else {
                lastMsgId = Math.max(lastMsgId, msg.id);
            }
        });

        if (wasAtBottom) scrollToBottom();
    }
}

// Initial scroll
scrollToBottom();

// Poll every 3 seconds
polling = setInterval(pollMessages, 3000);

// Pause when tab hidden
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        clearInterval(polling);
    } else {
        polling = setInterval(pollMessages, 3000);
    }
});
