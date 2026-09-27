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

$soldStmt = $pdo->prepare("
    SELECT COALESCE(SUM(oi.quantity), 0)
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.product_id = ?
      AND o.status IN ('delivered','shipped','processing')
");
$soldStmt->execute([$productId]);
$unitsSold = (int)$soldStmt->fetchColumn();

// Reviews with media aggregated
$reviewsStmt = $pdo->prepare("
    SELECT r.*, u.username, u.full_name
    FROM product_reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.product_id = ?
    ORDER BY r.created_at DESC
    LIMIT 30
");
$reviewsStmt->execute([$productId]);
$reviews = $reviewsStmt->fetchAll();

// Fetch all media for these reviews in one query
$reviewIds = array_column($reviews, 'id');
$mediaByReview = [];
if (!empty($reviewIds)) {
    $in = str_repeat('?,', count($reviewIds) - 1) . '?';
    $mStmt = $pdo->prepare("SELECT * FROM review_media WHERE review_id IN ($in) ORDER BY id ASC");
    $mStmt->execute($reviewIds);
    foreach ($mStmt->fetchAll() as $m) {
        $mediaByReview[$m['review_id']][] = $m;
    }
}

// Check if current user already reviewed
$myReviewStmt = $pdo->prepare("SELECT * FROM product_reviews WHERE product_id = ? AND user_id = ?");
$myReviewStmt->execute([$productId, $uid]);
$myReview = $myReviewStmt->fetch();

// Has the user purchased this product?
$purchasedStmt = $pdo->prepare("
    SELECT COUNT(*) FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.product_id = ? AND o.user_id = ?
      AND o.status IN ('delivered','shipped')
");
$purchasedStmt->execute([$productId, $uid]);
$hasPurchased = $purchasedStmt->fetchColumn() > 0;

// Related products
$relatedStmt = $pdo->prepare("
    SELECT * FROM products
    WHERE category = ? AND id != ? AND stock > 0
    ORDER BY RAND() LIMIT 4
");
$relatedStmt->execute([$product['category'], $productId]);
$related = $relatedStmt->fetchAll();

// ==================== REVIEW SUBMISSION ====================
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
                $pdo->beginTransaction();

                // Insert/update the review
                $pdo->prepare("
                    INSERT INTO product_reviews (product_id, user_id, rating, review_text, is_verified_purchase, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE rating = VALUES(rating), review_text = VALUES(review_text), updated_at = NOW()
                ")->execute([$productId, $uid, $rating, $text, $hasPurchased ? 1 : 0]);

                // Get the review ID (whether inserted or updated)
                $rStmt = $pdo->prepare("SELECT id FROM product_reviews WHERE product_id = ? AND user_id = ?");
                $rStmt->execute([$productId, $uid]);
                $reviewId = $rStmt->fetchColumn();

                // Handle multiple media files
                if (!empty($_FILES['review_media']['name'][0])) {
                    $count = count($_FILES['review_media']['name']);
                    for ($i = 0; $i < $count && $i < 5; $i++) { // max 5 files
                        if ($_FILES['review_media']['error'][$i] !== UPLOAD_ERR_OK) continue;

                        // Repack the file for handleUpload()
                        $_FILES['__single'] = [
                            'name'     => $_FILES['review_media']['name'][$i],
                            'type'     => $_FILES['review_media']['type'][$i],
                            'tmp_name' => $_FILES['review_media']['tmp_name'][$i],
                            'error'    => $_FILES['review_media']['error'][$i],
                            'size'     => $_FILES['review_media']['size'][$i],
                        ];
                        $url = handleUpload('__single', 'reviews');
                        if ($url) {
                            $mt = (strpos($_FILES['review_media']['type'][$i], 'video/') === 0) ? 'video' : 'image';
                            $pdo->prepare("INSERT INTO review_media (review_id, file_url, media_type) VALUES (?, ?, ?)")
                                ->execute([$reviewId, $url, $mt]);
                        }
                    }
                }

                $pdo->commit();

                header("Location: " . moduleUrl('product_detail.php?id=' . $productId . '&reviewed=1'));
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $reviewMsg = 'Error: ' . $e->getMessage();
                $reviewMsgType = 'error';
            }
        }
    }
}

if (isset($_GET['reviewed'])) {
    $reviewMsg = 'Thank you! Your review has been submitted.';
}

$ratingStmt->execute([$productId]);
$ratings = $ratingStmt->fetch();
$avgRating = round($ratings['avg_rating'], 1);

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.pd-container { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 32px; }
@media (max-width: 900px) { .pd-container { grid-template-columns: 1fr; } }

.pd-image { background: #eff6ff; border-radius: 16px; aspect-ratio: 1 / 1; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid #e5e7eb; position: relative; }
.pd-image img { width: 100%; height: 100%; object-fit: cover; }
.pd-image .placeholder { font-size: 8rem; font-weight: 800; color: #2563eb; line-height: 1; }

.pd-info { display: flex; flex-direction: column; gap: 16px; }
.pd-category { display: inline-block; padding: 4px 12px; background: #dbeafe; color: #1d4ed8; border-radius: 999px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; align-self: flex-start; }
.pd-title { font-size: 1.9rem; font-weight: 800; color: #111827; letter-spacing: -0.02em; margin: 0; line-height: 1.2; }
.pd-rating-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.pd-stars { display: inline-flex; gap: 2px; font-size: 1.1rem; }
.pd-stars .star-filled { color: #f59e0b; }
.pd-stars .star-empty { color: #d1d5db; }
.pd-rating-text { color: #6b7280; font-size: 0.88rem; }
.pd-rating-text strong { color: #111827; }
.pd-price { font-size: 2rem; font-weight: 800; color: #1d4ed8; letter-spacing: -0.02em; }
.pd-stock { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #ecfdf5; color: #065f46; border-radius: 999px; font-size: 0.82rem; font-weight: 600; align-self: flex-start; }
.pd-stock.low { background: #fef3c7; color: #92400e; }
.pd-stock.out { background: #fee2e2; color: #991b1b; }

.pd-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; padding: 16px; background: #f9fafb; border-radius: 12px; border: 1px solid #e5e7eb; }
.pd-stat { text-align: center; }
.pd-stat-label { font-size: 0.7rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
.pd-stat-value { font-size: 1.15rem; font-weight: 800; color: #111827; }
.pd-desc { color: #374151; font-size: 0.95rem; line-height: 1.7; white-space: pre-line; }

.pd-form { display: flex; gap: 12px; align-items: flex-end; padding-top: 16px; border-top: 1px solid #f3f4f6; }
.pd-qty { display: flex; align-items: center; background: #f4f7fb; border-radius: 10px; padding: 4px; border: 1.5px solid #e5e7eb; }
.pd-qty button { background: transparent; border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; color: #2563eb; font-weight: 800; font-size: 1.1rem; }
.pd-qty button:hover { background: #dbeafe; }
.pd-qty input { width: 50px; text-align: center; border: none; background: transparent; font-size: 1rem; font-weight: 700; font-family: inherit; }
.pd-add-btn { flex: 1; padding: 14px 20px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; border: none; border-radius: 10px; font-size: 1rem; font-weight: 700; font-family: inherit; cursor: pointer; box-shadow: 0 8px 24px rgba(37,99,235,0.32); transition: all 0.15s; }
.pd-add-btn:hover { transform: translateY(-1px); box-shadow: 0 12px 32px rgba(37,99,235,0.42); }

/* Reviews */
.reviews-grid { display: grid; grid-template-columns: 320px 1fr; gap: 32px; }
@media (max-width: 900px) { .reviews-grid { grid-template-columns: 1fr; } }

.ratings-summary { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 14px; padding: 24px; text-align: center; }
.ratings-summary .big-rating { font-size: 3.5rem; font-weight: 800; color: #111827; line-height: 1; letter-spacing: -0.03em; }
.ratings-summary .big-stars { font-size: 1.4rem; margin: 8px 0 6px; }
.ratings-summary .total { color: #6b7280; font-size: 0.85rem; margin-bottom: 20px; }

.rating-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 0.82rem; }
.rating-bar-label { color: #6b7280; font-weight: 600; min-width: 40px; text-align: left; }
.rating-bar-track { flex: 1; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; }
.rating-bar-fill { height: 100%; background: #f59e0b; border-radius: 4px; transition: width 0.5s; }
.rating-bar-count { color: #9ca3af; min-width: 30px; text-align: right; font-size: 0.78rem; }

.review-item { padding: 22px 0; border-bottom: 1px solid #f3f4f6; }
.review-item:last-child { border-bottom: none; }
.review-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 8px; flex-wrap: wrap; }
.review-user { display: flex; align-items: center; gap: 10px; }
.review-avatar { width: 42px; height: 42px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem; flex-shrink: 0; }
.review-user-name { font-weight: 700; color: #111827; font-size: 0.92rem; }
.review-date { color: #9ca3af; font-size: 0.78rem; margin-top: 2px; }
.review-stars { font-size: 1rem; }
.review-stars .star-filled { color: #f59e0b; }
.review-stars .star-empty { color: #d1d5db; }
.review-text { color: #374151; font-size: 0.92rem; line-height: 1.65; margin: 10px 0 0; white-space: pre-line; }

.verified-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 9px; background: #ecfdf5; color: #065f46;
    font-size: 0.7rem; font-weight: 600; border-radius: 999px;
    border: 1px solid #a7f3d0;
    margin-left: 6px;
}

/* Media gallery on review */
.review-media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 8px;
    margin-top: 12px;
}
.review-media-item {
    aspect-ratio: 1/1;
    background: #f4f7fb;
    border-radius: 10px;
    overflow: hidden;
    cursor: pointer;
    border: 1px solid #e5e7eb;
    position: relative;
}
.review-media-item img,
.review-media-item video {
    width: 100%; height: 100%; object-fit: cover;
}
.review-media-item .video-overlay {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    background: rgba(0,0,0,0.3);
    color: #fff;
}
.review-media-item .video-overlay svg { width: 28px; height: 28px; }

/* Review form */
.review-form-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 14px; padding: 22px; margin-bottom: 24px; }
.review-form-box h4 { margin: 0 0 12px; color: #111827; font-size: 1rem; font-weight: 700; }
.star-rating-input { display: flex; gap: 6px; margin-bottom: 14px; }
.star-rating-input .star { font-size: 1.8rem; cursor: pointer; color: #d1d5db; transition: all 0.15s; background: transparent; border: none; padding: 0; line-height: 1; }
.star-rating-input .star:hover, .star-rating-input .star.active { color: #f59e0b; }

.review-textarea { width: 100%; padding: 12px 14px; border: 1.5px solid #e5e7eb; border-radius: 10px; font-family: inherit; font-size: 0.9rem; resize: vertical; min-height: 90px; background: #fff; }
.review-textarea:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37,99,235,0.12); }

.file-drop {
    display: block;
    border: 2px dashed #bfdbfe;
    border-radius: 10px;
    padding: 18px;
    text-align: center;
    cursor: pointer;
    background: #f8fbff;
    transition: all 0.15s;
    margin-top: 12px;
}
.file-drop:hover { border-color: #2563eb; background: #eff6ff; }
.file-drop input[type="file"] { display: none; }
.file-drop .label { color: #1d4ed8; font-weight: 700; font-size: 0.88rem; }
.file-drop .hint { color: #6b7280; font-size: 0.75rem; margin-top: 4px; }
.file-preview { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.file-preview .preview-item { width: 72px; height: 72px; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb; background: #f4f7fb; }
.file-preview .preview-item img, .file-preview .preview-item video { width: 100%; height: 100%; object-fit: cover; }

.no-reviews { text-align: center; padding: 40px 20px; color: #6b7280; }
.no-reviews .icon { font-size: 3rem; margin-bottom: 12px; opacity: 0.5; }

.related-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; margin-top: 16px; }
.related-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; text-decoration: none; color: inherit; transition: all 0.2s; }
.related-card:hover { transform: translateY(-3px); box-shadow: 0 12px 24px rgba(16,24,40,0.08); border-color: #dbeafe; }
.related-image { height: 140px; background: #eff6ff; display: flex; align-items: center; justify-content: center; color: #2563eb; font-size: 2rem; font-weight: 800; overflow: hidden; }
.related-image img { width: 100%; height: 100%; object-fit: cover; }
.related-body { padding: 14px; }
.related-name { font-weight: 700; color: #111827; font-size: 0.9rem; margin: 0 0 4px; }
.related-price { color: #1d4ed8; font-weight: 700; font-size: 0.95rem; }

/* Lightbox */
.lightbox {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.9);
    z-index: 999;
    display: none;
    align-items: center; justify-content: center;
    padding: 20px;
}
.lightbox.active { display: flex; }
.lightbox-content { max-width: 90vw; max-height: 90vh; position: relative; }
.lightbox-content img, .lightbox-content video { max-width: 90vw; max-height: 90vh; border-radius: 12px; }
.lightbox-close {
    position: absolute; top: -44px; right: 0;
    background: rgba(255,255,255,0.15); color: #fff;
    border: none; width: 36px; height: 36px;
    border-radius: 50%; cursor: pointer; font-size: 20px;
}
.lightbox-close:hover { background: rgba(255,255,255,0.3); }
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
            <?php if (isset($_GET['added'])): ?>
                <div class="alert alert-success" style="background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7;padding:14px 16px;border-radius:10px;margin-bottom:20px;">
                    ✅ Added to cart! <a href="<?= moduleUrl('shop.php') ?>" style="color:#065f46;font-weight:700;text-decoration:underline;">Go to shop to checkout →</a>
                </div>
            <?php endif; ?>

            <?php if ($reviewMsg): ?>
                <div class="alert alert-<?= $reviewMsgType === 'success' ? 'success' : 'error' ?>" style="background:<?= $reviewMsgType === 'success' ? '#ecfdf5' : '#fef2f2' ?>;color:<?= $reviewMsgType === 'success' ? '#065f46' : '#991b1b' ?>;border:1px solid <?= $reviewMsgType === 'success' ? '#6ee7b7' : '#fca5a5' ?>;padding:14px 16px;border-radius:10px;margin-bottom:20px;">
                    <?= $reviewMsgType === 'success' ? '✅ ' : '⚠️ ' ?><?= e($reviewMsg) ?>
                </div>
            <?php endif; ?>

            <!-- Product Detail Grid -->
            <div class="pd-container">
                <div class="pd-image">
                    <?php if ($product['image_url']): ?>
                        <img src="<?= baseUrl($product['image_url']) ?>" alt="<?= e($product['name']) ?>">
                    <?php else: ?>
                        <div class="placeholder"><?= strtoupper(substr($product['name'], 0, 1)) ?></div>
                    <?php endif; ?>
                </div>

                <div class="pd-info">
                    <span class="pd-category"><?= e($product['category']) ?></span>
                    <h1 class="pd-title"><?= e($product['name']) ?></h1>

                    <div class="pd-rating-row">
                        <span class="pd-stars">
                            <?php for ($i = 1; $i <= 5; $i++) echo '<span class="star-' . ($i <= round($avgRating) ? 'filled' : 'empty') . '">★</span>'; ?>
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

                    <div class="pd-price">₱<?= number_format($product['price'], 2) ?></div>

                    <?php if ($product['stock'] == 0): ?>
                        <span class="pd-stock out">❌ Out of Stock</span>
                    <?php elseif ($product['stock'] < 10): ?>
                        <span class="pd-stock low">⚠️ Only <?= $product['stock'] ?> left in stock</span>
                    <?php else: ?>
                        <span class="pd-stock">✅ In Stock (<?= $product['stock'] ?> available)</span>
                    <?php endif; ?>

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

                    <div>
                        <div class="pd-stat-label" style="margin-bottom:8px;">Description</div>
                        <div class="pd-desc"><?= e($product['description'] ?: 'No description provided.') ?></div>
                    </div>

                    <?php if ($product['stock'] > 0 && (isCustomer() || isAdmin())): ?>
                        <form method="POST" action="<?= moduleUrl('shop.php') ?>" class="pd-form">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <input type="hidden" name="redirect_back" value="<?= moduleUrl('product_detail.php?id=' . $product['id']) ?>">
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
                    <!-- Left: Summary + Form -->
                    <div>
                        <div class="ratings-summary">
                            <div class="big-rating"><?= $ratings['total_reviews'] > 0 ? number_format($avgRating, 1) : '—' ?></div>
                            <div class="big-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span style="color:<?= $i <= round($avgRating) ? '#f59e0b' : '#d1d5db' ?>;">★</span>
                                <?php endfor; ?>
                            </div>
                            <div class="total"><?= $ratings['total_reviews'] ?> review<?= $ratings['total_reviews'] != 1 ? 's' : '' ?></div>

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

                        <?php if (isCustomer()): ?>
                            <div class="review-form-box" style="margin-top:16px;">
                                <h4><?= $myReview ? '✏️ Update Your Review' : '✍️ Write a Review' ?></h4>
                                <?php if ($hasPurchased && !$myReview): ?>
                                    <p style="margin:0 0 12px;font-size:0.82rem;color:#065f46;background:#ecfdf5;padding:8px 10px;border-radius:8px;">
                                        ✅ You purchased this — your review will show a <strong>Verified Purchase</strong> badge.
                                    </p>
                                <?php elseif (!$hasPurchased && !$myReview): ?>
                                    <p style="margin:0 0 12px;font-size:0.82rem;color:#6b7280;">
                                        💡 Tip: You'll get a <strong>Verified Purchase</strong> badge after ordering this product.
                                    </p>
                                <?php endif; ?>
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="rating" id="ratingValue" value="<?= $myReview ? $myReview['rating'] : 5 ?>">
                                    <div class="star-rating-input" id="starInput">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <button type="button" class="star <?= $myReview && $i <= $myReview['rating'] ? 'active' : '' ?>" data-value="<?= $i ?>" onclick="setRating(<?= $i ?>)">★</button>
                                        <?php endfor; ?>
                                    </div>
                                    <textarea name="review_text" class="review-textarea" placeholder="Share your thoughts about this product..."><?= e($myReview['review_text'] ?? '') ?></textarea>

                                    <label class="file-drop" for="reviewMediaInput">
                                        <div class="label">📷 Add Photos / Videos</div>
                                        <div class="hint">Upload up to 5 files (JPG, PNG, MP4, MOV — max 20MB each)</div>
                                        <input type="file" id="reviewMediaInput" name="review_media[]" accept="image/*,video/*" multiple onchange="previewFiles(this)">
                                    </label>
                                    <div class="file-preview" id="filePreview"></div>

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
                            <?php foreach ($reviews as $r):
                                $media = $mediaByReview[$r['id']] ?? [];
                            ?>
                                <div class="review-item">
                                    <div class="review-header">
                                        <div class="review-user">
                                            <div class="review-avatar"><?= strtoupper(substr($r['full_name'] ?: $r['username'], 0, 1)) ?></div>
                                            <div>
                                                <div class="review-user-name">
                                                    <?= e($r['full_name'] ?: $r['username']) ?>
                                                    <?php if (!empty($r['is_verified_purchase'])): ?>
                                                        <span class="verified-badge">✓ Verified Purchase</span>
                                                    <?php endif; ?>
                                                </div>
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
                                    <?php if (!empty($media)): ?>
                                        <div class="review-media-grid">
                                            <?php foreach ($media as $m): ?>
                                                <div class="review-media-item" onclick='openLightbox(<?= json_encode(baseUrl($m["file_url"])) ?>, <?= json_encode($m["media_type"]) ?>)'>
                                                    <?php if ($m['media_type'] === 'video'): ?>
                                                        <video src="<?= baseUrl($m['file_url']) ?>" preload="metadata"></video>
                                                        <div class="video-overlay">
                                                            <svg fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                        </div>
                                                    <?php else: ?>
                                                        <img src="<?= baseUrl($m['file_url']) ?>" alt="Review media" loading="lazy">
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
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

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox(event)">
    <div class="lightbox-content" id="lightboxContent"></div>
    <button class="lightbox-close" onclick="closeLightbox()">✕</button>
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

function previewFiles(input) {
    const box = document.getElementById('filePreview');
    box.innerHTML = '';
    const files = Array.from(input.files).slice(0, 5);
    files.forEach(f => {
        const div = document.createElement('div');
        div.className = 'preview-item';
        const url = URL.createObjectURL(f);
        if (f.type.startsWith('video/')) {
            div.innerHTML = `<video src="${url}" muted></video>`;
        } else {
            div.innerHTML = `<img src="${url}" alt="">`;
        }
        box.appendChild(div);
    });
}

function openLightbox(url, type) {
    const lb = document.getElementById('lightbox');
    const content = document.getElementById('lightboxContent');
    if (type === 'video') {
        content.innerHTML = `<video src="${url}" controls autoplay></video>`;
    } else {
        content.innerHTML = `<img src="${url}" alt="">`;
    }
    lb.classList.add('active');
}

function closeLightbox(e) {
    if (e && e.target.id !== 'lightbox' && !e.target.classList.contains('lightbox-close')) return;
    document.getElementById('lightbox').classList.remove('active');
    document.getElementById('lightboxContent').innerHTML = '';
}

document.addEventListener('DOMContentLoaded', function() {
    const current = parseInt(document.getElementById('ratingValue').value) || 5;
    document.querySelectorAll('#starInput .star').forEach(btn => {
        btn.classList.toggle('active', parseInt(btn.dataset.value) <= current);
    });
});
</script>