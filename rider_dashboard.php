<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
requireRider();
requirePasswordChange();

$pageTitle = 'Rider Dashboard — E-Commerce Core';
$uid = $_SESSION['user_id'];

// Get rider record
$r = $pdo->prepare("SELECT * FROM riders WHERE user_id = ?");
$r->execute([$uid]);
$rider = $r->fetch();

if (!$rider) {
    echo "<p style='padding:40px;text-align:center;'>Your rider profile is not set up. Please contact admin.</p>";
    exit;
}
$riderId = $rider['id'];

// Stats
$stats = [];
$s1 = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE rider_id = ? AND status IN ('shipped')"); $s1->execute([$riderId]);
$stats['active'] = $s1->fetchColumn();

$s2 = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE rider_id = ? AND status = 'delivered'"); $s2->execute([$riderId]);
$stats['delivered'] = $s2->fetchColumn();

$s3 = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE rider_id = ? AND DATE(delivered_at) = CURDATE()"); $s3->execute([$riderId]);
$stats['today'] = $s3->fetchColumn();

$s4 = $pdo->prepare("SELECT COALESCE(SUM(amount_collected),0) FROM orders WHERE rider_id = ? AND DATE(delivered_at) = CURDATE()"); $s4->execute([$riderId]);
$stats['collected_today'] = $s4->fetchColumn();

$s5 = $pdo->prepare("SELECT COALESCE(SUM(amount_collected),0) FROM orders WHERE rider_id = ?"); $s5->execute([$riderId]);
$stats['collected_total'] = $s5->fetchColumn();

// Active deliveries
$active = $pdo->prepare("
    SELECT o.*, u.username, u.full_name AS customer_name, u.phone AS customer_phone
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.rider_id = ? AND o.status = 'shipped'
    ORDER BY o.assigned_at ASC
");
$active->execute([$riderId]);
$activeOrders = $active->fetchAll();

// Recent delivered
$recent = $pdo->prepare("
    SELECT o.*, u.username, u.full_name AS customer_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.rider_id = ? AND o.status = 'delivered'
    ORDER BY o.delivered_at DESC
    LIMIT 5
");
$recent->execute([$riderId]);
$recentOrders = $recent->fetchAll();

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
                    <h2>Hello, <?= e($rider['full_name']) ?>! 🛵</h2>
                    <p>Your daily delivery overview</p>
                </div>
            </div>
            <div class="topbar-date"><?= date('l, F j, Y') ?></div>
        </header>

        <div class="page-body">
            <!-- Stats -->
            <div class="grid-4">
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Active Deliveries</div>
                        <div class="stat-value" style="<?= $stats['active'] > 0 ? 'color:#2563eb;' : '' ?>"><?= $stats['active'] ?></div>
                    </div>
                    <div class="stat-icon" style="<?= $stats['active'] > 0 ? 'background:#dbeafe;color:#2563eb;' : '' ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Delivered Today</div>
                        <div class="stat-value"><?= $stats['today'] ?></div>
                    </div>
                    <div class="stat-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Total Delivered</div>
                        <div class="stat-value"><?= $stats['delivered'] ?></div>
                    </div>
                    <div class="stat-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Collected Today</div>
                        <div class="stat-value">₱<?= number_format($stats['collected_today'], 2) ?></div>
                    </div>
                    <div class="stat-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
            </div>

            <!-- Active Deliveries -->
            <div class="card" style="margin-bottom:24px;">
                <div class="flex-between" style="padding:20px 24px;border-bottom:1px solid #e5e7eb;">
                    <div>
                        <h3 class="section-title">🚚 Active Deliveries</h3>
                        <p style="color:#6b7280;font-size:0.83rem;margin:4px 0 0;">These orders are assigned to you and out for delivery</p>
                    </div>
                    <a href="<?= baseUrl('rider_delivery.php') ?>" class="btn btn-secondary btn-sm">View All →</a>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr><th>Order</th><th>Customer</th><th>Address</th><th>Amount</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activeOrders)): ?>
                                <tr><td colspan="5" style="text-align:center;padding:40px;color:#6b7280;">
                                    ✨ No active deliveries right now. Enjoy your break!
                                </td></tr>
                            <?php else: foreach ($activeOrders as $o): ?>
                                <tr>
                                    <td><strong>#<?= e($o['order_number'] ?: $o['id']) ?></strong></td>
                                    <td>
                                        <div><?= e($o['customer_name'] ?: $o['username']) ?></div>
                                        <?php if ($o['contact_phone']): ?>
                                            <div style="color:#6b7280;font-size:0.78rem;">📞 <?= e($o['contact_phone']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width:280px;font-size:0.83rem;color:#374151;"><?= e($o['shipping_address']) ?></td>
                                    <td><strong style="color:#1d4ed8;">₱<?= number_format($o['total_amount'], 2) ?></strong>
                                        <div style="font-size:0.72rem;color:#6b7280;"><?= e($o['payment_method']) ?></div>
                                    </td>
                                    <td>
                                        <a href="<?= baseUrl('rider_delivery.php?id=' . $o['id']) ?>" class="btn btn-primary btn-sm">Deliver Now</a>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Deliveries -->
            <div class="card">
                <div class="flex-between" style="padding:20px 24px;border-bottom:1px solid #e5e7eb;">
                    <h3 class="section-title">Recent Completed Deliveries</h3>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr><th>Order</th><th>Customer</th><th>Amount Collected</th><th>Delivered</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                                <tr><td colspan="4" style="text-align:center;padding:32px;color:#6b7280;">No completed deliveries yet</td></tr>
                            <?php else: foreach ($recentOrders as $o): ?>
                                <tr>
                                    <td><strong>#<?= e($o['order_number'] ?: $o['id']) ?></strong></td>
                                    <td><?= e($o['customer_name'] ?: $o['username']) ?></td>
                                    <td><span class="badge badge-green">₱<?= number_format($o['amount_collected'] ?? 0, 2) ?></span></td>
                                    <td style="color:#6b7280;font-size:0.83rem;"><?= timeAgo($o['delivered_at']) ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </div>
</div>