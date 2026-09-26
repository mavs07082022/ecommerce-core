<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    if (isAdmin()) redirect('index.php');
    if (isProductManager()) redirect('pm_dashboard.php');
    redirect('customer_dashboard.php');
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
            // Block unverified customers
            if ($user['role'] === 'customer' && !$user['email_verified']) {
                $_SESSION['pending_verification_email'] = $user['email'];
                $_SESSION['pending_verification_name']  = $user['full_name'];
                redirect('auth/verify_otp.php');
            }

            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];

            if ($user['role'] === 'admin') redirect('index.php');
            if ($user['role'] === 'product_manager') redirect('pm_dashboard.php');
            redirect('customer_dashboard.php');
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
    <title>Sign In — E-Commerce Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0;
            font-family: 'Inter', -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .login-wrapper {
            min-height: 100vh;
            background: linear-gradient(135deg, #1e293b 0%, #111827 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            position: relative;
            overflow: hidden;
        }
        .login-wrapper::before {
            content: '';
            position: absolute;
            top: -20%; right: -10%;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(37,99,235,0.18), transparent 60%);
            pointer-events: none;
        }
        .login-wrapper::after {
            content: '';
            position: absolute;
            bottom: -20%; left: -10%;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(29,78,216,0.15), transparent 60%);
            pointer-events: none;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            background: #fff;
            border-radius: 20px;
            padding: 44px 40px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.4);
            position: relative;
            z-index: 1;
            overflow: hidden;
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #1e293b, #2563eb, #1d4ed8);
        }

        .login-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 16px;
        }
        .login-badge svg { width: 12px; height: 12px; }

        .login-brand-logo {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, #1e293b, #111827);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 800;
            font-size: 1.75rem;
            margin-bottom: 20px;
            box-shadow: 0 12px 28px rgba(17,24,39,0.32);
            text-decoration: none;
        }

        .login-brand { margin-bottom: 28px; }
        .login-brand h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #111827;
            margin: 0 0 6px;
            letter-spacing: -0.02em;
        }
        .login-brand p { color: #6b7280; font-size: 0.9rem; margin: 0; }

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

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1e293b, #111827);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.15s;
            box-shadow: 0 8px 24px rgba(17,24,39,0.32);
            margin-top: 8px;
        }
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 32px rgba(17,24,39,0.42);
            background: linear-gradient(135deg, #111827, #1e293b);
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
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #f3f4f6;
            color: #6b7280;
            font-size: 0.83rem;
        }
        .login-footer a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
        }
        .login-footer a:hover { text-decoration: underline; }

        .register-section {
            text-align: center;
            margin-top: 22px;
            padding-top: 22px;
            border-top: 1px solid #f3f4f6;
        }
        .register-section p {
            color: #6b7280;
            font-size: 0.85rem;
            margin: 0 0 12px;
        }
        .btn-secondary {
            width: 100%;
            padding: 12px;
            background: #f3f4f6;
            color: #1f2937;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-secondary:hover {
            background: #e5e7eb;
            border-color: #d1d5db;
        }

        .staff-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 14px;
            font-size: 0.78rem;
            color: #9ca3af;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 8px;
            transition: all 0.15s;
        }
        .staff-link:hover { color: #2563eb; background: #eff6ff; }
        .staff-link svg { width: 13px; height: 13px; }

        .security-note {
            background: #f4f7fb;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 0.72rem;
            color: #6b7280;
            margin-top: 20px;
            text-align: center;
            line-height: 1.5;
        }

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
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-badge">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Customer Portal
            </div>

            <a href="<?= baseUrl('landing.php') ?>" class="login-brand-logo">E</a>

            <div class="login-brand">
                <h1>Welcome Back</h1>
                <p>Sign in to your customer account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-input no-icon" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="form-input-wrap">
                        <input type="password" name="password" id="customer_password" class="form-input" required>
                        <button type="button" class="toggle-pw" onclick="togglePassword('customer_password', this)" tabindex="-1">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login">Sign In</button>
            </form>

            <div class="register-section">
                <p>New customer?</p>
                <a href="<?= baseUrl('auth/register.php') ?>" class="btn-secondary">Create an Account</a>
            </div>

            
            <div class="security-note">
                🔒 Your connection is secure. We never share your personal information.
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