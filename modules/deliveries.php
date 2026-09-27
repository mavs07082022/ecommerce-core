<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireProductManager();

$pageTitle = 'Deliveries — E-Commerce Core';
$msg = '';
$error = '';

// Assign rider
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_rider'])) {
    $orderId = (int)$_POST['order_id'];
    $riderId = (int)$_POST['rider_id'];

    try {
        $pdo->beginTransaction();

        $o = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $o->execute([$orderId]);
        $order = $o->fetch();

        if (!$order) {
            throw new Exception('Order not found.');
        }

        $r = $pdo->prepare("SELECT * FROM riders WHERE id = ? AND status = 'active'");
        $r->execute([$riderId]);
        $rider = $r->fetch();

        if (!$rider) {
            throw new Exception('Rider not found or inactive.');
        }

        // Update order
        $pdo->prepare("UPDATE orders SET rider_id = ?, assigned_at = NOW() WHERE id = ?")
            ->execute([$riderId, $orderId]);

        // If order status is processing, move to shipped
        if (in_array($order['status'], ['pending','processing'])) {
            $pdo->prepare("UPDATE orders SET status = 'shipped', out_for_delivery_at = NOW() WHERE id = ?")
                ->execute([$orderId]);
        }

        // Tracking entry
        $pdo->prepare("
            INSERT INTO order_tracking (order_id, status, message, location)
            VALUES (?, 'shipped', ?, 'In Transit')
        ")->execute([$orderId, "Assigned to rider {$rider['full_name']} ({$rider['phone']}) for delivery"]);

        $pdo->commit();
        $msg = "Rider {$rider['full_name']} assigned to order #{$order['order_number']}.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error: ' . $e->getMessage();
    }
}

// Unassign rider
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unassign_rider'])) {
    $orderId = (int)$_POST['order_id'];
    $pdo->prepare("UPDATE orders SET rider_id = NULL, assigned_at = NULL WHERE id = ?")->execute([$orderId]);
    $msg = "Rider removed from order.";
}

// Filters
$filter = $_GET['filter'] ?? 'unassigned';
$sql = "SELECT o.*, u.username, u.full_name AS customer_name, r.full_name AS rider_name, r.phone AS rider_phone
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN riders r ON o.rider_id = r.id
        WHERE o.status IN ('pending','processing','shipped')";

if ($filter === 'unassigned') $sql .= " AND o.rider_id IS NULL";
elseif ($filter === 'assigned') $sql .= " AND o.rider_id IS NOT NULL";

$sql .= " ORDER BY o.id DESC";
$orders = $pdo->query($sql)->fetchAll();

$riders = $pdo->query("SELECT * FROM riders WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();

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
                    <h2>Deliveries</h2>
                    <p>Assign riders & monitor deliveries</p>
                </div>
            </div>
        </header>

        <div class="page-body">
            <?php if ($msg): ?><div class="alert alert-success">✅ <?= e($msg) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error">⚠️ <?= e($error) ?></div><?php endif; ?>

            <div class="card card-pad" style="margin-bottom:20px;">
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="?filter=unassigned" class="btn <?= $filter === 'unassigned' ? 'btn-primary' : 'btn-secondary' ?>">Needs Rider</a>
                    <a href="?filter=assigned" class="btn <?= $filter === 'assigned' ? 'btn-primary' : 'btn-secondary' ?>">Assigned</a>
                    <a href="?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">All Active</a>
                </div>
            </div>

            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr><th>Order</th><th>Customer</th><th>Address</th><th>Amount</th><th>Assigned Rider</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr><td colspan="7" style="text-align:center;padding:40px;color:#6b7280;">No orders in this view</td></tr>
                            <?php else: foreach ($orders as $o):
                                $colors = ['pending'=>'badge-yellow','processing'=>'badge-blue','shipped'=>'badge-indigo'];
                            ?>
                                <tr>
                                    <td><strong>#<?= e($o['order_number'] ?: $o['id']) ?></strong></td>
                                    <td>
                                        <div><?= e($o['customer_name'] ?: $o['username']) ?></div>
                                        <?php if ($o['contact_phone']): ?><div style="color:#6b7280;font-size:0.78rem;">📞 <?= e($o['contact_phone']) ?></div><?php endif; ?>
                                    </td>
                                    <td style="max-width:260px;font-size:0.83rem;"><?= e($o['shipping_address']) ?></td>
                                    <td><strong style="color:#1d4ed8;">₱<?= number_format($o['total_amount'], 2) ?></strong></td>
                                    <td>
                                        <?php if ($o['rider_name']): ?>
                                            <div style="display:flex;align-items:center;gap:8px;">
                                                <div style="width:30px;height:30px;background:#2563eb;color:#fff;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.75rem;">
                                                    <?= strtoupper(substr($o['rider_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight:600;font-size:0.85rem;"><?= e($o['rider_name']) ?></div>
                                                    <div style="color:#6b7280;font-size:0.72rem;"><?= e($o['rider_phone']) ?></div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:#9ca3af;font-size:0.83rem;">— Unassigned —</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?= $colors[$o['status']] ?? 'badge-gray' ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                                    <td style="white-space:nowrap;">
                                        <?php if ($o['rider_id']): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove rider from this order?')">
                                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                <button type="submit" name="unassign_rider" class="btn btn-secondary btn-sm">Unassign</button>
                                            </form>
                                        <?php else: ?>
                                            <button onclick="assignRider(<?= $o['id'] ?>, '<?= e($o['order_number'] ?: $o['id']) ?>')" class="btn btn-primary btn-sm">Assign Rider</button>
                                        <?php endif; ?>
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

<!-- Assign Modal -->
<div id="assignModal" class="modal-overlay" style="display:none;">
    <div class="modal" style="max-width:480px;">
        <h3>Assign Rider to Order #<span id="assignOrderNo"></span></h3>
        <form method="POST">
            <input type="hidden" name="order_id" id="assignOrderId">
            <div class="form-group">
                <label class="form-label">Select Rider *</label>
                <select name="rider_id" class="form-select" required>
                    <option value="">— Choose a rider —</option>
                    <?php foreach ($riders as $r): ?>
                        <option value="<?= $r['id'] ?>">
                            <?= e($r['full_name']) ?> · <?= e($r['phone']) ?> (<?= (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE rider_id = {$r['id']} AND status = 'shipped'")->fetchColumn() ?> active)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="document.getElementById('assignModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" name="assign_rider" class="btn btn-primary">Assign & Ship</button>
            </div>
        </form>
    </div>
</div>

<script>
function assignRider(orderId, orderNo) {
    document.getElementById('assignOrderId').value = orderId;
    document.getElementById('assignOrderNo').textContent = orderNo;
    document.getElementById('assignModal').style.display = 'flex';
}
</script>