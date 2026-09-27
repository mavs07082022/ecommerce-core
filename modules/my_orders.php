<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireCustomer();

$pageTitle = 'Order History — E-Commerce Core';
$uid = $_SESSION['user_id'];

// Filters
$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['q'] ?? '');

$sql = "SELECT o.*, 
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count,
        (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) AS total_qty
        FROM orders o
        WHERE o.user_id = ?";
$params = [$uid];

if ($statusFilter !== 'all') {
    $sql .= " AND o.status = ?";
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= " AND (o.order_number LIKE ? OR o.id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY o.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Stats
$stats = [
    'total' => $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?"),
];
$stats['total']->execute([$uid]); $stats['total'] = $stats['total']->fetchColumn();

$stats['delivered'] = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'delivered'");
$stats['delivered']->execute([$uid]); $stats['delivered'] = $stats['delivered']->fetchColumn();

$stats['active'] = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status IN ('pending','processing','shipped')");
$stats['active']->execute([$uid]); $stats['active'] = $stats['active']->fetchColumn();

$stats['spent'] = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id = ? AND status != 'cancelled' AND payment_status = 'paid'");
$stats['spent']->execute([$uid]); $stats['spent'] = $stats['spent']->fetchColumn();

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
                    <h2>Order History</h2>
                    <p>All your past & current orders</p>
                </div>
            </div>
            <a href="<?= moduleUrl('shop.php') ?>" class="btn btn-primary btn-sm">+ Shop More</a>
        </header>

        <div class="page-body">
            <!-- Stats -->
            <div class="grid-4" style="margin-bottom:24px;">
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Total Orders</div>
                        <div class="stat-value"><?= $stats['total'] ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Delivered</div>
                        <div class="stat-value"><?= $stats['delivered'] ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Active</div>
                        <div class="stat-value"><?= $stats['active'] ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Total Spent</div>
                        <div class="stat-value">₱<?= number_format($stats['spent'], 2) ?></div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card card-pad" style="margin-bottom:20px;">
                <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;">
                    <div class="form-group" style="margin:0;flex:1;min-width:180px;">
                        <label class="form-label">Search Order #</label>
                        <input type="text" name="q" class="form-input" placeholder="e.g. ORD-..." value="<?= e($search) ?>">
                    </div>
                    <div class="form-group" style="margin:0;flex:1;min-width:180px;">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['all'=>'All Statuses','pending'=>'Pending','processing'=>'Processing','shipped'=>'Shipped','delivered'=>'Delivered','cancelled'=>'Cancelled','returned'=>'Returned'] as $k=>$v): ?>
                                <option value="<?= $k ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="<?= moduleUrl('my_orders.php') ?>" class="btn btn-secondary">Reset</a>
                </form>
            </div>

            <!-- Orders list -->
            <?php if (empty($orders)): ?>
                <div class="card card-pad" style="text-align:center;padding:60px 20px;">
                    <div style="font-size:3rem;opacity:0.4;margin-bottom:12px;">📦</div>
                    <h3 style="margin:0 0 6px;color:#111827;">No orders found</h3>
                    <p style="color:#6b7280;margin:0 0 20px;"><?= $search || $statusFilter !== 'all' ? 'Try adjusting your filters.' : 'Start shopping to see your orders here.' ?></p>
                    <a href="<?= moduleUrl('shop.php') ?>" class="btn btn-primary">Browse Products</a>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $o):
                                    $colors = ['pending'=>'badge-yellow','processing'=>'badge-blue','shipped'=>'badge-indigo','delivered'=>'badge-green','cancelled'=>'badge-red','returned'=>'badge-gray'];
                                    $pColors = ['unpaid'=>'badge-red','pending_verification'=>'badge-yellow','paid'=>'badge-green','refunded'=>'badge-blue'];
                                ?>
                                <tr>
                                    <td><strong style="color:#111827;">#<?= e($o['order_number'] ?: $o['id']) ?></strong></td>
                                    <td><?= (int)$o['item_count'] ?> item<?= $o['item_count'] != 1 ? 's' : '' ?> (<?= (int)$o['total_qty'] ?> qty)</td>
                                    <td>₱<?= number_format($o['total_amount'], 2) ?></td>
                                    <td><span class="badge <?= $pColors[$o['payment_status']] ?? 'badge-gray' ?>"><?= ucwords(str_replace('_',' ',$o['payment_status'])) ?></span></td>
                                    <td><span class="badge <?= $colors[$o['status']] ?? 'badge-gray' ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                                    <td style="color:#6b7280;font-size:0.83rem;"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                                    <td style="white-space:nowrap;">
                                        <a href="<?= moduleUrl('track_order.php?id=' . $o['id']) ?>" class="btn btn-secondary btn-sm">Track</a>
                                        <?php if ($o['payment_status'] === 'unpaid' && !in_array($o['status'], ['cancelled','returned'])): ?>
                                            <a href="<?= moduleUrl('payment.php?id=' . $o['id']) ?>" class="btn btn-primary btn-sm">Pay</a>
                                        <?php endif; ?>
                                        <?php if (in_array($o['status'], ['delivered','shipped'])): ?>
                                            <a href="<?= moduleUrl('order_review.php?id=' . $o['id']) ?>" class="btn btn-secondary btn-sm">Review</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>