<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';

// If already logged in as rider → send them onward
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'rider') {
    if (!empty($_SESSION['must_change_password'])) {
        redirect('change_password.php');
    }
    redirect('rider_dashboard.php');
}

// If logged in as another role, send them to their own area
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') redirect('index.php');
    if ($_SESSION['role'] === 'product_manager') redirect('pm_dashboard.php');
    if ($_SESSION['role'] === 'customer') redirect('customer_dashboard.php');
}

$error = '';
$debugInfo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        // ── Look up by username only (then verify role + password in PHP) ──
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Invalid username or password.';
        } elseif ($user['role'] !== 'rider') {
            $error = 'This account is not a rider account. Please use the main login.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Your rider account is inactive. Please contact the administrator.';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Invalid username or password.';
        } else {
            // ✅ Successful login
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role']      = 'rider';
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['must_change_password'] = (int)($user['must_change_password'] ?? 0);

            if (!empty($_SESSION['must_change_password'])) {
                redirect('change_password.php');
            }
            redirect('rider_dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider Login — Greenika</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0;
            font-family: 'Inter', -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .rider-login-wrapper {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            position: relative;
            overflow: hidden;
        }
        .rider-login-wrapper::before {
            content: '';
            position: absolute;
            top: -15%; right: -10%;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(37,99,235,0.22), transparent 60%);
            pointer-events: none;
        }
        .rider-login-wrapper::after {
            content: '';
            position: absolute;
            bottom: -20%; left: -10%;
            width: 520px; height: 520px;
            background: radial-gradient(circle, rgba(16,185,129,0.12), transparent 60%);
            pointer-events: none;
        }

        .rider-login-card {
            width: 100%;
            max-width: 440px;
            background: #fff;
            border-radius: 20px;
            padding: 44px 40px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.5);
            position: relative;
            z-index: 1;
            overflow: hidden;
        }
        .rider-login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #2563eb, #1d4ed8, #10b981);
        }

        .rider-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            background: #ecfdf5;
            color: #065f46;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 16px;
            border: 1px solid #a7f3d0;
        }
        .rider-badge svg { width: 12px; height: 12px; }

        .rider-logo {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.75rem;
            margin-bottom: 20px;
            box-shadow: 0 12px 28px rgba(37,99,235,0.32);
            text-decoration: none;
        }

        .rider-brand { margin-bottom: 28px; }
        .rider-brand h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #111827;
            margin: 0 0 6px;
            letter-spacing: -0.02em;
        }
        .rider-brand p { color: #6b7280; font-size: 0.9rem; margin: 0; }

        .form-group { margin-bottom: 18px; }
        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .form-input-wrap { position: relative; }
        .form-input {
            width: 100%;
            padding: 13px 44px 13px 14px;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.92rem;
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
        .form-input.no-icon { padding-right: 14px; }

        .toggle-pw {
            position: absolute;
            top: 50%; right: 10px;
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

        .btn-rider-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.15s;
            box-shadow: 0 8px 24px rgba(37,99,235,0.32);
            margin-top: 8px;
        }
        .btn-rider-login:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 12px 32px rgba(37,99,235,0.42);
        }
        .btn-rider-login:disabled { opacity: 0.7; cursor: not-allowed; }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.87rem;
            margin-bottom: 20px;
            border: 1px solid;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .alert-error { background: #fef2f2; color: #991b1b; border-color: #fca5a5; }
        .alert-info { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }

        .rider-note {
            background: #f4f7fb;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.78rem;
            color: #6b7280;
            margin-top: 20px;
            line-height: 1.6;
            border-left: 3px solid #2563eb;
        }
        .rider-note strong { color: #111827; }

        .rider-footer {
            text-align: center;
            margin-top: 22px;
            padding-top: 22px;
            border-top: 1px solid #f3f4f6;
            color: #6b7280;
            font-size: 0.83rem;
        }
        .rider-footer a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
        }
        .rider-footer a:hover { text-decoration: underline; }

        .back-home {
            text-align: center;
            margin-top: 18px;
        }
        .back-home a {
            color: #6b7280;
            font-size: 0.83rem;
            text-decoration: none;
            transition: color 0.15s;
        }
        .back-home a:hover { color: #2563eb; }

        .security-note {
            background: #f4f7fb;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 0.72rem;
            color: #6b7280;
            margin-top: 18px;
            text-align: center;
            line-height: 1.5;
        }

        .debug-box {
            background: #111827; color: #f87171;
            padding: 12px 14px; border-radius: 8px;
            font-family: monospace; font-size: 0.75rem;
            margin-bottom: 20px; word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="rider-login-wrapper">
        <div class="rider-login-card">
            <div class="rider-badge">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Rider Portal
            </div>

            <a href="<?= baseUrl('landing.php') ?>" class="rider-logo">🛵</a>

            <div class="rider-brand">
                <h1>Rider Sign In</h1>
                <p>Access your delivery dashboard</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span>⚠️</span>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['logout']) && $_GET['logout'] === '1'): ?>
                <div class="alert alert-info">
                    <span>👋</span>
                    <span>You have been signed out successfully.</span>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
                <div class="alert alert-info">
                    <span>✅</span>
                    <span>Password updated. Please sign in with your new password.</span>
                </div>
            <?php endif; ?>

            <form method="POST" id="riderLoginForm" autocomplete="on">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-input no-icon"
                           value="<?= e($_POST['username'] ?? '') ?>"
                           required autofocus autocomplete="username">
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="form-input-wrap">
                        <input type="password" name="password" id="rider_password" class="form-input" required autocomplete="current-password">
                        <button type="button" class="toggle-pw" onclick="togglePassword('rider_password', this)" tabindex="-1">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-rider-login" id="riderLoginBtn">Sign In to Dashboard</button>
            </form>

            <div class="rider-note">
                <strong>🛵 First time logging in?</strong><br>
                Use the username and temporary password we sent to your email. You'll be asked to change your password on first login.
            </div>

            <div class="rider-footer">
                <p style="margin:0;">Not a rider?</p>
                <a href="<?= baseUrl('login.php') ?>" style="display:inline-block;margin-top:6px;">← Customer / Staff Login</a>
            </div>

            <div class="security-note">
                🔒 Rider accounts are provided by the store administrator.
            </div>

            <div class="back-home">
                <a href="<?= baseUrl('landing.php') ?>">← Back to home</a>
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
    </script>
</body>
</html>