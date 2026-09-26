<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pageTitle = 'Track Order — E-Commerce Core';
$uid = $_SESSION['user_id'];

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) redirectRoot('customer_dashboard.php');

// Fetch order and verify ownership
$stmt = $pdo->prepare("SELECT o.*, u.username, u.email, u.phone FROM orders o JOIN users u ON o.user_id=u.id WHERE o.id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    echo "<p style='padding:40px;text-align:center;font-family:sans-serif;'>Order not found.</p>";
    exit;
}
if (!isAdmin() && !isProductManager() && $order['user_id'] != $uid) {
    echo "<p style='padding:40px;text-align:center;font-family:sans-serif;'>Unauthorized.</p>";
    exit;
}

// Fetch order items
$items = $pdo->prepare("SELECT oi.*, p.name, p.image_url FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id=?");
$items->execute([$orderId]);
$orderItems = $items->fetchAll();

// Fetch tracking history
$tracking = $pdo->prepare("SELECT * FROM order_tracking WHERE order_id = ? ORDER BY created_at ASC");
$tracking->execute([$orderId]);
$trackingHistory = $tracking->fetchAll();

// If no tracking records exist, generate defaults
if (empty($trackingHistory)) {
    $stageOrder = ['pending'=>1,'processing'=>2,'shipped'=>3,'delivered'=>4,'cancelled'=>5,'returned'=>5];
    $currentStage = $stageOrder[$order['status']] ?? 1;

    $defaultHistory = [
        1 => ['pending', 'Order placed successfully', 'Processing Center'],
        2 => ['processing', 'Order is being prepared', 'Warehouse'],
        3 => ['shipped', 'Package is on the way', 'In Transit'],
        4 => ['delivered', 'Package has been delivered', 'Customer Address'],
    ];

    foreach ($defaultHistory as $stage => $info) {
        if ($stage <= $currentStage) {
            $trackingHistory[] = [
                'status' => $info[0],
                'message' => $info[1],
                'location' => $info[2],
                'created_at' => $order['created_at'],
            ];
        }
    }
}

$progressStages = [
    ['key' => 'pending',    'label' => 'Order Placed',  'icon' => '📋'],
    ['key' => 'processing', 'label' => 'Processing',    'icon' => '📦'],
    ['key' => 'shipped',    'label' => 'Shipped',       'icon' => '🚚'],
    ['key' => 'delivered',  'label' => 'Delivered',     'icon' => '✅'],
];

$stageOrder = ['pending'=>0,'processing'=>1,'shipped'=>2,'delivered'=>3,'cancelled'=>-1,'returned'=>-1];
$currentStageIndex = $stageOrder[$order['status']] ?? 0;

$statusColors = [
    'pending' => ['badge-yellow', 'Pending'],
    'processing' => ['badge-blue', 'Processing'],
    'shipped' => ['badge-indigo', 'Shipped'],
    'delivered' => ['badge-green', 'Delivered'],
    'cancelled' => ['badge-red', 'Cancelled'],
    'returned' => ['badge-gray', 'Returned'],
];
$statusBadge = $statusColors[$order['status']] ?? ['badge-gray', 'Unknown'];

