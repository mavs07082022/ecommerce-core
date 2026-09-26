<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pageTitle = 'Shop — E-Commerce Core';
$uid = $_SESSION['user_id'];

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $pid = (int)$_POST['product_id'];
    $qty = max(1, (int)$_POST['quantity']);
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + $qty;
    $returnMsg = "Added to cart!";
}

// Cancel order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $oid = (int)$_POST['order_id'];
    $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=? AND user_id=? AND status IN ('pending','processing')")
        ->execute([$oid, $uid]);
    $pdo->prepare("INSERT INTO order_tracking (order_id, status, message, location) VALUES (?, 'cancelled', 'Order cancelled by customer', 'System')")
        ->execute([$oid]);
    $returnMsg = "Order cancelled.";
}

// Request return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_return'])) {
    $pdo->prepare("INSERT INTO returns (order_id, user_id, reason) VALUES (?, ?, ?)")
        ->execute([(int)$_POST['order_id'], $uid, $_POST['reason']]);
    $returnMsg = "Return request submitted.";
}

// Fetch products
$products = $pdo->query("SELECT * FROM products WHERE stock > 0 ORDER BY id DESC")->fetchAll();

// Fetch user's orders & returns
$myOrders = [];
$myReturns = [];
if (!isAdmin() && !isProductManager()) {
    $s = $pdo->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 10");
    $s->execute([$uid]);
    $myOrders = $s->fetchAll();

    $s = $pdo->prepare("SELECT * FROM returns WHERE user_id=? ORDER BY id DESC LIMIT 10");
    $s->execute([$uid]);
    $myReturns = $s->fetchAll();
}

// Build cart data for JS
$cartData = [];
if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $in  = str_repeat('?,', count($ids) - 1) . '?';
    $s = $pdo->prepare("SELECT id, name, price FROM products WHERE id IN ($in)");
    $s->execute($ids);
    foreach ($s->fetchAll() as $row) {
        $row['qty'] = $_SESSION['cart'][$row['id']];
        $cartData[] = $row;
    }
}

