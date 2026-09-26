<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mailer.php';

if (isset($_SESSION['user_id'])) {
    redirectRoot('landing.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username    = trim($_POST['username'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';
    $fullName    = trim($_POST['full_name'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $province    = trim($_POST['province'] ?? '');
    $postalCode  = trim($_POST['postal_code'] ?? '');

    // Validation
    if (!$username || !$email || !$password || !$fullName || !$phone || !$address || !$city || !$province || !$postalCode) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        $error = 'Please enter a valid phone number (e.g., 0917 123 4567).';
    } elseif (!preg_match('/^[0-9]{4,6}$/', $postalCode)) {
        $error = 'Postal code must be 4-6 digits.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'Username or email is already registered.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, full_name, phone, address, city, province, postal_code, role, status, email_verified, profile_completed)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'customer', 'active', 0, 1)
                ");
                $stmt->execute([
                    $username,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    $fullName,
                    $phone,
                    $address,
                    $city,
                    $province,
                    $postalCode
                ]);
                $newUserId = $pdo->lastInsertId();

                // Generate OTP (expires in 10 minutes)
                $otp = generateOTP(6);
                $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                // Invalidate any old unused OTPs for this email
                $pdo->prepare("DELETE FROM email_otps WHERE email = ? AND used = 0")->execute([$email]);

                // Save new OTP
                $pdo->prepare("INSERT INTO email_otps (user_id, email, otp_code, purpose, expires_at) VALUES (?, ?, ?, 'register', ?)")
                    ->execute([$newUserId, $email, $otp, $expiresAt]);

                // Send OTP email
                $mailResult = sendOTPEmail($email, $fullName, $otp, 'register');

                // Save pending verification session
                $_SESSION['pending_verification_email'] = $email;
                $_SESSION['pending_verification_name']  = $fullName;

                if ($mailResult['success']) {
                    redirect('verify_otp.php?sent=1');
                } else {
                    $_SESSION['otp_send_error'] = $mailResult['error'] ?? 'Unknown error';
                    redirect('verify_otp.php');
                }
            } catch (PDOException $e) {
                $error = 'Registration failed: ' . $e->getMessage();
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
    <title>Create Account — E-Commerce Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            margin: 0; padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 15px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            background: #f4f7fb;
            color: #1f2937;
        }

        .auth-bg {
            min-height: 100vh;
            padding: 40px 20px;
            background:
                radial-gradient(circle at 15% 15%, rgba(37,99,235,0.10), transparent 50%),
                radial-gradient(circle at 85% 85%, rgba(29,78,216,0.10), transparent 50%),
                linear-gradient(180deg, #f4f7fb 0%, #eef2f9 100%);
            display: flex;
            align-items: flex-start;
            justify-content: center;
        }

        .auth-card {
            width: 100%;
            max-width: 720px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 48px 52px;
            box-shadow:
                0 20px 60px rgba(16,24,40,0.08),
                0 4px 12px rgba(16,24,40,0.04);
            position: relative;
            overflow: hidden;
        }
        .auth-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #2563eb, #1d4ed8, #60a5fa);
        }

        .auth-header { text-align: center; margin-bottom: 36px; }
        .auth-logo {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-weight: 800;
            font-size: 1.75rem;
            margin-bottom: 20px;
            box-shadow: 0 12px 28px rgba(37,99,235,0.32);
            text-decoration: none;
        }
        .auth-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #111827;
            margin: 0 0 8px;
            letter-spacing: -0.025em;
        }
        .auth-header p {
            color: #6b7280;
            font-size: 0.95rem;
            margin: 0;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 0.88rem;
            margin-bottom: 24px;
            border: 1px solid;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            line-height: 1.5;
        }
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-color: #fca5a5;
        }
        .alert-error::before {
            content: '⚠';
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .section { margin-bottom: 28px; }
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.72rem;
            font-weight: 700;
            color: #1d4ed8;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin: 0 0 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eff6ff;
        }
        .section-title .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #2563eb;
            flex-shrink: 0;
        }
        .section-hint {
            font-size: 0.72rem;
            color: #9ca3af;
            font-weight: 500;
            text-transform: none;
            letter-spacing: 0;
            margin-left: auto;
            font-style: italic;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full { grid-column: 1 / -1; }

        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
            letter-spacing: -0.005em;
        }
        .form-label .req {
            color: #dc2626;
            margin-left: 2px;
        }

        .form-input,
        .form-textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.9rem;
            color: #111827;
            background: #ffffff;
            font-family: inherit;
            transition: all 0.15s ease;
            line-height: 1.4;
        }
        .form-input::placeholder,
        .form-textarea::placeholder { color: #9ca3af; }
        .form-input:hover,
        .form-textarea:hover { border-color: #d1d5db; }
        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37,99,235,0.12);
        }
        .form-textarea {
            resize: vertical;
            min-height: 78px;
            line-height: 1.5;
        }

        .input-wrap { position: relative; }
        .input-wrap .form-input { padding-right: 44px; }
        .toggle-password {
            position: absolute;
            top: 50%; right: 12px;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 6px;
            color: #6b7280;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .toggle-password:hover { background: #f4f7fb; color: #2563eb; }
        .toggle-password svg { width: 18px; height: 18px; }

        .pw-strength {
            display: flex;
            gap: 4px;
            margin-top: 6px;
        }
        .pw-bar {
            flex: 1;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            transition: background 0.25s;
        }
        .pw-bar.active-weak   { background: #ef4444; }
        .pw-bar.active-fair   { background: #f59e0b; }
        .pw-bar.active-good   { background: #10b981; }
        .pw-bar.active-strong { background: #059669; }

        .pw-text {
            font-size: 0.72rem;
            color: #6b7280;
            margin-top: 4px;
            font-weight: 500;
        }

        .btn-submit {
            width: 100%;
            padding: 15px 20px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 8px 24px rgba(37,99,235,0.32);
            letter-spacing: -0.005em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 8px;
        }
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 32px rgba(37,99,235,0.42);
        }
        .btn-submit:active { transform: translateY(0); }

        .auth-footer {
            text-align: center;
            margin-top: 26px;
            padding-top: 22px;
            border-top: 1px solid #f3f4f6;
            color: #6b7280;
            font-size: 0.88rem;
        }
        .auth-footer a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
        }
        .auth-footer a:hover { text-decoration: underline; }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #9ca3af;
            font-size: 0.8rem;
            text-decoration: none;
            margin-top: 18px;
            transition: color 0.15s;
        }
        .back-link:hover { color: #2563eb; }

        .info-note {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.8rem;
            color: #1d4ed8;
            line-height: 1.5;
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
        }
        .info-note .icon { flex-shrink: 0; font-size: 1rem; }

        @media (max-width: 640px) {
            .auth-bg { padding: 20px 12px; }
            .auth-card { padding: 32px 24px; border-radius: 16px; }
            .auth-header h1 { font-size: 1.4rem; }
            .form-grid { grid-template-columns: 1fr; gap: 14px; }
            .section-title { font-size: 0.68rem; }
        }
    </style>
</head>
<body>
<div class="auth-bg">
    <div class="auth-card">
        <!-- Header -->
        <div class="auth-header">
            <a href="<?= rootUrl('landing.php') ?>" class="auth-logo">E</a>
            <h1>Create Your Account</h1>
            <p>Join us — we'll verify your email with a 6-digit code</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="info-note">
            <span class="icon">📌</span>
            <div>
                <strong>Heads up:</strong> Your shipping address below will be <strong>locked at checkout</strong>. Please make sure it's accurate.
            </div>
        </div>

        <form method="POST" autocomplete="off">
            <!-- ===================== ACCOUNT CREDENTIALS ===================== -->
            <div class="section">
                <div class="section-title">
                    <span class="dot"></span>
                    Account Credentials
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Username <span class="req">*</span></label>
                        <input type="text" name="username" class="form-input" placeholder="Choose a username"
                               required value="<?= e($_POST['username'] ?? '') ?>" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address <span class="req">*</span></label>
                        <input type="email" name="email" class="form-input" placeholder="you@example.com"
                               required value="<?= e($_POST['email'] ?? '') ?>" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password <span class="req">*</span></label>
                        <div class="input-wrap">
                            <input type="password" name="password" id="password" class="form-input"
                                   placeholder="Min. 6 characters" required minlength="6"
                                   oninput="checkStrength(this.value)" autocomplete="new-password">
                            <button type="button" class="toggle-password" onclick="togglePw('password', this)" tabindex="-1">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                        <div class="pw-strength">
                            <div class="pw-bar" id="pw1"></div>
                            <div class="pw-bar" id="pw2"></div>
                            <div class="pw-bar" id="pw3"></div>
                            <div class="pw-bar" id="pw4"></div>
                        </div>
                        <div class="pw-text" id="pwText">Password strength: —</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm Password <span class="req">*</span></label>
                        <div class="input-wrap">
                            <input type="password" name="confirm_password" id="confirm_password"
                                   class="form-input" placeholder="Re-enter password" required minlength="6"
                                   autocomplete="new-password">
                            <button type="button" class="toggle-password" onclick="togglePw('confirm_password', this)" tabindex="-1">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===================== PERSONAL INFO ===================== -->
            <div class="section">
                <div class="section-title">
                    <span class="dot"></span>
                    Personal Information
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="req">*</span></label>
                        <input type="text" name="full_name" class="form-input"
                               placeholder="Juan Dela Cruz" required
                               value="<?= e($_POST['full_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number <span class="req">*</span></label>
                        <input type="tel" name="phone" class="form-input"
                               placeholder="0917 123 4567" required
                               value="<?= e($_POST['phone'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- ===================== SHIPPING ADDRESS ===================== -->
            <div class="section">
                <div class="section-title">
                    <span class="dot"></span>
                    Shipping Address
                    <span class="section-hint">🔒 Locked at checkout</span>
                </div>

                <div class="form-grid">
                    <div class="form-group full">
                        <label class="form-label">Street Address <span class="req">*</span></label>
                        <textarea name="address" class="form-textarea"
                                  placeholder="House/Unit No., Street, Barangay" required><?= e($_POST['address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">City / Municipality <span class="req">*</span></label>
                        <input type="text" name="city" class="form-input"
                               placeholder="e.g. Quezon City" required
                               value="<?= e($_POST['city'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Province <span class="req">*</span></label>
                        <input type="text" name="province" class="form-input"
                               placeholder="e.g. Metro Manila" required
                               value="<?= e($_POST['province'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="max-width: 200px;">
                        <label class="form-label">Postal Code <span class="req">*</span></label>
                        <input type="text" name="postal_code" class="form-input"
                               placeholder="e.g. 1100" required
                               value="<?= e($_POST['postal_code'] ?? '') ?>"
                               pattern="[0-9]{4,6}" maxlength="6">
                    </div>
                </div>
            </div>

            <!-- ===================== SUBMIT ===================== -->
            <button type="submit" class="btn-submit">
                <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Create Account &amp; Send OTP
            </button>

            <div class="auth-footer">
                Already have an account?
                <a href="<?= rootUrl('login.php') ?>">Sign In</a>
            </div>

            <div style="text-align:center;">
                <a href="<?= rootUrl('landing.php') ?>" class="back-link">
                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to home
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.innerHTML = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>';
    } else {
        inp.type = 'password';
        btn.innerHTML = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>';
    }
}

function checkStrength(pw) {
    const bars = [
        document.getElementById('pw1'),
        document.getElementById('pw2'),
        document.getElementById('pw3'),
        document.getElementById('pw4'),
    ];
    const text = document.getElementById('pwText');

    bars.forEach(b => b.className = 'pw-bar');

    let score = 0;
    if (pw.length >= 6) score++;
    if (pw.length >= 10) score++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    if (score > 4) score = 4;

    const levels = [
        { cls: '', label: '—', color: '#6b7280' },
        { cls: 'active-weak',   label: 'Weak',   color: '#ef4444' },
        { cls: 'active-fair',   label: 'Fair',   color: '#f59e0b' },
        { cls: 'active-good',   label: 'Good',   color: '#10b981' },
        { cls: 'active-strong', label: 'Strong', color: '#059669' },
    ];

    if (!pw) {
        text.textContent = 'Password strength: —';
        text.style.color = '#6b7280';
        return;
    }

    for (let i = 0; i < score; i++) {
        bars[i].className = 'pw-bar ' + levels[score].cls;
    }

    text.textContent = 'Password strength: ' + levels[score].label;
    text.style.color = levels[score].color;
}

// Optional: prevent double submit
document.querySelector('form').addEventListener('submit', function() {
    const btn = this.querySelector('.btn-submit');
    btn.disabled = true;
    btn.innerHTML = '<svg style="width:18px;height:18px;animation:spin 1s linear infinite;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Creating account...';
});

// Spin animation
const style = document.createElement('style');
style.textContent = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
document.head.appendChild(style);
</script>
</body>
</html>