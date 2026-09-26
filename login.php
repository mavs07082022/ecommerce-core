<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    if (isAdmin()) redirectRoot('index.php');
    if (isProductManager()) redirectRoot('pm_dashboard.php');
    redirectRoot('customer_dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Block admin/PM from logging in here
            if (in_array($user['role'], ['admin', 'product_manager'], true)) {
                $error = 'Staff accounts must sign in at the staff login page.';
            } elseif (!$user['email_verified']) {
                $_SESSION['pending_verification_email'] = $user['email'];
                $_SESSION['pending_verification_name']  = $user['full_name'];
                redirect('auth/verify_otp.php');
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                redirectRoot('customer_dashboard.php');
            }
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Sign In — E-Commerce Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= baseUrl('assets/css/style.css') ?>">
    <style>
        .login-wrapper {
            min-height: 100vh;
            padding: 40px 20px;
            background:
                radial-gradient(circle at 15% 15%, rgba(37,99,235,0.10), transparent 50%),
                radial-gradient(circle at 85% 85%, rgba(29,78,216,0.10), transparent 50%),
                linear-gradient(180deg, #f4f7fb 0%, #eef2f9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 440px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 44px 40px;
            box-shadow: 0 20px 60px rgba(16,24,40,0.08), 0 4px 12px rgba(16,24,40,0.04);
            position: relative;
            overflow: hidden;
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #2563eb, #1d4ed8, #60a5fa);
        }
        .login-logo {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 800;
            font-size: 1.75rem;
            margin: 0 auto 20px;
            box-shadow: 0 12px 28px rgba(37,99,235,0.32);
            text-decoration: none;
        }
        .login-header { text-align: center; margin-bottom: 32px; }
        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #111827;
            margin: 0 0 6px;
            letter-spacing: -0.02em;
        }
        .login-header p { color: #6b7280; font-size: 0.9rem; margin: 0; }

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
            box-sizing: border-box;
        }
        .form-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37,99,235,0.12);
        }
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

        .btn-login {
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
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 32px rgba(37,99,235,0.42);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.87rem;
            margin-bottom: 20px;
            border: 1px solid;
        }
        .alert-error { background: #fef2f2; color: #991b1b; border-color: #fca5a5; }

        .login-footer {
            text-align: center;
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid #f3f4f6;
            color: #6b7280;
            font-size: 0.87rem;
        }
        .login-footer a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
        }
        .login-footer a:hover { text-decoration: underline; }

        .staff-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 18px;
            font-size: 0.78rem;
            color: #9ca3af;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 8px;
            transition: all 0.15s;
        }
        .staff-link:hover {
            color: #2563eb;
            background: #eff6ff;
        }
        .staff-link svg { width: 13px; height: 13px; }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <a href="<?= baseUrl('landing.php') ?>" class="login-logo">E</a>
            <div class="login-header">
                <h1>Welcome Back</h1>
                <p>Sign in to your customer account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-input" style="padding-right:14px;" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="form-input-wrap">
                        <input type="password" name="password" id="password" class="form-input" required>
                        <button type="button" class="toggle-pw" onclick="togglePassword('password', this)" tabindex="-1">
                            <svg id="eye-closed" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login">Sign In</button>
            </form>

            <div class="login-footer">
                New customer? <a href="<?= baseUrl('auth/register.php') ?>">Create an account</a>
            </div>

            <div style="text-align:center;">
                <a href="<?= baseUrl('admin/login.php') ?>" class="staff-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Staff Login
                </a>
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