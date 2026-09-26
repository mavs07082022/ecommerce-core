<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';

// If logged in, redirect by role
if (isset($_SESSION['user_id'])) {
    if (isAdmin()) redirect('index.php');
    if (isProductManager()) redirect('pm_dashboard.php');
    redirect('customer_dashboard.php');
}

// Featured products (only in-stock)
$products = $pdo->query("SELECT * FROM products WHERE stock > 0 ORDER BY id DESC LIMIT 8")->fetchAll();

// Live store stats
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE stock > 0")->fetchColumn();
$totalOrders   = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalUsers    = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Commerce Core — Shop Smart, Shop Easy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= baseUrl('assets/css/style.css') ?>">
    <style>
        body { background: #ffffff; }

        /* Navbar */
        .landing-nav {
            background: #111827;
            padding: 16px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 50;
            border-bottom: 1px solid #1e293b;
        }
        .landing-nav-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .landing-nav-brand .logo {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 800; font-size: 1rem;
            box-shadow: 0 4px 12px rgba(37,99,235,0.4);
        }
        .landing-nav-brand h1 {
            color: #fff; font-size: 1rem; font-weight: 700; margin: 0;
        }
        .landing-nav-links {
            display: flex; gap: 8px; align-items: center;
        }
        .landing-nav-links a {
            color: #d1d5db; text-decoration: none; font-size: 0.88rem;
            padding: 8px 16px; border-radius: 8px; font-weight: 500;
            transition: all 0.15s;
        }
        .landing-nav-links a:hover { background: #1e293b; color: #fff; }
        .landing-nav-links a.btn-cta {
            background: #2563eb; color: #fff; font-weight: 600;
        }
        .landing-nav-links a.btn-cta:hover { background: #1d4ed8; }

        /* Hero */
        .hero {
            background: linear-gradient(135deg, #1e293b 0%, #111827 100%);
            padding: 100px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute; top: -30%; right: -10%;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(37,99,235,0.25), transparent 65%);
            pointer-events: none;
        }
        .hero::after {
            content: '';
            position: absolute; bottom: -40%; left: -10%;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(29,78,216,0.2), transparent 65%);
            pointer-events: none;
        }
        .hero-content { position: relative; z-index: 1; max-width: 760px; margin: 0 auto; }
        .hero-badge {
            display: inline-block;
            background: rgba(37,99,235,0.15);
            color: #dbeafe;
            padding: 6px 16px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            margin-bottom: 22px;
            border: 1px solid rgba(37,99,235,0.4);
        }
        .hero h1 {
            color: #ffffff;
            font-size: 3rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin: 0 0 20px;
            line-height: 1.1;
        }
        .hero h1 span {
            background: linear-gradient(135deg, #60a5fa, #2563eb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero p {
            color: #d1d5db;
            font-size: 1.1rem;
            line-height: 1.6;
            margin: 0 0 32px;
        }
        .hero-cta {
            display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;
        }
        .hero-cta a {
            padding: 14px 28px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.15s;
        }
        .hero-cta .primary {
            background: #2563eb; color: #fff;
            box-shadow: 0 8px 20px rgba(37,99,235,0.4);
        }
        .hero-cta .primary:hover {
            background: #1d4ed8; transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(37,99,235,0.5);
        }
        .hero-cta .secondary {
            background: rgba(255,255,255,0.1);
            color: #fff; border: 1px solid rgba(255,255,255,0.2);
        }
        .hero-cta .secondary:hover { background: rgba(255,255,255,0.18); }

        /* Stats */
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 60px;
            margin-top: 60px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .hero-stat { text-align: center; }
        .hero-stat .num {
            color: #ffffff;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .hero-stat .lbl {
            color: #9ca3af;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 600;
            margin-top: 4px;
        }

        /* Features */
        .features { padding: 80px 40px; background: #f4f7fb; }
        .features-grid {
            max-width: 1200px; margin: 0 auto;
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px;
        }
        .feature-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 32px 28px;
            transition: all 0.2s;
        }
        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 32px rgba(16,24,40,0.08);
            border-color: #dbeafe;
        }
        .feature-icon {
            width: 52px; height: 52px;
            background: #eff6ff; color: #2563eb;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 18px;
        }
        .feature-icon svg { width: 26px; height: 26px; }
        .feature-card h3 {
            font-size: 1.1rem; font-weight: 700; color: #111827;
            margin: 0 0 8px;
        }
        .feature-card p { color: #6b7280; font-size: 0.9rem; margin: 0; line-height: 1.6; }

        /* Products */
        .products-section { padding: 80px 40px; background: #ffffff; }
        .products-section-inner { max-width: 1200px; margin: 0 auto; }
        .products-header { text-align: center; margin-bottom: 48px; }
        .products-header h2 {
            font-size: 2rem; font-weight: 800; color: #111827;
            margin: 0 0 12px; letter-spacing: -0.02em;
        }
        .products-header p { color: #6b7280; font-size: 1rem; margin: 0; }

        /* Footer */
        .landing-footer {
            background: #111827; color: #9ca3af;
            padding: 40px;
            text-align: center;
            font-size: 0.85rem;
        }
        .landing-footer a { color: #d1d5db; text-decoration: none; margin: 0 12px; }
        .landing-footer a:hover { color: #fff; }

        @media (max-width: 900px) {
            .features-grid { grid-template-columns: 1fr; }
            .hero h1 { font-size: 2.1rem; }
            .hero { padding: 70px 24px; }
            .features, .products-section { padding: 60px 24px; }
            .landing-nav { padding: 14px 20px; }
            .landing-nav-links a { padding: 8px 12px; font-size: 0.82rem; }
            .hero-stats { gap: 30px; }
            .hero-stat .num { font-size: 1.5rem; }
        }
        @media (max-width: 560px) {
            .hero h1 { font-size: 1.7rem; }
            .landing-nav-links a:not(.btn-cta) { display: none; }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="landing-nav">
        <a href="<?= baseUrl('landing.php') ?>" class="landing-nav-brand">
            <div class="logo">E</div>
            <h1>E-Commerce Core</h1>
        </a>
        <div class="landing-nav-links">
            <a href="#features">Features</a>
            <a href="#products">Products</a>
            <a href="<?= baseUrl('login.php') ?>">Sign In</a>
            <a href="<?= baseUrl('auth/register.php') ?>" class="btn-cta">Sign Up Free</a>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-badge">🇵🇭 Now serving the Philippines</div>
            <h1>Shop Smarter with <span>AI-Powered</span> Recommendations</h1>
            <p>Discover products tailored to your needs. Our intelligent assistant helps you find exactly what you're looking for — fast, friendly, and always in Philippine Peso.</p>
            <div class="hero-cta">
                <a href="<?= baseUrl('auth/register.php') ?>" class="primary">Create Free Account →</a>
                <a href="#products" class="secondary">Browse Products</a>
            </div>

            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="num"><?= number_format($totalProducts) ?>+</div>
                    <div class="lbl">Products</div>
                </div>
                <div class="hero-stat">
                    <div class="num"><?= number_format($totalOrders) ?>+</div>
                    <div class="lbl">Orders Placed</div>
                </div>
                <div class="hero-stat">
                    <div class="num"><?= number_format($totalUsers) ?>+</div>
                    <div class="lbl">Happy Customers</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section class="features" id="features">
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <h3>AI-Powered Recommendations</h3>
                <p>Just describe what you need and our Cohere-powered assistant will find the perfect product for you.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h3>Secure Checkout</h3>
                <p>Shop with confidence. Multiple payment options including COD, GCash, and Card.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                </div>
                <h3>Real-Time Order Tracking</h3>
                <p>Track every order from placement to delivery with a live timeline and status updates.</p>
            </div>
        </div>
    </section>

    <!-- Products -->
    <section class="products-section" id="products">
        <div class="products-section-inner">
            <div class="products-header">
                <h2>Featured Products</h2>
                <p>Handpicked selections from our store</p>
            </div>

            <?php if (empty($products)): ?>
                <div style="text-align:center;padding:60px;color:#6b7280;">
                    <p>No products available yet. Check back soon!</p>
                </div>
            <?php else: ?>
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
                            <a href="<?= baseUrl('login.php') ?>" class="btn btn-primary btn-sm" style="margin-top:12px;width:100%;">Sign In to Buy</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
        <p style="margin:0 0 12px;">&copy; <?= date('Y') ?> E-Commerce Core. All rights reserved.</p>
        <div>
            <a href="#features">Features</a>
            <a href="#products">Products</a>
            <a href="<?= baseUrl('login.php') ?>">Sign In</a>
            <a href="<?= baseUrl('auth/register.php') ?>">Sign Up</a>
        </div>
    </footer>
</body>
</html>