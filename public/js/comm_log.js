// ============================================
// Journal parental — messages immuables, sans suppression/édition
// ============================================

let commLogLastId = LAST_MSG_ID;
let commLogPolling = null;

function commLogScrollToBottom() {
    const msgs = document.getElementById('chat-messages');
    msgs.scrollTop = msgs.scrollHeight;
}

async function sendCommLogMessage() {
    const input = document.getElementById('chat-input');
    const content = input.value.trim();
    if (!content) return;

    input.value = '';
    const data = await apiFetch(BASE_URL + '/api/comm-log/send', {
        method: 'POST',
        body: JSON.stringify({ content })
    });

    if (data.success) {
        appendCommLogMessage(data.message, true);
        commLogLastId = data.message.id;
        commLogScrollToBottom();
    }
}

async function sendCommLogVoiceMessage({ blob, mimeType, durationSec }) {
    const input = document.getElementById('chat-input');
    const content = input.value.trim();
    input.value = '';

    const fd = new FormData();
    fd.append('audio', blob, 'voice.' + voiceExtensionForMime(mimeType));
    fd.append('content', content);
    fd.append('duration', durationSec);

    const res = await fetch(BASE_URL + '/api/comm-log/send', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        appendCommLogMessage(data.message, true);
        commLogLastId = data.message.id;
        commLogScrollToBottom();
    } else {
        Dialog.toast(data.error || "Erreur lors de l'envoi du message vocal.", 'error');
    }
}

const commLogVoiceBtn = document.getElementById('chat-voice-btn');
if (commLogVoiceBtn) {
    initVoiceRecorder(commLogVoiceBtn, document.getElementById('chat-voice-timer'), sendCommLogVoiceMessage);
}

function appendCommLogMessage(msg, own) {
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

    const voice = msg.audio_path ? voiceBubbleHtml(`${BASE_URL}/api/comm-log/${msg.id}/audio`, msg.audio_duration) : '';
    const text = msg.content ? `<div class="message-text">${escapeHtml(msg.content).replace(/\n/g,'<br>')}</div>` : '';

    div.innerHTML = `
        ${avatar}
        <div class="message-bubble ${isOwn ? 'bubble-own' : 'bubble-other'}">
            ${author}
            ${voice}
            ${text}
            <div class="message-time">${commLogFormatTime(msg.created_at)}</div>
        </div>
    `;

    container.appendChild(div);
}

function commLogFormatTime(datetime) {
    const iso = datetime.includes('T') ? datetime : datetime.replace(' ', 'T') + 'Z';
    const d = new Date(iso);
    return d.toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: APP_TIMEZONE });
}

async function pollCommLog() {
    const data = await apiFetch(BASE_URL + '/api/comm-log/poll?after=' + commLogLastId);
    if (data.messages && data.messages.length > 0) {
        const container = document.getElementById('chat-messages');
        const wasAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 50;

        data.messages.forEach(msg => {
            if (msg.user_id !== CURRENT_USER_ID) {
                appendCommLogMessage(msg, false);
            }
            commLogLastId = Math.max(commLogLastId, msg.id);
        });

        if (wasAtBottom) commLogScrollToBottom();
    }
}

commLogScrollToBottom();
commLogPolling = setInterval(pollCommLog, 5000);

document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        clearInterval(commLogPolling);
    } else {
        commLogPolling = setInterval(pollCommLog, 5000);
    }
});
