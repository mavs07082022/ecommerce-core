<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

header('Content-Type: application/json');

$uid  = $_SESSION['user_id'];
$role = $_SESSION['role'];

$convId = (int)($_POST['conversation_id'] ?? 0);
$msg    = trim($_POST['message'] ?? '');

if (!$convId || $msg === '') {
    echo json_encode(['success' => false, 'error' => 'Missing conversation_id or message']);
    exit;
}

// Verify access
if (isCustomer()) {
    $chk = $pdo->prepare("SELECT id FROM chat_conversations WHERE id = ? AND customer_id = ?");
    $chk->execute([$convId, $uid]);
} else {
    $chk = $pdo->prepare("SELECT id FROM chat_conversations WHERE id = ?");
    $chk->execute([$convId]);
}
if (!$chk->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$senderRole = isCustomer() ? 'customer' : $role;

// Insert the message
$pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_id, sender_role, message) VALUES (?, ?, ?, ?)")
    ->execute([$convId, $uid, $senderRole, $msg]);
$msgId = $pdo->lastInsertId();

$pdo->prepare("UPDATE chat_conversations SET last_message_at = NOW() WHERE id = ?")->execute([$convId]);

// If staff is replying, assign staff_id
if (isStaff()) {
    $pdo->prepare("UPDATE chat_conversations SET staff_id = ? WHERE id = ?")->execute([$uid, $convId]);
}

// ===== AI auto-reply (only when customer sends) =====
$aiReply = null;
$escalated = false;

if (isCustomer()) {
    // Skip AI if the customer explicitly asks for a human
    if (preg_match('/\b(human|person|agent|staff|admin|real person|talk to someone)\b/i', $msg)) {
        $aiText = "👤 Got it — I've flagged this for our team. A real person will reply as soon as possible. In the meantime, feel free to leave more details here.";
        $pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_id, sender_role, message) VALUES (?, 0, 'ai', ?)")
            ->execute([$convId, $aiText]);
        $pdo->prepare("UPDATE chat_conversations SET needs_human = 1 WHERE id = ?")->execute([$convId]);
        $escalated = true;
    } else {
        $reply = generateAiReply($pdo, $msg, $uid);
        $pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_id, sender_role, message) VALUES (?, 0, 'ai', ?)")
            ->execute([$convId, $reply['text']]);
        if (!empty($reply['escalate'])) {
            $pdo->prepare("UPDATE chat_conversations SET needs_human = 1 WHERE id = ?")->execute([$convId]);
            $escalated = true;
        }
    }
}

// Return the customer's message so the UI can render it immediately
echo json_encode([
    'success'    => true,
    'message_id' => $msgId,
    'escalated'  => $escalated,
]);