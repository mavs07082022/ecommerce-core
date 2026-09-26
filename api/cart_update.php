<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pid = (int)($_POST['product_id'] ?? 0);
$qty = (int)($_POST['quantity'] ?? 0);

if (!$pid) {
    echo json_encode(['success' => false, 'msg' => 'Invalid product']);
    exit;
}

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

if ($qty <= 0) {
    unset($_SESSION['cart'][$pid]);
} else {
    // Check stock
    $s = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
    $s->execute([$pid]);
    $stock = $s->fetchColumn();

    if ($stock === false) {
        echo json_encode(['success' => false, 'msg' => 'Product not found']);
        exit;
    }
    if ($stock < $qty) {
        echo json_encode(['success' => false, 'msg' => 'Only ' . $stock . ' in stock']);
        exit;
    }
    $_SESSION['cart'][$pid] = $qty;
}

echo json_encode(['success' => true, 'cart_count' => array_sum($_SESSION['cart'])]);