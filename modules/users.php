<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireAdmin();

$pageTitle = 'Users — E-Commerce Core';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        try {
            $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?,?,?,?,?)")
                ->execute([
                    trim($_POST['username']),
                    trim($_POST['email']),
                    password_hash($_POST['password'], PASSWORD_DEFAULT),
                    trim($_POST['full_name']),
                    $_POST['role']
                ]);
            $msg = "User created successfully.";
        } catch (PDOException $ex) {
            $msg = "Error: " . $ex->getMessage();
        }
    } elseif ($action === 'update') {
        $id = (int)$_POST['id'];
        $newPass = $_POST['new_password'] ?? '';
        if ($newPass) {
            $pdo->prepare("UPDATE users SET email=?, full_name=?, role=?, status=?, password=? WHERE id=?")
                ->execute([$_POST['email'], $_POST['full_name'], $_POST['role'], $_POST['status'], password_hash($newPass, PASSWORD_DEFAULT), $id]);
        } else {
            $pdo->prepare("UPDATE users SET email=?, full_name=?, role=?, status=? WHERE id=?")
                ->execute([$_POST['email'], $_POST['full_name'], $_POST['role'], $_POST['status'], $id]);
        }
        $msg = "User updated.";
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id === (int)$_SESSION['user_id']) {
            $msg = "You cannot delete your own account.";
        } else {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            $msg = "User deleted.";
        }
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
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
                    <h2>User & Account Management</h2>
                    <p>Create, edit, and manage all users</p>
                </div>
            </div>
            <button onclick="openCreate()" class="btn btn-primary">+ Add User</button>
        </header>

        <div class="page-body">
            <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th><th>Username</th><th>Email</th><th>Full Name</th>
                                <th>Role</th><th>Status</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td>#<?= $u['id'] ?></td>
                                <td><strong style="color:#111827;"><?= e($u['username']) ?></strong></td>
                                <td><?= e($u['email']) ?></td>
                                <td><?= e($u['full_name']) ?></td>
                                <td><span class="badge <?= $u['role']==='admin' ? 'badge-blue' : 'badge-gray' ?>"><?= e(ucfirst($u['role'])) ?></span></td>
                                <td><span class="badge <?= $u['status']==='active' ? 'badge-green' : 'badge-red' ?>"><?= e(ucfirst($u['status'])) ?></span></td>
                                <td>
                                    <button onclick='editUser(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="btn btn-secondary btn-sm">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this user?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<div id="createModal" class="modal-overlay" style="display:none;">
    <div class="modal">
        <h3>Add New User</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group"><label class="form-label">Username *</label><input type="text" name="username" class="form-input" required></div>
            <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-input" required></div>
            <div class="form-group"><label class="form-label">Password *</label><input type="password" name="password" class="form-input" required></div>
            <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-input"></div>
            <div class="form-group"><label class="form-label">Role</label>
                <select name="role" class="form-select"><option value="customer">Customer</option><option value="admin">Admin</option></select>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="document.getElementById('createModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="modal-overlay" style="display:none;">
    <div class="modal">
        <h3>Edit User</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group"><label class="form-label">Username</label><input type="text" id="edit_username" class="form-input" disabled></div>
            <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" id="edit_email" class="form-input" required></div>
            <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="full_name" id="edit_fullname" class="form-input"></div>
            <div class="form-group"><label class="form-label">New Password (leave blank to keep current)</label><input type="password" name="new_password" class="form-input"></div>
            <div class="form-group"><label class="form-label">Role</label>
                <select name="role" id="edit_role" class="form-select"><option value="customer">Customer</option><option value="admin">Admin</option></select>
            </div>
            <div class="form-group"><label class="form-label">Status</label>
                <select name="status" id="edit_status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Update User</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreate() { document.getElementById('createModal').style.display = 'flex'; }
function editUser(u) {
    document.getElementById('edit_id').value = u.id;
    document.getElementById('edit_username').value = u.username;
    document.getElementById('edit_email').value = u.email;
    document.getElementById('edit_fullname').value = u.full_name || '';
    document.getElementById('edit_role').value = u.role;
    document.getElementById('edit_status').value = u.status;
    document.getElementById('editModal').style.display = 'flex';
}
</script>