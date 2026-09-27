<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pageTitle = 'Product Details — E-Commerce Core';
$uid = $_SESSION['user_id'];

$productId = (int)($_GET['id'] ?? 0);
if (!$productId) redirectRoot('customer_dashboard.php');

// Fetch product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    echo "<p style='padding:40px;text-align:center;'>Product not found.</p>";
    exit;
}

// ==================== STATS ====================
// Ratings summary
$ratingStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_reviews,
        COALESCE(AVG(rating), 0) as avg_rating,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as r5,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as r4,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as r3,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as r2,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as r1
    FROM product_reviews WHERE product_id = ?
");
$ratingStmt->execute([$productId]);
$ratings = $ratingStmt->fetch();

// Units sold
$soldStmt = $pdo->prepare("
    SELECT COALESCE(SUM(oi.quantity), 0) 
    FROM order_items oi 
    JOIN orders o ON oi.order_id = o.id 
    WHERE oi.product_id = ? 
      AND o.status IN ('delivered','shipped','processing')
");
$soldStmt->execute([$productId]);
$unitsSold = (int)$soldStmt->fetchColumn();

// Reviews list
$reviewsStmt = $pdo->prepare("
    SELECT r.*, u.username, u.full_name
    FROM product_reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.product_id = ?
    ORDER BY r.created_at DESC
    LIMIT 20
");
$reviewsStmt->execute([$productId]);
$reviews = $reviewsStmt->fetchAll();

// Check if current user has already reviewed
$myReviewStmt = $pdo->prepare("SELECT * FROM product_reviews WHERE product_id = ? AND user_id = ?");
$myReviewStmt->execute([$productId, $uid]);
$myReview = $myReviewStmt->fetch();

// Check if user has purchased this product (for "Verified Purchase" badge)
$purchasedStmt = $pdo->prepare("
    SELECT COUNT(*) FROM order_items oi 
    JOIN orders o ON oi.order_id = o.id 
    WHERE oi.product_id = ? AND o.user_id = ? 
      AND o.status IN ('delivered','shipped')
");
$purchasedStmt->execute([$productId, $uid]);
$hasPurchased = $purchasedStmt->fetchColumn() > 0;

// Related products (same category, exclude current)
$relatedStmt = $pdo->prepare("
    SELECT * FROM products 
    WHERE category = ? AND id != ? AND stock > 0 
    ORDER BY RAND() LIMIT 4
");
$relatedStmt->execute([$product['category'], $productId]);
$related = $relatedStmt->fetchAll();

// Handle review submission
$reviewMsg = '';
$reviewMsgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isCustomer()) {
        $reviewMsg = 'Only customers can submit reviews.';
        $reviewMsgType = 'error';
    } else {
        $rating = (int)($_POST['rating'] ?? 0);
        $text = trim($_POST['review_text'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $reviewMsg = 'Please select a rating between 1 and 5.';
            $reviewMsgType = 'error';
        } else {
            try {
                $pdo->prepare("
                    INSERT INTO product_reviews (product_id, user_id, rating, review_text)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE rating = VALUES(rating), review_text = VALUES(review_text), updated_at = NOW()
                ")->execute([$productId, $uid, $rating, $text]);

                $reviewMsg = $myReview ? 'Your review has been updated.' : 'Thank you! Your review has been submitted.';
                // Refresh
                header("Location: " . moduleUrl('product_detail.php?id=' . $productId . '&reviewed=1'));
                exit;
            } catch (PDOException $e) {
                $reviewMsg = 'Error: ' . $e->getMessage();
                $reviewMsgType = 'error';
            }
        }
    }
}

if (isset($_GET['reviewed'])) {
    $reviewMsg = 'Thank you! Your review has been submitted.';
}

// Re-fetch ratings after potential new review
$ratingStmt->execute([$productId]);
$ratings = $ratingStmt->fetch();

$avgRating = round($ratings['avg_rating'], 1);

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.pd-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 32px;
    margin-bottom: 32px;
}
@media (max-width: 900px) { .pd-container { grid-template-columns: 1fr; } }

.pd-image {
    background: #eff6ff;
    border-radius: 16px;
    aspect-ratio: 1 / 1;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    position: relative;
}
.pd-image img { width: 100%; height: 100%; object-fit: cover; }
.pd-image .placeholder {
    font-size: 8rem;
    font-weight: 800;
    color: #2563eb;
    line-height: 1;
}

.pd-info { display: flex; flex-direction: column; gap: 16px; }
.pd-category {
    display: inline-block;
    padding: 4px 12px;
    background: #dbeafe;
    color: #1d4ed8;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    align-self: flex-start;
}
.pd-title {
    font-size: 1.9rem;
    font-weight: 800;
    color: #111827;
    letter-spacing: -0.02em;
    margin: 0;
    line-height: 1.2;
}
.pd-rating-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.pd-stars { display: inline-flex; gap: 2px; font-size: 1.1rem; }
.pd-stars .star-filled { color: #f59e0b; }
.pd-stars .star-empty { color: #d1d5db; }
.pd-rating-text { color: #6b7280; font-size: 0.88rem; }
.pd-rating-text strong { color: #111827; }

.pd-price {
    font-size: 2rem;
    font-weight: 800;
    color: #1d4ed8;
    letter-spacing: -0.02em;
}
.pd-stock {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #ecfdf5;
    color: #065f46;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 600;
    align-self: flex-start;
}
.pd-stock.low { background: #fef3c7; color: #92400e; }
.pd-stock.out { background: #fee2e2; color: #991b1b; }

.pd-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    padding: 16px;
    background: #f9fafb;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}
.pd-stat { text-align: center; }
.pd-stat-label {
    font-size: 0.7rem;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 4px;
}
.pd-stat-value {
    font-size: 1.15rem;
    font-weight: 800;
    color: #111827;
}

.pd-desc {
    color: #374151;
    font-size: 0.95rem;
    line-height: 1.7;
    white-space: pre-line;
}

.pd-form {
    display: flex;
    gap: 12px;
    align-items: flex-end;
    padding-top: 16px;
    border-top: 1px solid #f3f4f6;
}
.pd-qty {
    display: flex;
    align-items: center;
    background: #f4f7fb;
    border-radius: 10px;
    padding: 4px;
    border: 1.5px solid #e5e7eb;
}
.pd-qty button {
    background: transparent;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    cursor: pointer;
    color: #2563eb;
    font-weight: 800;
    font-size: 1.1rem;
}
.pd-qty button:hover { background: #dbeafe; }
.pd-qty input {
    width: 50px;
    text-align: center;
    border: none;
    background: transparent;
    font-size: 1rem;
    font-weight: 700;
    font-family: inherit;
}
.pd-qty input:focus { outline: none; }
.pd-add-btn {
    flex: 1;
    padding: 14px 20px;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    box-shadow: 0 8px 24px rgba(37,99,235,0.32);
    transition: all 0.15s;
}
.pd-add-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 12px 32px rgba(37,99,235,0.42);
}
.pd-add-btn:disabled {
    background: #cbd5e1;
    cursor: not-allowed;
    box-shadow: none;
}

/* Reviews Section */
.reviews-grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 32px;
}
@media (max-width: 900px) { .reviews-grid { grid-template-columns: 1fr; } }

.ratings-summary {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 24px;
    text-align: center;
}
.ratings-summary .big-rating {
    font-size: 3.5rem;
    font-weight: 800;
    color: #111827;
    line-height: 1;
    letter-spacing: -0.03em;
}
.ratings-summary .big-stars {
    font-size: 1.4rem;
    margin: 8px 0 6px;
}
.ratings-summary .total {
    color: #6b7280;
    font-size: 0.85rem;
    margin-bottom: 20px;
}

.rating-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    font-size: 0.82rem;
}
.rating-bar-label { color: #6b7280; font-weight: 600; min-width: 40px; text-align: left; }
.rating-bar-track {
    flex: 1;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
}
.rating-bar-fill {
    height: 100%;
    background: #f59e0b;
    border-radius: 4px;
    transition: width 0.5s;
}
.rating-bar-count { color: #9ca3af; min-width: 30px; text-align: right; font-size: 0.78rem; }

.review-item {
    padding: 20px 0;
    border-bottom: 1px solid #f3f4f6;
}
.review-item:last-child { border-bottom: none; }
.review-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 8px;
}
.review-user {
    display: flex;
    align-items: center;
    gap: 10px;
}
.review-avatar {
    width: 38px;
    height: 38px;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.review-user-name { font-weight: 700; color: #111827; font-size: 0.9rem; }
.review-date { color: #9ca3af; font-size: 0.78rem; }
.review-stars { font-size: 0.95rem; }
.review-stars .star-filled { color: #f59e0b; }
.review-stars .star-empty { color: #d1d5db; }
.review-text { color: #374151; font-size: 0.9rem; line-height: 1.6; margin: 8px 0 0; }
.verified-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    background: #ecfdf5;
    color: #065f46;
    font-size: 0.7rem;
    font-weight: 600;
    border-radius: 999px;
}

.review-form-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
}
.review-form-box h4 {
    margin: 0 0 12px;
    color: #111827;
    font-size: 1rem;
    font-weight: 700;
}
.star-rating-input {
    display: flex;
    gap: 6px;
    margin-bottom: 14px;
}
.star-rating-input .star {
    font-size: 1.8rem;
    cursor: pointer;
    color: #d1d5db;
    transition: all 0.15s;
    background: transparent;
    border: none;
    padding: 0;
    line-height: 1;
}
.star-rating-input .star:hover,
.star-rating-input .star.active { color: #f59e0b; }

.review-textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    font-family: inherit;
    font-size: 0.9rem;
    resize: vertical;
    min-height: 90px;
    background: #fff;
}
.review-textarea:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 4px rgba(37,99,235,0.12);
}

.no-reviews {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
}
.no-reviews .icon { font-size: 3rem; margin-bottom: 12px; opacity: 0.5; }

.related-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 20px;
    margin-top: 16px;
}
.related-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
}
.related-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px rgba(16,24,40,0.08);
    border-color: #dbeafe;
}
.related-image {
    height: 140px;
    background: #eff6ff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563eb;
    font-size: 2rem;
    font-weight: 800;
    overflow: hidden;
}
.related-image img { width: 100%; height: 100%; object-fit: cover; }
.related-body { padding: 14px; }
.related-name { font-weight: 700; color: #111827; font-size: 0.9rem; margin: 0 0 4px; }
.related-price { color: #1d4ed8; font-weight: 700; font-size: 0.95rem; }
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
                    <h2>Product Details</h2>
                    <p>Full information &amp; customer reviews</p>
                </div>
            </div>
            <a href="<?= moduleUrl('shop.php') ?>" class="btn btn-secondary">← Back to Shop</a>
        </header>

        <div class="page-body">
            <?php if ($reviewMsg): ?>
                <div class="alert alert-<?= $reviewMsgType === 'success' ? 'success' : 'error' ?>" style="background:<?= $reviewMsgType === 'success' ? '#ecfdf5' : '#fef2f2' ?>;color:<?= $reviewMsgType === 'success' ? '#065f46' : '#991b1b' ?>;border:1px solid <?= $reviewMsgType === 'success' ? '#6ee7b7' : '#fca5a5' ?>;padding:14px 16px;border-radius:10px;margin-bottom:20px;">
                    <?= $reviewMsgType === 'success' ? '✅ ' : '⚠️ ' ?><?= e($reviewMsg) ?>
                </div>
            <?php endif; ?>

            <!-- Product Detail Grid -->
            <div class="pd-container">
                <!-- Left: Image -->
                <div class="pd-image">
                    <?php if ($product['image_url']): ?>
                        <img src="<?= baseUrl($product['image_url']) ?>" alt="<?= e($product['name']) ?>">
                    <?php else: ?>
                        <div class="placeholder"><?= strtoupper(substr($product['name'], 0, 1)) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Right: Info -->
                <div class="pd-info">
                    <span class="pd-category"><?= e($product['category']) ?></span>
                    <h1 class="pd-title"><?= e($product['name']) ?></h1>

                    <!-- Rating Row -->
                    <div class="pd-rating-row">
                        <span class="pd-stars">
                            <?php
                            for ($i = 1; $i <= 5; $i++) {
                                echo '<span class="star-' . ($i <= round($avgRating) ? 'filled' : 'empty') . '">★</span>';
                            }
                            ?>
                        </span>
                        <span class="pd-rating-text">
                            <?php if ($ratings['total_reviews'] > 0): ?>
                                <strong><?= number_format($avgRating, 1) ?></strong>
                                (<?= $ratings['total_reviews'] ?> review<?= $ratings['total_reviews'] != 1 ? 's' : '' ?>)
                            <?php else: ?>
                                No reviews yet
                            <?php endif; ?>
                        </span>
                        <?php if ($unitsSold > 0): ?>
                            <span style="color:#9ca3af;">·</span>
                            <span class="pd-rating-text"><strong><?= number_format($unitsSold) ?></strong> sold</span>
                        <?php endif; ?>
                    </div>

                    <!-- Price -->
                    <div class="pd-price">₱<?= number_format($product['price'], 2) ?></div>

                    <!-- Stock -->
                    <?php if ($product['stock'] == 0): ?>
                        <span class="pd-stock out">❌ Out of Stock</span>
                    <?php elseif ($product['stock'] < 10): ?>
                        <span class="pd-stock low">⚠️ Only <?= $product['stock'] ?> left in stock</span>
                    <?php else: ?>
                        <span class="pd-stock">✅ In Stock (<?= $product['stock'] ?> available)</span>
                    <?php endif; ?>

                    <!-- Stats Grid -->
                    <div class="pd-stats">
                        <div class="pd-stat">
                            <div class="pd-stat-label">Rating</div>
                            <div class="pd-stat-value"><?= $ratings['total_reviews'] > 0 ? number_format($avgRating, 1) . '/5' : '—' ?></div>
                        </div>
                        <div class="pd-stat">
                            <div class="pd-stat-label">Reviews</div>
                            <div class="pd-stat-value"><?= $ratings['total_reviews'] ?></div>
                        </div>
                        <div class="pd-stat">
                            <div class="pd-stat-label">Sold</div>
                            <div class="pd-stat-value"><?= number_format($unitsSold) ?></div>
                        </div>
                    </div>

                    <!-- Full Description -->
                    <div>
                        <div class="pd-stat-label" style="margin-bottom:8px;">Description</div>
                        <div class="pd-desc"><?= e($product['description'] ?: 'No description provided.') ?></div>
                    </div>

                    <!-- Add to Cart -->
                    <?php if ($product['stock'] > 0 && (isCustomer() || isAdmin())): ?>
                        <form method="POST" action="<?= moduleUrl('shop.php') ?>" class="pd-form">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <div class="pd-qty">
                                <button type="button" onclick="changeQty(-1)">−</button>
                                <input type="number" name="quantity" id="qtyInput" value="1" min="1" max="<?= $product['stock'] ?>">
                                <button type="button" onclick="changeQty(1)">+</button>
                            </div>
                            <button type="submit" name="add_to_cart" class="pd-add-btn">🛒 Add to Cart</button>
                        </form>
                    <?php else: ?>
                        <div style="padding:16px;background:#f4f7fb;border-radius:10px;text-align:center;color:#6b7280;">
                            This product is not available for purchase.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reviews Section -->
            <div class="card card-pad" style="margin-top:24px;">
                <h3 class="section-title" style="margin-bottom:20px;">⭐ Customer Reviews</h3>

                <div class="reviews-grid">
                    <!-- Left: Summary -->
                    <div>
                        <div class="ratings-summary">
                            <div class="big-rating"><?= $ratings['total_reviews'] > 0 ? number_format($avgRating, 1) : '—' ?></div>
                            <div class="big-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="star-<?= $i <= round($avgRating) ? 'filled' : 'empty' ?>" style="color:<?= $i <= round($avgRating) ? '#f59e0b' : '#d1d5db' ?>;">★</span>
                                <?php endfor; ?>
                            </div>
                            <div class="total"><?= $ratings['total_reviews'] ?> review<?= $ratings['total_reviews'] != 1 ? 's' : '' ?></div>

                            <!-- Rating distribution -->
                            <?php
                            $total = max($ratings['total_reviews'], 1);
                            for ($star = 5; $star >= 1; $star--):
                                $count = $ratings['r' . $star] ?? 0;
                                $pct = $total > 0 ? ($count / $total) * 100 : 0;
                            ?>
                                <div class="rating-bar">
                                    <span class="rating-bar-label"><?= $star ?> ★</span>
                                    <div class="rating-bar-track">
                                        <div class="rating-bar-fill" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                    <span class="rating-bar-count"><?= $count ?></span>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <!-- Review Form -->
                        <?php if (isCustomer()): ?>
                            <div class="review-form-box" style="margin-top:16px;">
                                <h4><?= $myReview ? '✏️ Update Your Review' : '✍️ Write a Review' ?></h4>
                                <?php if (!$hasPurchased && !$myReview): ?>
                                    <p style="margin:0 0 12px;font-size:0.82rem;color:#6b7280;">
                                        💡 Tip: You'll get a <strong>Verified Purchase</strong> badge after ordering this product.
                                    </p>
                                <?php endif; ?>
                                <form method="POST">
                                    <input type="hidden" name="rating" id="ratingValue" value="<?= $myReview ? $myReview['rating'] : 5 ?>">
                                    <div class="star-rating-input" id="starInput">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <button type="button" class="star <?= $myReview && $i <= $myReview['rating'] ? 'active' : '' ?>" data-value="<?= $i ?>" onclick="setRating(<?= $i ?>)">★</button>
                                        <?php endfor; ?>
                                    </div>
                                    <textarea name="review_text" class="review-textarea" placeholder="Share your thoughts about this product..."><?= e($myReview['review_text'] ?? '') ?></textarea>
                                    <button type="submit" name="submit_review" class="btn btn-primary" style="margin-top:12px;width:100%;padding:12px;">
                                        <?= $myReview ? 'Update Review' : 'Submit Review' ?>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right: Reviews List -->
                    <div>
                        <?php if (empty($reviews)): ?>
                            <div class="no-reviews">
                                <div class="icon">💬</div>
                                <p style="margin:0 0 6px;font-weight:600;color:#111827;">No reviews yet</p>
                                <p style="margin:0;font-size:0.85rem;">Be the first to review this product!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($reviews as $r): ?>
                                <div class="review-item">
                                    <div class="review-header">
                                        <div class="review-user">
                                            <div class="review-avatar"><?= strtoupper(substr($r['full_name'] ?: $r['username'], 0, 1)) ?></div>
                                            <div>
                                                <div class="review-user-name"><?= e($r['full_name'] ?: $r['username']) ?></div>
                                                <div class="review-date"><?= date('M j, Y', strtotime($r['created_at'])) ?></div>
                                            </div>
                                        </div>
                                        <div style="text-align:right;">
                                            <div class="review-stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <span class="<?= $i <= $r['rating'] ? 'star-filled' : 'star-empty' ?>">★</span>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($r['review_text']): ?>
                                        <p class="review-text"><?= nl2br(e($r['review_text'])) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Related Products -->
            <?php if (!empty($related)): ?>
                <div style="margin-top:32px;">
                    <h3 class="section-title" style="margin-bottom:16px;">🔎 You May Also Like</h3>
                    <div class="related-grid">
                        <?php foreach ($related as $rp): ?>
                            <a href="<?= moduleUrl('product_detail.php?id=' . $rp['id']) ?>" class="related-card">
                                <div class="related-image">
                                    <?php if ($rp['image_url']): ?>
                                        <img src="<?= baseUrl($rp['image_url']) ?>" alt="<?= e($rp['name']) ?>">
                                    <?php else: ?>
                                        <?= strtoupper(substr($rp['name'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="related-body">
                                    <div style="font-size:0.68rem;color:#2563eb;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:2px;"><?= e($rp['category']) ?></div>
                                    <div class="related-name"><?= e($rp['name']) ?></div>
                                    <div class="related-price">₱<?= number_format($rp['price'], 2) ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<script>
function changeQty(delta) {
    const input = document.getElementById('qtyInput');
    const max = parseInt(input.max) || 99;
    let val = parseInt(input.value) || 1;
    val += delta;
    if (val < 1) val = 1;
    if (val > max) val = max;
    input.value = val;
}

function setRating(value) {
    document.getElementById('ratingValue').value = value;
    document.querySelectorAll('#starInput .star').forEach(btn => {
        btn.classList.toggle('active', parseInt(btn.dataset.value) <= value);
    });
}

// Initialize stars based on current value
document.addEventListener('DOMContentLoaded', function() {
    const current = parseInt(document.getElementById('ratingValue').value) || 5;
    document.querySelectorAll('#starInput .star').forEach(btn => {
        btn.classList.toggle('active', parseInt(btn.dataset.value) <= current);
    });
});
</script>