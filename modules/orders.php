<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireProductManager();

$pageTitle = 'Orders — E-Commerce Core';
$msg = '';

// ---- Update order status ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $oid = (int)$_POST['order_id'];
    $newStatus = $_POST['status'];
    $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$newStatus, $oid]);

    $messages = [
        'pending' => 'Order pending — awaiting processing',
        'processing' => 'Order is being prepared in our warehouse',
        'shipped' => 'Your package has been shipped',
        'delivered' => 'Package has been delivered successfully',
        'cancelled' => 'Order has been cancelled',
        'returned' => 'Order has been returned'
    ];
    $locations = [
        'pending' => 'Processing Center',
        'processing' => 'Warehouse',
        'shipped' => 'In Transit',
        'delivered' => 'Customer Address',
        'cancelled' => 'System',
        'returned' => 'Returns Center'
    ];
    $pdo->prepare("INSERT INTO order_tracking (order_id, status, message, location) VALUES (?, ?, ?, ?)")
        ->execute([$oid, $newStatus, $messages[$newStatus] ?? 'Status updated', $locations[$newStatus] ?? 'System']);

    $msg = "Order #{$oid} status updated to " . ucfirst($newStatus) . ".";
}

// ---- Verify payment ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    $oid = (int)$_POST['order_id'];
    $pdo->prepare("UPDATE orders SET payment_status = 'paid', paid_at = NOW() WHERE id = ?")->execute([$oid]);

    $s = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $s->execute([$oid]);
    $curStatus = $s->fetchColumn();

    $pdo->prepare("INSERT INTO order_tracking (order_id, status, message, location) VALUES (?, ?, 'Payment verified and confirmed', 'Payment Center')")
        ->execute([$oid, $curStatus]);

    $msg = "Order #{$oid} payment has been verified.";
}

// ---- Reject payment ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_payment'])) {
    $oid = (int)$_POST['order_id'];
    $pdo->prepare("UPDATE orders SET payment_status = 'unpaid', payment_reference = NULL, payment_proof = NULL WHERE id = ?")->execute([$oid]);

    $s = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $s->execute([$oid]);
    $curStatus = $s->fetchColumn();

    $pdo->prepare("INSERT INTO order_tracking (order_id, status, message, location) VALUES (?, ?, 'Payment could not be verified. Customer needs to submit again.', 'Payment Center')")
        ->execute([$oid, $curStatus]);

    $msg = "Order #{$oid} payment was rejected. Customer will need to re-submit.";
}

// ---- Cancel order ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order_admin'])) {
    $oid = (int)$_POST['order_id'];
    $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=?")->execute([$oid]);

    $pdo->prepare("INSERT INTO order_tracking (order_id, status, message, location) VALUES (?, 'cancelled', 'Order cancelled by staff', 'System')")
        ->execute([$oid]);

    $msg = "Order #{$oid} has been cancelled.";
}

// ---- View individual order ----
$viewOrder = null; $viewItems = []; $viewTracking = [];
if (isset($_GET['view'])) {
    $s = $pdo->prepare("SELECT o.*, u.username, u.email, u.phone FROM orders o JOIN users u ON o.user_id=u.id WHERE o.id=?");
    $s->execute([(int)$_GET['view']]);
    $viewOrder = $s->fetch();
    if ($viewOrder) {
        $s = $pdo->prepare("SELECT oi.*, p.name, p.image_url FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id=?");
        $s->execute([$viewOrder['id']]);
        $viewItems = $s->fetchAll();

        $s = $pdo->prepare("SELECT * FROM order_tracking WHERE order_id = ? ORDER BY created_at DESC LIMIT 10");
        $s->execute([$viewOrder['id']]);
        $viewTracking = $s->fetchAll();
    }
}