// Fetch current user for checkout display
$cust = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$cust->execute([$uid]);
$custInfo = $cust->fetch();
$fullAddr = trim(
    ($custInfo['address'] ?? '') . ', ' .
    ($custInfo['city'] ?? '') . ', ' .
    ($custInfo['province'] ?? '') . ' ' .
    ($custInfo['postal_code'] ?? '')
, ', ');

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
                    <h2>Shop</h2>
                    <p><?= count($products) ?> products available</p>
                </div>
            </div>
            <button onclick="document.getElementById('cartPanel').style.display='flex'" class="btn btn-primary">
                🛒 Cart (<span id="cartCount"><?= array_sum($_SESSION['cart'] ?? []) ?></span>)
            </button>
        </header>

        <div class="page-body">
            <?php if (isset($returnMsg)): ?><div class="alert alert-success"><?= e($returnMsg) ?></div><?php endif; ?>

            <?php if (!isAdmin() && !isProductManager()): ?>
            <div class="ai-banner">
                <h3>🤖 Ask AI for a Recommendation</h3>
                <p>Describe what you're looking for and our AI will find it</p>
                <form id="shopAiForm" class="ai-form">
                    <input type="text" id="shopAiQuery" placeholder="e.g. 'best gift for a fitness lover under ₱5,000'">
                    <button type="submit" class="btn btn-primary">Ask AI</button>
                </form>
                <div id="shopAiResult" class="ai-response" style="display:none;"></div>
            </div>
            <?php endif; ?>

            <div class="section-header">
                <h3 class="section-title">Browse Products</h3>
            </div>
            <div class="product-grid">
                <?php foreach ($products as $p): ?>
                <div class="product-card">
                    <div class="product-image">
                        <?php if ($p['image_url']): ?>
                            <img src="<?= baseUrl($p['image_url']) ?>" alt="<?= e($p['name']) ?>">
                        <?php else: ?>
                            <?= strtoupper(substr($p['name'],0,1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="product-body">
                        <div class="product-category"><?= e($p['category']) ?></div>
                        <div class="product-name"><?= e($p['name']) ?></div>
                        <div class="product-desc"><?= e($p['description']) ?></div>
                        <div class="product-footer">
                            <span class="product-price">₱<?= number_format($p['price'], 2) ?></span>
                            <span class="product-stock">Stock: <?= (int)$p['stock'] ?></span>
                        </div>
                        <form method="POST" class="product-actions">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <input type="number" name="quantity" value="1" min="1" max="<?= $p['stock'] ?>">
                            <button type="submit" name="add_to_cart" class="btn btn-primary btn-sm">Add to Cart</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!isAdmin() && !isProductManager() && !empty($myOrders)): ?>
                <div class="section-header" style="margin-top:36px;">
                    <h3 class="section-title">My Recent Orders</h3>
                    <a href="<?= baseUrl('customer_dashboard.php') ?>" class="btn btn-secondary btn-sm">View All →</a>
                </div>
                <div class="card">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr><th>Order</th><th>Total</th><th>Payment</th><th>Status</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myOrders as $o):
                                    $colors = ['pending'=>'badge-yellow','processing'=>'badge-blue','shipped'=>'badge-indigo','delivered'=>'badge-green','cancelled'=>'badge-red','returned'=>'badge-gray'];
                                    $pColors = ['unpaid'=>'badge-red','pending_verification'=>'badge-yellow','paid'=>'badge-green','refunded'=>'badge-blue'];
                                ?>
                                <tr>
                                    <td><strong>#<?= e($o['order_number'] ?: $o['id']) ?></strong></td>
                                    <td>₱<?= number_format($o['total_amount'],2) ?></td>
                                    <td><span class="badge <?= $pColors[$o['payment_status']] ?? 'badge-gray' ?>"><?= ucwords(str_replace('_',' ',$o['payment_status'])) ?></span></td>
                                    <td><span class="badge <?= $colors[$o['status']] ?? 'badge-gray' ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                                    <td style="white-space:nowrap;">
                                        <a href="<?= baseUrl('modules/track_order.php?id=' . $o['id']) ?>" class="btn btn-secondary btn-sm">Track</a>
                                        <?php if ($o['payment_status'] === 'unpaid' && !in_array($o['status'], ['cancelled','returned'])): ?>
                                            <a href="<?= baseUrl('modules/payment.php?id=' . $o['id']) ?>" class="btn btn-primary btn-sm">Pay</a>
                                        <?php endif; ?>
                                        <?php if (in_array($o['status'], ['pending','processing'])): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this order?')">
                                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                <button type="submit" name="cancel_order" class="btn btn-danger btn-sm">Cancel</button>
                                            </form>
                                        <?php elseif ($o['status'] === 'delivered'): ?>
                                            <button onclick="openReturn(<?= $o['id'] ?>)" class="btn btn-secondary btn-sm">Return</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!isAdmin() && !isProductManager() && !empty($myReturns)): ?>
                <div class="section-header" style="margin-top:36px;"><h3 class="section-title">My Returns</h3></div>
                <div class="card">
                    <div class="table-wrapper">
                        <table>
                            <thead><tr><th>Return</th><th>Order</th><th>Reason</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($myReturns as $r):
                                    $rColors = ['pending'=>'badge-yellow','approved'=>'badge-blue','rejected'=>'badge-red','refunded'=>'badge-green'];
                                ?>
                                <tr>
                                    <td><strong>R<?= $r['id'] ?></strong></td>
                                    <td>#<?= $r['order_id'] ?></td>
                                    <td><?= e($r['reason']) ?></td>
                                    <td><span class="badge <?= $rColors[$r['status']] ?? 'badge-gray' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<!-- Cart Panel -->
