<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$email = $_SESSION['pending_verification_email'] ?? '';
$name  = $_SESSION['pending_verification_name'] ?? 'Customer';

if (!$email) {
    echo json_encode(['success' => false, 'error' => 'No pending verification session.']);
    exit;
}

// Rate limit: max 3 OTPs per 5 minutes
$stmt = $pdo->prepare("SELECT COUNT(*) FROM email_otps WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
$stmt->execute([$email]);
if ($stmt->fetchColumn() >= 3) {
    echo json_encode(['success' => false, 'error' => 'Too many requests. Please wait 5 minutes.']);
    exit;
}

try {
    // Invalidate old unused OTPs
    $pdo->prepare("UPDATE email_otps SET used = 1 WHERE email = ? AND used = 0")->execute([$email]);

    // Get user_id
    $u = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $u->execute([$email]);
    $userId = $u->fetchColumn();

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'User not found.']);
        exit;
    }

    // Generate new OTP — expires in 10 minutes
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $pdo->prepare("INSERT INTO email_otps (user_id, email, otp_code, purpose, expires_at) VALUES (?, ?, ?, 'register', ?)")
        ->execute([$userId, $email, $otp, $expiresAt]);

    // Return OTP so client-side EmailJS can send it
    echo json_encode([
        'success' => true,
        'otp' => $otp,
        'email' => $email,
        'name' => $name
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}