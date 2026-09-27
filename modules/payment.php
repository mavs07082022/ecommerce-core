<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pageTitle = 'Complete Payment — E-Commerce Core';
$uid = $_SESSION['user_id'];

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) redirectRoot('customer_dashboard.php');

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $uid]);
$order = $stmt->fetch();

if (!$order) {
    echo "<p style='padding:40px;text-align:center;font-family:sans-serif;'>Order not found.</p>";
    exit;
}

// If already paid OR under verification → go to tracking page
if (in_array($order['payment_status'], ['paid', 'pending_verification'])) {
    redirectRoot('modules/track_order.php?id=' . $orderId);
}

$preselectedMethod = $order['payment_method'] ?? '';

$items = $pdo->prepare("SELECT oi.*, p.name, p.image_url FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id=?");
$items->execute([$orderId]);
$orderItems = $items->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.payment-grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 24px; }
@media (max-width: 900px) { .payment-grid { grid-template-columns: 1fr; } }
.method-card { background:#fff; border:2px solid #e5e7eb; border-radius:12px; padding:18px; margin-bottom:12px; cursor:pointer; transition:all .15s; display:flex; align-items:center; gap:14px; position:relative; }
.method-card:hover { border-color:#bfdbfe; background:#f8fafc; }
.method-card.selected { border-color:#2563eb; background:#eff6ff; box-shadow:0 0 0 4px rgba(37,99,235,.08); }
.method-card.selected::after { content:'✓'; position:absolute; top:12px; right:14px; background:#2563eb; color:#fff; width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:700; }
.method-icon { width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.5rem; background:#f4f7fb; flex-shrink:0; }
.method-info { flex:1; }
.method-info h4 { margin:0 0 2px; font-weight:700; color:#111827; font-size:.95rem; }
.method-info p { margin:0; color:#6b7280; font-size:.8rem; }
.payment-panel { display:none; margin-top:12px; }
.payment-panel.active { display:block; }
.gcash-qr-box { background:#fff; border:2px dashed #2563eb; border-radius:12px; padding:22px; text-align:center; margin-top:12px; }
.gcash-qr-box img { max-width:260px; width:100%; border-radius:12px; margin:12px auto; display:block; border:1px solid #e5e7eb; padding:8px; background:#fff; }
.gcash-detail-box { background:#eff6ff; padding:14px 16px; border-radius:10px; margin-top:14px; text-align:left; border:1px solid #bfdbfe; }
.gcash-detail-row { display:flex; justify-content:space-between; align-items:center; padding:6px 0; font-size:.88rem; border-bottom:1px dashed #c7ddfc; }
.gcash-detail-row:last-child { border-bottom:none; }
.gcash-detail-row .label { color:#6b7280; font-size:.78rem; font-weight:600; text-transform:uppercase; letter-spacing:.03em; }
.gcash-detail-row .value { color:#111827; font-weight:700; }
.copy-btn { background:#dbeafe; color:#1d4ed8; border:none; padding:4px 10px; border-radius:6px; font-size:.72rem; font-weight:600; cursor:pointer; margin-left:8px; transition:all .15s; }
.copy-btn:hover { background:#bfdbfe; }
.copy-btn.copied { background:#10b981; color:#fff; }
.upload-zone { border:2px dashed #d1d5db; border-radius:10px; padding:24px; text-align:center; cursor:pointer; transition:all .15s; background:#f9fafb; }
.upload-zone:hover { border-color:#2563eb; background:#eff6ff; }
.upload-zone.has-file { border-color:#10b981; background:#ecfdf5; }
.form-input { width:100%; padding:12px 14px; border:1.5px solid #e5e7eb; border-radius:10px; font-size:.9rem; color:#111827; background:#fff; font-family:inherit; transition:all .15s; }
.form-input:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 4px rgba(37,99,235,.12); }
.form-label { display:block; font-size:.8rem; font-weight:600; color:#374151; margin-bottom:6px; }
.form-label .req { color:#dc2626; }
.validation-banner { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; padding:12px 14px; border-radius:10px; font-size:.85rem; margin-bottom:16px; display:none; }
.validation-banner.show { display:flex; gap:10px; align-items:flex-start; }
.session-error { background:#fef2f2; color:#991b1b; border:1px solid #fca5a5; padding:12px 14px; border-radius:10px; margin-bottom:16px; font-size:.85rem; }
.btn-submit { width:100%; padding:15px 20px; background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; border:none; border-radius:12px; font-size:.95rem; font-weight:700; font-family:inherit; cursor:pointer; transition:all .2s; box-shadow:0 8px 24px rgba(37,99,235,.32); display:flex; align-items:center; justify-content:center; gap:8px; margin-top:20px; }
.btn-submit:hover:not(:disabled) { transform:translateY(-1px); box-shadow:0 12px 32px rgba(37,99,235,.42); }
.btn-submit:disabled { background:#cbd5e1; box-shadow:none; cursor:not-allowed; }
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
                    <h2>Complete Payment</h2>
                    <p>Order #<?= e($order['order_number'] ?: 'ORD-' . str_pad($order['id'], 6, '0', STR_PAD_LEFT)) ?></p>
                </div>
            </div>
            <a href="<?= baseUrl('modules/track_order.php?id=' . $orderId) ?>" class="btn btn-secondary">← Back to Order</a>
        </header>

        <div class="page-body">
            <div class="payment-grid">
                <div>
                    <div class="card card-pad" style="margin-bottom:20px;">
                        <h3 class="section-title" style="margin-bottom:16px;">Order Summary</h3>
                        <?php foreach ($orderItems as $it): ?>
                            <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f3f4f6;font-size:.88rem;">
                                <div>
                                    <div style="font-weight:600;color:#111827;"><?= e($it['name']) ?></div>
                                    <div style="color:#6b7280;font-size:.78rem;">₱<?= number_format($it['price'], 2) ?> × <?= $it['quantity'] ?></div>
                                </div>
                                <div style="font-weight:600;">₱<?= number_format($it['price'] * $it['quantity'], 2) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <div style="display:flex;justify-content:space-between;padding:16px 0 0;border-top:2px solid #111827;margin-top:12px;">
                            <strong style="font-size:1.05rem;">Total to Pay</strong>
                            <strong style="color:#1d4ed8;font-size:1.3rem;">₱<?= number_format($order['total_amount'], 2) ?></strong>
                        </div>
                    </div>

                    <div class="card card-pad">
                        <h3 class="section-title" style="margin-bottom:12px;">Shipping To</h3>
                        <div style="font-size:.88rem;line-height:1.7;">
                            <div style="font-weight:600;color:#111827;"><?= e($_SESSION['full_name'] ?? '') ?></div>
                            <div style="color:#374151;"><?= nl2br(e($order['shipping_address'])) ?></div>
                            <?php if ($order['contact_phone']): ?>
                                <div style="color:#6b7280;margin-top:4px;">📞 <?= e($order['contact_phone']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="card card-pad">
                        <h3 class="section-title" style="margin-bottom:16px;">Choose Payment Method *</h3>

                        <?php if (!empty($_SESSION['payment_error'])): ?>
                            <div class="session-error">⚠ <?= e($_SESSION['payment_error']) ?></div>
                            <?php unset($_SESSION['payment_error']); ?>
                        <?php endif; ?>

                        <div class="validation-banner" id="validationBanner">
                            <span>⚠</span>
                            <span id="validationText">Please complete the required fields.</span>
                        </div>

                        <form method="POST" action="<?= baseUrl('api/verify_payment.php') ?>" enctype="multipart/form-data" id="paymentForm" novalidate>
                            <input type="hidden" name="order_id" value="<?= $orderId ?>">
                            <input type="hidden" name="method" id="selectedMethod" value="">

                            <div class="method-card <?= $preselectedMethod === 'GCash' ? 'selected' : '' ?>" data-method="GCash" onclick="selectMethod('GCash')">
                                <div class="method-icon" style="background:#e0f2fe;">💙</div>
                                <div class="method-info"><h4>GCash</h4><p>Scan QR or send to our GCash number</p></div>
                            </div>

                            <div class="method-card <?= $preselectedMethod === 'Card' ? 'selected' : '' ?>" data-method="Card" onclick="selectMethod('Card')">
                                <div class="method-icon" style="background:#ede9fe;">💳</div>
                                <div class="method-info"><h4>Credit / Debit Card</h4><p>Visa, Mastercard, JCB, Amex</p></div>
                            </div>

                            <div class="method-card <?= $preselectedMethod === 'COD' ? 'selected' : '' ?>" data-method="COD" onclick="selectMethod('COD')">
                                <div class="method-icon" style="background:#fef3c7;">💵</div>
                                <div class="method-info"><h4>Cash on Delivery</h4><p>Pay when your order arrives</p></div>
                            </div>

                            <!-- GCASH PANEL -->
                            <div id="panel-GCash" class="payment-panel">
                                <div class="gcash-qr-box">
                                    <h4 style="margin:0 0 6px;color:#111827;">Scan to Pay via GCash</h4>
                                    <p style="color:#6b7280;font-size:.82rem;margin:0;">
                                        Send exactly <strong style="color:#1d4ed8;">₱<?= number_format($order['total_amount'], 2) ?></strong>
                                    </p>

                                    <img src="<?= baseUrl('assets/img/qr.png') ?>" alt="GCash QR Code"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <div style="display:none; padding:30px; background:#f4f7fb; border-radius:12px;">
                                        <div style="font-size:2rem; margin-bottom:8px;">📱</div>
                                        <div style="color:#6b7280; font-size:.85rem;">
                                            Add your GCash QR image to<br>
                                            <code style="background:#fff;padding:2px 6px;border-radius:4px;">assets/img/qr.png</code>
                                        </div>
                                    </div>

                                    <div class="gcash-detail-box">
                                        <div class="gcash-detail-row">
                                            <span class="label">Account Name</span>
                                            <span class="value">Jerlex Navarez</span>
                                        </div>
                                        <div class="gcash-detail-row">
                                            <span class="label">GCash Number</span>
                                            <span class="value">0907 071 1902 <button type="button" class="copy-btn" onclick="copyText('09070711902', this)">Copy</button></span>
                                        </div>
                                        <div class="gcash-detail-row">
                                            <span class="label">Amount</span>
                                            <span class="value" style="color:#1d4ed8;">₱<?= number_format($order['total_amount'], 2) ?> </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group" style="margin-top:16px;">
                                    <label class="form-label">GCash Reference No. <span class="req">*</span></label>
                                    <input type="text" name="reference" id="gcashReference" class="form-input" placeholder="e.g. 1234567890123" maxlength="20">
                                    <small style="color:#9ca3af;font-size:.72rem;">Find this in your GCash receipt / SMS confirmation.</small>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Payment Screenshot <span class="req">*</span></label>
                                    <div class="upload-zone" id="uploadZone" onclick="document.getElementById('proofInput').click()">
                                        <div id="uploadText">
                                            <div style="font-size:1.6rem;">📸</div>
                                            <div style="color:#6b7280;font-size:.83rem;margin-top:6px;">Click to upload your GCash receipt</div>
                                            <div style="color:#9ca3af;font-size:.72rem;margin-top:2px;">JPG, PNG, WEBP — max 5MB</div>
                                        </div>
                                    </div>
                                    <input type="file" name="proof" id="proofInput" accept="image/*" style="display:none;" onchange="handleFile(this)">
                                </div>
                            </div>

                            <!-- CARD PANEL -->
                            <div id="panel-Card" class="payment-panel">
                                <div style="background:#eff6ff;padding:20px;border-radius:12px;margin-top:12px;">
                                    <h4 style="margin:0 0 4px;color:#111827;">💳 Card Payment</h4>
                                    <p style="color:#6b7280;font-size:.82rem;margin:0 0 16px;">Enter your card details. This is a demo — no real charges will be made.</p>

                                    <div class="form-group">
                                        <label class="form-label">Card Number <span class="req">*</span></label>
                                        <input type="text" name="card_number" id="cardNumber" class="form-input" placeholder="1234 5678 9012 3456" maxlength="19" inputmode="numeric" oninput="formatCard(this)">
                                    </div>

                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                        <div class="form-group">
                                            <label class="form-label">Expiry (MM/YY) <span class="req">*</span></label>
                                            <input type="text" name="card_expiry" id="cardExpiry" class="form-input" placeholder="MM/YY" maxlength="5" inputmode="numeric" oninput="formatExpiry(this)">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">CVV <span class="req">*</span></label>
                                            <input type="text" name="card_cvv" id="cardCvv" class="form-input" placeholder="123" maxlength="4" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,''); validateForm();">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Cardholder Name <span class="req">*</span></label>
                                        <input type="text" name="card_name" id="cardName" class="form-input" placeholder="Name as shown on card" oninput="validateForm()">
                                    </div>
                                </div>
                            </div>

                            <!-- COD PANEL -->
                            <div id="panel-COD" class="payment-panel">
                                <div style="background:#fef3c7;padding:18px;border-radius:12px;margin-top:12px;border:1px solid #fde68a;">
                                    <h4 style="margin:0 0 6px;color:#92400e;">💵 Cash on Delivery</h4>
                                    <p style="color:#78350f;font-size:.85rem;margin:0;line-height:1.6;">
                                        Please prepare <strong>₱<?= number_format($order['total_amount'], 2) ?></strong> in cash upon delivery.
                                    </p>
                                </div>
                            </div>

                            <button type="submit" class="btn-submit" id="submitBtn" disabled>
                                <span id="submitText">Select a payment method to continue</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<script>
let currentMethod = '';

function selectMethod(method) {
    currentMethod = method;
    document.getElementById('selectedMethod').value = method;
    document.querySelectorAll('.method-card').forEach(c => c.classList.toggle('selected', c.dataset.method === method));
    document.querySelectorAll('.payment-panel').forEach(p => p.classList.remove('active'));
    const panel = document.getElementById('panel-' + method);
    if (panel) panel.classList.add('active');
    hideValidation();
    validateForm();
}

function validateForm() {
    const btn = document.getElementById('submitBtn');
    const txt = document.getElementById('submitText');

    if (!currentMethod) { btn.disabled = true; txt.textContent = 'Select a payment method to continue'; return false; }

    if (currentMethod === 'GCash') {
        const ref = document.getElementById('gcashReference').value.trim();
        const proof = document.getElementById('proofInput').files.length > 0;
        if (!ref || ref.length < 6) { btn.disabled = true; txt.textContent = 'Enter your GCash reference number (6+ digits)'; return false; }
        if (!proof) { btn.disabled = true; txt.textContent = 'Upload a screenshot of your GCash receipt'; return false; }
    }

    if (currentMethod === 'Card') {
        const num = document.getElementById('cardNumber').value.replace(/\s/g, '');
        const exp = document.getElementById('cardExpiry').value;
        const cvv = document.getElementById('cardCvv').value;
        const name = document.getElementById('cardName').value.trim();
        if (num.length < 15) { btn.disabled = true; txt.textContent = 'Enter a valid card number'; return false; }
        if (!/^\d{2}\/\d{2}$/.test(exp)) { btn.disabled = true; txt.textContent = 'Enter a valid expiry date (MM/YY)'; return false; }
        if (cvv.length < 3) { btn.disabled = true; txt.textContent = 'Enter a valid CVV'; return false; }
        if (!name) { btn.disabled = true; txt.textContent = 'Enter the cardholder name'; return false; }
    }

    btn.disabled = false;
    if (currentMethod === 'GCash') txt.textContent = 'Submit Payment for Verification';
    else if (currentMethod === 'Card') txt.textContent = 'Pay ₱<?= number_format($order['total_amount'], 2) ?>';
    else txt.textContent = 'Place Order (Cash on Delivery)';
    return true;
}

function handleFile(input) {
    const zone = document.getElementById('uploadZone');
    const text = document.getElementById('uploadText');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 5 * 1024 * 1024) { alert('File too large. Maximum 5MB.'); input.value = ''; zone.classList.remove('has-file'); return; }
        zone.classList.add('has-file');
        text.innerHTML = `<div style="font-size:1.6rem;">✅</div><div style="color:#059669;font-size:.85rem;margin-top:6px;font-weight:600;">${file.name}</div><div style="color:#6b7280;font-size:.72rem;margin-top:2px;">${(file.size/1024).toFixed(0)} KB — Click to replace</div>`;
        hideValidation();
    }
    validateForm();
}

function formatCard(el) { el.value = el.value.replace(/\D/g,'').replace(/(.{4})/g,'$1 ').trim().slice(0,19); validateForm(); }
function formatExpiry(el) { let v = el.value.replace(/\D/g,''); if (v.length >= 3) v = v.slice(0,2) + '/' + v.slice(2,4); el.value = v; validateForm(); }

function copyText(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.textContent;
        btn.textContent = 'Copied!'; btn.classList.add('copied');
        setTimeout(() => { btn.textContent = orig; btn.classList.remove('copied'); }, 1500);
    });
}

function showValidation(msg) { document.getElementById('validationText').textContent = msg; document.getElementById('validationBanner').classList.add('show'); }
function hideValidation() { document.getElementById('validationBanner').classList.remove('show'); }

document.getElementById('paymentForm').addEventListener('submit', (e) => {
    hideValidation();
    if (!validateForm()) {
        e.preventDefault();
        showValidation(document.getElementById('submitText').textContent);
        return;
    }
    if (currentMethod === 'GCash') {
        const ref = document.getElementById('gcashReference').value.trim();
        if (!/^\d{6,20}$/.test(ref)) { e.preventDefault(); showValidation('GCash reference must be 6-20 digits only.'); return; }
    }
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('submitText').textContent = 'Processing...';
});

document.getElementById('gcashReference')?.addEventListener('input', function() { this.value = this.value.replace(/\D/g, ''); validateForm(); });

<?php if ($preselectedMethod): ?>
selectMethod(<?= json_encode($preselectedMethod) ?>);
<?php else: ?>
validateForm();
<?php endif; ?>
</script>