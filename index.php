<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
requireAdmin();

$pageTitle = 'Dashboard — E-Commerce Core';

$totalUsers     = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$totalProducts  = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalOrders    = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalRevenue   = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status != 'cancelled'")->fetchColumn();
$pendingReturns = $pdo->query("SELECT COUNT(*) FROM returns WHERE status='pending'")->fetchColumn();
$lowStockCount  = $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 15")->fetchColumn();
$recentOrders   = $pdo->query("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.id DESC LIMIT 6")->fetchAll();

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
                    <h2>Dashboard Overview</h2>
                    <p>Welcome back, <?= e($_SESSION['full_name'] ?? $_SESSION['username']) ?></p>
                </div>
            </div>
            <div class="topbar-date"><?= date('l, F j, Y') ?></div>
        </header>

        <div class="page-body">
            <!-- Stats -->
            <div class="grid-4">
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-value">₱<?= number_format($totalRevenue, 2) ?></div>
                    </div>
                    <div class="stat-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Orders</div>
                        <div class="stat-value"><?= $totalOrders ?></div>
                    </div>
                    <div class="stat-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Products</div>
                        <div class="stat-value"><?= $totalProducts ?></div>
                    </div>
                    <div class="stat-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Pending Returns</div>
                        <div class="stat-value" style="<?= $pendingReturns > 0 ? 'color:#dc2626;' : '' ?>"><?= $pendingReturns ?></div>
                    </div>
                    <div class="stat-icon" style="<?= $pendingReturns > 0 ? 'background:#fee2e2;color:#dc2626;' : '' ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"/></svg>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card card-pad" style="margin-bottom:28px;">
                <h3 class="section-title" style="margin-bottom:16px;">⚡ Quick Actions</h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
                    <a href="<?= baseUrl('modules/reports.php') ?>" class="btn btn-primary" style="padding:16px;text-decoration:none;">
                        📊 View Reports
                    </a>
                    <a href="<?= baseUrl('modules/products.php') ?>" class="btn btn-secondary" style="padding:16px;text-decoration:none;">
                        📦 Manage Products
                    </a>
                    <a href="<?= baseUrl('modules/orders.php') ?>" class="btn btn-secondary" style="padding:16px;text-decoration:none;">
                        🧾 View Orders
                    </a>
                    <a href="<?= baseUrl('modules/users.php') ?>" class="btn btn-secondary" style="padding:16px;text-decoration:none;">
                        👥 Manage Users
                    </a>
                </div>
            </div>

            <!-- Low Stock Alert Banner -->
            <?php if ($lowStockCount > 0): ?>
                <div class="card card-pad" style="border-left:4px solid #dc2626;margin-bottom:28px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                    <div>
                        <h3 style="color:#dc2626;margin:0 0 4px;font-size:1rem;">⚠️ Low Stock Alert</h3>
                        <p style="color:#6b7280;margin:0;font-size:0.88rem;"><?= $lowStockCount ?> product<?= $lowStockCount != 1 ? 's' : '' ?> running low (under 15 units)</p>
                    </div>
                    <a href="<?= baseUrl('modules/reports.php') ?>" class="btn btn-primary btn-sm">View in Reports</a>
                </div>
            <?php endif; ?>

            <!-- AI Assistant -->
            <div class="ai-banner">
                <h3>🤖 Cohere AI Assistant</h3>
                <p>Ask anything about your products — recommendations, semantic search, categorization.</p>
                <form id="aiForm" class="ai-form">
                    <input type="text" id="aiQuery" placeholder="e.g. 'best budget laptop for students under ₱50,000'" required>
                    <button type="submit" class="btn btn-primary">Ask AI</button>
                </form>
                <div id="aiResult" class="ai-response" style="display:none;"></div>
            </div>

            <!-- Recent Orders -->
            <div class="card">
                <div class="flex-between" style="padding:20px 24px; border-bottom:1px solid #e5e7eb;">
                    <h3 class="section-title">Recent Orders</h3>
                    <a href="<?= baseUrl('modules/orders.php') ?>" class="btn btn-secondary btn-sm">View All →</a>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                                <tr><td colspan="5" style="text-align:center; padding:32px; color:#6b7280;">No orders yet</td></tr>
                            <?php else: foreach ($recentOrders as $o):
                                $colors = ['pending'=>'badge-yellow','processing'=>'badge-blue','shipped'=>'badge-indigo','delivered'=>'badge-green','cancelled'=>'badge-red','returned'=>'badge-gray'];
                            ?>
                                <tr>
                                    <td><strong style="color:#111827;">#<?= e($o['order_number'] ?: $o['id']) ?></strong></td>
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

<script>
document.getElementById('aiForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const q = document.getElementById('aiQuery').value;
    const box = document.getElementById('aiResult');
    box.style.display = 'block';
    box.textContent = '🤔 Thinking...';
    try {
        const res = await fetch('<?= baseUrl('api/ai_query.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'query=' + encodeURIComponent(q)
        });
        const data = await res.json();
        box.textContent = data.response || 'No response.';
    } catch (err) {
        box.textContent = 'Error: ' + err.message;
    }
});
</script>