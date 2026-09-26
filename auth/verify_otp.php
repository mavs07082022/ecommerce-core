<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$email = $_SESSION['pending_verification_email'] ?? '';
$name  = $_SESSION['pending_verification_name'] ?? 'there';

if (!$email) {
    redirect('register.php');
}

$error = '';
$sent = isset($_GET['sent']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');
    $otp = preg_replace('/\D/', '', $otp);

    if (strlen($otp) !== 6) {
        $error = 'Please enter all 6 digits.';
    } else {
        // Debug: fetch latest OTP for smarter errors
        $debug = $pdo->prepare("SELECT id, otp_code, expires_at, used FROM email_otps WHERE email = ? ORDER BY id DESC LIMIT 1");
        $debug->execute([$email]);
        $latest = $debug->fetch();

        $stmt = $pdo->prepare("
            SELECT * FROM email_otps
            WHERE email = ? AND otp_code = ? AND used = 0 AND expires_at > NOW()
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$email, $otp]);
        $record = $stmt->fetch();

        if ($record) {
            $pdo->prepare("UPDATE email_otps SET used = 1 WHERE id = ?")->execute([$record['id']]);
            $pdo->prepare("UPDATE users SET email_verified = 1 WHERE email = ?")->execute([$email]);

            $u = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $u->execute([$email]);
            $user = $u->fetch();

            if ($user) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];

                unset($_SESSION['pending_verification_email'], $_SESSION['pending_verification_name']);

                redirectRoot('customer_dashboard.php');
            } else {
                $error = 'Account not found. Please register again.';
            }
        } else {
            if (!$latest) {
                $error = 'No verification code found for this email. Please request a new one.';
            } elseif ($latest['used']) {
                $error = 'This code has already been used. Please request a new one.';
            } elseif (strtotime($latest['expires_at']) < time()) {
                $error = 'The code has expired. Please request a new one.';
            } elseif ($latest['otp_code'] !== $otp) {
                $error = 'Incorrect code. Please check your email and try again.';
            } else {
                $error = 'Invalid or expired code. Please try again.';
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
    <title>Verify Email — E-Commerce Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
    <script>
        (function() {
            emailjs.init("3wNkVwO4O9bDjbwoz"); // ← Replace
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
        .alert-warning { background: #fef3c7; color: #92400e; border-color: #fcd34d; }

        .otp-boxes { display: flex; gap: 10px; justify-content: center; margin: 26px 0; }
        .otp-input {
            width: 54px; height: 64px; text-align: center;
            font-size: 1.6rem; font-weight: 700; color: #111827;
            border: 2px solid #e5e7eb; border-radius: 12px;
            transition: all 0.15s; font-family: 'SF Mono', Monaco, monospace;
            background: #fff;
        }
        .otp-input:focus {
            outline: none; border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37,99,235,0.15);
        }
        .otp-input:not(:placeholder-shown) {
            border-color: #2563eb; background: #eff6ff;
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
            .otp-input { width: 42px; height: 54px; font-size: 1.3rem; }
            .otp-boxes { gap: 6px; }
        }
    </style>
</head>
<body>
<div class="auth-bg">
    <div class="auth-card">
        <div class="auth-header">
            <a href="<?= rootUrl('landing.php') ?>" class="auth-logo">✉</a>
            <h1>Verify Your Email</h1>
            <p>We sent a 6-digit code to your inbox</p>
        </div>

        <div class="info-box">
            We sent a code to <strong><?= e($email) ?></strong><br>
            <span style="font-size:0.8rem;color:#6b7280;display:inline-block;margin-top:4px;">⏱ This code expires in 10 minutes</span>
        </div>

        <?php if ($sent && !$error): ?>
            <div class="alert alert-success">✅ Verification code sent! Check your inbox (and Spam folder).</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="otpForm">
            <input type="hidden" name="otp" id="otpHidden">
            <div class="otp-boxes">
                <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autofocus>
                <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]">
            </div>
            <button type="submit" class="btn btn-primary">Verify &amp; Continue</button>
        </form>

        <div class="footer-links">
            <p>Didn't receive the code?</p>
            <button onclick="resendOTP(this)" class="btn btn-secondary" style="width:auto;padding:10px 20px;font-size:0.85rem;" id="resendBtn">
                Resend Code
            </button>
            <div style="margin-top:16px;">
                <a href="<?= baseUrl('register.php') ?>">← Use a different email</a>
            </div>
        </div>
    </div>
</div>

<script>
// ---------- OTP input handling ----------
const inputs = document.querySelectorAll('.otp-input');
const hidden = document.getElementById('otpHidden');

inputs.forEach((input, idx) => {
    input.addEventListener('input', (e) => {
        const val = e.target.value.replace(/\D/g, '');
        e.target.value = val;
        if (val && idx < inputs.length - 1) inputs[idx + 1].focus();
        updateHidden();
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !e.target.value && idx > 0) inputs[idx - 1].focus();
        if (e.key === 'ArrowLeft' && idx > 0) inputs[idx - 1].focus();
        if (e.key === 'ArrowRight' && idx < inputs.length - 1) inputs[idx + 1].focus();
    });

    input.addEventListener('paste', (e) => {
        e.preventDefault();
        const pasted = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
        pasted.split('').forEach((c, i) => { if (inputs[i]) inputs[i].value = c; });
        inputs[Math.min(pasted.length, 5)].focus();
        updateHidden();
    });
});

function updateHidden() {
    hidden.value = Array.from(inputs).map(i => i.value).join('');
}

document.getElementById('otpForm').addEventListener('submit', (e) => {
    updateHidden();
    if (hidden.value.length !== 6) {
        e.preventDefault();
        alert('Please enter all 6 digits.');
    }
});

// ---------- Resend OTP via EmailJS ----------
async function resendOTP(btn) {
    btn.disabled = true;
    const original = btn.textContent;
    btn.textContent = 'Sending...';

    try {
        const res = await fetch('<?= baseUrl('auth/resend_otp.php') ?>', { method: 'POST' });
        const data = await res.json();

        if (!data.success) {
            alert('Failed: ' + (data.error || 'Unknown error'));
            btn.disabled = false;
            btn.textContent = original;
            return;
        }

        // Send via EmailJS using the OTP returned from the server
        await emailjs.send(
            'service_h6zywtr',   // ← Replace
            'template_s050h21',  // ← Replace
            {
                email: data.email,
                passcode: data.otp,
                name: data.name
            }
        );

        btn.textContent = '✅ Sent! Check inbox';
        setTimeout(() => {
            btn.disabled = false;
            btn.textContent = original;
        }, 4000);
    } catch (err) {
        console.error('EmailJS error:', err);
        alert('Error: ' + (err.text || err.message || 'Could not send the code'));
        btn.disabled = false;
        btn.textContent = original;
    }
}
</script>
</body>
</html>