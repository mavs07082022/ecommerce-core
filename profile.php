<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
requireLogin();

$pageTitle = 'My Profile — E-Commerce Core';
$uid = $_SESSION['user_id'];

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $address  = trim($_POST['address'] ?? '');
        $city     = trim($_POST['city'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $postal   = trim($_POST['postal_code'] ?? '');

        if (!$fullName || !$phone || !$address || !$city || !$province || !$postal) {
            $error = 'All fields are required.';
        } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            $error = 'Please enter a valid phone number.';
        } elseif (!preg_match('/^[0-9]{4,6}$/', $postal)) {
            $error = 'Postal code must be 4-6 digits.';
        } else {
            $pdo->prepare("UPDATE users SET full_name=?, phone=?, address=?, city=?, province=?, postal_code=? WHERE id=?")
                ->execute([$fullName, $phone, $address, $city, $province, $postal, $uid]);
            $_SESSION['full_name'] = $fullName;
            $success = 'Profile updated successfully.';
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $u = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $u->execute([$uid]);
        $hash = $u->fetchColumn();

        if (!password_verify($current, $hash)) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/[0-9]/', $new)) {
            $error = 'Password must include uppercase, lowercase, and a number.';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            $success = 'Password changed successfully.';
        }
    }
}

// Fetch user
$u = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$u->execute([$uid]);
$user = $u->fetch();

// Stats
$orderCount = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$orderCount->execute([$uid]);
$orderCount = $orderCount->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>
<style>
.profile-grid { display: grid; grid-template-columns: 320px 1fr; gap: 24px; }
@media (max-width: 900px) { .profile-grid { grid-template-columns: 1fr; } }

.profile-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
.profile-avatar {
    width: 100px; height: 100px;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    font-weight: 800;
    margin: 0 auto 16px;
    box-shadow: 0 10px 28px rgba(37,99,235,0.32);
}
.profile-info-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
    font-size: 0.88rem;
}
.profile-info-item:last-child { border-bottom: none; }
.profile-info-item .label { color: #6b7280; }
.profile-info-item .value { color: #111827; font-weight: 600; text-align: right; }

.tab-nav {
    display: flex;
    gap: 4px;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 24px;
}
.tab-btn {
    padding: 12px 20px;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    font-family: inherit;
    font-size: 0.9rem;
    font-weight: 600;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.15s;
    margin-bottom: -1px;
}
.tab-btn:hover { color: #2563eb; }
.tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; }

.tab-panel { display: none; }
.tab-panel.active { display: block; }

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.form-group.full { grid-column: 1 / -1; }
.form-input, .form-textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    font-size: 0.9rem;
    font-family: inherit;
    transition: all 0.15s;
}
.form-input:focus, .form-textarea:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 4px rgba(37,99,235,0.12);
}
.form-label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
}
.form-label .req { color: #dc2626; }

.btn-primary-full {
    width: 100%;
    padding: 13px;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 0.92rem;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.15s;
    box-shadow: 0 6px 20px rgba(37,99,235,0.32);
    margin-top: 8px;
}
.btn-primary-full:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 28px rgba(37,99,235,0.42);
}
</style>

