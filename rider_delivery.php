<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
requireRider();

$pageTitle = 'My Deliveries — E-Commerce Core';
$uid = $_SESSION['user_id'];

$r = $pdo->prepare("SELECT * FROM riders WHERE user_id = ?");
$r->execute([$uid]);
$rider = $r->fetch();
if (!$rider) { echo "Rider profile not found."; exit; }
$riderId = $rider['id'];

$orderId = (int)($_GET['id'] ?? 0);
$success = '';
$error = '';

// ==================== Handle Proof of Delivery ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_delivery'])) {
    $oid = (int)$_POST['order_id'];
    $amount = (float)$_POST['amount_collected'];
    $recipient = trim($_POST['recipient_name'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Verify ownership
    $chk = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND rider_id = ? AND status = 'shipped'");
    $chk->execute([$oid, $riderId]);
    $order = $chk->fetch();

    if (!$order) {
        $error = 'Order not found or not assigned to you.';
    } elseif (empty($_FILES['parcel_photo']['name'])) {
        $error = 'Please upload a photo of the delivered parcel.';
    } else {
        try {
            $pdo->beginTransaction();

            // Parcel photo (required)
            $parcelPhoto = handleUpload('parcel_photo', 'delivery_proofs');
            if (!$parcelPhoto) {
                throw new Exception('Failed to upload parcel photo. Check file size/type.');
            }

            // Payment photo (optional — required for COD)
            $paymentPhoto = null;
            if (!empty($_FILES['payment_photo']['name'])) {
                $paymentPhoto = handleUpload('payment_photo', 'delivery_proofs');
            }

            // Insert proof
            $pdo->prepare("
                INSERT INTO delivery_proofs 
                    (order_id, rider_id, parcel_photo, payment_photo, amount_collected, payment_method, notes, recipient_name)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $oid, $riderId, $parcelPhoto, $paymentPhoto, $amount,
                $order['payment_method'], $notes, $recipient
            ]);

            // Update order
            $pdo->prepare("
                UPDATE orders
                SET status = 'delivered',
                    delivered_at = NOW(),
                    amount_collected = ?,
                    payment_status = CASE
                        WHEN payment_status = 'paid' THEN payment_status
                        ELSE 'paid'
                    END,
                    paid_at = COALESCE(paid_at, NOW())
                WHERE id = ?
            ")->execute([$amount, $oid]);

            // Add tracking entry
            $pdo->prepare("
                INSERT INTO order_tracking (order_id, status, message, location)
                VALUES (?, 'delivered', ?, 'Customer Address')
            ")->execute([$oid, "Order delivered by {$rider['full_name']} · ₱" . number_format($amount, 2) . " collected"]);

            // If COD, mark payment as paid
            if (strtoupper($order['payment_method']) === 'COD') {
                $pdo->prepare("UPDATE orders SET payment_status = 'paid', paid_at = NOW() WHERE id = ?")->execute([$oid]);
            }

            $pdo->commit();
            $success = "🎉 Delivery completed! ₱" . number_format($amount, 2) . " recorded.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// If a specific order is selected, load it
$selectedOrder = null; $items = []; $proof = null;
if ($orderId) {
    $s = $pdo->prepare("
        SELECT o.*, u.username, u.full_name AS customer_name, u.phone AS customer_phone, u.email AS customer_email
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ? AND o.rider_id = ?
    ");
    $s->execute([$orderId, $riderId]);
    $selectedOrder = $s->fetch();

    if ($selectedOrder) {
        $i = $pdo->prepare("SELECT oi.*, p.name, p.image_url FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $i->execute([$orderId]);
        $items = $i->fetchAll();

        $p = $pdo->prepare("SELECT * FROM delivery_proofs WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $p->execute([$orderId]);
        $proof = $p->fetch();
    }
}

// List: active + delivered
$active = $pdo->prepare("
    SELECT o.*, u.username, u.full_name AS customer_name, u.phone AS customer_phone
    FROM orders o JOIN users u ON o.user_id = u.id
    WHERE o.rider_id = ? AND o.status = 'shipped'
    ORDER BY o.assigned_at ASC
");
$active->execute([$riderId]);
$activeOrders = $active->fetchAll();

$delivered = $pdo->prepare("
    SELECT o.*, u.username, u.full_name AS customer_name
    FROM orders o JOIN users u ON o.user_id = u.id
    WHERE o.rider_id = ? AND o.status = 'delivered'
    ORDER BY o.delivered_at DESC LIMIT 20
");
$delivered->execute([$riderId]);
$deliveredOrders = $delivered->fetchAll();

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
                    <h2><?= $selectedOrder ? 'Deliver Order #' . e($selectedOrder['order_number'] ?: $selectedOrder['id']) : 'My Deliveries' ?></h2>
                    <p><?= $selectedOrder ? 'Upload proof of delivery' : 'All assigned & completed deliveries' ?></p>
                </div>
            </div>
            <?php if ($selectedOrder): ?>
                <a href="<?= baseUrl('rider_delivery.php') ?>" class="btn btn-secondary">← Back to list</a>
            <?php endif; ?>
        </header>

        <div class="page-body">
            <?php if ($success): ?><div class="alert alert-success">✅ <?= e($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error">⚠️ <?= e($error) ?></div><?php endif; ?>

            <?php if ($selectedOrder): ?>
                <!-- Delivery Detail -->
                <div style="display:grid;grid-template-columns:1fr 400px;gap:24px;">
                    <div>
                        <!-- Customer info card -->
                        <div class="card card-pad" style="margin-bottom:20px;">
                            <h3 class="section-title" style="margin-bottom:14px;">📋 Delivery Information</h3>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                                <div>
                                    <div class="form-label">Customer</div>
                                    <div style="font-weight:700;"><?= e($selectedOrder['customer_name'] ?: $selectedOrder['username']) ?></div>
                                    <?php if ($selectedOrder['contact_phone']): ?>
                                        <div style="color:#6b7280;font-size:0.85rem;margin-top:4px;">📞 <?= e($selectedOrder['contact_phone']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="form-label">Payment Method</div>
                                    <div style="font-weight:700;"><?= e($selectedOrder['payment_method']) ?></div>
                                    <div style="font-size:0.82rem;color:#6b7280;margin-top:4px;">Status: <?= ucwords(str_replace('_',' ', $selectedOrder['payment_status'])) ?></div>
                                </div>
                            </div>

                            <div style="margin-top:16px;padding:14px;background:#eff6ff;border-radius:10px;">
                                <div class="form-label" style="color:#1d4ed8;">📍 Shipping Address</div>
                                <div style="font-size:0.9rem;line-height:1.5;"><?= nl2br(e($selectedOrder['shipping_address'])) ?></div>
                            </div>

                            <div style="margin-top:16px;padding:16px;background:#fef3c7;border-radius:10px;border:1px solid #fcd34d;">
                                <div class="form-label" style="color:#92400e;">💰 Amount to Collect</div>
                                <div style="font-size:1.6rem;font-weight:800;color:#92400e;">₱<?= number_format($selectedOrder['total_amount'], 2) ?></div>
                            </div>
                        </div>

                        <!-- Items list -->
                        <div class="card card-pad">
                            <h3 class="section-title" style="margin-bottom:14px;">📦 Package Contents</h3>
                            <div class="table-wrapper">
                                <table>
                                    <thead><tr><th>Product</th><th>Qty</th><th>Subtotal</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($items as $it): ?>
                                            <tr>
                                                <td><?= e($it['name']) ?></td>
                                                <td><?= (int)$it['quantity'] ?></td>
                                                <td>₱<?= number_format($it['price'] * $it['quantity'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Proof of delivery form -->
                    <div>
                        <?php if ($proof): ?>
                            <div class="card card-pad" style="border-left:4px solid #10b981;">
                                <h3 class="section-title" style="color:#065f46;margin-bottom:14px;">✅ Delivery Completed</h3>
                                <div style="font-size:0.85rem;color:#6b7280;margin-bottom:12px;">Submitted <?= timeAgo($proof['created_at']) ?></div>
                                <div class="form-label">Parcel Photo</div>
                                <img src="<?= baseUrl($proof['parcel_photo']) ?>" style="width:100%;border-radius:10px;margin-bottom:12px;">
                                <?php if ($proof['payment_photo']): ?>
                                    <div class="form-label">Payment Photo</div>
                                    <img src="<?= baseUrl($proof['payment_photo']) ?>" style="width:100%;border-radius:10px;margin-bottom:12px;">
                                <?php endif; ?>
                                <div style="padding:12px;background:#ecfdf5;border-radius:8px;">
                                    <div style="font-size:0.78rem;color:#065f46;font-weight:700;">Amount Collected</div>
                                    <div style="font-size:1.3rem;font-weight:800;color:#065f46;">₱<?= number_format($proof['amount_collected'], 2) ?></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="POST" enctype="multipart/form-data" class="card card-pad" style="border-left:4px solid #2563eb;">
                                <h3 class="section-title" style="margin-bottom:14px;">📸 Proof of Delivery</h3>
                                <input type="hidden" name="order_id" value="<?= $selectedOrder['id'] ?>">

                                <div class="form-group">
                                    <label class="form-label">Parcel Photo *</label>
                                    <label class="file-drop" for="parcelPhoto">
                                        <div class="label">📷 Take / Upload Photo</div>
                                        <div class="hint">Show the parcel at the customer's door</div>
                                        <input type="file" id="parcelPhoto" name="parcel_photo" accept="image/*" capture="environment" required onchange="previewImg(this, 'parcelPreview')">
                                    </label>
                                    <div id="parcelPreview"></div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Payment Photo (if paying on the spot)</label>
                                    <label class="file-drop" for="paymentPhoto">
                                        <div class="label">💰 Upload Payment Photo</div>
                                        <div class="hint">Screenshot of GCash / receipt / cash</div>
                                        <input type="file" id="paymentPhoto" name="payment_photo" accept="image/*" onchange="previewImg(this, 'paymentPreview')">
                                    </label>
                                    <div id="paymentPreview"></div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Amount Collected (₱) *</label>
                                    <input type="number" step="0.01" min="0" name="amount_collected" class="form-input" value="<?= number_format($selectedOrder['total_amount'], 2, '.', '') ?>" required>
                                    <div style="font-size:0.75rem;color:#6b7280;margin-top:6px;">Amount expected: ₱<?= number_format($selectedOrder['total_amount'], 2) ?></div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Recipient Name</label>
                                    <input type="text" name="recipient_name" class="form-input" placeholder="Who received the parcel?" value="<?= e($selectedOrder['customer_name'] ?: $selectedOrder['username']) ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Notes (optional)</label>
                                    <textarea name="notes" class="form-textarea" placeholder="Any delivery notes..."></textarea>
                                </div>

                                <button type="submit" name="submit_delivery" class="btn btn-primary" style="width:100%;padding:14px;font-weight:700;" onclick="return confirm('Confirm delivery? This will mark the order as delivered.')">
                                    ✅ Mark as Delivered
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- Active list -->
                <div class="card" style="margin-bottom:24px;">
                    <div style="padding:20px 24px;border-bottom:1px solid #e5e7eb;">
                        <h3 class="section-title">🚚 Active Deliveries (<?= count($activeOrders) ?>)</h3>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead><tr><th>Order</th><th>Customer</th><th>Address</th><th>Amount</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php if (empty($activeOrders)): ?>
                                    <tr><td colspan="5" style="text-align:center;padding:32px;color:#6b7280;">No active deliveries</td></tr>
                                <?php else: foreach ($activeOrders as $o): ?>
                                    <tr>
                                        <td><strong>#<?= e($o['order_number'] ?: $o['id']) ?></strong></td>
                                        <td>
                                            <div><?= e($o['customer_name'] ?: $o['username']) ?></div>
                                            <?php if ($o['contact_phone']): ?><div style="color:#6b7280;font-size:0.78rem;">📞 <?= e($o['contact_phone']) ?></div><?php endif; ?>
                                        </td>
                                        <td style="max-width:280px;font-size:0.83rem;"><?= e($o['shipping_address']) ?></td>
                                        <td><strong style="color:#1d4ed8;">₱<?= number_format($o['total_amount'], 2) ?></strong></td>
                                        <td><a href="<?= baseUrl('rider_delivery.php?id=' . $o['id']) ?>" class="btn btn-primary btn-sm">Deliver</a></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Delivered list -->
                <div class="card">
                    <div style="padding:20px 24px;border-bottom:1px solid #e5e7eb;">
                        <h3 class="section-title">✅ Completed Deliveries</h3>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead><tr><th>Order</th><th>Customer</th><th>Collected</th><th>Delivered</th><th></th></tr></thead>
                            <tbody>
                                <?php if (empty($deliveredOrders)): ?>
                                    <tr><td colspan="5" style="text-align:center;padding:32px;color:#6b7280;">No completed deliveries yet</td></tr>
                                <?php else: foreach ($deliveredOrders as $o): ?>
                                    <tr>
                                        <td><strong>#<?= e($o['order_number'] ?: $o['id']) ?></strong></td>
                                        <td><?= e($o['customer_name'] ?: $o['username']) ?></td>
                                        <td><span class="badge badge-green">₱<?= number_format($o['amount_collected'] ?? 0, 2) ?></span></td>
                                        <td style="color:#6b7280;font-size:0.83rem;"><?= timeAgo($o['delivered_at']) ?></td>
                                        <td><a href="<?= baseUrl('rider_delivery.php?id=' . $o['id']) ?>" class="btn btn-secondary btn-sm">View</a></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </div>
</div>

<style>
.file-drop {
    display: block;
    border: 2px dashed #bfdbfe;
    border-radius: 10px;
    padding: 18px;
    text-align: center;
    cursor: pointer;
    background: #f8fbff;
    transition: all 0.15s;
    margin-top: 6px;
}
.file-drop:hover { border-color: #2563eb; background: #eff6ff; }
.file-drop input[type="file"] { display: none; }
.file-drop .label { color: #1d4ed8; font-weight: 700; font-size: 0.88rem; }
.file-drop .hint { color: #6b7280; font-size: 0.75rem; margin-top: 4px; }
img.preview-thumb { width: 100%; max-height: 180px; object-fit: cover; border-radius: 10px; margin-top: 10px; }
</style>

<script>
function previewImg(input, previewId) {
    const box = document.getElementById(previewId);
    box.innerHTML = '';
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            box.innerHTML = '<img class="preview-thumb" src="' + e.target.result + '" alt="">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>