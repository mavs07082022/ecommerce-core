<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireCustomer();

$pageTitle = 'Review Order — E-Commerce Core';
$uid = $_SESSION['user_id'];
$orderId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? AND status IN ('delivered','shipped')");
$stmt->execute([$orderId, $uid]);
$order = $stmt->fetch();

if (!$order) {
    redirectRoot('modules/my_orders.php');
}

$items = $pdo->prepare("
    SELECT oi.*, p.name, p.image_url, p.id AS product_id
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$items->execute([$orderId]);
$items = $items->fetchAll();

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
                    <h2>Review Your Order</h2>
                    <p>Order #<?= e($order['order_number'] ?: $order['id']) ?></p>
                </div>
            </div>
            <a href="<?= moduleUrl('my_orders.php') ?>" class="btn btn-secondary">← Back</a>
        </header>

        <div class="page-body">
            <div class="card card-pad" style="margin-bottom:20px;">
                <h3 style="margin:0 0 6px;font-size:1rem;">Tap a product to write a review</h3>
                <p style="color:#6b7280;margin:0;font-size:0.85rem;">Share photos or videos of the product you received.</p>
            </div>

            <div class="product-grid">
                <?php foreach ($items as $it): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if ($it['image_url']): ?>
                                <img src="<?= baseUrl($it['image_url']) ?>" alt="<?= e($it['name']) ?>">
                            <?php else: ?>
                                <?= strtoupper(substr($it['name'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="product-body">
                            <div class="product-name"><?= e($it['name']) ?></div>
                            <div style="color:#6b7280;font-size:0.8rem;margin:4px 0;">Qty: <?= (int)$it['quantity'] ?> · ₱<?= number_format($it['price'], 2) ?></div>
                            <a href="<?= moduleUrl('product_detail.php?id=' . $it['product_id']) ?>" class="btn btn-primary btn-sm" style="width:100%;margin-top:8px;">⭐ Write Review</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>