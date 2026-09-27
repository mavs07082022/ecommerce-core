<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireProductManager();

$pageTitle = 'Manage Riders — E-Commerce Core';
$msg = '';
$error = '';

// Flash data to trigger EmailJS
$sendCredentials = false;
$credEmail    = '';
$credName     = '';
$credUsername = '';
$credPassword = '';
$credLoginUrl = '';

// ==================== Create / Update Rider ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_rider'])) {
    $id         = (int)($_POST['id'] ?? 0);
    $fullName   = trim($_POST['full_name'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $vehicle    = trim($_POST['vehicle_type'] ?? 'Motorcycle');
    $plate      = trim($_POST['plate_number'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (!$fullName || !$phone) {
        $error = 'Name and phone are required.';
    } else {
        try {
            if ($id) {
                // ---- UPDATE EXISTING RIDER ----
                $pdo->prepare("UPDATE riders SET full_name=?, phone=?, email=?, vehicle_type=?, plate_number=? WHERE id=?")
                    ->execute([$fullName, $phone, $email ?: null, $vehicle, $plate ?: null, $id]);

                if ($email) {
                    $u = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
                    $u->execute([$email]);
                    $existing = $u->fetch();

                    if ($existing) {
                        $pdo->prepare("UPDATE users SET full_name=?, phone=?, role='rider' WHERE id=?")
                            ->execute([$fullName, $phone, $existing['id']]);
                        $pdo->prepare("UPDATE riders SET user_id=? WHERE id=?")->execute([$existing['id'], $id]);

                        if ($password) {
                            $pdo->prepare("UPDATE users SET password=?, must_change_password=1, temp_password_issued_at=NOW() WHERE id=?")
                                ->execute([password_hash($password, PASSWORD_DEFAULT), $existing['id']]);

                            $sendCredentials = true;
                            $credEmail    = $email;
                            $credName     = $fullName;
                            $credUsername = $existing['username'];
                            $credPassword = $password;
                        }
                    } else {
                        // Create new login for existing rider
                        $uname = $username ?: ('rider' . $id);
                        $pw    = $password ?: generateTempPassword();
                        $pdo->prepare("INSERT INTO users (username, email, phone, password, full_name, role, status, email_verified, profile_completed, must_change_password, temp_password_issued_at) VALUES (?, ?, ?, ?, ?, 'rider', 'active', 1, 1, 1, NOW())")
                            ->execute([$uname, $email, $phone, password_hash($pw, PASSWORD_DEFAULT), $fullName]);
                        $newUserId = $pdo->lastInsertId();
                        $pdo->prepare("UPDATE riders SET user_id=? WHERE id=?")->execute([$newUserId, $id]);

                        $sendCredentials = true;
                        $credEmail    = $email;
                        $credName     = $fullName;
                        $credUsername = $uname;
                        $credPassword = $pw;
                    }
                }

                $msg = 'Rider updated successfully.';
            } else {
                // ---- CREATE NEW RIDER ----
                $newUserId = null;

                if ($email) {
                    $uname = $username ?: ('rider' . time());
                    $pw    = $password ?: generateTempPassword();

                    $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $chk->execute([$uname, $email]);
                    if ($chk->fetch()) {
                        $error = 'Username or email already in use.';
                    } else {
                        $pdo->prepare("INSERT INTO users (username, email, phone, password, full_name, role, status, email_verified, profile_completed, must_change_password, temp_password_issued_at) VALUES (?, ?, ?, ?, ?, 'rider', 'active', 1, 1, 1, NOW())")
                            ->execute([$uname, $email, $phone, password_hash($pw, PASSWORD_DEFAULT), $fullName]);
                        $newUserId = $pdo->lastInsertId();

                        $sendCredentials = true;
                        $credEmail    = $email;
                        $credName     = $fullName;
                        $credUsername = $uname;
                        $credPassword = $pw;
                    }
                }

                if (!$error) {
                    $pdo->prepare("INSERT INTO riders (user_id, full_name, phone, email, vehicle_type, plate_number) VALUES (?, ?, ?, ?, ?, ?)")
                        ->execute([$newUserId, $fullName, $phone, $email ?: null, $vehicle, $plate ?: null]);

                    $msg = 'Rider created successfully.';
                    if (!$newUserId) {
                        $msg .= ' (No login account — provide an email to enable login.)';
                    } else {
                        $msg .= ' Credentials are being sent to ' . e($email) . '.';
                        $credLoginUrl = baseUrl('login.php');
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Toggle rider status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $id = (int)$_POST['id'];
    $pdo->prepare("UPDATE riders SET status = IF(status='active','inactive','active') WHERE id = ?")->execute([$id]);
    $msg = 'Rider status updated.';
}

// Resend credentials
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_creds'])) {
    $id = (int)$_POST['id'];
    $r = $pdo->prepare("SELECT r.*, u.id AS user_id, u.username FROM riders r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
    $r->execute([$id]);
    $rider = $r->fetch();

    if ($rider && $rider['email'] && $rider['username']) {
        $newPw = generateTempPassword();
        $pdo->prepare("UPDATE users SET password=?, must_change_password=1, temp_password_issued_at=NOW() WHERE id=?")
            ->execute([password_hash($newPw, PASSWORD_DEFAULT), $rider['user_id']]);

        $sendCredentials = true;
        $credEmail    = $rider['email'];
        $credName     = $rider['full_name'];
        $credUsername = $rider['username'];
        $credPassword = $newPw;
        $credLoginUrl = baseUrl('login.php');

        $msg = 'New temporary credentials sent to ' . e($rider['email']) . '.';
    } else {
        $error = 'Rider has no linked account or email.';
    }
}

$riders = $pdo->query("
    SELECT r.*, u.username, u.must_change_password,
        (SELECT COUNT(*) FROM orders WHERE rider_id = r.id AND status = 'shipped') AS active_orders,
        (SELECT COUNT(*) FROM orders WHERE rider_id = r.id AND status = 'delivered') AS completed
    FROM riders r
    LEFT JOIN users u ON r.user_id = u.id
    ORDER BY r.id DESC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" onclick="openSidebar()" aria-label="Toggle menu">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <h2>Manage Riders</h2>
                    <p><?= count($riders) ?> rider<?= count($riders) != 1 ? 's' : '' ?> registered</p>
                </div>
            </div>
            <button onclick="openAddModal()" class="btn btn-primary">+ Add Rider</button>
        </header>

        <div class="page-body">
            <?php if ($msg): ?><div class="alert alert-success">✅ <?= $msg ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error">⚠️ <?= e($error) ?></div><?php endif; ?>

            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Rider</th>
                                <th>Contact</th>
                                <th>Vehicle</th>
                                <th>Active</th>
                                <th>Completed</th>
                                <th>Login</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($riders)): ?>
                                <tr><td colspan="8" style="text-align:center;padding:40px;color:#6b7280;">
                                    No riders yet. Click "Add Rider" to create one.
                                </td></tr>
                            <?php else: foreach ($riders as $r): ?>
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <div style="width:36px;height:36px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700;">
                                                <?= strtoupper(substr($r['full_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:700;"><?= e($r['full_name']) ?></div>
                                                <?php if ($r['username']): ?>
                                                    <div style="color:#6b7280;font-size:0.75rem;">@<?= e($r['username']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>📞 <?= e($r['phone']) ?></div>
                                        <?php if ($r['email']): ?><div style="color:#6b7280;font-size:0.78rem;"><?= e($r['email']) ?></div><?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-size:0.85rem;"><?= e($r['vehicle_type']) ?></div>
                                        <?php if ($r['plate_number']): ?><div style="color:#6b7280;font-size:0.78rem;"><?= e($r['plate_number']) ?></div><?php endif; ?>
                                    </td>
                                    <td><span class="badge badge-blue"><?= (int)$r['active_orders'] ?></span></td>
                                    <td><span class="badge badge-green"><?= (int)$r['completed'] ?></span></td>
                                    <td>
                                        <?php if (!$r['username']): ?>
                                            <span class="badge badge-gray">No account</span>
                                        <?php elseif (!empty($r['must_change_password'])): ?>
                                            <span class="badge badge-yellow">Temp password</span>
                                        <?php else: ?>
                                            <span class="badge badge-green">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $r['status'] === 'active' ? 'badge-green' : 'badge-gray' ?>">
                                            <?= ucfirst($r['status']) ?>
                                        </span>
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <button onclick='editRider(<?= json_encode($r) ?>)' class="btn btn-secondary btn-sm">Edit</button>
                                        <?php if ($r['username'] && $r['email']): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Send new temporary credentials to this rider?')">
                                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                                <button type="submit" name="resend_creds" class="btn btn-secondary btn-sm">Resend</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Toggle rider status?')">
                                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                            <button type="submit" name="toggle_status" class="btn btn-secondary btn-sm"><?= $r['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<!-- Rider Modal -->
<div id="riderModal" class="modal-overlay" style="display:none;">
    <div class="modal" style="max-width:520px;">
        <h3 id="modalTitle">Add Rider</h3>
        <form method="POST" id="riderForm">
            <input type="hidden" name="id" id="riderId">

            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" id="r_full_name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Phone *</label>
                <input type="text" name="phone" id="r_phone" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email *</label>
                <input type="email" name="email" id="r_email" class="form-input" placeholder="Rider's email for credentials" required>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label">Vehicle Type</label>
                    <input type="text" name="vehicle_type" id="r_vehicle" class="form-input" value="Motorcycle">
                </div>
                <div class="form-group">
                    <label class="form-label">Plate Number</label>
                    <input type="text" name="plate_number" id="r_plate" class="form-input">
                </div>
            </div>

            <div style="padding:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin-bottom:12px;font-size:0.82rem;color:#1d4ed8;line-height:1.5;">
                <strong>ℹ️ Auto-generated credentials</strong><br>
                If you leave username/password blank, the system will auto-generate a secure temporary password and email it to the rider. They'll be required to change it on first login.
            </div>

            <div class="form-group">
                <label class="form-label">Username (optional)</label>
                <input type="text" name="username" id="r_username" class="form-input" placeholder="Leave blank to auto-generate">
            </div>
            <div class="form-group">
                <label class="form-label">Password (optional)</label>
                <input type="text" name="password" id="r_password" class="form-input" placeholder="Leave blank to auto-generate">
            </div>

            <div class="modal-actions">
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" name="save_rider" class="btn btn-primary" id="saveBtn">Save &amp; Send Credentials</button>
            </div>
        </form>
    </div>
</div>

<!-- EmailJS SDK -->
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
<script>
(function() {
    emailjs.init("3wNkVwO4O9bDjbwoz");
})();

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Rider';
    document.getElementById('riderId').value = '';
    document.getElementById('riderForm').reset();
    document.getElementById('r_vehicle').value = 'Motorcycle';
    document.getElementById('riderModal').style.display = 'flex';
    document.getElementById('r_email').required = true;
}

function editRider(r) {
    document.getElementById('modalTitle').textContent = 'Edit Rider';
    document.getElementById('riderId').value    = r.id;
    document.getElementById('r_full_name').value = r.full_name || '';
    document.getElementById('r_phone').value    = r.phone || '';
    document.getElementById('r_email').value    = r.email || '';
    document.getElementById('r_vehicle').value  = r.vehicle_type || 'Motorcycle';
    document.getElementById('r_plate').value    = r.plate_number || '';
    document.getElementById('r_username').value = r.username || '';
    document.getElementById('r_password').value = '';
    document.getElementById('riderModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('riderModal').style.display = 'none';
    document.querySelectorAll('#riderModal input').forEach(i => i.value = '');
    document.getElementById('riderId').value = '';
}

<?php if ($sendCredentials): ?>
document.addEventListener('DOMContentLoaded', async function() {
    const creds = {
        email:     <?= json_encode($credEmail) ?>,
        name:      <?= json_encode($credName) ?>,
        username:  <?= json_encode($credUsername) ?>,
        password:  <?= json_encode($credPassword) ?>,
        login_url: <?= json_encode($credLoginUrl ?: baseUrl('login.php')) ?>
    };

    if (!creds.email || !creds.username || !creds.password) return;

    try {
        await emailjs.send(
            'service_h6zywtr',      // ← Service ID
            'template_f4tehcl',     // ← Rider credentials template
            {
                email:     creds.email,
                name:      creds.name,
                username:  creds.username,
                password:  creds.password,
                login_url: creds.login_url
            }
        );
        console.log('✅ Credentials email sent to', creds.email);
    } catch (err) {
        console.error('❌ EmailJS error:', err);
        alert('Rider saved, but the credentials email could not be sent.\n\nError: ' + (err.text || err.message || 'Unknown'));
    }
});
<?php endif; ?>
</script>