<div class="app-layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" onclick="openSidebar()" aria-label="Toggle menu">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <h2>My Profile</h2>
                    <p>Manage your account information</p>
                </div>
            </div>
        </header>

        <div class="page-body">
            <?php if ($success): ?>
                <div class="alert alert-success" style="background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7;padding:12px 16px;border-radius:10px;margin-bottom:20px;">✅ <?= e($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error" style="background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;padding:12px 16px;border-radius:10px;margin-bottom:20px;">⚠ <?= e($error) ?></div>
            <?php endif; ?>

            <div class="profile-grid">
                <!-- LEFT: Summary Card -->
                <div>
                    <div class="profile-card" style="margin-bottom:20px;">
                        <div class="profile-avatar"><?= strtoupper(substr($user['full_name'] ?: $user['username'], 0, 1)) ?></div>
                        <h3 style="text-align:center;margin:0 0 4px;color:#111827;"><?= e($user['full_name']) ?></h3>
                        <p style="text-align:center;color:#6b7280;font-size:0.85rem;margin:0 0 20px;">@<?= e($user['username']) ?></p>

                        <div style="display:flex;justify-content:center;gap:12px;margin-bottom:20px;">
                            <span class="badge badge-blue"><?= ucwords(str_replace('_',' ',$user['role'])) ?></span>
                            <?php if ($user['email_verified']): ?>
                                <span class="badge badge-green">✓ Verified</span>
                            <?php else: ?>
                                <span class="badge badge-yellow">Unverified</span>
                            <?php endif; ?>
                        </div>

                        <div class="profile-info-item">
                            <span class="label">Orders Placed</span>
                            <span class="value"><?= $orderCount ?></span>
                        </div>
                        <div class="profile-info-item">
                            <span class="label">Email</span>
                            <span class="value" style="font-size:0.8rem;"><?= e($user['email']) ?></span>
                        </div>
                        <div class="profile-info-item">
                            <span class="label">Member Since</span>
                            <span class="value"><?= date('M Y', strtotime($user['created_at'])) ?></span>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Tabbed forms -->
                <div>
                    <div class="profile-card">
                        <div class="tab-nav">
                            <button class="tab-btn active" onclick="showTab('profile', this)">Profile Information</button>
                            <button class="tab-btn" onclick="showTab('password', this)">Change Password</button>
                            <button class="tab-btn" onclick="showTab('address', this)">Shipping Address</button>
                        </div>

                        <!-- Profile Tab -->
                        <div id="tab-profile" class="tab-panel active">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">

                                <div class="form-grid">
                                    <div class="form-group full">
                                        <label class="form-label">Full Name <span class="req">*</span></label>
                                        <input type="text" name="full_name" class="form-input" required value="<?= e($user['full_name']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Username (locked)</label>
                                        <input type="text" class="form-input" value="<?= e($user['username']) ?>" disabled style="background:#f4f7fb;color:#6b7280;">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Email (locked)</label>
                                        <input type="email" class="form-input" value="<?= e($user['email']) ?>" disabled style="background:#f4f7fb;color:#6b7280;">
                                    </div>
                                    <div class="form-group full">
                                        <label class="form-label">Phone Number <span class="req">*</span></label>
                                        <input type="tel" name="phone" class="form-input" required value="<?= e($user['phone']) ?>">
                                    </div>

                                    <!-- Hidden address fields kept for update query -->
                                    <input type="hidden" name="address" value="<?= e($user['address']) ?>">
                                    <input type="hidden" name="city" value="<?= e($user['city']) ?>">
                                    <input type="hidden" name="province" value="<?= e($user['province']) ?>">
                                    <input type="hidden" name="postal_code" value="<?= e($user['postal_code']) ?>">
                                </div>

                                <button type="submit" class="btn-primary-full">Save Changes</button>
                            </form>
                        </div>

                        <!-- Password Tab -->
                        <div id="tab-password" class="tab-panel">
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                <div class="form-group">
                                    <label class="form-label">Current Password <span class="req">*</span></label>
                                    <input type="password" name="current_password" class="form-input" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">New Password <span class="req">*</span></label>
                                    <input type="password" name="new_password" class="form-input" required minlength="8">
                                    <small style="color:#6b7280;font-size:0.75rem;display:block;margin-top:4px;">Must be 8+ chars with uppercase, lowercase, and a number.</small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Confirm New Password <span class="req">*</span></label>
                                    <input type="password" name="confirm_password" class="form-input" required minlength="8">
                                </div>
                                <button type="submit" class="btn-primary-full">Update Password</button>
                            </form>
                        </div>

                        <!-- Address Tab -->
                        <div id="tab-address" class="tab-panel">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                <input type="hidden" name="full_name" value="<?= e($user['full_name']) ?>">
                                <input type="hidden" name="phone" value="<?= e($user['phone']) ?>">

                                <div style="background:#eff6ff;padding:12px 14px;border-radius:10px;margin-bottom:20px;font-size:0.83rem;color:#1d4ed8;">
                                    📌 This address is used as your <strong>default shipping address</strong> at checkout. Keep it accurate.
                                </div>

                                <div class="form-grid">
                                    <div class="form-group full">
                                        <label class="form-label">Street Address <span class="req">*</span></label>
                                        <textarea name="address" class="form-textarea" required rows="2"><?= e($user['address']) ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">City / Municipality <span class="req">*</span></label>
                                        <input type="text" name="city" class="form-input" required value="<?= e($user['city']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Province <span class="req">*</span></label>
                                        <input type="text" name="province" class="form-input" required value="<?= e($user['province']) ?>">
                                    </div>
                                    <div class="form-group" style="max-width:200px;">
                                        <label class="form-label">Postal Code <span class="req">*</span></label>
                                        <input type="text" name="postal_code" class="form-input" required value="<?= e($user['postal_code']) ?>" pattern="[0-9]{4,6}">
                                    </div>
                                </div>

                                <button type="submit" class="btn-primary-full">Save Address</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </div>
</div>

<script>
function showTab(name, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}
</script>