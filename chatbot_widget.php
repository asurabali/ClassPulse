<!-- ============================================================
     ClassPulse — PulseBot Chatbot Widget
     This file is included in index.php to add the floating chat UI.
     ============================================================ -->

<!-- Floating Launcher Button -->
<button id="chatbot-launcher" title="Ask PulseBot about attendance" aria-label="Open chatbot">
    🤖
    <span class="launcher-badge" id="chat-badge" style="display:none">1</span>
</button>

<!-- Chat Window -->
<div id="chatbot-window">
    <!-- Header -->
    <div class="chat-header">
        <div class="chat-avatar">🤖</div>
        <div class="chat-header-info">
            <div class="chat-name">PulseBot</div>
            <div class="chat-status">
                <span class="status-dot"></span>
                Online — Attendance Assistant
            </div>
        </div>
        <div class="chat-header-actions">
            <button class="chat-action-btn" id="chat-clear" title="Clear chat">🗑️</button>
            <button class="chat-action-btn" id="chat-close" title="Close">✕</button>
        </div>
    </div>

    <!-- Messages -->
    <div class="chat-messages" id="chat-messages">
        <!-- Welcome message is injected by JS on init -->
    </div>

    <!-- Footer / Input -->
    <div class="chat-footer">
        <div class="chat-input-wrap">
            <input type="text" class="chat-input" id="chat-input"
                   placeholder="Ask about attendance..."
                   autocomplete="off" maxlength="300">
            <button class="chat-send-btn" id="chat-send" title="Send">➤</button>
        </div>
        <div class="chat-hint">
            Try: "Attendance of Alice in CS101" or "Low attendance students"
        </div>
    </div>
</div>

<script>
/**
 * PulseBot — Client-Side Chatbot Controller
 */
