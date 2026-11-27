// ============================================
// FamilyBoard - Assistant IA (bulle superposée, voir .ai-assistant-panel)
// ============================================

let aiPanelOpen = false;
let aiHistoryLoaded = false;

function toggleAiAssistantPanel(autoTalk) {
    const panel = document.getElementById('ai-assistant-panel');
    if (!panel) return;
    aiPanelOpen = !aiPanelOpen;
    panel.style.display = aiPanelOpen ? 'flex' : 'none';
    if (aiPanelOpen) {
        if (!aiHistoryLoaded) loadAiAssistantHistory();
        if (autoTalk && voiceRecognition) toggleVoiceInput();
    } else if (voiceListening) {
        voiceRecognition.stop();
    }
}

function loadAiAssistantHistory() {
    aiHistoryLoaded = true;
    fetch(`${BASE_URL}/api/ai-assistant/messages`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const wrap = document.getElementById('chat-messages');
            wrap.innerHTML = '';
            if (!data.messages.length) {
                wrap.innerHTML = '<p class="empty-state">Posez une question, ou demandez d\'ajouter une tâche, un article de courses ou un événement.</p>';
                return;
            }
            data.messages.forEach(m => appendAssistantMessage(m.role, m.content, m.user_name));
        });
}

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

// ── Saisie vocale (Web Speech API) ──────────────────────────
// Reconnaissance embarquée du navigateur, jamais un service tiers — cohérent avec le principe
// "aucune donnée envoyée hors de l'infrastructure auto-hébergée" de l'assistant lui-même.
// Non disponible sur tous les navigateurs (Firefox desktop notamment) : le micro reste caché
// dans ce cas plutôt que d'afficher un bouton qui échouerait à chaque appui.

let voiceRecognition = null;
let voiceListening = false;

function initVoiceInput() {
    const SpeechRecognitionApi = window.SpeechRecognition || window.webkitSpeechRecognition;
    const micBtn = document.getElementById('chat-mic-btn');
    if (!SpeechRecognitionApi || !micBtn) return;

    micBtn.style.display = '';
    voiceRecognition = new SpeechRecognitionApi();
    voiceRecognition.lang = 'fr-FR';
    voiceRecognition.continuous = false;
    voiceRecognition.interimResults = true;

    let finalTranscript = '';

    voiceRecognition.onresult = (event) => {
        let interim = '';
        for (let i = event.resultIndex; i < event.results.length; i++) {
            const transcript = event.results[i][0].transcript;
            if (event.results[i].isFinal) finalTranscript += transcript;
            else interim += transcript;
        }
        document.getElementById('chat-input').value = (finalTranscript + interim).trim();
    };

    voiceRecognition.onerror = () => {
        stopVoiceListening();
    };

    voiceRecognition.onend = () => {
        stopVoiceListening();
        const text = document.getElementById('chat-input').value.trim();
        if (text) sendAssistantMessage();
        finalTranscript = '';
    };

    voiceRecognition.onstart = () => {
        finalTranscript = '';
        voiceListening = true;
        micBtn.classList.add('recording');
        document.getElementById('chat-input').value = '';
        document.getElementById('chat-input').placeholder = 'Je vous écoute…';
    };
}

function stopVoiceListening() {
    voiceListening = false;
    const micBtn = document.getElementById('chat-mic-btn');
    if (micBtn) micBtn.classList.remove('recording');
    document.getElementById('chat-input').placeholder = "Écrire à l'assistant…";
}

function toggleVoiceInput() {
    if (!voiceRecognition) return;
    if (voiceListening) {
        voiceRecognition.stop();
    } else {
        // La bulle doit être ouverte pour que le micro (dans son en-tête) capte quoi que ce soit.
        if (!aiPanelOpen) toggleAiAssistantPanel();
        try {
            voiceRecognition.start();
        } catch (e) {
            // start() jette si un appel est déjà en cours (double-clic) — sans conséquence.
        }
    }
}

document.addEventListener('DOMContentLoaded', initVoiceInput);

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