$orders = $pdo->query("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id=u.id ORDER BY o.id DESC")->fetchAll();

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
                    <h2>Order & Delivery Management</h2>
                    <p>Track and update order fulfilment</p>
                </div>
            </div>
        </header>

        <div class="page-body">
            <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

            <?php if ($viewOrder):
                $colors = ['pending'=>'badge-yellow','processing'=>'badge-blue','shipped'=>'badge-indigo','delivered'=>'badge-green','cancelled'=>'badge-red','returned'=>'badge-gray'];
                $pColors = ['unpaid'=>'badge-red','pending_verification'=>'badge-yellow','paid'=>'badge-green','refunded'=>'badge-blue'];
            ?>
                <div class="card card-pad" style="margin-bottom:24px;">
                    <div class="flex-between" style="margin-bottom:16px;flex-wrap:wrap;gap:12px;">
                        <div>
                            <h3 style="font-size:1.15rem;font-weight:700;color:#111827;margin:0;">
                                Order #<?= e($viewOrder['order_number'] ?: 'ORD-' . str_pad($viewOrder['id'], 6, '0', STR_PAD_LEFT)) ?>
                            </h3>
                            <p style="color:#6b7280;font-size:0.85rem;margin:6px 0 0;">
                                <strong><?= e($viewOrder['username']) ?></strong> · <?= e($viewOrder['email']) ?>
                            </p>
                            <p style="color:#6b7280;font-size:0.85rem;margin:2px 0 0;">Placed: <?= date('M j, Y g:ia', strtotime($viewOrder['created_at'])) ?></p>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <a href="<?= baseUrl('modules/orders.php') ?>" class="btn btn-secondary btn-sm">← Back</a>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                        <div style="background:#f4f7fb;padding:14px;border-radius:10px;">
                            <div class="form-label">Shipping Address</div>
                            <div style="font-size:0.9rem;"><?= nl2br(e($viewOrder['shipping_address'])) ?></div>
                            <?php if ($viewOrder['contact_phone']): ?>
                                <div style="color:#6b7280;font-size:0.85rem;margin-top:6px;">📞 <?= e($viewOrder['contact_phone']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="background:#f4f7fb;padding:14px;border-radius:10px;">
                            <div class="form-label">Payment Details</div>
                            <div style="font-size:0.9rem;">Method: <strong><?= e($viewOrder['payment_method']) ?></strong></div>
                            <div style="font-size:0.9rem;margin-top:4px;">
                                Status: <span class="badge <?= $pColors[$viewOrder['payment_status']] ?? 'badge-gray' ?>"><?= ucwords(str_replace('_',' ',$viewOrder['payment_status'])) ?></span>
                            </div>
                            <?php if ($viewOrder['payment_reference']): ?>
                                <div style="font-size:0.85rem;margin-top:6px;color:#6b7280;">Reference: <strong><?= e($viewOrder['payment_reference']) ?></strong></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($viewOrder['payment_proof']): ?>
                        <div style="margin-bottom:20px;">
                            <div class="form-label">Payment Screenshot</div>
                            <a href="<?= baseUrl($viewOrder['payment_proof']) ?>" target="_blank">
                                <img src="<?= baseUrl($viewOrder['payment_proof']) ?>" style="max-width:300px;border-radius:10px;border:1px solid #e5e7eb;">
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($viewOrder['payment_status'] === 'pending_verification'): ?>
                        <div style="background:#fef3c7;padding:16px;border-radius:10px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                            <div>
                                <div style="font-weight:700;color:#92400e;">⏳ Payment awaiting verification</div>
                                <div style="color:#78350f;font-size:0.85rem;">Please verify the reference and screenshot, then approve or reject.</div>
                            </div>
                            <form method="POST" style="display:flex;gap:8px;">
                                <input type="hidden" name="order_id" value="<?= $viewOrder['id'] ?>">
                                <button type="submit" name="verify_payment" class="btn btn-primary" onclick="return confirm('Confirm this payment?')">✓ Verify Payment</button>
                                <button type="submit" name="reject_payment" class="btn btn-danger" onclick="return confirm('Reject this payment?')">✕ Reject</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <div class="table-wrapper" style="margin-bottom:20px;">
                        <table>
                            <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
                            <tbody>
                                <?php foreach ($viewItems as $it): ?>
                                    <tr>
                                        <td><?= e($it['name']) ?></td>
                                        <td><?= $it['quantity'] ?></td>
                                        <td>₱<?= number_format($it['price'], 2) ?></td>
                                        <td>₱<?= number_format($it['price'] * $it['quantity'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr style="background:#f4f7fb;font-weight:700;">
                                    <td colspan="3" class="text-right">Total:</td>
                                    <td style="color:#1d4ed8;">₱<?= number_format($viewOrder['total_amount'], 2) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($viewTracking)): ?>
                        <div style="margin-top:20px;">
                            <div class="form-label" style="margin-bottom:10px;">Recent Tracking Activity</div>
                            <?php foreach ($viewTracking as $t): ?>
                                <div style="display:flex;gap:12px;padding:10px 0;border-bottom:1px solid #f3f4f6;">
                                    <div style="width:8px;height:8px;border-radius:50%;background:#2563eb;margin-top:6px;flex-shrink:0;"></div>
                                    <div style="flex:1;">
                                        <div style="font-size:0.88rem;color:#111827;font-weight:600;"><?= e($t['message']) ?></div>
                                        <div style="font-size:0.78rem;color:#9ca3af;"><?= e($t['location']) ?> · <?= date('M j, Y g:ia', strtotime($t['created_at'])) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Orders List -->
            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr><td colspan="7" style="text-align:center;padding:32px;color:#6b7280;">No orders yet</td></tr>
                            <?php else: foreach ($orders as $o):
                                $colors = ['pending'=>'badge-yellow','processing'=>'badge-blue','shipped'=>'badge-indigo','delivered'=>'badge-green','cancelled'=>'badge-red','returned'=>'badge-gray'];
                                $pColors = ['unpaid'=>'badge-red','pending_verification'=>'badge-yellow','paid'=>'badge-green','refunded'=>'badge-blue'];
                            ?>
                            <tr>
                                <td><strong style="color:#111827;">#<?= e($o['order_number'] ?: $o['id']) ?></strong></td>
                                <td><?= e($o['username']) ?></td>
                                <td>₱<?= number_format($o['total_amount'], 2) ?></td>
                                <td>
                                    <span class="badge <?= $pColors[$o['payment_status']] ?? 'badge-gray' ?>"><?= ucwords(str_replace('_',' ',$o['payment_status'])) ?></span>
                                    <div style="color:#9ca3af;font-size:0.72rem;margin-top:2px;"><?= e($o['payment_method']) ?></div>
                                </td>
                                <td><span class="badge <?= $colors[$o['status']] ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                                <td style="color:#6b7280;"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                                <td style="white-space:nowrap;">
                                    <a href="?view=<?= $o['id'] ?>" class="btn btn-secondary btn-sm">View</a>
                                    <form method="POST" style="display:inline-flex;gap:4px;align-items:center;">
                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                        <select name="status" class="form-select" style="padding:5px 8px;font-size:0.78rem;width:auto;">
                                            <?php foreach (['pending','processing','shipped','delivered','cancelled'] as $s): ?>
                                                <option value="<?= $s ?>" <?= $s===$o['status']?'selected':'' ?>><?= ucfirst($s) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="update_status" class="btn btn-primary btn-sm">Set</button>
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