(function() {
    const launcher  = document.getElementById('chatbot-launcher');
    const chatWindow= document.getElementById('chatbot-window');
    const closeBtn  = document.getElementById('chat-close');
    const clearBtn  = document.getElementById('chat-clear');
    const input     = document.getElementById('chat-input');
    const sendBtn   = document.getElementById('chat-send');
    const msgContainer = document.getElementById('chat-messages');

    let isOpen = false;

    // ---- Toggle chat window ----
    launcher.addEventListener('click', () => {
        isOpen = !isOpen;
        chatWindow.classList.toggle('open', isOpen);
        launcher.style.transform = isOpen ? 'scale(0)' : '';
        launcher.style.opacity   = isOpen ? '0' : '1';
        launcher.style.pointerEvents = isOpen ? 'none' : 'all';
        if (isOpen) {
            input.focus();
            // Hide badge
            document.getElementById('chat-badge').style.display = 'none';
        }
    });

    closeBtn.addEventListener('click', () => {
        isOpen = false;
        chatWindow.classList.remove('open');
        launcher.style.transform = '';
        launcher.style.opacity   = '1';
        launcher.style.pointerEvents = 'all';
    });

    clearBtn.addEventListener('click', () => {
        msgContainer.innerHTML = '';
        addWelcomeMessage();
    });

    // ---- Send message ----
    function sendMessage() {
        const text = input.value.trim();
        if (!text) return;

        appendMessage('user', text);
        input.value = '';
        input.disabled = true;
        sendBtn.disabled = true;

        // Show typing indicator
        const typingEl = appendTyping();

        fetch('api/chatbot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text })
        })
        .then(r => r.json())
        .then(resp => {
            typingEl.remove();
            renderBotResponse(resp);
        })
        .catch(err => {
            typingEl.remove();
            appendMessage('bot', '❌ Network error. Please try again.');
            console.error(err);
        })
        .finally(() => {
            input.disabled = false;
            sendBtn.disabled = false;
            input.focus();
        });
    }

    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // ---- Render Bot Response ----
    function renderBotResponse(resp) {
        // Main text reply (supports markdown-like bold)
        if (resp.reply) {
            const formatted = formatText(resp.reply);
            appendMessage('bot', formatted, true);
        }

        // Card-type response
        if (resp.type === 'card' && resp.data && Array.isArray(resp.data)) {
            const card = document.createElement('div');
            card.className = 'chat-result-card';
            card.innerHTML = resp.data.map(item =>
                `<div class="chat-result-row">
                    <span class="label">${escHtml(item.label)}</span>
                    <span class="value">${escHtml(String(item.value))}</span>
                </div>`
            ).join('');
            appendElement('bot', card);
        }

        // Table-type response
        if (resp.type === 'table' && resp.data && Array.isArray(resp.data)) {
            const card = document.createElement('div');
            card.className = 'chat-result-card';

            let headerHtml = '';
            if (resp.columns) {
                headerHtml = `<div class="chat-result-header">${resp.columns.join(' &nbsp;|&nbsp; ')}</div>`;
            }

            const rowsHtml = resp.data.map(item => {
                const vals = Object.values(item);
                return `<div class="chat-result-row" style="justify-content:flex-start;gap:16px;">
                    ${vals.map(v => `<span style="flex:1">${escHtml(String(v))}</span>`).join('')}
                </div>`;
            }).join('');

            card.innerHTML = headerHtml + rowsHtml;
            appendElement('bot', card);
        }

        // Suggestions / Quick replies
        if (resp.suggestions && resp.suggestions.length > 0) {
            const wrap = document.createElement('div');
            wrap.className = 'quick-replies';
            resp.suggestions.forEach(s => {
                const chip = document.createElement('span');
                chip.className = 'quick-reply-chip';
                chip.textContent = s;
                chip.addEventListener('click', () => {
                    input.value = s;
                    sendMessage();
                });
                wrap.appendChild(chip);
            });
            appendElement('bot', wrap);
        }

        scrollToBottom();
    }

    // ---- DOM Helpers ----
    function appendMessage(sender, html, isHtml = false) {
        const wrapper = document.createElement('div');
        wrapper.className = `msg ${sender}`;

        const avatar = document.createElement('div');
        avatar.className = 'msg-avatar';
        avatar.textContent = sender === 'bot' ? '🤖' : '👤';

        const bubble = document.createElement('div');
        bubble.className = 'msg-bubble';
        if (isHtml) {
            bubble.innerHTML = html;
        } else {
            bubble.textContent = html;
        }

        wrapper.appendChild(avatar);
        wrapper.appendChild(bubble);
        msgContainer.appendChild(wrapper);
        scrollToBottom();
        return wrapper;
    }

    function appendElement(sender, element) {
        const wrapper = document.createElement('div');
        wrapper.className = `msg ${sender}`;
        wrapper.style.maxWidth = '95%';

        const avatar = document.createElement('div');
        avatar.className = 'msg-avatar';
        avatar.textContent = '🤖';

        wrapper.appendChild(avatar);
        wrapper.appendChild(element);
        msgContainer.appendChild(wrapper);
        scrollToBottom();
    }

    function appendTyping() {
        const wrapper = document.createElement('div');
        wrapper.className = 'msg bot';
        wrapper.id = 'typing-indicator';

        const avatar = document.createElement('div');
        avatar.className = 'msg-avatar';
        avatar.textContent = '🤖';

        const bubble = document.createElement('div');
        bubble.className = 'msg-bubble typing-bubble';
        bubble.innerHTML = '<span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>';

        wrapper.appendChild(avatar);
        wrapper.appendChild(bubble);
        msgContainer.appendChild(wrapper);
        scrollToBottom();
        return wrapper;
    }

    function scrollToBottom() {
        requestAnimationFrame(() => {
            msgContainer.scrollTop = msgContainer.scrollHeight;
        });
    }

    function formatText(text) {
        // Convert **bold** to <strong>
        return text
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n/g, '<br>');
    }

    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ---- Welcome Message on Init ----
    function addWelcomeMessage() {
        const name = document.querySelector('.user-name')?.textContent || 'there';
        appendMessage('bot',
            formatText(`👋 Hi **${name}**! I'm **PulseBot**, your attendance assistant.\n\nI can help you with:\n• Student attendance lookups\n• Low attendance alerts\n• Subject statistics\n\nJust ask me anything!`),
            true
        );

        // Add quick replies
        const wrap = document.createElement('div');
        wrap.className = 'quick-replies';
        ['Show overall summary', 'Low attendance students', 'Help'].forEach(s => {
            const chip = document.createElement('span');
            chip.className = 'quick-reply-chip';
            chip.textContent = s;
            chip.addEventListener('click', () => {
                input.value = s;
                sendMessage();
            });
            wrap.appendChild(chip);
        });
        appendElement('bot', wrap);
    }

    // Init
    addWelcomeMessage();

    // Show badge after delay to catch attention
    setTimeout(() => {
        if (!isOpen) {
            document.getElementById('chat-badge').style.display = 'flex';
        }
    }, 3000);

})();
</script>
