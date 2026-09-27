<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

// If already logged in, no need to reset
if (isset($_SESSION['user_id'])) {
    redirect('customer_dashboard.php');
}

$error   = '';
$success = '';
$sendOtp = false;
$otpEmail = '';
$otpCode  = '';
$otpName  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Look up the user by email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            // Do not reveal whether the email exists (prevents enumeration)
            $success = 'If that email is registered, you will receive a 6-digit code shortly.';
        } else {
            // Rate limit: max 3 reset OTPs per 5 minutes per email
            $rl = $pdo->prepare("SELECT COUNT(*) FROM email_otps WHERE email = ? AND purpose = 'reset' AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
            $rl->execute([$user['email']]);
            if ($rl->fetchColumn() >= 3) {
                $error = 'Too many reset requests. Please wait 5 minutes and try again.';
            } else {
                // Invalidate old unused reset OTPs
                $pdo->prepare("UPDATE email_otps SET used = 1 WHERE user_id = ? AND purpose = 'reset' AND used = 0")
                    ->execute([$user['id']]);

                // Generate OTP — expires in 2 minutes
                $otp       = generateOTP(6);
                $expiresAt = date('Y-m-d H:i:s', strtotime('+2 minutes'));

                $pdo->prepare("
                    INSERT INTO email_otps (user_id, email, otp_code, purpose, expires_at, used)
                    VALUES (?, ?, ?, 'reset', ?, 0)
                ")->execute([$user['id'], $user['email'], $otp, $expiresAt]);

                // Session: remember who we're resetting for
                $_SESSION['reset_user_id']    = $user['id'];
                $_SESSION['reset_user_email'] = $user['email'];
                $_SESSION['reset_user_name']  = $user['full_name'] ?: $user['username'];

                // Pass to page for client-side EmailJS send
                $sendOtp  = true;
                $otpEmail = $user['email'];
                $otpCode  = $otp;
                $otpName  = $user['full_name'] ?: $user['username'];

                $success = 'A 6-digit verification code has been sent to your email.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — E-Commerce Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- EmailJS SDK -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
    <script>
        (function() {
            emailjs.init("3wNkVwO4O9bDjbwoz"); // ← Your public key
        })();
    </script>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 15px; line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            background: #f4f7fb; color: #1f2937;
        }
        .auth-bg {
            min-height: 100vh; padding: 40px 20px;
            background:
                radial-gradient(circle at 15% 15%, rgba(37,99,235,0.10), transparent 50%),
                radial-gradient(circle at 85% 85%, rgba(29,78,216,0.10), transparent 50%),
                linear-gradient(180deg, #f4f7fb 0%, #eef2f9 100%);
            display: flex; align-items: center; justify-content: center;
        }
        .auth-card {
            width: 100%; max-width: 520px;
            background: #fff; border: 1px solid #e5e7eb;
            border-radius: 20px; padding: 44px 40px;
            box-shadow: 0 20px 60px rgba(16,24,40,0.08), 0 4px 12px rgba(16,24,40,0.04);
            position: relative; overflow: hidden;
        }
        .auth-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0;
            height: 4px; background: linear-gradient(90deg, #2563eb, #1d4ed8, #60a5fa);
        }
        .auth-header { text-align: center; margin-bottom: 28px; }
        .auth-logo {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 18px;
            display: inline-flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1.75rem;
            margin-bottom: 18px;
            box-shadow: 0 12px 28px rgba(37,99,235,0.32);
            text-decoration: none;
        }
        .auth-header h1 {
            font-size: 1.6rem; font-weight: 800; color: #111827;
            margin: 0 0 8px; letter-spacing: -0.025em;
        }
        .auth-header p { color: #6b7280; font-size: 0.92rem; margin: 0; }

        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.88rem;
            color: #1d4ed8;
            line-height: 1.5;
        }
        .info-box strong { color: #111827; }

        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 0.88rem;
            margin-bottom: 20px;
            border: 1px solid;
            line-height: 1.5;
        }
        .alert-error   { background: #fef2f2; color: #991b1b; border-color: #fca5a5; }
        .alert-success { background: #ecfdf5; color: #065f46; border-color: #6ee7b7; }

        .form-group { margin-bottom: 18px; }
        .form-label {
            display: block; font-size: 0.8rem; font-weight: 600;
            color: #374151; margin-bottom: 8px;
        }
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.95rem;
            color: #111827;
            background: #fff;
            font-family: inherit;
            transition: all 0.15s;
        }
        .form-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37,99,235,0.12);
        }
        .form-hint {
            font-size: 0.78rem; color: #6b7280; margin-top: 8px;
        }

        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: 8px; padding: 14px 20px; border-radius: 12px;
            font-size: 0.95rem; font-weight: 700; font-family: inherit;
            cursor: pointer; border: none; text-decoration: none;
            transition: all 0.15s; line-height: 1.2; width: 100%;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            box-shadow: 0 8px 24px rgba(37,99,235,0.32);
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 32px rgba(37,99,235,0.42);
        }
        .btn-secondary {
            background: #fff; color: #374151;
            border: 1.5px solid #e5e7eb;
        }
        .btn-secondary:hover { background: #f4f7fb; border-color: #d1d5db; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none !important; }

        .footer-links {
            text-align: center; margin-top: 24px; padding-top: 22px;
            border-top: 1px solid #f3f4f6;
        }
        .footer-links p {
            color: #6b7280; font-size: 0.85rem; margin: 0 0 12px;
        }
        .footer-links a {
            color: #2563eb; text-decoration: none; font-weight: 600;
            font-size: 0.85rem;
        }
        .footer-links a:hover { text-decoration: underline; }

        @media (max-width: 500px) {
            .auth-card { padding: 32px 22px; }
        }
    </style>
</head>
<body>
<div class="auth-bg">
    <div class="auth-card">
        <div class="auth-header">
            <a href="<?= rootUrl('landing.php') ?>" class="auth-logo">🔑</a>
            <h1>Forgot Password?</h1>
            <p>Enter your registered email and we'll send you a 6-digit reset code</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success && !$sendOtp): ?>
            <div class="alert alert-success">✅ <?= e($success) ?></div>
        <?php endif; ?>

        <?php if (!$sendOtp): ?>
        <form method="POST" id="forgotForm">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-input" placeholder="you@example.com" required autofocus>
                <div class="form-hint">We'll send a reset code to this email if it matches our records. The code expires in <strong>2 minutes</strong>.</div>
            </div>
            <button type="submit" class="btn btn-primary">Send Reset Code</button>
        </form>
        <?php endif; ?>

        <div class="footer-links">
            <p>Remembered your password?</p>
            <a href="<?= baseUrl('login.php') ?>">← Back to Sign In</a>
        </div>
    </div>
</div>

<?php if ($sendOtp): ?>
<script>
// Automatically send the OTP email via EmailJS when the page loads
(async function sendResetOtp() {
    try {
        await emailjs.send(
            'service_h6zywtr',   // ← Your EmailJS service ID
            'template_s050h21',  // ← Your EmailJS template ID (same as register)
            {
                email:    <?= json_encode($otpEmail) ?>,
                passcode: <?= json_encode($otpCode) ?>,
                name:     <?= json_encode($otpName) ?>
            }
        );
        // Redirect to OTP verification step
        window.location.href = '<?= baseUrl('auth/verify_reset_otp.php') ?>?sent=1';
    } catch (err) {
        console.error('EmailJS error:', err);
        alert('Could not send the reset code. Please try again.\n\n' + (err.text || err.message || 'Unknown error'));
        // Reload page so user can retry
        window.location.href = '<?= baseUrl('auth/forgot_password.php') ?>?error=send';
    }
})();
</script>
<noscript>
    <div class="alert alert-error">Please enable JavaScript to receive your reset code.</div>
</noscript>
<?php endif; ?>

</body>
</html>