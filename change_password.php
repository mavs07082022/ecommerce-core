<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
requireLogin();

$pageTitle = 'Change Password — E-Commerce Core';
$uid  = $_SESSION['user_id'];
$role = $_SESSION['role'];

$u = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$u->execute([$uid]);
$user = $u->fetch();

if (!$user) {
    session_destroy();
    redirect('login.php');
}

$forced = !empty($user['must_change_password']);
$error  = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$current || !$new || !$confirm) {
        $error = 'Please fill in all fields.';
    } elseif (!password_verify($current, $user['password'])) {
        $error = 'Your current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
        $error = 'New password must contain at least one letter and one number.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } elseif ($new === $current) {
        $error = 'New password must be different from your current password.';
    } else {
        try {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?")
                ->execute([$hash, $uid]);

            $_SESSION['must_change_password'] = 0;
            $success = 'Password changed successfully! Redirecting...';

            $redirectMap = [
                'admin'           => 'index.php',
                'product_manager' => 'pm_dashboard.php',
                'rider'           => 'rider_dashboard.php',
                'customer'        => 'customer_dashboard.php',
            ];
            $target = $redirectMap[$role] ?? 'customer_dashboard.php';

            header("Refresh: 1.5; url=" . baseUrl($target));
        } catch (PDOException $e) {
            $error = 'Could not update password. Please try again.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="app-layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" onclick="openSidebar()" aria-label="Toggle menu">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <h2><?= $forced ? '🔒 Set Your New Password' : 'Change Password' ?></h2>
                    <p><?= $forced ? 'You must change your temporary password to continue' : 'Update your account password' ?></p>
                </div>
            </div>
        </header>

        <div class="page-body">
            <div style="max-width:560px;margin:0 auto;">

                <?php if ($forced): ?>
                    <div class="alert" style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;padding:14px 16px;border-radius:10px;margin-bottom:20px;">
                        <div style="font-weight:700;margin-bottom:6px;">🔐 First-time Login</div>
                        <div style="font-size:0.88rem;line-height:1.5;">
                            You are currently using a temporary password. For security reasons, please set a new password before continuing.
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error" style="background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;padding:14px 16px;border-radius:10px;margin-bottom:20px;">
                        ⚠️ <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" style="background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7;padding:14px 16px;border-radius:10px;margin-bottom:20px;">
                        ✅ <?= e($success) ?>
                    </div>
                <?php endif; ?>

                <div class="card card-pad">
                    <form method="POST" id="pwForm">
                        <div class="form-group">
                            <label class="form-label">Current Password <?= $forced ? '(the temporary one from your email)' : '' ?></label>
                            <div style="position:relative;">
                                <input type="password" name="current_password" id="curPw" class="form-input" required autofocus style="padding-right:44px;">
                                <button type="button" onclick="togglePw('curPw', this)" tabindex="-1"
                                    style="position:absolute;top:50%;right:10px;transform:translateY(-50%);background:transparent;border:none;cursor:pointer;padding:8px;color:#9ca3af;border-radius:6px;">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <div style="position:relative;">
                                <input type="password" name="new_password" id="newPw" class="form-input" required minlength="8" oninput="checkRules()" style="padding-right:44px;">
                                <button type="button" onclick="togglePw('newPw', this)" tabindex="-1"
                                    style="position:absolute;top:50%;right:10px;transform:translateY(-50%);background:transparent;border:none;cursor:pointer;padding:8px;color:#9ca3af;border-radius:6px;">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <div style="background:#f4f7fb;border-radius:10px;padding:12px 14px;margin-top:10px;font-size:0.8rem;color:#4b5563;line-height:1.7;">
                                <div id="rule-length" style="display:flex;align-items:center;gap:8px;"><span style="width:14px;height:14px;border-radius:50%;border:1.5px solid #d1d5db;display:inline-flex;align-items:center;justify-content:center;font-size:9px;color:#fff;">✓</span> At least 8 characters</div>
                                <div id="rule-letter" style="display:flex;align-items:center;gap:8px;"><span style="width:14px;height:14px;border-radius:50%;border:1.5px solid #d1d5db;display:inline-flex;align-items:center;justify-content:center;font-size:9px;color:#fff;">✓</span> Contains a letter</div>
                                <div id="rule-number" style="display:flex;align-items:center;gap:8px;"><span style="width:14px;height:14px;border-radius:50%;border:1.5px solid #d1d5db;display:inline-flex;align-items:center;justify-content:center;font-size:9px;color:#fff;">✓</span> Contains a number</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <div style="position:relative;">
                                <input type="password" name="confirm_password" id="confirmPw" class="form-input" required minlength="8" oninput="checkRules()" style="padding-right:44px;">
                                <button type="button" onclick="togglePw('confirmPw', this)" tabindex="-1"
                                    style="position:absolute;top:50%;right:10px;transform:translateY(-50%);background:transparent;border:none;cursor:pointer;padding:8px;color:#9ca3af;border-radius:6px;">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <div id="matchRule" style="display:none;background:#f4f7fb;border-radius:10px;padding:12px 14px;margin-top:10px;font-size:0.8rem;color:#4b5563;">
                                <div id="rule-match" style="display:flex;align-items:center;gap:8px;"><span style="width:14px;height:14px;border-radius:50%;border:1.5px solid #d1d5db;display:inline-flex;align-items:center;justify-content:center;font-size:9px;color:#fff;">✓</span> Passwords match</div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%;padding:14px;font-weight:700;margin-top:8px;">
                            <?= $forced ? 'Set New Password' : 'Update Password' ?>
                        </button>

                        <div style="text-align:center;margin-top:16px;">
                            <a href="<?= baseUrl('logout.php') ?>" style="color:<?= $forced ? '#dc2626' : '#6b7280' ?>;font-size:0.83rem;text-decoration:none;<?= $forced ? 'font-weight:600;' : '' ?>">← Sign out</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </div>
</div>

<script>
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.innerHTML = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>';
    } else {
        inp.type = 'password';
        btn.innerHTML = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>';
    }
}

function checkRules() {
    const pw = document.getElementById('newPw').value;
    const cf = document.getElementById('confirmPw').value;

    toggleRule('rule-length', pw.length >= 8);
    toggleRule('rule-letter', /[A-Za-z]/.test(pw));
    toggleRule('rule-number', /\d/.test(pw));

    const matchBox = document.getElementById('matchRule');
    if (cf.length > 0) {
        matchBox.style.display = 'block';
        toggleRule('rule-match', pw === cf && pw.length > 0);
    } else {
        matchBox.style.display = 'none';
    }
}

function toggleRule(id, ok) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.color = ok ? '#065f46' : '#4b5563';
    const dot = el.querySelector('span');
    if (dot) {
        dot.style.background = ok ? '#10b981' : 'transparent';
        dot.style.borderColor = ok ? '#10b981' : '#d1d5db';
    }
}

document.getElementById('pwForm').addEventListener('submit', (e) => {
    const nw = document.getElementById('newPw').value;
    const cf = document.getElementById('confirmPw').value;
    if (nw.length < 8 || !/[A-Za-z]/.test(nw) || !/\d/.test(nw)) {
        e.preventDefault();
        alert('New password must be at least 8 characters and contain a letter and a number.');
    } else if (nw !== cf) {
        e.preventDefault();
        alert('Passwords do not match.');
    }
});
</script>