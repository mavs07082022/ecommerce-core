<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pageTitle = 'Track Order — E-Commerce Core';
$uid = $_SESSION['user_id'];

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) redirectRoot('customer_dashboard.php');

// Fetch order with rider info joined
$stmt = $pdo->prepare("
    SELECT o.*, u.username, u.email, u.phone,
           r.full_name AS rider_name,
           r.phone     AS rider_phone,
           r.vehicle_type AS rider_vehicle,
           r.plate_number AS rider_plate
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN riders r ON o.rider_id = r.id
    WHERE o.id = ?
");
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

// Fallback to default timeline if no tracking records
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

// Fetch proof of delivery if delivered
$proof = $pdo->prepare("SELECT * FROM delivery_proofs WHERE order_id = ? ORDER BY id DESC LIMIT 1");
$proof->execute([$orderId]);
$proof = $proof->fetch();

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

/* Rider card */
.rider-card {
    background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
    border: 1px solid #bfdbfe;
    border-left: 4px solid #2563eb;
    border-radius: 14px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
    margin-bottom: 24px;
    box-shadow: 0 4px 14px rgba(37,99,235,0.08);
}
.rider-avatar-lg {
    width: 64px; height: 64px;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff; border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem;
    flex-shrink: 0;
    box-shadow: 0 8px 20px rgba(37,99,235,0.3);
}
.rider-info { flex: 1; min-width: 200px; }
.rider-info .lbl {
    font-size: 0.72rem; font-weight: 700;
    color: #1d4ed8; text-transform: uppercase;
    letter-spacing: 0.08em;
}
.rider-info .name {
    font-size: 1.2rem; font-weight: 800;
    color: #111827; margin: 4px 0;
}
.rider-info .meta { color: #6b7280; font-size: 0.85rem; }
.rider-contact { text-align: right; }
.rider-contact .lbl {
    font-size: 0.72rem; font-weight: 700;
    color: #1d4ed8; text-transform: uppercase;
    letter-spacing: 0.08em; margin-bottom: 6px;
}
.rider-contact a {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 18px; border-radius: 10px;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff; font-weight: 700; font-size: 0.9rem;
    text-decoration: none;
    box-shadow: 0 6px 16px rgba(37,99,235,0.3);
    transition: all 0.15s;
}
.rider-contact a:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(37,99,235,0.4); }

/* Proof card */
.proof-card {
    background: linear-gradient(135deg, #ecfdf5 0%, #ffffff 100%);
    border: 1px solid #a7f3d0;
    border-left: 4px solid #10b981;
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 24px;
}
.proof-title {
    color: #065f46; font-weight: 800; font-size: 1.05rem;
    margin: 0 0 16px; display: flex; align-items: center; gap: 8px;
}
.proof-photos { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px; }
.proof-photo {
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #a7f3d0;
    background: #fff;
}
.proof-photo img { width: 100%; display: block; max-height: 280px; object-fit: cover; cursor: zoom-in; }
.proof-photo .cap {
    padding: 8px 12px; font-size: 0.72rem; font-weight: 700;
    color: #065f46; background: #ecfdf5;
    text-transform: uppercase; letter-spacing: 0.05em;
}
.proof-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.proof-stat {
    background: #ecfdf5; border-radius: 10px; padding: 12px 14px;
    border: 1px solid #a7f3d0;
}
.proof-stat .lbl {
    font-size: 0.68rem; font-weight: 700; color: #065f46;
    text-transform: uppercase; letter-spacing: 0.05em;
}
.proof-stat .val {
    font-size: 1.15rem; font-weight: 800; color: #065f46;
    margin-top: 4px;
}

/* Lightbox */
.lightbox { position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 20px; }
.lightbox.active { display: flex; }
.lightbox img { max-width: 92vw; max-height: 88vh; border-radius: 12px; }
.lightbox-close { position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.15); color: #fff; border: none; width: 42px; height: 42px; border-radius: 50%; cursor: pointer; font-size: 22px; }

@media (max-width: 640px) {
    .track-stage-label { font-size: 0.7rem; }
    .track-stage-circle { width: 38px; height: 38px; font-size: 1rem; }
    .track-timeline::before, .track-progress-line { top: 19px; left: 20px; right: 20px; }
    .rider-card { padding: 16px; }
    .rider-avatar-lg { width: 52px; height: 52px; font-size: 1.3rem; }
    .rider-info .name { font-size: 1.05rem; }
    .rider-contact { width: 100%; text-align: left; }
    .proof-stats { grid-template-columns: 1fr; }
}
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

            <!-- ===================== RIDER CARD ===================== -->
            <?php if (!empty($order['rider_name']) && in_array($order['status'], ['shipped','delivered'])): ?>
                <div class="rider-card">
                    <div class="rider-avatar-lg">🛵</div>
                    <div class="rider-info">
                        <div class="lbl">Your Delivery Rider</div>
                        <div class="name"><?= e($order['rider_name']) ?></div>
                        <div class="meta">
                            <?= e($order['rider_vehicle'] ?: 'Motorcycle') ?>
                            <?php if (!empty($order['rider_plate'])): ?>
                                · Plate <?= e($order['rider_plate']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="rider-contact">
                        <div class="lbl">Contact</div>
                        <a href="tel:<?= e($order['rider_phone']) ?>">
                            📞 <?= e($order['rider_phone']) ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ===================== PROOF OF DELIVERY ===================== -->
            <?php if ($proof && $order['status'] === 'delivered'): ?>
                <div class="proof-card">
                    <h3 class="proof-title">✅ Proof of Delivery</h3>

                    <div class="proof-photos">
                        <div class="proof-photo">
                            <img src="<?= baseUrl($proof['parcel_photo']) ?>"
                                 alt="Parcel photo"
                                 onclick="openLightbox('<?= baseUrl($proof['parcel_photo']) ?>')">
                            <div class="cap">📷 Parcel Photo</div>
                        </div>
                        <?php if (!empty($proof['payment_photo'])): ?>
                            <div class="proof-photo">
                                <img src="<?= baseUrl($proof['payment_photo']) ?>"
                                     alt="Payment photo"
                                     onclick="openLightbox('<?= baseUrl($proof['payment_photo']) ?>')">
                                <div class="cap">💰 Payment Photo</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="proof-stats">
                        <div class="proof-stat">
                            <div class="lbl">Amount Collected</div>
                            <div class="val">₱<?= number_format($proof['amount_collected'], 2) ?></div>
                        </div>
                        <div class="proof-stat">
                            <div class="lbl">Received By</div>
                            <div class="val" style="font-size:1rem;"><?= e($proof['recipient_name'] ?: $order['username']) ?></div>
                        </div>
                    </div>

                    <?php if (!empty($proof['notes'])): ?>
                        <div style="margin-top:14px;padding:12px 14px;background:#fff;border:1px solid #a7f3d0;border-radius:10px;">
                            <div class="lbl" style="font-size:0.7rem;font-weight:700;color:#065f46;text-transform:uppercase;letter-spacing:0.05em;">Notes</div>
                            <div style="color:#374151;font-size:0.88rem;margin-top:4px;"><?= nl2br(e($proof['notes'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- ===================== PAYMENT ALERTS ===================== -->
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

            <!-- ===================== ORDER + SHIPPING ===================== -->
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

            <!-- ===================== ITEMS ===================== -->
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

            <!-- ===================== ACTIVITY LOG ===================== -->
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

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="if(event.target.id==='lightbox')closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()">✕</button>
    <img id="lightboxImg" src="" alt="">
</div>

<script>
function openLightbox(url) {
    document.getElementById('lightboxImg').src = url;
    document.getElementById('lightbox').classList.add('active');
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeLightbox();
});
</script>