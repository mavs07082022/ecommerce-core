<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

header('Content-Type: application/json');

$uid    = $_SESSION['user_id'];
$role   = $_SESSION['role'];
$convId = (int)($_POST['conversation_id'] ?? 0);

if (!$convId) {
    echo json_encode(['success' => false]);
    exit;
}

// Verify access
if (isCustomer()) {
    $chk = $pdo->prepare("SELECT id FROM chat_conversations WHERE id = ? AND customer_id = ?");
    $chk->execute([$convId, $uid]);
    $col = 'customer_typing_at';
} else {
    $chk = $pdo->prepare("SELECT id FROM chat_conversations WHERE id = ?");
    $chk->execute([$convId]);
    $col = 'staff_typing_at';
}
if (!$chk->fetch()) {
    echo json_encode(['success' => false]);
    exit;
}

$pdo->prepare("UPDATE chat_conversations SET $col = NOW() WHERE id = ?")->execute([$convId]);

echo json_encode(['success' => true]);