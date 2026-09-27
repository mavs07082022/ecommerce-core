<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

header('Content-Type: application/json');

$uid  = $_SESSION['user_id'];
$role = $_SESSION['role'];
$convId = (int)($_GET['conversation_id'] ?? 0);
$sinceId = (int)($_GET['since_id'] ?? 0);

if (!$convId) {
    echo json_encode(['success' => false, 'error' => 'Missing conversation_id']);
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

// 1. Mark messages as delivered (any message not sent by this user, delivered_at IS NULL)
$pdo->prepare("
    UPDATE chat_messages
    SET delivered_at = NOW()
    WHERE conversation_id = ?
      AND sender_id != ?
      AND sender_role != 'ai'
      AND delivered_at IS NULL
")->execute([$convId, $uid]);

// 2. Mark messages as read (any message not sent by this user, read_at IS NULL)
$pdo->prepare("
    UPDATE chat_messages
    SET read_at = NOW(), is_read = 1
    WHERE conversation_id = ?
      AND sender_id != ?
      AND sender_role != 'ai'
      AND read_at IS NULL
")->execute([$convId, $uid]);

// 3. Fetch new messages since $sinceId
$stmt = $pdo->prepare("
    SELECT cm.*, u.full_name AS sender_name, u.username AS sender_username
    FROM chat_messages cm
    LEFT JOIN users u ON cm.sender_id = u.id
    WHERE cm.conversation_id = ? AND cm.id > ?
    ORDER BY cm.id ASC
");
$stmt->execute([$convId, $sinceId]);
$newMessages = $stmt->fetchAll();

// 4. Also re-fetch status of messages already shown (to update ✓/✓✓ ticks)
$stmt2 = $pdo->prepare("
    SELECT id, delivered_at, read_at, is_read
    FROM chat_messages
    WHERE conversation_id = ?
      AND sender_id = ?
      AND sender_role != 'ai'
    ORDER BY id DESC LIMIT 30
");
$stmt2->execute([$convId, $uid]);
$myStatuses = [];
foreach ($stmt2->fetchAll() as $row) {
    $myStatuses[$row['id']] = [
        'delivered_at' => $row['delivered_at'],
        'read_at'      => $row['read_at'],
        'is_read'      => (int)$row['is_read'],
    ];
}

// 5. Typing indicator (from the OTHER side)
$convStmt = $pdo->prepare("SELECT customer_typing_at, staff_typing_at, needs_human FROM chat_conversations WHERE id = ?");
$convStmt->execute([$convId]);
$convRow = $convStmt->fetch();

$otherTyping = false;
if (isCustomer()) {
    if ($convRow['staff_typing_at'] && (time() - strtotime($convRow['staff_typing_at']) < 5)) {
        $otherTyping = true;
    }
} else {
    if ($convRow['customer_typing_at'] && (time() - strtotime($convRow['customer_typing_at']) < 5)) {
        $otherTyping = true;
    }
}

echo json_encode([
    'success'       => true,
    'messages'      => $newMessages,
    'statuses'      => $myStatuses,
    'other_typing'  => $otherTyping,
    'needs_human'   => (int)$convRow['needs_human'],
    'server_time'   => date('c'),
]);