<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectRoot('customer_dashboard.php');
}

$uid = $_SESSION['user_id'];
$orderId = (int)($_POST['order_id'] ?? 0);
$method = $_POST['method'] ?? '';
$reference = trim($_POST['reference'] ?? '');

// Helper: go back to payment page with an error
function backToPayment($orderId, $msg) {
    $_SESSION['payment_error'] = $msg;
    redirectRoot('modules/payment.php?id=' . $orderId);
}

// Validate method
$allowedMethods = ['GCash', 'Card', 'COD'];
if (!in_array($method, $allowedMethods, true)) {
    backToPayment($orderId, 'Please select a valid payment method.');
}

// Verify order ownership — must be UNPAID (not yet pending)
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? AND payment_status = 'unpaid'");
$stmt->execute([$orderId, $uid]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['payment_error'] = 'Order not found or already submitted.';
    redirectRoot('customer_dashboard.php');
}

// ==================== SERVER-SIDE VALIDATION ====================

if ($method === 'GCash') {
    if (!preg_match('/^\d{6,20}$/', $reference)) {
        backToPayment($orderId, 'GCash reference must be 6-20 digits.');
    }
    if (empty($_FILES['proof']['name']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
        backToPayment($orderId, 'Please upload a screenshot of your GCash receipt.');
    }
}

if ($method === 'Card') {
    $cardNumber = preg_replace('/\s/', '', $_POST['card_number'] ?? '');
    $cardExpiry = trim($_POST['card_expiry'] ?? '');
    $cardCvv    = trim($_POST['card_cvv'] ?? '');
    $cardName   = trim($_POST['card_name'] ?? '');

    if (strlen($cardNumber) < 15 || !ctype_digit($cardNumber)) {
        backToPayment($orderId, 'Please enter a valid card number.');
    }
    if (!preg_match('#^\d{2}/\d{2}$#', $cardExpiry)) {
        backToPayment($orderId, 'Please enter a valid expiry date (MM/YY).');
    }
    if (strlen($cardCvv) < 3 || !ctype_digit($cardCvv)) {
        backToPayment($orderId, 'Please enter a valid CVV.');
    }
    if ($cardName === '') {
        backToPayment($orderId, 'Please enter the cardholder name.');
    }

    $reference = 'CARD-' . substr($cardNumber, -4) . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
}

// ==================== UPLOAD PROOF (GCash) ====================
$proofPath = null;
if ($method === 'GCash' && !empty($_FILES['proof']['name'])) {
    $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($ext, $allowed, true)) {
        backToPayment($orderId, 'Screenshot must be JPG, PNG, GIF, or WEBP.');
    }

    if ($_FILES['proof']['size'] > 5 * 1024 * 1024) {
        backToPayment($orderId, 'Screenshot must be under 5MB.');
    }

    // Ensure uploads folder exists and is writable
    $uploadDir = __DIR__ . '/../assets/uploads/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            backToPayment($orderId, 'Server error: upload folder could not be created.');
        }
    }
    if (!is_writable($uploadDir)) {
        backToPayment($orderId, 'Server error: upload folder is not writable.');
    }

    $newName = 'pay_' . $orderId . '_' . uniqid() . '.' . $ext;
    $dest = $uploadDir . $newName;

    if (move_uploaded_file($_FILES['proof']['tmp_name'], $dest)) {
        $proofPath = 'assets/uploads/' . $newName;
    } else {
        backToPayment($orderId, 'Failed to upload screenshot. Please try again.');
    }
}

// ==================== DETERMINE NEW PAYMENT STATUS ====================
if ($method === 'COD') {
    $paymentStatus = 'unpaid';
    $trackingMsg = 'Order placed with Cash on Delivery. Payment will be collected upon delivery.';
} else {
    $paymentStatus = 'pending_verification';
    $trackingMsg = 'Payment submitted via ' . $method . '. Awaiting admin verification.';
}

// ==================== UPDATE ORDER ====================
try {
    $pdo->prepare("
        UPDATE orders
        SET payment_method = ?, payment_status = ?, payment_reference = ?, payment_proof = ?
        WHERE id = ?
    ")->execute([$method, $paymentStatus, $reference ?: null, $proofPath, $orderId]);

    $pdo->prepare("INSERT INTO order_tracking (order_id, status, message, location) VALUES (?, ?, ?, ?)")
        ->execute([$orderId, $order['status'], $trackingMsg, 'Payment Center']);

} catch (PDOException $e) {
    backToPayment($orderId, 'Database error: ' . $e->getMessage());
}

// SUCCESS — redirect to tracking page with success flag
$_SESSION['payment_success'] = 'Your payment has been submitted and is awaiting verification.';
redirectRoot('modules/track_order.php?id=' . $orderId);