$paymentColors = [
    'unpaid' => 'badge-red',
    'pending_verification' => 'badge-yellow',
    'paid' => 'badge-green',
    'refunded' => 'badge-blue',
];

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.track-timeline { display: flex; justify-content: space-between; margin: 32px 0; position: relative; padding: 0 8px; }
.track-timeline::before { content: ''; position: absolute; top: 24px; left: 40px; right: 40px; height: 3px; background: #e5e7eb; z-index: 0; }
.track-progress-line { position: absolute; top: 24px; left: 40px; height: 3px; background: linear-gradient(90deg, #2563eb, #1d4ed8); z-index: 1; transition: width 0.5s; }
.track-stage { position: relative; z-index: 2; text-align: center; flex: 1; }
.track-stage-circle { width: 48px; height: 48px; border-radius: 50%; background: #fff; border: 3px solid #e5e7eb; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 1.2rem; transition: all 0.3s; }
.track-stage.completed .track-stage-circle { background: #2563eb; border-color: #2563eb; color: #fff; box-shadow: 0 4px 14px rgba(37,99,235,0.4); }
.track-stage.current .track-stage-circle { background: #2563eb; border-color: #2563eb; color: #fff; box-shadow: 0 0 0 6px rgba(37,99,235,0.15), 0 4px 14px rgba(37,99,235,0.4); animation: pulse 2s infinite; }
@keyframes pulse { 0%, 100% { box-shadow: 0 0 0 6px rgba(37,99,235,0.15), 0 4px 14px rgba(37,99,235,0.4); } 50% { box-shadow: 0 0 0 12px rgba(37,99,235,0.08), 0 4px 14px rgba(37,99,235,0.4); } }
.track-stage-label { font-size: 0.82rem; font-weight: 600; color: #6b7280; }
.track-stage.completed .track-stage-label, .track-stage.current .track-stage-label { color: #111827; }
.track-log-item { display: flex; gap: 16px; padding: 16px 0; border-bottom: 1px solid #f3f4f6; }
.track-log-item:last-child { border-bottom: none; }
.track-log-dot { width: 12px; height: 12px; border-radius: 50%; background: #2563eb; flex-shrink: 0; margin-top: 6px; box-shadow: 0 0 0 4px rgba(37,99,235,0.15); }
@media (max-width: 640px) { .track-stage-label { font-size: 0.7rem; } .track-stage-circle { width: 38px; height: 38px; font-size: 1rem; } .track-timeline::before, .track-progress-line { top: 19px; left: 20px; right: 20px; } }
</style>

<div class="app-layout">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" onclick="openSidebar()" aria-label="Toggle menu">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <h2>Track Order #<?= e($order['order_number'] ?: 'ORD-' . str_pad($order['id'], 6, '0', STR_PAD_LEFT)) ?></h2>
                    <p>Placed on <?= date('F j, Y g:ia', strtotime($order['created_at'])) ?></p>
                </div>
            </div>
            <a href="<?= baseUrl('modules/shop.php') ?>" class="btn btn-secondary">← Back to Shop</a>
        </header>

        <div class="page-body">
            <?php if ($order['status'] === 'cancelled'): ?>
                <div class="card card-pad" style="border-left:4px solid #dc2626;margin-bottom:24px;">
                    <h3 style="color:#dc2626;margin:0 0 6px;">❌ Order Cancelled</h3>
                    <p style="color:#6b7280;margin:0;">This order has been cancelled.</p>
                </div>
            <?php elseif ($order['status'] === 'returned'): ?>
                <div class="card card-pad" style="border-left:4px solid #6b7280;margin-bottom:24px;">
                    <h3 style="color:#374151;margin:0 0 6px;">↩️ Order Returned</h3>
                    <p style="color:#6b7280;margin:0;">This order has been returned. Refund is being processed.</p>
                </div>
            <?php else: ?>
                <div class="card card-pad" style="margin-bottom:24px;">
                    <h3 class="section-title" style="margin-bottom:20px;">Delivery Progress</h3>
                    <div class="track-timeline">
                        <div class="track-progress-line" style="width: calc(<?= ($currentStageIndex / 3) * 100 ?>% - 40px);"></div>
                        <?php foreach ($progressStages as $i => $stage):
                            $cls = '';
                            if ($i < $currentStageIndex) $cls = 'completed';
                            elseif ($i === $currentStageIndex) $cls = 'current';
                        ?>
                            <div class="track-stage <?= $cls ?>">
                                <div class="track-stage-circle"><?= $i <= $currentStageIndex ? '✓' : $stage['icon'] ?></div>
                                <div class="track-stage-label"><?= $stage['label'] ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($order['payment_status'] === 'unpaid' && !in_array($order['status'], ['cancelled','returned'])): ?>
                <div class="card card-pad" style="border-left:4px solid #dc2626;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                    <div>
                        <h3 style="color:#dc2626;margin:0 0 6px;">💳 Payment Required</h3>
                        <p style="color:#6b7280;margin:0;">Please complete payment to proceed with this order.</p>
                    </div>
                    <a href="<?= baseUrl('modules/payment.php?id=' . $order['id']) ?>" class="btn btn-primary">Pay Now →</a>
                </div>
            <?php elseif ($order['payment_status'] === 'pending_verification'): ?>
                <div class="card card-pad" style="border-left:4px solid #f59e0b;margin-bottom:24px;">
                    <h3 style="color:#92400e;margin:0 0 6px;">⏳ Payment Under Review</h3>
                    <p style="color:#6b7280;margin:0;">Your payment is being verified by our team. We'll update you shortly.</p>
                </div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
                <div class="card card-pad">
                    <h3 class="section-title" style="margin-bottom:16px;">📦 Order Information</h3>
                    <div style="display:flex;flex-direction:column;gap:10px;font-size:0.88rem;">
                        <div class="flex-between"><span style="color:#6b7280;">Order Number:</span><strong><?= e($order['order_number'] ?: 'ORD-' . str_pad($order['id'], 6, '0', STR_PAD_LEFT)) ?></strong></div>
                        <div class="flex-between"><span style="color:#6b7280;">Status:</span><span class="badge <?= $statusBadge[0] ?>"><?= $statusBadge[1] ?></span></div>
                        <div class="flex-between"><span style="color:#6b7280;">Payment:</span><span class="badge <?= $paymentColors[$order['payment_status']] ?? 'badge-gray' ?>"><?= ucwords(str_replace('_', ' ', $order['payment_status'])) ?></span></div>
                        <div class="flex-between"><span style="color:#6b7280;">Method:</span><strong><?= e($order['payment_method']) ?></strong></div>
                        <div class="flex-between" style="padding-top:10px;border-top:1px solid #f3f4f6;">
                            <span style="color:#6b7280;">Total:</span>
                            <strong style="color:#1d4ed8;font-size:1.05rem;">₱<?= number_format($order['total_amount'], 2) ?></strong>
                        </div>
                    </div>
                </div>

                <div class="card card-pad">
                    <h3 class="section-title" style="margin-bottom:16px;">🚚 Shipping Details</h3>
                    <div style="font-size:0.88rem;line-height:1.7;">
                        <div style="color:#6b7280;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">Delivery Address</div>
                        <div style="color:#111827;font-weight:600;"><?= e($order['username']) ?></div>
                        <div style="color:#374151;"><?= nl2br(e($order['shipping_address'])) ?></div>
                        <?php if ($order['contact_phone']): ?>
                            <div style="color:#6b7280;margin-top:6px;">📞 <?= e($order['contact_phone']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom:24px;">
                <div style="padding:20px 24px;border-bottom:1px solid #e5e7eb;">
                    <h3 class="section-title">Items Ordered</h3>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $it): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <?php if ($it['image_url']): ?>
                                            <img src="<?= baseUrl($it['image_url']) ?>" style="width:40px;height:40px;border-radius:8px;object-fit:cover;">
                                        <?php else: ?>
                                            <div style="width:40px;height:40px;border-radius:8px;background:#eff6ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-weight:800;"><?= strtoupper(substr($it['name'],0,1)) ?></div>
                                        <?php endif; ?>
                                        <strong><?= e($it['name']) ?></strong>
                                    </div>
                                </td>
                                <td><?= $it['quantity'] ?></td>
                                <td>₱<?= number_format($it['price'], 2) ?></td>
                                <td>₱<?= number_format($it['price'] * $it['quantity'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr style="background:#f4f7fb;font-weight:700;">
                                <td colspan="3" class="text-right">Total:</td>
                                <td style="color:#1d4ed8;">₱<?= number_format($order['total_amount'], 2) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card card-pad">
                <h3 class="section-title" style="margin-bottom:16px;">📋 Activity Log</h3>
                <?php if (empty($trackingHistory)): ?>
                    <p style="color:#6b7280;">No tracking updates yet.</p>
                <?php else: ?>
                    <?php foreach ($trackingHistory as $t): ?>
                        <div class="track-log-item">
                            <div class="track-log-dot"></div>
                            <div style="flex:1;">
                                <div style="font-weight:600;color:#111827;"><?= e($t['message'] ?: ucfirst($t['status'])) ?></div>
                                <?php if (!empty($t['location'])): ?>
                                    <div style="color:#6b7280;font-size:0.83rem;margin-top:2px;">📍 <?= e($t['location']) ?></div>
                                <?php endif; ?>
                                <div style="color:#9ca3af;font-size:0.78rem;margin-top:4px;"><?= date('F j, Y \a\t g:ia', strtotime($t['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>