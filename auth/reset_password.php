<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$email  = $_SESSION['reset_user_email'] ?? '';
$userId = $_SESSION['reset_user_id']   ?? 0;

// Must have completed OTP verification first
if (!$email || !$userId || empty($_SESSION['reset_verified'])) {
    redirect('auth/forgot_password.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $error = 'Password must contain at least one letter and one number.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // 🔑 KEY FIX: Mark email as verified at the same time.
            // Receiving the OTP at this email proves ownership of the account.
            $pdo->prepare("UPDATE users SET password = ?, email_verified = 1 WHERE id = ? AND email = ?")
                ->execute([$hash, $userId, $email]);

            // Fetch the username so we can show it on the login success page
            $u = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $u->execute([$userId]);
            $username = $u->fetchColumn();

            // Stash username for the success banner (one-time use)
            $_SESSION['reset_success_username'] = $username ?: '';

            // 🧹 Clean up ALL reset-related sessions
            unset(
                $_SESSION['reset_user_id'],
                $_SESSION['reset_user_email'],
                $_SESSION['reset_user_name'],
                $_SESSION['reset_verified'],
                // Also clear any leftover registration verification sessions
                $_SESSION['pending_verification_email'],
                $_SESSION['pending_verification_name']
            );

            redirect('login.php?reset=success');
        } catch (PDOException $e) {
            $error = 'Could not update password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password — E-Commerce Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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

        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 0.88rem;
            margin-bottom: 20px;
            border: 1px solid;
            line-height: 1.5;
        }
        .alert-error { background: #fef2f2; color: #991b1b; border-color: #fca5a5; }

        .form-group { margin-bottom: 18px; }
        .form-label {
            display: block; font-size: 0.8rem; font-weight: 600;
            color: #374151; margin-bottom: 8px;
        }
        .form-input-wrap { position: relative; }
        .form-input {
            width: 100%;
            padding: 14px 46px 14px 16px;
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
        .toggle-pw {
            position: absolute;
            top: 50%; right: 12px;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 8px;
            color: #9ca3af;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s;
        }
        .toggle-pw:hover { color: #2563eb; background: #eff6ff; }
        .toggle-pw svg { width: 18px; height: 18px; }

        .pw-rules {
            background: #f4f7fb;
            border-radius: 10px;
            padding: 12px 14px;
            margin-top: 10px;
            font-size: 0.8rem;
            color: #4b5563;
            line-height: 1.7;
        }
        .pw-rules .rule {
            display: flex; align-items: center; gap: 8px;
        }
        .pw-rules .rule.ok { color: #065f46; }
        .pw-rules .rule .dot {
            width: 14px; height: 14px; border-radius: 50%;
            border: 1.5px solid #d1d5db; flex-shrink: 0;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 9px; color: #fff;
        }
        .pw-rules .rule.ok .dot {
            background: #10b981; border-color: #10b981;
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
            margin-top: 8px;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 32px rgba(37,99,235,0.42);
        }

        .footer-links {
            text-align: center; margin-top: 24px; padding-top: 22px;
            border-top: 1px solid #f3f4f6;
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
            <a href="<?= rootUrl('landing.php') ?>" class="auth-logo">🔒</a>
            <h1>Set New Password</h1>
            <p>Choose a strong password for <strong><?= e($email) ?></strong></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="pwForm">
            <div class="form-group">
                <label class="form-label">New Password</label>
                <div class="form-input-wrap">
                    <input type="password" name="password" id="new_password" class="form-input" required minlength="8" autofocus oninput="checkRules()">
                    <button type="button" class="toggle-pw" onclick="togglePassword('new_password', this)" tabindex="-1">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
                <div class="pw-rules">
                    <div class="rule" id="rule-length"><span class="dot">✓</span> At least 8 characters</div>
                    <div class="rule" id="rule-letter"><span class="dot">✓</span> Contains a letter</div>
                    <div class="rule" id="rule-number"><span class="dot">✓</span> Contains a number</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <div class="form-input-wrap">
                    <input type="password" name="confirm_password" id="confirm_password" class="form-input" required minlength="8" oninput="checkRules()">
                    <button type="button" class="toggle-pw" onclick="togglePassword('confirm_password', this)" tabindex="-1">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
                <div class="pw-rules" id="matchRule" style="display:none;">
                    <div class="rule" id="rule-match"><span class="dot">✓</span> Passwords match</div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Reset Password</button>
        </form>

        <div class="footer-links">
            <a href="<?= baseUrl('login.php') ?>">← Back to Sign In</a>
        </div>
    </div>
</div>

<script>
function togglePassword(id, btn) {
    const inp = document.getElementById(id);
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.innerHTML = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>';
    } else {
        inp.type = 'password';
        btn.innerHTML = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>';
    }
}

function checkRules() {
    const pw      = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;

    document.getElementById('rule-length').classList.toggle('ok', pw.length >= 8);
    document.getElementById('rule-letter').classList.toggle('ok', /[A-Za-z]/.test(pw));
    document.getElementById('rule-number').classList.toggle('ok', /\d/.test(pw));

    const matchBox = document.getElementById('matchRule');
    if (confirm.length > 0) {
        matchBox.style.display = 'block';
        document.getElementById('rule-match').classList.toggle('ok', pw === confirm && pw.length > 0);
    } else {
        matchBox.style.display = 'none';
    }
}

document.getElementById('pwForm').addEventListener('submit', (e) => {
    const pw = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    if (pw.length < 8 || !/[A-Za-z]/.test(pw) || !/\d/.test(pw)) {
        e.preventDefault();
        alert('Password must be at least 8 characters and contain both a letter and a number.');
    } else if (pw !== confirm) {
        e.preventDefault();
        alert('Passwords do not match.');
    }
});
</script>
</body>
</html>