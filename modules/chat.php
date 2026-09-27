<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pageTitle = 'Messages — E-Commerce Core';
$uid  = $_SESSION['user_id'];
$role = $_SESSION['role'];

if (!isCustomer()) redirectRoot('modules/staff_chat.php');

// Find or create open conversation
$conv = $pdo->prepare("SELECT * FROM chat_conversations WHERE customer_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
$conv->execute([$uid]);
$conversation = $conv->fetch();

if (!$conversation) {
    $pdo->prepare("INSERT INTO chat_conversations (customer_id, subject) VALUES (?, 'General Inquiry')")->execute([$uid]);
    $convId = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_id, sender_role, message) VALUES (?, 0, 'ai', ?)")
        ->execute([$convId, "👋 Hi! I'm your AI assistant. Ask me about delivery times, order status, returns, or payments. If I can't help, I'll forward your message to our team."]);

    $conv = $pdo->prepare("SELECT * FROM chat_conversations WHERE id = ?");
    $conv->execute([$convId]);
    $conversation = $conv->fetch();
}

$convId = $conversation['id'];

// Handle POST (non-AJAX fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $msg = trim($_POST['message'] ?? '');
    if ($msg !== '') {
        $pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_id, sender_role, message) VALUES (?, ?, 'customer', ?)")
            ->execute([$convId, $uid, $msg]);
        $pdo->prepare("UPDATE chat_conversations SET last_message_at = NOW() WHERE id = ?")->execute([$convId]);

        if (preg_match('/\b(human|person|agent|staff|admin|real person|talk to someone)\b/i', $msg)) {
            $pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_id, sender_role, message) VALUES (?, 0, 'ai', ?)")
                ->execute([$convId, "👤 Got it — I've flagged this for our team. A real person will reply as soon as possible."]);
            $pdo->prepare("UPDATE chat_conversations SET needs_human = 1 WHERE id = ?")->execute([$convId]);
        } else {
            $reply = generateAiReply($pdo, $msg, $uid);
            $pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_id, sender_role, message) VALUES (?, 0, 'ai', ?)")
                ->execute([$convId, $reply['text']]);
            if (!empty($reply['escalate'])) {
                $pdo->prepare("UPDATE chat_conversations SET needs_human = 1 WHERE id = ?")->execute([$convId]);
            }
        }

        header("Location: " . moduleUrl('chat.php'));
        exit;
    }
}

// Mark incoming as read
$pdo->prepare("UPDATE chat_messages SET is_read = 1, read_at = NOW() WHERE conversation_id = ? AND sender_role IN ('admin','product_manager') AND read_at IS NULL")
    ->execute([$convId]);
$pdo->prepare("UPDATE chat_messages SET delivered_at = NOW() WHERE conversation_id = ? AND sender_id != ? AND sender_role != 'ai' AND delivered_at IS NULL")
    ->execute([$convId, $uid]);

