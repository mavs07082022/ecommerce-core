<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
requireLogin();

if (isAdmin()) redirectRoot('index.php');
if (isProductManager()) redirectRoot('pm_dashboard.php');

$pageTitle = 'My Dashboard — E-Commerce Core';
$uid = $_SESSION['user_id'];

// Stats
$myOrderCount = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$myOrderCount->execute([$uid]);
$myOrderCount = $myOrderCount->fetchColumn();

$myTotalSpent = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id = ? AND status != 'cancelled' AND payment_status = 'paid'");
$myTotalSpent->execute([$uid]);
$myTotalSpent = $myTotalSpent->fetchColumn();

$myActiveOrders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status IN ('pending','processing','shipped')");
$myActiveOrders->execute([$uid]);
$myActiveOrders = $myActiveOrders->fetchColumn();

$myUnpaidOrders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND payment_status IN ('unpaid','pending_verification')");
$myUnpaidOrders->execute([$uid]);
$myUnpaidOrders = $myUnpaidOrders->fetchColumn();

// Recent orders
$recentOrders = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$recentOrders->execute([$uid]);
$recentOrders = $recentOrders->fetchAll();

// Featured products
$featured = $pdo->query("SELECT * FROM products WHERE stock > 0 ORDER BY id DESC LIMIT 4")->fetchAll();

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
                    <h2>Welcome back, <?= e($_SESSION['full_name'] ?? $_SESSION['username']) ?>! 👋</h2>
                    <p>Here's what's happening with your account today</p>
                </div>
            </div>
            <div style="display:flex;gap:12px;align-items:center;">
                <a href="<?= baseUrl('profile.php') ?>" class="btn btn-secondary btn-sm">👤 My Profile</a>
                <div class="topbar-date"><?= date('l, F j, Y') ?></div>
            </div>
        </header>

        <div class="page-body">
            <!-- Stats -->
            <div class="grid-4">
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Total Orders</div>
                        <div class="stat-value"><?= $myOrderCount ?></div>
                    </div>
                    <div class="stat-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Total Spent</div>
                        <div class="stat-value">₱<?= number_format($myTotalSpent, 2) ?></div>
                    </div>
                    <div class="stat-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Active Orders</div>
                        <div class="stat-value"><?= $myActiveOrders ?></div>
                    </div>
                    <div class="stat-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-label">Unpaid Orders</div>
                        <div class="stat-value" style="<?= $myUnpaidOrders > 0 ? 'color:#dc2626;' : '' ?>"><?= $myUnpaidOrders ?></div>
                    </div>
                    <div class="stat-icon" style="<?= $myUnpaidOrders > 0 ? 'background:#fee2e2;color:#dc2626;' : '' ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    </div>
                </div>
            </div>

            <!-- AI Assistant -->
            <div class="ai-banner">
                <h3>🤖 Ask Our AI Assistant</h3>
                <p>Not sure what to buy? Describe what you need and get instant recommendations.</p>
                <form id="aiForm" class="ai-form">
                    <input type="text" id="aiQuery" placeholder="e.g. 'best budget laptop for students under ₱50,000'" required>
                    <button type="submit" class="btn btn-primary">Ask AI</button>
                </form>
                <div id="aiResult" class="ai-response" style="display:none;"></div>
            </div>

            <!-- Recent Orders -->
            <div class="card" style="margin-bottom:28px;">
                <div class="flex-between" style="padding:20px 24px; border-bottom:1px solid #e5e7eb;">
                    <h3 class="section-title">My Recent Orders</h3>
                    <a href="<?= moduleUrl('shop.php') ?>" class="btn btn-secondary btn-sm">Shop More →</a>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                                <tr><td colspan="6" style="text-align:center;padding:32px;color:#6b7280;">
                                    You haven't placed any orders yet. <a href="<?= moduleUrl('shop.php') ?>" style="color:#2563eb;text-decoration:none;font-weight:600;">Start shopping →</a>
                                </td></tr>
                            <?php else: foreach ($recentOrders as $o):
                                $colors = ['pending'=>'badge-yellow','processing'=>'badge-blue','shipped'=>'badge-indigo','delivered'=>'badge-green','cancelled'=>'badge-red','returned'=>'badge-gray'];
                                $pColors = ['unpaid'=>'badge-red','pending_verification'=>'badge-yellow','paid'=>'badge-green','refunded'=>'badge-blue'];
                            ?>
                                <tr>
                                    <td><strong style="color:#111827;">#<?= e($o['order_number'] ?: $o['id']) ?></strong></td>
                                    <td>₱<?= number_format($o['total_amount'], 2) ?></td>
                                    <td><span class="badge <?= $pColors[$o['payment_status']] ?? 'badge-gray' ?>"><?= ucwords(str_replace('_',' ',$o['payment_status'])) ?></span></td>
                                    <td><span class="badge <?= $colors[$o['status']] ?? 'badge-gray' ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                                    <td style="color:#6b7280;"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                                    <td style="white-space:nowrap;">
                                        <a href="<?= moduleUrl('track_order.php?id=' . $o['id']) ?>" class="btn btn-secondary btn-sm">Track</a>
                                        <?php if ($o['payment_status'] === 'unpaid' && !in_array($o['status'], ['cancelled','returned'])): ?>
                                            <a href="<?= moduleUrl('payment.php?id=' . $o['id']) ?>" class="btn btn-primary btn-sm">Pay Now</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Featured Products -->
            <div class="section-header">
                <h3 class="section-title">Featured Products</h3>
                <a href="<?= moduleUrl('shop.php') ?>" class="btn btn-secondary btn-sm">View All →</a>
            </div>
            <div class="product-grid">
                <?php foreach ($featured as $p): ?>
                <div class="product-card">
                    <a href="<?= moduleUrl('product_detail.php?id=' . $p['id']) ?>" style="text-decoration:none;color:inherit;display:block;">
                        <div class="product-image">
                            <?php if ($p['image_url']): ?>
                                <img src="<?= baseUrl($p['image_url']) ?>" alt="<?= e($p['name']) ?>">
                            <?php else: ?>
                                <?= strtoupper(substr($p['name'],0,1)) ?>
                            <?php endif; ?>
                        </div>
                    </a>
                    <div class="product-body">
                        <div class="product-category"><?= e($p['category']) ?></div>
                        <a href="<?= moduleUrl('product_detail.php?id=' . $p['id']) ?>" style="text-decoration:none;color:inherit;">
                            <div class="product-name"><?= e($p['name']) ?></div>
                        </a>
                        <div class="product-footer">
                            <span class="product-price">₱<?= number_format($p['price'], 2) ?></span>
                        </div>
                        <a href="<?= moduleUrl('product_detail.php?id=' . $p['id']) ?>" class="btn btn-primary btn-sm" style="margin-top:12px;width:100%;">View Details</a>
                    </div>
                </div>
                <?php endforeach; ?>
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
    } catch (err) { box.textContent = 'Error: ' + err.message; }
});
</script>