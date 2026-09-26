<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireProductManager();

$pageTitle = 'Returns — E-Commerce Core';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_return'])) {
    $id     = (int)$_POST['return_id'];
    $status = $_POST['status'];
    $refund = (float)($_POST['refund_amount'] ?? 0);
    $pdo->prepare("UPDATE returns SET status=?, refund_amount=? WHERE id=?")->execute([$status, $refund, $id]);

    if (in_array($status, ['approved','refunded'])) {
        $r = $pdo->prepare("SELECT order_id FROM returns WHERE id=?");
        $r->execute([$id]);
        if ($oid = $r->fetchColumn()) {
            $pdo->prepare("UPDATE orders SET status='returned' WHERE id=?")->execute([$oid]);
        }
    }
    $msg = "Return #{$id} updated.";
}

$returns = $pdo->query("SELECT r.*, u.username FROM returns r JOIN users u ON r.user_id=u.id ORDER BY r.id DESC")->fetchAll();
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
                    <h2>Returns, Cancellation & Refund System</h2>
                    <p>Manage refunds and product returns</p>
                </div>
            </div>
        </header>

        <div class="page-body">
            <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

            <div class="card">
                <?php if (empty($returns)): ?>
                    <div class="card-pad" style="text-align:center;color:#6b7280;">No return requests yet.</div>
                <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr><th>Return</th><th>Order</th><th>Customer</th><th>Reason</th><th>Status</th><th>Refund</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($returns as $r): 
                                $colors = ['pending'=>'badge-yellow','approved'=>'badge-blue','rejected'=>'badge-red','refunded'=>'badge-green'];
                            ?>
                            <tr>
                                <td><strong>R<?= $r['id'] ?></strong></td>
                                <td>#<?= $r['order_id'] ?></td>
                                <td><?= e($r['username']) ?></td>
                                <td style="max-width:220px;font-size:0.82rem;"><?= e($r['reason']) ?></td>
                                <td><span class="badge <?= $colors[$r['status']] ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                                <td>₱<?= number_format($r['refund_amount'], 2) ?></td>
                                <td>
                                    <form method="POST" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                        <input type="hidden" name="return_id" value="<?= $r['id'] ?>">
                                        <input type="number" step="0.01" name="refund_amount" value="<?= $r['refund_amount'] ?>" class="form-input" style="width:100px;padding:5px 8px;font-size:0.78rem;" placeholder="₱">
                                        <select name="status" class="form-select" style="padding:5px 8px;font-size:0.78rem;width:auto;">
                                            <?php foreach (['pending','approved','rejected','refunded'] as $s): ?>
                                                <option value="<?= $s ?>" <?= $s===$r['status']?'selected':'' ?>><?= ucfirst($s) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="update_return" class="btn btn-primary btn-sm">Update</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>