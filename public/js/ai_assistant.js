// ============================================
// FamilyBoard - Assistant IA
// ============================================

function appendAssistantMessage(role, content, authorName) {
    const wrap = document.getElementById('chat-messages');
    const emptyState = wrap.querySelector('.empty-state');
    if (emptyState) emptyState.remove();

    const isAssistant = role === 'assistant';
    const row = document.createElement('div');
    row.className = 'message-row' + (isAssistant ? '' : ' own');
    row.innerHTML = `
        ${isAssistant ? '<div class="user-avatar-sm" style="background:#4A90D9" title="Assistant">🤖</div>' : ''}
        <div class="message-bubble ${isAssistant ? 'bubble-other' : 'bubble-own'}">
            ${!isAssistant ? `<div class="message-author">${escHtmlAi(authorName || '')}</div>` : ''}
            <div class="message-text"></div>
            <div class="message-time">${new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}</div>
        </div>`;
    row.querySelector('.message-text').textContent = content;
    wrap.appendChild(row);
    wrap.scrollTop = wrap.scrollHeight;
}

function escHtmlAi(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

async function sendAssistantMessage() {
    const input = document.getElementById('chat-input');
    const btn = document.getElementById('chat-send-btn');
    const text = input.value.trim();
    if (!text) return;

    input.value = '';
    appendAssistantMessage('user', text, CURRENT_USER_NAME);
    btn.disabled = true;
    btn.textContent = 'Réflexion…';

    try {
        const r = await apiFetch(`${BASE_URL}/api/ai-assistant/message`, { method: 'POST', body: JSON.stringify({ message: text }) });
        if (r.success) {
            appendAssistantMessage('assistant', r.reply);
        } else {
            appendAssistantMessage('assistant', '⚠️ ' + (r.error || "Erreur de l'assistant."));
        }
    } catch (e) {
        appendAssistantMessage('assistant', "⚠️ L'assistant n'a pas répondu (délai dépassé ou serveur injoignable).");
    } finally {
        btn.disabled = false;
        btn.textContent = 'Envoyer';
    }
}
