<?php
require '../includes/auth.php';
require '../config/db.php';
header('Content-Type: application/json');

$productId = (int)($_POST['product_id'] ?? 0);
$qty = max(1, (int)($_POST['quantity'] ?? 1));

if (!$productId) { echo json_encode(['success'=>false,'msg'=>'Invalid product']); exit; }

// Check stock
$stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
$stmt->execute([$productId]);
$stock = $stmt->fetchColumn();

if ($stock === false) { echo json_encode(['success'=>false,'msg'=>'Product not found']); exit; }
if ($stock < $qty) { echo json_encode(['success'=>false,'msg'=>'Not enough stock']); exit; }

// Init cart
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

if (isset($_SESSION['cart'][$productId])) {
    $_SESSION['cart'][$productId] += $qty;
} else {
    $_SESSION['cart'][$productId] = $qty;
}

echo json_encode(['success'=>true, 'cart_count' => array_sum($_SESSION['cart'])]);