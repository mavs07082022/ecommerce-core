<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireProductManager();

$pageTitle = 'Customer Messages — E-Commerce Core';
$uid  = $_SESSION['user_id'];
$role = $_SESSION['role'];

$convId = (int)($_GET['c'] ?? 0);

// List all conversations
$convs = $pdo->query("
    SELECT c.*, u.username, u.full_name,
        (SELECT message FROM chat_messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1) AS last_msg,
        (SELECT COUNT(*) FROM chat_messages WHERE conversation_id = c.id AND is_read = 0 AND sender_role = 'customer') AS unread
    FROM chat_conversations c
    JOIN users u ON c.customer_id = u.id
    WHERE c.status = 'open'
    ORDER BY c.last_message_at DESC
")->fetchAll();

$selectedConv = null;
if ($convId) {
    $c = $pdo->prepare("SELECT c.*, u.username, u.full_name, u.email FROM chat_conversations c JOIN users u ON c.customer_id = u.id WHERE c.id = ?");
    $c->execute([$convId]);
    $selectedConv = $c->fetch();
} elseif (!empty($convs)) {
    $convId = $convs[0]['id'];
    $c = $pdo->prepare("SELECT c.*, u.username, u.full_name, u.email FROM chat_conversations c JOIN users u ON c.customer_id = u.id WHERE c.id = ?");
    $c->execute([$convId]);
    $selectedConv = $c->fetch();
}

// Close conversation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_conv'])) {
    $cid = (int)$_POST['conversation_id'];
    $pdo->prepare("UPDATE chat_conversations SET status = 'closed' WHERE id = ?")->execute([$cid]);
    header("Location: " . moduleUrl('staff_chat.php'));
    exit;
}

// Mark read
if ($convId) {
    $pdo->prepare("UPDATE chat_messages SET is_read = 1, read_at = NOW() WHERE conversation_id = ? AND sender_role = 'customer' AND read_at IS NULL")->execute([$convId]);
    $pdo->prepare("UPDATE chat_messages SET delivered_at = NOW() WHERE conversation_id = ? AND sender_id != ? AND sender_role NOT IN ('ai') AND delivered_at IS NULL")->execute([$convId, $uid]);
    $pdo->prepare("UPDATE chat_conversations SET needs_human = 0 WHERE id = ?")->execute([$convId]);
}

