<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
requireProductManager();

// If admin, redirect to admin dashboard
if (isAdmin()) redirect('index.php');

$pageTitle = 'Product Manager Dashboard — E-Commerce Core';

// Stats
$totalProducts   = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$lowStock        = $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 10")->fetchColumn();
$outOfStock      = $pdo->query("SELECT COUNT(*) FROM products WHERE stock = 0")->fetchColumn();
$totalOrders     = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders   = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$pendingReturns  = $pdo->query("SELECT COUNT(*) FROM returns WHERE status = 'pending'")->fetchColumn();

// Low stock products list
$lowStockProducts = $pdo->query("SELECT * FROM products WHERE stock < 10 ORDER BY stock ASC LIMIT 6")->fetchAll();

// Recent orders
$recentOrders = $pdo->query("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id=u.id ORDER BY o.id DESC LIMIT 6")->fetchAll();

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
                    <h2>Product Manager Dashboard</h2>
                    <p>Welcome, <?= e($_SESSION['full_name'] ?? $_SESSION['username']) ?></p>
                </div>
            </div>
            <div class="topbar-date"><?= date('l, F j, Y') ?></div>
        </header>

        <div class="page-body">
            <div class="grid-4">
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Total Products</div>
                        <div class="stat-value"><?= $totalProducts ?></div>
                    </div>
                    <div class="stat-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Low Stock Alerts</div>
                        <div class="stat-value" style="color:<?= $lowStock > 0 ? '#dc2626' : '#111827' ?>;"><?= $lowStock ?></div>
                    </div>
                    <div class="stat-icon" style="<?= $lowStock > 0 ? 'background:#fee2e2;color:#dc2626;' : '' ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Pending Orders</div>
                        <div class="stat-value"><?= $pendingOrders ?></div>
                    </div>
                    <div class="stat-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Pending Returns</div>
                        <div class="stat-value"><?= $pendingReturns ?></div>
                    </div>
                    <div class="stat-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"/></svg>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card card-pad" style="margin-bottom:28px;">
                <h3 class="section-title" style="margin-bottom:16px;">Quick Actions</h3>
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <a href="<?= baseUrl('modules/products.php') ?>" class="btn btn-primary">+ Manage Products</a>
                    <a href="<?= baseUrl('modules/orders.php') ?>" class="btn btn-secondary">View All Orders</a>
                    <a href="<?= baseUrl('modules/returns.php') ?>" class="btn btn-secondary">Handle Returns</a>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <?php if (!empty($lowStockProducts)): ?>
                <div class="card" style="margin-bottom:28px;border-left:4px solid #dc2626;">
                    <div style="padding:20px 24px;border-bottom:1px solid #e5e7eb;">
                        <h3 class="section-title" style="color:#dc2626;">⚠️ Low Stock Products</h3>
                        <p style="color:#6b7280;font-size:0.85rem;margin:4px 0 0;">These products need restocking soon</p>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $p): ?>
                                <tr>
                                    <td><strong><?= e($p['name']) ?></strong></td>
                                    <td><span class="badge badge-blue"><?= e($p['category']) ?></span></td>
                                    <td>₱<?= number_format($p['price'], 2) ?></td>
                                    <td><span class="badge <?= $p['stock'] == 0 ? 'badge-red' : 'badge-yellow' ?>"><?= $p['stock'] ?> left</span></td>
                                    <td><a href="<?= baseUrl('modules/products.php') ?>" class="btn btn-secondary btn-sm">Update Stock</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Orders -->
            <div class="card">
                <div class="flex-between" style="padding:20px 24px;border-bottom:1px solid #e5e7eb;">
                    <h3 class="section-title">Recent Orders</h3>
                    <a href="<?= baseUrl('modules/orders.php') ?>" class="btn btn-secondary btn-sm">View All →</a>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr><th>Order</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                                <tr><td colspan="5" style="text-align:center;padding:32px;color:#6b7280;">No orders yet</td></tr>
                            <?php else: foreach ($recentOrders as $o):
                                $colors = ['pending'=>'badge-yellow','processing'=>'badge-blue','shipped'=>'badge-indigo','delivered'=>'badge-green','cancelled'=>'badge-red','returned'=>'badge-gray'];
                            ?>
                                <tr>
                                    <td><strong style="color:#111827;">#<?= $o['id'] ?></strong></td>
                                    <td><?= e($o['username']) ?></td>
                                    <td>₱<?= number_format($o['total_amount'], 2) ?></td>
                                    <td><span class="badge <?= $colors[$o['status']] ?? 'badge-gray' ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                                    <td style="color:#6b7280;"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
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