$msgs = $pdo->prepare("
    SELECT cm.*, u.full_name AS sender_name, u.username AS sender_username
    FROM chat_messages cm
    LEFT JOIN users u ON cm.sender_id = u.id
    WHERE cm.conversation_id = ?
    ORDER BY cm.id ASC
");
$msgs->execute([$convId]);
$messages = $msgs->fetchAll();

// Last message id (for polling)
$lastId = 0;
if (!empty($messages)) $lastId = (int)end($messages)['id'];

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.chat-wrap {
    max-width: 900px; margin: 0 auto;
    background: #fff; border: 1px solid #e5e7eb;
    border-radius: 16px; overflow: hidden;
    display: flex; flex-direction: column;
    height: calc(100vh - 200px); min-height: 500px;
}
.chat-head {
    padding: 18px 22px;
    background: linear-gradient(135deg, #1e293b, #111827);
    color: #fff;
    display: flex; align-items: center; gap: 12px;
}
.chat-head-avatar {
    width: 42px; height: 42px;
    background: #2563eb; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 1rem; flex-shrink: 0;
}
.chat-head-info h3 { margin: 0; font-size: 1rem; }
.chat-head-info p  { margin: 2px 0 0; font-size: 0.78rem; color: #cbd5e1; }

.chat-body {
    flex: 1; padding: 20px;
    overflow-y: auto;
    background: #f4f7fb;
    display: flex; flex-direction: column; gap: 14px;
    scroll-behavior: smooth;
}
.msg-row { display: flex; gap: 10px; align-items: flex-end; animation: fadeIn 0.2s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
.msg-row.mine  { justify-content: flex-end; }
.msg-row.ai    { justify-content: flex-start; }
.msg-row.staff { justify-content: flex-start; }

.msg-avatar {
    width: 32px; height: 32px;
    border-radius: 10px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.75rem; color: #fff;
}
.msg-avatar.customer { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
.msg-avatar.ai       { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
.msg-avatar.staff    { background: linear-gradient(135deg, #059669, #047857); }

.msg-bubble {
    max-width: 70%;
    padding: 11px 15px;
    border-radius: 14px;
    font-size: 0.9rem;
    line-height: 1.55;
    white-space: pre-line;
    word-wrap: break-word;
    box-shadow: 0 1px 2px rgba(16,24,40,0.04);
}
.msg-bubble.customer { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; border-bottom-right-radius: 4px; }
.msg-bubble.ai       { background: #fff; color: #111827; border: 1px solid #e5e7eb; border-bottom-left-radius: 4px; }
.msg-bubble.staff    { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; border-bottom-left-radius: 4px; }

.msg-meta {
    font-size: 0.7rem; color: #9ca3af;
    margin-top: 4px; padding: 0 4px;
    display: flex; align-items: center; gap: 5px;
}
.msg-row.mine .msg-meta { text-align: right; justify-content: flex-end; }

/* Read receipts */
.tick {
    display: inline-flex; align-items: center;
    font-size: 0.75rem; font-weight: 700;
    margin-left: 4px;
}
.tick.sent      { color: #9ca3af; }
.tick.delivered { color: #9ca3af; }
.tick.read      { color: #60a5fa; }

.chat-input {
    padding: 14px; background: #fff;
    border-top: 1px solid #e5e7eb;
    display: flex; gap: 10px; align-items: flex-end;
}
.chat-input textarea {
    flex: 1;
    border: 1.5px solid #e5e7eb;
    border-radius: 12px;
    padding: 11px 14px;
    font-family: inherit; font-size: 0.9rem;
    resize: none; max-height: 120px; min-height: 44px; line-height: 1.5;
}
.chat-input textarea:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37,99,235,0.12); }
.chat-input button {
    padding: 11px 20px;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff; border: none; border-radius: 12px;
    font-weight: 700; font-family: inherit; cursor: pointer;
    font-size: 0.9rem;
    box-shadow: 0 6px 16px rgba(37,99,235,0.28);
}
.chat-input button:hover:not(:disabled) { transform: translateY(-1px); }
.chat-input button:disabled { opacity: 0.6; cursor: not-allowed; }

.ai-suggestions {
    display: flex; gap: 8px; flex-wrap: wrap;
    padding: 0 14px 14px; background: #fff;
}
.suggestion-chip {
    padding: 7px 12px;
    background: #eff6ff; color: #1d4ed8;
    border: 1px solid #bfdbfe;
    border-radius: 999px;
    font-size: 0.78rem; font-weight: 600;
    cursor: pointer; font-family: inherit;
}
.suggestion-chip:hover { background: #dbeafe; }

.typing-indicator {
    display: none;
    align-items: center; gap: 6px;
    font-size: 0.78rem; color: #6b7280;
    padding: 6px 14px 0;
    font-style: italic;
}
.typing-indicator.active { display: flex; }
.typing-dots { display: inline-flex; gap: 3px; }
.typing-dots span {
    width: 5px; height: 5px;
    background: #6b7280; border-radius: 50%;
    animation: typingBounce 1.2s infinite;
}
.typing-dots span:nth-child(2) { animation-delay: 0.15s; }
.typing-dots span:nth-child(3) { animation-delay: 0.3s; }
@keyframes typingBounce {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
    30% { transform: translateY(-4px); opacity: 1; }
}
</style>

<div class="app-layout">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" onclick="openSidebar()" aria-label="Toggle menu">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <h2>Messages</h2>
                    <p>Chat with our team & AI assistant</p>
                </div>
            </div>
        </header>

        <div class="page-body">
            <div class="chat-wrap">
                <div class="chat-head">
                    <div class="chat-head-avatar">💬</div>
                    <div class="chat-head-info">
                        <h3>Support Chat</h3>
                        <p>AI replies instantly · Staff replies during business hours</p>
                    </div>
                </div>

                <div class="chat-body" id="chatBody">
                    <?php foreach ($messages as $m):
                        $isMine = (int)$m['sender_id'] === $uid && $m['sender_role'] === 'customer';
                        $roleClass = $isMine ? 'mine' : ($m['sender_role'] === 'ai' ? 'ai' : 'staff');
                        $bubbleClass = $isMine ? 'customer' : ($m['sender_role'] === 'ai' ? 'ai' : 'staff');
                        $avatarClass = $bubbleClass;

                        $displayName = 'You';
                        if ($m['sender_role'] === 'ai') $displayName = 'AI Assistant';
                        elseif ($m['sender_role'] === 'staff') $displayName = $m['sender_name'] ?: 'Support Team';

                        // Read receipt tick
                        $tick = '';
                        if ($isMine) {
                            if ($m['read_at']) {
                                $tick = '<span class="tick read" title="Seen">✓✓</span>';
                            } elseif ($m['delivered_at']) {
                                $tick = '<span class="tick delivered" title="Delivered">✓✓</span>';
                            } else {
                                $tick = '<span class="tick sent" title="Sent">✓</span>';
                            }
                        }
                    ?>
                        <div class="msg-row <?= $roleClass ?>" data-msg-id="<?= $m['id'] ?>">
                            <?php if (!$isMine): ?>
                                <div class="msg-avatar <?= $avatarClass ?>">
                                    <?= $m['sender_role'] === 'ai' ? '🤖' : strtoupper(substr($displayName, 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="msg-bubble <?= $bubbleClass ?>"><?= e($m['message']) ?></div>
                                <div class="msg-meta">
                                    <?= e($displayName) ?> · <?= timeAgo($m['created_at']) ?><?= $tick ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="typing-indicator" id="typingIndicator">
                    <div class="typing-dots"><span></span><span></span><span></span></div>
                    <span id="typingText">Support is typing...</span>
                </div>

                <div class="ai-suggestions">
                    <button type="button" class="suggestion-chip" onclick="fillMsg('How many days to deliver my order?')">📦 Delivery time</button>
                    <button type="button" class="suggestion-chip" onclick="fillMsg('What payment methods do you accept?')">💳 Payment methods</button>
                    <button type="button" class="suggestion-chip" onclick="fillMsg('How do I return an item?')">↩️ Return policy</button>
                    <button type="button" class="suggestion-chip" onclick="fillMsg('I want to talk to a human')">👤 Talk to a human</button>
                </div>

                <div class="chat-input">
                    <textarea id="msgInput" placeholder="Type your message..." rows="1"></textarea>
                    <button type="button" id="sendBtn" onclick="sendMessage()">Send →</button>
                </div>
            </div>
        </div>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<script>
const CONV_ID = <?= (int)$convId ?>;
const MY_ID   = <?= (int)$uid ?>;
const POLL_URL   = '<?= baseUrl('api/chat_poll.php') ?>';
const SEND_URL   = '<?= baseUrl('api/chat_send.php') ?>';
const TYPING_URL = '<?= baseUrl('api/chat_typing.php') ?>';

let lastId = <?= (int)$lastId ?>;
let typingTimeout = null;
let isPolling = false;

const chatBody  = document.getElementById('chatBody');
const msgInput  = document.getElementById('msgInput');
const sendBtn   = document.getElementById('sendBtn');
const typingEl  = document.getElementById('typingIndicator');
const typingTxt = document.getElementById('typingText');

function scrollToBottom() {
    chatBody.scrollTop = chatBody.scrollHeight;
}
scrollToBottom();

function fillMsg(text) {
    msgInput.value = text;
    msgInput.focus();
    msgInput.dispatchEvent(new Event('input'));
}

function renderMessage(m) {
    const isMine = parseInt(m.sender_id) === MY_ID && m.sender_role === 'customer';
    const roleClass = isMine ? 'mine' : (m.sender_role === 'ai' ? 'ai' : 'staff');
    const bubbleClass = isMine ? 'customer' : (m.sender_role === 'ai' ? 'ai' : 'staff');
    const avatarClass = bubbleClass;

    let displayName = 'Support Team';
    if (m.sender_role === 'ai') displayName = 'AI Assistant';
    else if (m.sender_role === 'staff') displayName = m.sender_name || 'Support Team';

    const tick = isMine ? '<span class="tick sent" data-tick="' + m.id + '">✓</span>' : '';
    const avatar = isMine ? '' : '<div class="msg-avatar ' + avatarClass + '">' + (m.sender_role === 'ai' ? '🤖' : displayName.charAt(0).toUpperCase()) + '</div>';

    const html = `
        <div class="msg-row ${roleClass}" data-msg-id="${m.id}">
            ${avatar}
            <div>
                <div class="msg-bubble ${bubbleClass}">${escapeHtml(m.message)}</div>
                <div class="msg-meta">${escapeHtml(displayName)} · just now ${tick}</div>
            </div>
        </div>`;
    chatBody.insertAdjacentHTML('beforeend', html);
    scrollToBottom();
}

function escapeHtml(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
}

function updateTicks(statuses) {
    for (const [id, s] of Object.entries(statuses)) {
        const tick = document.querySelector(`[data-tick="${id}"]`);
        if (!tick) continue;
        if (s.read_at) {
            tick.className = 'tick read';
            tick.textContent = '✓✓';
            tick.title = 'Seen';
        } else if (s.delivered_at) {
            tick.className = 'tick delivered';
            tick.textContent = '✓✓';
            tick.title = 'Delivered';
        } else {
            tick.className = 'tick sent';
            tick.textContent = '✓';
            tick.title = 'Sent';
        }
    }
}

async function poll() {
    if (isPolling) return;
    isPolling = true;
    try {
        const res = await fetch(`${POLL_URL}?conversation_id=${CONV_ID}&since_id=${lastId}`);
        const data = await res.json();
        if (!data.success) return;

        // New messages
        if (data.messages && data.messages.length > 0) {
            data.messages.forEach(m => {
                renderMessage(m);
                lastId = Math.max(lastId, parseInt(m.id));
            });
        }

        // Update ticks for my messages
        if (data.statuses) updateTicks(data.statuses);

        // Typing indicator
        if (data.other_typing) {
            typingEl.classList.add('active');
            typingTxt.textContent = 'Support is typing...';
        } else {
            typingEl.classList.remove('active');
        }
    } catch (e) {
        console.error('Poll error:', e);
    } finally {
        isPolling = false;
    }
}

async function sendMessage() {
    const text = msgInput.value.trim();
    if (!text) return;

    sendBtn.disabled = true;
    msgInput.disabled = true;

    // Optimistic render
    const tempId = 'temp-' + Date.now();
    const tempHtml = `
        <div class="msg-row mine" data-msg-id="${tempId}">
            <div>
                <div class="msg-bubble customer">${escapeHtml(text)}</div>
                <div class="msg-meta">You · sending... <span class="tick sent">🕐</span></div>
            </div>
        </div>`;
    chatBody.insertAdjacentHTML('beforeend', tempHtml);
    scrollToBottom();

    msgInput.value = '';
    msgInput.style.height = 'auto';

    try {
        const res = await fetch(SEND_URL, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'conversation_id=' + CONV_ID + '&message=' + encodeURIComponent(text)
        });
        const data = await res.json();
        if (data.success) {
            // Remove temp message; the real one will arrive via poll
            document.querySelector(`[data-msg-id="${tempId}"]`)?.remove();
            lastId = Math.max(lastId, parseInt(data.message_id) - 1); // will pick up from next
            setTimeout(poll, 300);
        } else {
            alert('Failed to send: ' + (data.error || 'Unknown error'));
            document.querySelector(`[data-msg-id="${tempId}"]`)?.remove();
            msgInput.value = text;
        }
    } catch (e) {
        alert('Error: ' + e.message);
        document.querySelector(`[data-msg-id="${tempId}"]`)?.remove();
        msgInput.value = text;
    } finally {
        sendBtn.disabled = false;
        msgInput.disabled = false;
        msgInput.focus();
    }
}

async function notifyTyping() {
    try {
        await fetch(TYPING_URL, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'conversation_id=' + CONV_ID
        });
    } catch (e) {}
}

// Textarea events
msgInput.addEventListener('input', () => {
    msgInput.style.height = 'auto';
    msgInput.style.height = Math.min(msgInput.scrollHeight, 120) + 'px';

    // Typing indicator (throttle: 1 update every 2 seconds)
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(notifyTyping, 300);
});

msgInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

sendBtn.addEventListener('click', sendMessage);

// Poll every 2 seconds
setInterval(poll, 2000);
poll();
</script>