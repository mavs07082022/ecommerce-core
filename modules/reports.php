<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireProductManager();

$pageTitle = 'Reports & Analytics — E-Commerce Core';

// Date range (default: last 30 days)
$range = $_GET['range'] ?? '30';
$ranges = ['7' => '7 days', '30' => '30 days', '90' => '90 days', 'all' => 'All time'];
if (!array_key_exists($range, $ranges)) $range = '30';

$dateFilter = $range === 'all' ? '' : 'AND created_at >= DATE_SUB(NOW(), INTERVAL ' . (int)$range . ' DAY)';

// ============ KPIs ============
$revenue = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status NOT IN ('cancelled','returned') AND payment_status='paid' $dateFilter")->fetchColumn();
$ordersCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE 1=1 $dateFilter")->fetchColumn();
$avgOrder = $ordersCount > 0 ? $revenue / $ordersCount : 0;
$customers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$productsSold = $pdo->query("SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON oi.order_id=o.id WHERE o.status NOT IN ('cancelled','returned') $dateFilter")->fetchColumn();

// ============ Top Selling Products ============
$topProducts = $pdo->query("
    SELECT p.name, p.category, SUM(oi.quantity) as total_qty, SUM(oi.quantity * oi.price) as total_revenue
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status NOT IN ('cancelled','returned')
    GROUP BY p.id
    ORDER BY total_qty DESC
    LIMIT 5
")->fetchAll();

// ============ Low Stock ============
$lowStock = $pdo->query("SELECT name, category, stock, price FROM products WHERE stock < 15 ORDER BY stock ASC LIMIT 8")->fetchAll();

// ============ Recent Orders ============
$recentOrders = $pdo->query("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id=u.id ORDER BY o.id DESC LIMIT 6")->fetchAll();

// ============ Revenue by Category ============
$revByCategory = $pdo->query("
    SELECT p.category, SUM(oi.quantity * oi.price) as revenue
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status NOT IN ('cancelled','returned')
    GROUP BY p.category
    ORDER BY revenue DESC
    LIMIT 6
")->fetchAll();

$maxCatRevenue = !empty($revByCategory) ? max(array_column($revByCategory, 'revenue')) : 1;

// ============ Order Status Breakdown ============
$statusBreakdown = $pdo->query("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 28px; }
@media (max-width: 900px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 500px) { .kpi-grid { grid-template-columns: 1fr; } }

.kpi-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 22px;
    box-shadow: 0 1px 2px rgba(16,24,40,0.04);
    transition: all 0.2s;
}
.kpi-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16,24,40,0.06); border-color: #dbeafe; }
.kpi-label { color: #6b7280; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 8px; }
.kpi-value { font-size: 1.75rem; font-weight: 700; color: #111827; letter-spacing: -0.02em; }
.kpi-sub { color: #9ca3af; font-size: 0.75rem; margin-top: 4px; }

.reports-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
@media (max-width: 1000px) { .reports-grid { grid-template-columns: 1fr; } }

.bar-track { background: #f3f4f6; border-radius: 6px; height: 24px; overflow: hidden; position: relative; }
.bar-fill { height: 100%; background: linear-gradient(90deg, #2563eb, #1d4ed8); border-radius: 6px; transition: width 0.6s; }

.range-tabs { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
.range-tab { padding: 8px 16px; border-radius: 8px; text-decoration: none; color: #6b7280; font-size: 0.83rem; font-weight: 600; border: 1px solid #e5e7eb; background: #fff; transition: all 0.15s; }
.range-tab:hover { background: #f4f7fb; }
.range-tab.active { background: #2563eb; color: #fff; border-color: #2563eb; box-shadow: 0 4px 12px rgba(37,99,235,0.3); }
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
                    <h2>Reports & Analytics</h2>
                    <p>Business insights and performance metrics</p>
                </div>
            </div>
            <button onclick="window.print()" class="btn btn-secondary">🖨 Print / Export</button>
        </header>

        <div class="page-body">
            <!-- Date Range Tabs -->
            <div class="range-tabs">
                <?php foreach ($ranges as $key => $label): ?>
                    <a href="?range=<?= $key ?>" class="range-tab <?= $range === $key ? 'active' : '' ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>

            <!-- KPI Cards -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-label">Total Revenue</div>
                    <div class="kpi-value">₱<?= number_format($revenue, 2) ?></div>
                    <div class="kpi-sub">For selected period</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Orders</div>
                    <div class="kpi-value"><?= number_format($ordersCount) ?></div>
                    <div class="kpi-sub">All statuses</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Avg Order Value</div>
                    <div class="kpi-value">₱<?= number_format($avgOrder, 2) ?></div>
                    <div class="kpi-sub">Revenue ÷ orders</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Products Sold</div>
                    <div class="kpi-value"><?= number_format($productsSold) ?></div>
                    <div class="kpi-sub">Total units</div>
                </div>
            </div>

            <!-- Charts row -->
            <div class="reports-grid">
                <!-- Top Selling Products -->
                <div class="card card-pad">
                    <h3 class="section-title" style="margin-bottom:20px;">🏆 Top Selling Products</h3>
                    <?php if (empty($topProducts)): ?>
                        <p style="color:#6b7280;">No sales data yet.</p>
                    <?php else: foreach ($topProducts as $p): ?>
                        <div style="margin-bottom:16px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                <div>
                                    <div style="font-weight:600;color:#111827;font-size:0.9rem;"><?= e($p['name']) ?></div>
                                    <div style="color:#6b7280;font-size:0.78rem;"><?= e($p['category']) ?> · <?= $p['total_qty'] ?> sold</div>
                                </div>
                                <div style="font-weight:700;color:#1d4ed8;font-size:0.88rem;">₱<?= number_format($p['total_revenue'], 2) ?></div>
                            </div>
                            <div class="bar-track">
                                <div class="bar-fill" style="width: <?= $topProducts[0]['total_qty'] > 0 ? ($p['total_qty'] / $topProducts[0]['total_qty'] * 100) : 0 ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <!-- Revenue by Category -->
                <div class="card card-pad">
                    <h3 class="section-title" style="margin-bottom:20px;">📊 Revenue by Category</h3>
                    <?php if (empty($revByCategory)): ?>
                        <p style="color:#6b7280;">No category data yet.</p>
                    <?php else: foreach ($revByCategory as $c): ?>
                        <div style="margin-bottom:16px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                <span style="font-weight:600;color:#111827;font-size:0.88rem;"><?= e($c['category']) ?></span>
                                <span style="font-weight:700;color:#1d4ed8;font-size:0.85rem;">₱<?= number_format($c['revenue'], 2) ?></span>
                            </div>
                            <div class="bar-track">
                                <div class="bar-fill" style="width: <?= $maxCatRevenue > 0 ? ($c['revenue'] / $maxCatRevenue * 100) : 0 ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Low Stock + Order Status -->
            <div class="reports-grid">
                <div class="card">
                    <div style="padding:20px 24px;border-bottom:1px solid #e5e7eb;">
                        <h3 class="section-title" style="color:#dc2626;">⚠️ Low Stock Products</h3>
                    </div>
                    <?php if (empty($lowStock)): ?>
                        <div class="card-pad" style="text-align:center;color:#6b7280;">All products are well stocked!</div>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table>
                                <thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Value</th></tr></thead>
                                <tbody>
                                    <?php foreach ($lowStock as $p): ?>
                                        <tr>
                                            <td><strong><?= e($p['name']) ?></strong></td>
                                            <td><span class="badge badge-blue"><?= e($p['category']) ?></span></td>
                                            <td><span class="badge <?= $p['stock'] == 0 ? 'badge-red' : 'badge-yellow' ?>"><?= $p['stock'] ?> left</span></td>
                                            <td>₱<?= number_format($p['price'] * $p['stock'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div style="padding:20px 24px;border-bottom:1px solid #e5e7eb;">
                        <h3 class="section-title">📋 Order Status Breakdown</h3>
                    </div>
                    <div class="card-pad">
                        <?php 
                        $statusColors = [
                            'pending' => ['#fef3c7', '#92400e'],
                            'processing' => ['#dbeafe', '#1d4ed8'],
                            'shipped' => ['#e0e7ff', '#3730a3'],
                            'delivered' => ['#d1fae5', '#065f46'],
                            'cancelled' => ['#fee2e2', '#991b1b'],
                            'returned' => ['#f3f4f6', '#374151']
                        ];
                        foreach ($statusBreakdown as $s):
                            $color = $statusColors[$s['status']] ?? ['#f3f4f6', '#374151'];
                        ?>
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #f3f4f6;">
                                <span style="background:<?= $color[0] ?>;color:<?= $color[1] ?>;padding:4px 12px;border-radius:999px;font-size:0.78rem;font-weight:600;text-transform:capitalize;">
                                    <?= e($s['status']) ?>
                                </span>
                                <span style="font-weight:700;color:#111827;"><?= $s['cnt'] ?> order<?= $s['cnt'] != 1 ? 's' : '' ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="card">
                <div style="padding:20px 24px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
                    <h3 class="section-title">🧾 Recent Orders</h3>
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
                                    <td><strong>#<?= e($o['order_number'] ?: $o['id']) ?></strong></td>
                                    <td><?= e($o['username']) ?></td>
                                    <td>₱<?= number_format($o['total_amount'], 2) ?></td>
                                    <td><span class="badge <?= $colors[$o['status']] ?? 'badge-gray' ?>"><?= ucfirst($o['status']) ?></span></td>
                                    <td style="color:#6b7280;"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
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