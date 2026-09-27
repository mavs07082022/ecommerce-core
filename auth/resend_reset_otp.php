<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$email = $_SESSION['reset_user_email'] ?? '';
$name  = $_SESSION['reset_user_name']  ?? 'Customer';

if (!$email) {
    echo json_encode(['success' => false, 'error' => 'No reset session found. Please start over.']);
    exit;
}

// Rate limit: max 3 reset OTPs per 5 minutes
$stmt = $pdo->prepare("SELECT COUNT(*) FROM email_otps WHERE email = ? AND purpose = 'reset' AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
$stmt->execute([$email]);
if ($stmt->fetchColumn() >= 3) {
    echo json_encode(['success' => false, 'error' => 'Too many requests. Please wait 5 minutes.']);
    exit;
}

try {
    // Invalidate old unused reset OTPs
    $pdo->prepare("UPDATE email_otps SET used = 1 WHERE email = ? AND purpose = 'reset' AND used = 0")
        ->execute([$email]);

    // Get user_id
    $u = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $u->execute([$email]);
    $userId = $u->fetchColumn();

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'User not found.']);
        exit;
    }

    // Generate new OTP — expires in 2 minutes
    $otp = generateOTP(6);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+2 minutes'));

    $pdo->prepare("INSERT INTO email_otps (user_id, email, otp_code, purpose, expires_at) VALUES (?, ?, ?, 'reset', ?)")
        ->execute([$userId, $email, $otp, $expiresAt]);

    // Return OTP so client-side EmailJS can send it
    echo json_encode([
        'success' => true,
        'otp'     => $otp,
        'email'   => $email,
        'name'    => $name
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}