$messages = [];
$lastId = 0;
if ($convId) {
    $m = $pdo->prepare("
        SELECT cm.*, u.full_name AS sender_name, u.username AS sender_username
        FROM chat_messages cm
        LEFT JOIN users u ON cm.sender_id = u.id
        WHERE cm.conversation_id = ?
        ORDER BY cm.id ASC
    ");
    $m->execute([$convId]);
    $messages = $m->fetchAll();
    if (!empty($messages)) $lastId = (int)end($messages)['id'];
}

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.staff-chat-layout {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 20px;
    height: calc(100vh - 180px);
    min-height: 560px;
}
@media (max-width: 900px) { .staff-chat-layout { grid-template-columns: 1fr; height: auto; } }

.conv-list {
    background: #fff; border: 1px solid #e5e7eb;
    border-radius: 14px; overflow-y: auto;
}
.conv-item {
    display: flex; gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid #f3f4f6;
    cursor: pointer; text-decoration: none; color: inherit;
    transition: background 0.15s;
}
.conv-item:hover { background: #f9fafb; }
.conv-item.active { background: #eff6ff; border-left: 3px solid #2563eb; }
.conv-avatar {
    width: 40px; height: 40px;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.85rem; flex-shrink: 0;
}
.conv-info { flex: 1; min-width: 0; }
.conv-name { font-weight: 700; color: #111827; font-size: 0.88rem; }
.conv-preview {
    color: #6b7280; font-size: 0.78rem;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    margin-top: 2px;
}
.conv-time { color: #9ca3af; font-size: 0.7rem; }
.conv-badge {
    display: inline-block;
    background: #dc2626; color: #fff;
    font-size: 0.65rem; font-weight: 700;
    padding: 2px 7px; border-radius: 999px;
    margin-left: 6px;
}
.conv-badge.human {
    background: #f59e0b;
    animation: pulse 1.5s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.chat-area {
    background: #fff; border: 1px solid #e5e7eb;
    border-radius: 14px;
    display: flex; flex-direction: column; overflow: hidden;
}
.chat-area-head {
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 10px;
}
.chat-msgs {
    flex: 1; padding: 20px;
    overflow-y: auto; background: #f4f7fb;
    display: flex; flex-direction: column; gap: 14px;
}
.msg-row { display: flex; gap: 10px; align-items: flex-end; animation: fadeIn 0.2s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
.msg-avatar {
    width: 32px; height: 32px; border-radius: 10px;
    flex-shrink: 0; display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.75rem; color: #fff;
}
.msg-avatar.customer { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
.msg-avatar.ai       { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
.msg-avatar.staff    { background: linear-gradient(135deg, #059669, #047857); }
.msg-bubble {
    max-width: 70%;
    padding: 11px 15px;
    border-radius: 14px;
    font-size: 0.9rem; line-height: 1.55;
    white-space: pre-line; word-wrap: break-word;
}
.msg-bubble.customer { background: #fff; color: #111827; border: 1px solid #e5e7eb; border-bottom-left-radius: 4px; }
.msg-bubble.ai       { background: #f3e8ff; color: #6b21a8; border: 1px solid #d8b4fe; border-bottom-left-radius: 4px; }
.msg-bubble.staff    { background: linear-gradient(135deg, #059669, #047857); color: #fff; border-bottom-right-radius: 4px; }
.msg-meta {
    font-size: 0.7rem; color: #9ca3af;
    margin-top: 4px; padding: 0 4px;
    display: flex; align-items: center; gap: 5px;
}
.msg-row.mine .msg-meta { justify-content: flex-end; }
.tick { display: inline-flex; align-items: center; font-size: 0.75rem; font-weight: 700; margin-left: 4px; }
.tick.sent { color: #9ca3af; }
.tick.delivered { color: #9ca3af; }
.tick.read { color: #60a5fa; }

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

.typing-indicator {
    display: none;
    align-items: center; gap: 6px;
    font-size: 0.78rem; color: #6b7280;
    padding: 6px 20px 0;
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
                    <h2>Customer Messages</h2>
                    <p><?= count($convs) ?> open conversation<?= count($convs) != 1 ? 's' : '' ?></p>
                </div>
            </div>
        </header>

        <div class="page-body">
            <?php if (empty($convs)): ?>
                <div class="card card-pad" style="text-align:center;padding:60px 20px;">
                    <div style="font-size:3rem;opacity:0.4;margin-bottom:12px;">💬</div>
                    <h3 style="margin:0 0 6px;color:#111827;">No messages yet</h3>
                    <p style="color:#6b7280;margin:0;">Customer messages will appear here.</p>
                </div>
            <?php else: ?>
                <div class="staff-chat-layout">
                    <div class="conv-list" id="convList">
                        <?php foreach ($convs as $cv): ?>
                            <a href="<?= moduleUrl('staff_chat.php?c=' . $cv['id']) ?>" class="conv-item <?= $cv['id'] == $convId ? 'active' : '' ?>">
                                <div class="conv-avatar"><?= strtoupper(substr($cv['full_name'] ?: $cv['username'], 0, 1)) ?></div>
                                <div class="conv-info">
                                    <div class="conv-name">
                                        <?= e($cv['full_name'] ?: $cv['username']) ?>
                                        <?php if ($cv['unread'] > 0): ?>
                                            <span class="conv-badge"><?= (int)$cv['unread'] ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($cv['needs_human'])): ?>
                                            <span class="conv-badge human">🙋 Needs human</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="conv-preview"><?= e($cv['last_msg'] ?? 'No messages') ?></div>
                                </div>
                                <div class="conv-time"><?= timeAgo($cv['last_message_at']) ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="chat-area">
                        <?php if ($selectedConv): ?>
                            <div class="chat-area-head">
                                <div>
                                    <div style="font-weight:700;color:#111827;"><?= e($selectedConv['full_name'] ?: $selectedConv['username']) ?></div>
                                    <div style="color:#6b7280;font-size:0.8rem;"><?= e($selectedConv['email']) ?></div>
                                </div>
                                <form method="POST" onsubmit="return confirm('Close this conversation?')">
                                    <input type="hidden" name="conversation_id" value="<?= $convId ?>">
                                    <button type="submit" name="close_conv" class="btn btn-secondary btn-sm">Close</button>
                                </form>
                            </div>

                            <div class="chat-msgs" id="staffChatBody">
                                <?php foreach ($messages as $m):
                                    $isStaff = in_array($m['sender_role'], ['admin','product_manager']);
                                    $roleClass = $isStaff ? 'mine' : ($m['sender_role'] === 'ai' ? 'ai' : 'customer');
                                    $bubbleClass = $isStaff ? 'staff' : ($m['sender_role'] === 'ai' ? 'ai' : 'customer');
                                    $avatarClass = $bubbleClass;

                                    $displayName = 'Customer';
                                    if ($m['sender_role'] === 'ai') $displayName = 'AI Assistant';
                                    elseif ($isStaff) $displayName = $m['sender_name'] ?: 'Staff';
                                    elseif ($m['sender_role'] === 'customer') $displayName = $selectedConv['full_name'] ?: $selectedConv['username'];

                                    $tick = '';
                                    if ($isStaff) {
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
                                        <?php if (!$isStaff): ?>
                                            <div class="msg-avatar <?= $avatarClass ?>">
                                                <?= $m['sender_role'] === 'ai' ? '🤖' : strtoupper(substr($displayName, 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="msg-bubble <?= $bubbleClass ?>"><?= e($m['message']) ?></div>
                                            <div class="msg-meta" style="<?= $isStaff ? 'text-align:right;justify-content:flex-end;' : '' ?>">
                                                <?= e($displayName) ?> · <?= timeAgo($m['created_at']) ?><?= $tick ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="typing-indicator" id="staffTypingIndicator">
                                <div class="typing-dots"><span></span><span></span><span></span></div>
                                <span>Customer is typing...</span>
                            </div>

                            <div class="chat-input">
                                <textarea id="staffMsgInput" placeholder="Type your reply..." rows="1"></textarea>
                                <button type="button" id="staffSendBtn" onclick="staffSend()">Send →</button>
                            </div>
                        <?php else: ?>
                            <div style="padding:60px 20px;text-align:center;color:#6b7280;">Select a conversation</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<?php if ($selectedConv): ?>
<script>
const CONV_ID    = <?= (int)$convId ?>;
const MY_ID      = <?= (int)$uid ?>;
const POLL_URL   = '<?= baseUrl('api/chat_poll.php') ?>';
const SEND_URL   = '<?= baseUrl('api/chat_send.php') ?>';
const TYPING_URL = '<?= baseUrl('api/chat_typing.php') ?>';

let lastId = <?= (int)$lastId ?>;
let typingTimeout = null;
let isPolling = false;

const chatBody  = document.getElementById('staffChatBody');
const msgInput  = document.getElementById('staffMsgInput');
const sendBtn   = document.getElementById('staffSendBtn');
const typingEl  = document.getElementById('staffTypingIndicator');

function scrollToBottom() { chatBody.scrollTop = chatBody.scrollHeight; }
scrollToBottom();

function escapeHtml(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
}

function renderMessage(m) {
    const isStaff = m.sender_role === 'admin' || m.sender_role === 'product_manager';
    const roleClass = isStaff ? 'mine' : (m.sender_role === 'ai' ? 'ai' : 'customer');
    const bubbleClass = isStaff ? 'staff' : (m.sender_role === 'ai' ? 'ai' : 'customer');
    const avatarClass = bubbleClass;

    let displayName = 'Customer';
    if (m.sender_role === 'ai') displayName = 'AI Assistant';
    else if (isStaff) displayName = m.sender_name || 'Staff';

    const tick = isStaff ? '<span class="tick sent" data-tick="' + m.id + '">✓</span>' : '';
    const avatar = isStaff ? '' : '<div class="msg-avatar ' + avatarClass + '">' + (m.sender_role === 'ai' ? '🤖' : displayName.charAt(0).toUpperCase()) + '</div>';
    const align = isStaff ? 'style="justify-content:flex-end;"' : '';

    chatBody.insertAdjacentHTML('beforeend', `
        <div class="msg-row ${roleClass}" data-msg-id="${m.id}" ${align}>
            ${avatar}
            <div>
                <div class="msg-bubble ${bubbleClass}">${escapeHtml(m.message)}</div>
                <div class="msg-meta" ${isStaff ? 'style="text-align:right;justify-content:flex-end;"' : ''}>${escapeHtml(displayName)} · just now ${tick}</div>
            </div>
        </div>`);
    scrollToBottom();
}

function updateTicks(statuses) {
    for (const [id, s] of Object.entries(statuses)) {
        const tick = document.querySelector(`[data-tick="${id}"]`);
        if (!tick) continue;
        if (s.read_at) {
            tick.className = 'tick read'; tick.textContent = '✓✓'; tick.title = 'Seen';
        } else if (s.delivered_at) {
            tick.className = 'tick delivered'; tick.textContent = '✓✓'; tick.title = 'Delivered';
        } else {
            tick.className = 'tick sent'; tick.textContent = '✓'; tick.title = 'Sent';
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

        if (data.messages && data.messages.length > 0) {
            data.messages.forEach(m => {
                renderMessage(m);
                lastId = Math.max(lastId, parseInt(m.id));
            });
        }
        if (data.statuses) updateTicks(data.statuses);

        if (data.other_typing) typingEl.classList.add('active');
        else typingEl.classList.remove('active');
    } catch (e) { console.error(e); }
    finally { isPolling = false; }
}

async function staffSend() {
    const text = msgInput.value.trim();
    if (!text) return;

    sendBtn.disabled = true;
    msgInput.disabled = true;

    const tempId = 'temp-' + Date.now();
    chatBody.insertAdjacentHTML('beforeend', `
        <div class="msg-row mine" data-msg-id="${tempId}" style="justify-content:flex-end;">
            <div>
                <div class="msg-bubble staff">${escapeHtml(text)}</div>
                <div class="msg-meta" style="text-align:right;justify-content:flex-end;">You · sending... <span class="tick sent">🕐</span></div>
            </div>
        </div>`);
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
            document.querySelector(`[data-msg-id="${tempId}"]`)?.remove();
            lastId = Math.max(lastId, parseInt(data.message_id) - 1);
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

msgInput.addEventListener('input', () => {
    msgInput.style.height = 'auto';
    msgInput.style.height = Math.min(msgInput.scrollHeight, 120) + 'px';
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(notifyTyping, 300);
});

msgInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        staffSend();
    }
});

sendBtn.addEventListener('click', staffSend);

setInterval(poll, 2000);
poll();
</script>
<?php endif; ?>