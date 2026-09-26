<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

$productId = (int)($_POST['product_id'] ?? 0);
if (isset($_SESSION['cart'][$productId])) {
    unset($_SESSION['cart'][$productId]);
}
echo json_encode(['success' => true, 'cart_count' => array_sum($_SESSION['cart'] ?? [])]);