<div id="cartPanel" class="modal-overlay" style="display:none;">
    <div class="modal" style="max-width:560px;">
        <div class="flex-between" style="margin-bottom:16px;">
            <h3 style="margin:0;">Your Cart</h3>
            <button onclick="document.getElementById('cartPanel').style.display='none'" class="btn btn-secondary btn-sm">Close</button>
        </div>
        <div id="cartItems"></div>

        <form id="checkoutForm" style="display:none;margin-top:20px;">
            <div style="background:#eff6ff;padding:14px;border-radius:10px;margin-bottom:14px;">
                <div style="font-size:0.72rem;font-weight:700;color:#1d4ed8;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px;">📌 Shipping to (from your account)</div>
                <div style="font-weight:600;color:#111827;font-size:0.88rem;"><?= e($custInfo['full_name']) ?></div>
                <div style="color:#374151;font-size:0.83rem;line-height:1.5;"><?= nl2br(e($fullAddr ?: 'Address not set — please update your profile')) ?></div>
                <div style="color:#6b7280;font-size:0.8rem;margin-top:4px;">📞 <?= e($custInfo['phone'] ?: 'No phone') ?></div>
                <div style="color:#6b7280;font-size:0.75rem;margin-top:6px;font-style:italic;">Address is locked to your registered profile.</div>
            </div>

            <div class="form-group">
                <label class="form-label">Delivery Option *</label>
                <div class="delivery-options">
                    <label class="delivery-option">
                        <input type="radio" name="delivery_radio" value="standard" checked onchange="updateTotals()">
                        <div class="delivery-info">
                            <div class="delivery-title">Standard</div>
                            <div class="delivery-meta">3-5 business days · Free</div>
                        </div>
                        <div class="delivery-price">₱0</div>
                    </label>
                    <label class="delivery-option">
                        <input type="radio" name="delivery_radio" value="express" onchange="updateTotals()">
                        <div class="delivery-info">
                            <div class="delivery-title">Express</div>
                            <div class="delivery-meta">1-2 business days</div>
                        </div>
                        <div class="delivery-price">₱150</div>
                    </label>
                    <label class="delivery-option">
                        <input type="radio" name="delivery_radio" value="sameday" onchange="updateTotals()">
                        <div class="delivery-info">
                            <div class="delivery-title">Same-day</div>
                            <div class="delivery-meta">Delivered today</div>
                        </div>
                        <div class="delivery-price">₱350</div>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Payment Method *</label>
                <select id="payMethod" class="form-select" required>
                    <option value="">— Select payment method —</option>
                    <option value="GCash">GCash</option>
                    <option value="Card">Credit/Debit Card</option>
                    <option value="COD">Cash on Delivery</option>
                </select>
            </div>

            <div id="checkoutTotals" style="background:#f4f7fb;border-radius:10px;padding:14px;margin-bottom:14px;font-size:0.87rem;"></div>

            <button type="submit" class="btn btn-primary" style="width:100%;padding:14px;font-weight:700;">Proceed to Payment →</button>
        </form>
    </div>
</div>

