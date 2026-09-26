<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

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
            // Only allow staff roles here
            if (!in_array($user['role'], ['admin', 'product_manager'], true)) {
                $error = 'This login is for staff only. Please use the customer login.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];

                if ($user['role'] === 'admin') redirectRoot('index.php');
                else redirectRoot('pm_dashboard.php');
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
    <title>Staff Sign In — E-Commerce Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0;
            font-family: 'Inter', -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .staff-wrapper {
            min-height: 100vh;
            background: linear-gradient(135deg, #1e293b 0%, #111827 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            position: relative;
            overflow: hidden;
        }
        .staff-wrapper::before {
            content: '';
            position: absolute;
            top: -20%; right: -10%;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(37,99,235,0.18), transparent 60%);
            pointer-events: none;
        }
        .staff-wrapper::after {
            content: '';
            position: absolute;
            bottom: -20%; left: -10%;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(29,78,216,0.15), transparent 60%);
            pointer-events: none;
        }

        .staff-card {
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
        .staff-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #1e293b, #2563eb, #1d4ed8);
        }

        .staff-badge {
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
        .staff-badge svg { width: 12px; height: 12px; }

        .staff-logo {
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

        .staff-header { margin-bottom: 28px; }
        .staff-header h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #111827;
            margin: 0 0 6px;
            letter-spacing: -0.02em;
        }
        .staff-header p { color: #6b7280; font-size: 0.9rem; margin: 0; }

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

        .staff-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #f3f4f6;
            color: #6b7280;
            font-size: 0.83rem;
        }
        .staff-footer a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
        }
        .staff-footer a:hover { text-decoration: underline; }

        .customer-link {
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
        .customer-link:hover { color: #2563eb; background: #eff6ff; }
        .customer-link svg { width: 13px; height: 13px; }

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
    </style>
</head>
<body>
    <div class="staff-wrapper">
        <div class="staff-card">
            <div class="staff-badge">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M5.618 4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Staff Only
            </div>

            <a href="<?= baseUrl('landing.php') ?>" class="staff-logo">E</a>

            <div class="staff-header">
                <h1>Staff Sign In</h1>
                <p>Administrator & Product Manager portal</p>
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
                        <input type="password" name="password" id="staff_password" class="form-input" required>
                        <button type="button" class="toggle-pw" onclick="togglePassword('staff_password', this)" tabindex="-1">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login">Sign In as Staff</button>
            </form>

            <div class="staff-footer">
                <a href="<?= baseUrl('admin/login.php') ?>">← Back to staff login</a>
                <br>
                <a href="<?= baseUrl('login.php') ?>" class="customer-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    I'm a customer instead
                </a>
            </div>

            <div class="security-note">
                🔒 This portal is monitored. Unauthorized access attempts are logged.
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