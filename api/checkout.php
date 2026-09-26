<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if (empty($_SESSION['cart'])) {
    echo json_encode(['success' => false, 'msg' => 'Cart is empty']);
    exit;
}

$payment = $_POST['payment'] ?? '';
$delivery = $_POST['delivery'] ?? 'standard';
$uid = $_SESSION['user_id'];

$allowedMethods = ['GCash', 'Card', 'COD'];
if (!in_array($payment, $allowedMethods, true)) {
    echo json_encode(['success' => false, 'msg' => 'Please select a valid payment method.']);
    exit;
}

// Delivery fees (₱)
$deliveryOptions = [
    'standard'  => ['label' => 'Standard (3-5 days)', 'fee' => 0],
    'express'   => ['label' => 'Express (1-2 days)',  'fee' => 150],
    'sameday'   => ['label' => 'Same-day Delivery',   'fee' => 350],
];
if (!array_key_exists($delivery, $deliveryOptions)) {
    echo json_encode(['success' => false, 'msg' => 'Invalid delivery option.']);
    exit;
}
$deliveryFee = $deliveryOptions[$delivery]['fee'];

$u = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$u->execute([$uid]);
$user = $u->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'msg' => 'User not found']);
    exit;
}

$fullAddress = trim(
    ($user['address'] ?? '') . ', ' .
    ($user['city'] ?? '') . ', ' .
    ($user['province'] ?? '') . ' ' .
    ($user['postal_code'] ?? '')
, ', ');

if (!$fullAddress || strlen($fullAddress) < 10) {
    echo json_encode(['success' => false, 'msg' => 'Your profile address is incomplete. Please update your account.']);
    exit;
}

try {
    $pdo->beginTransaction();
    $itemsTotal = 0;
    $items = [];
    foreach ($_SESSION['cart'] as $pid => $qty) {
        $s = $pdo->prepare("SELECT * FROM products WHERE id = ? FOR UPDATE");
        $s->execute([$pid]);
        $p = $s->fetch();
        if (!$p || $p['stock'] < $qty) {
            throw new Exception("Out of stock: " . ($p['name'] ?? 'unknown'));
        }
        $itemsTotal += $p['price'] * $qty;
        $items[] = ['product' => $p, 'qty' => $qty];
    }

    $total = $itemsTotal + $deliveryFee;
    $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

    $pdo->prepare("
        INSERT INTO orders (order_number, user_id, total_amount, status, shipping_address, contact_phone, payment_method, payment_status, delivery_option, delivery_fee, created_at)
        VALUES (?, ?, ?, 'pending', ?, ?, ?, 'unpaid', ?, ?, NOW())
    ")->execute([$orderNumber, $uid, $total, $fullAddress, $user['phone'], $payment, $delivery, $deliveryFee]);
    $orderId = $pdo->lastInsertId();

    foreach ($items as $it) {
        $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)")
            ->execute([$orderId, $it['product']['id'], $it['qty'], $it['product']['price']]);
        $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")
            ->execute([$it['qty'], $it['product']['id']]);
    }

    $pdo->prepare("INSERT INTO order_tracking (order_id, status, message, location) VALUES (?, 'pending', ?, 'Processing Center')")
        ->execute([$orderId, 'Order ' . $orderNumber . ' placed with ' . $deliveryOptions[$delivery]['label']]);

    $pdo->commit();
    $_SESSION['cart'] = [];

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'order_number' => $orderNumber,
        'total' => $total,
        'delivery_fee' => $deliveryFee,
        'redirect' => baseUrl('modules/payment.php?id=' . $orderId)
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}