<!-- Return Modal -->
<div id="returnModal" class="modal-overlay" style="display:none;">
    <div class="modal">
        <h3>Request Return</h3>
        <form method="POST">
            <input type="hidden" name="order_id" id="returnOrderId">
            <div class="form-group">
                <label class="form-label">Reason *</label>
                <textarea name="reason" class="form-textarea" required placeholder="Tell us why you want to return this order..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="document.getElementById('returnModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" name="request_return" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </div>
</div>

<style>
.delivery-options { display: flex; flex-direction: column; gap: 8px; }
.delivery-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.15s;
    background: #fff;
}
.delivery-option:hover { border-color: #bfdbfe; background: #f8fafc; }
.delivery-option input[type="radio"] { margin: 0; width: 18px; height: 18px; accent-color: #2563eb; }
.delivery-option:has(input:checked) { border-color: #2563eb; background: #eff6ff; box-shadow: 0 0 0 3px rgba(37,99,235,0.08); }
.delivery-info { flex: 1; }
.delivery-title { font-weight: 600; color: #111827; font-size: 0.88rem; }
.delivery-meta { color: #6b7280; font-size: 0.75rem; }
.delivery-price { font-weight: 700; color: #1d4ed8; font-size: 0.9rem; }

.cart-item-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 0;
    border-bottom: 1px solid #e5e7eb;
}
.cart-item-info { flex: 1; min-width: 0; }
.cart-item-name { font-weight: 600; color: #111827; font-size: 0.9rem; }
.cart-item-price { font-size: 0.82rem; color: #6b7280; margin-top: 2px; }
.qty-control { display: flex; align-items: center; gap: 4px; background: #f4f7fb; border-radius: 8px; padding: 4px; }
.qty-btn { background: transparent; border: none; width: 28px; height: 28px; border-radius: 6px; cursor: pointer; font-weight: 700; color: #2563eb; font-size: 1rem; line-height: 1; }
.qty-btn:hover { background: #dbeafe; }
.qty-input { width: 40px; text-align: center; border: none; background: transparent; font-weight: 600; font-family: inherit; font-size: 0.88rem; }
.qty-input:focus { outline: none; }
</style>

<script>
function openReturn(id) {
    document.getElementById('returnOrderId').value = id;
    document.getElementById('returnModal').style.display = 'flex';
}

<?php if (!isAdmin() && !isProductManager()): ?>
document.getElementById('shopAiForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const q = document.getElementById('shopAiQuery').value;
    const box = document.getElementById('shopAiResult');
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
<?php endif; ?>

const cartData = <?= json_encode($cartData) ?>;
let cartSubtotal = 0;

function renderCart() {
    const itemsDiv = document.getElementById('cartItems');
    if (!cartData || cartData.length === 0) {
        itemsDiv.innerHTML = '<p style="color:#6b7280;text-align:center;padding:20px;">Your cart is empty.</p>';
        document.getElementById('checkoutForm').style.display = 'none';
        return;
    }
    cartSubtotal = 0;
    let html = '';
    cartData.forEach(item => {
        const lineTotal = item.price * item.qty;
        cartSubtotal += lineTotal;
        html += `
            <div class="cart-item-row">
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.name}</div>
                    <div class="cart-item-price">₱${parseFloat(item.price).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})}</div>
                </div>
                <div class="qty-control">
                    <button type="button" class="qty-btn" onclick="changeQty(${item.id}, ${item.qty - 1})">−</button>
                    <input type="number" class="qty-input" value="${item.qty}" min="1" onchange="changeQty(${item.id}, parseInt(this.value))">
                    <button type="button" class="qty-btn" onclick="changeQty(${item.id}, ${item.qty + 1})">+</button>
                </div>
                <button type="button" class="btn btn-danger btn-sm" onclick="changeQty(${item.id}, 0)">✕</button>
            </div>`;
    });
    itemsDiv.innerHTML = html;
    document.getElementById('checkoutForm').style.display = 'block';
    updateTotals();
}

function updateTotals() {
    if (cartData.length === 0) return;
    const delivery = document.querySelector('input[name="delivery_radio"]:checked')?.value || 'standard';
    const fees = { standard: 0, express: 150, sameday: 350 };
    const fee = fees[delivery] || 0;
    const total = cartSubtotal + fee;

    document.getElementById('checkoutTotals').innerHTML = `
        <div style="display:flex;justify-content:space-between;padding:4px 0;">
            <span style="color:#6b7280;">Subtotal</span>
            <span style="font-weight:600;">₱${cartSubtotal.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})}</span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:4px 0;">
            <span style="color:#6b7280;">Delivery Fee</span>
            <span style="font-weight:600;">₱${fee.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})}</span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:10px 0 0;border-top:1px solid #e5e7eb;margin-top:8px;">
            <span style="font-weight:700;">Total</span>
            <span style="font-weight:700;color:#1d4ed8;font-size:1.05rem;">₱${total.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})}</span>
        </div>
    `;
}

async function changeQty(pid, newQty) {
    if (newQty < 0) return;
    try {
        const res = await fetch('<?= baseUrl('api/cart_update.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'product_id=' + pid + '&quantity=' + newQty
        });
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.msg || 'Failed to update'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

document.getElementById('checkoutForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payment = document.getElementById('payMethod').value;
    const delivery = document.querySelector('input[name="delivery_radio"]:checked')?.value || 'standard';

    if (!payment) {
        alert('Please select a payment method.');
        return;
    }

    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Creating order...';

    const res = await fetch('<?= baseUrl('api/checkout.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'payment=' + encodeURIComponent(payment) + '&delivery=' + encodeURIComponent(delivery)
    });
    const data = await res.json();

    if (data.success) {
        window.location.href = data.redirect;
    } else {
        alert('Error: ' + data.msg);
        btn.disabled = false;
        btn.textContent = 'Proceed to Payment →';
    }
});

renderCart();
</script>