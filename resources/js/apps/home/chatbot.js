/**
 * Sinemu Chatbot — "Sinu" AI Assistant
 * Floating chatbot widget with Gemini AI backend.
 */

const API_BASE = '/api/chatbot';
const SESSION_KEY = 'sinemu_chatbot_session';
const MAX_MSG_LENGTH = 2000;

/* ──────────────── State ──────────────── */
let sessionId = localStorage.getItem(SESSION_KEY);
if (!sessionId) {
    sessionId = crypto.randomUUID();
    localStorage.setItem(SESSION_KEY, sessionId);
}

let isSending = false;
let isOpen = false;
let historyLoaded = false;

/* ──────────────── DOM refs (set in init) ──────────────── */
let $toggle, $window, $messages, $input, $sendBtn, $typing, $quickActions;

/* ──────────────── Helpers ──────────────── */
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function scrollToBottom(smooth = true) {
    if (!$messages) return;
    requestAnimationFrame(() => {
        $messages.scrollTo({
            top: $messages.scrollHeight,
            behavior: smooth ? 'smooth' : 'instant',
        });
    });
}

function formatTime(dateStr) {
    const d = dateStr ? new Date(dateStr) : new Date();
    return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

/** Minimal markdown → HTML (bold, italic, lists, linebreaks). */
function renderMarkdown(text) {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/`(.+?)`/g, '<code>$1</code>')
        .replace(/\n/g, '<br>');
}

/* ──────────────── UI builders ──────────────── */
function appendMessage(role, content, time, animated = true) {
    // Hide quick actions after first real message
    if ($quickActions) $quickActions.style.display = 'none';

    const wrapper = document.createElement('div');
    wrapper.className = `chatbot-msg ${role}`;
    if (animated) wrapper.style.animationDelay = '0.05s';

    const avatarSrc = document.getElementById('chatbotIconImg')?.src ?? '';

    wrapper.innerHTML = `
        ${role === 'assistant' ? `<img src="${avatarSrc}" class="chatbot-msg-avatar" alt="Sinu">` : ''}
        <div>
            <div class="chatbot-msg-bubble">${renderMarkdown(content)}</div>
            <div class="chatbot-msg-time">${time ?? formatTime()}</div>
        </div>
    `;

    $messages.appendChild(wrapper);
    scrollToBottom(animated);
}

function showTyping() {
    if ($typing) {
        $typing.classList.add('show');
        scrollToBottom();
    }
}

function hideTyping() {
    if ($typing) $typing.classList.remove('show');
}

/* ──────────────── API calls ──────────────── */
async function loadHistory() {
    if (historyLoaded) return;
    historyLoaded = true;

    try {
        const res = await fetch(`${API_BASE}/history?session_id=${encodeURIComponent(sessionId)}`);
        if (!res.ok) return;
        const data = await res.json();

        if (data.messages && data.messages.length > 0) {
            // Remove welcome if we have history
            const welcome = $messages.querySelector('.chatbot-welcome');
            if (welcome) welcome.remove();
            if ($quickActions) $quickActions.style.display = 'none';

            data.messages.forEach((msg) => {
                appendMessage(msg.role, msg.content, formatTime(msg.created_at), false);
            });
            scrollToBottom(false);
        }
    } catch (err) {
        console.warn('[Chatbot] Could not load history:', err);
    }
}

async function sendMessage(text) {
    if (isSending || !text.trim()) return;
    isSending = true;
    $sendBtn.disabled = true;

    appendMessage('user', text);

    showTyping();

    try {
        const res = await fetch(`${API_BASE}/send`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                message: text,
                session_id: sessionId,
            }),
        });

        hideTyping();

        if (!res.ok) {
            appendMessage('assistant', 'Maaf, terjadi gangguan. Coba lagi nanti ya! 🙏');
            return;
        }

        const data = await res.json();
        appendMessage('assistant', data.message);
    } catch (err) {
        hideTyping();
        appendMessage('assistant', 'Koneksi terputus. Periksa internet kamu ya! 🌐');
        console.error('[Chatbot] Send error:', err);
    } finally {
        isSending = false;
        $sendBtn.disabled = false;
        $input?.focus();
    }
}

async function startNewConversation() {
    try {
        const res = await fetch(`${API_BASE}/new`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ session_id: sessionId }),
        });

        if (res.ok) {
            const data = await res.json();
            sessionId = data.new_session_id;
            localStorage.setItem(SESSION_KEY, sessionId);
        }
    } catch (err) {
        console.warn('[Chatbot] Could not reset conversation:', err);
    }

    // Clear UI
    if ($messages) {
        $messages.innerHTML = '';
        // Re-add welcome
        addWelcomeMessage();
    }
    if ($quickActions) $quickActions.style.display = '';
    historyLoaded = false;
}

function addWelcomeMessage() {
    const avatarSrc = document.getElementById('chatbotIconImg')?.src ?? '';
    const div = document.createElement('div');
    div.className = 'chatbot-welcome';
    div.innerHTML = `
        <img src="${avatarSrc}" class="chatbot-welcome-icon" alt="Sinu">
        <h4>Halo! Saya Sinu 👋</h4>
        <p>Asisten virtual Sinemu Indonesia.<br>Ada yang bisa saya bantu hari ini?</p>
    `;
    $messages.appendChild(div);
}

/* ──────────────── Toggle & Events ──────────────── */
function toggleChat() {
    isOpen = !isOpen;
    $window.classList.toggle('open', isOpen);
    $toggle.classList.toggle('active', isOpen);

    if (isOpen) {
        loadHistory();
        setTimeout(() => $input?.focus(), 350);
    }
}

function handleSend() {
    const text = $input.value.trim();
    if (!text || text.length > MAX_MSG_LENGTH) return;
    $input.value = '';
    $input.style.height = 'auto';
    sendMessage(text);
}

function autoResizeInput() {
    $input.style.height = 'auto';
    $input.style.height = Math.min($input.scrollHeight, 100) + 'px';
}

/* ──────────────── Init ──────────────── */
export function initChatbot() {
    $toggle = document.getElementById('chatbotToggle');
    $window = document.getElementById('chatbotWindow');
    $messages = document.getElementById('chatbotMessages');
    $input = document.getElementById('chatbotInput');
    $sendBtn = document.getElementById('chatbotSend');
    $typing = document.getElementById('chatbotTyping');
    $quickActions = document.getElementById('chatbotQuickActions');

    if (!$toggle || !$window) return;

    // Toggle button
    $toggle.addEventListener('click', toggleChat);

    // Send button
    $sendBtn?.addEventListener('click', handleSend);

    // Enter to send (Shift+Enter for newline)
    $input?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    });

    // Auto-resize textarea
    $input?.addEventListener('input', autoResizeInput);

    // New conversation button
    document.getElementById('chatbotNewBtn')?.addEventListener('click', startNewConversation);

    // Quick action buttons
    document.querySelectorAll('.chatbot-quick-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const text = btn.textContent.trim();
            $input.value = text;
            handleSend();
        });
    });

    // Close when pressing Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isOpen) toggleChat();
    });

    // Add welcome message
    addWelcomeMessage();
}
