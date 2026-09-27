<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireProductManager();

header('Content-Type: application/json');

$range = $_GET['range'] ?? '30';
$ranges = ['7', '30', '90', '365', 'all'];
if (!in_array($range, $ranges, true)) $range = '30';

$dateFilter = $range === 'all' ? '' : 'AND created_at >= DATE_SUB(NOW(), INTERVAL ' . (int)$range . ' DAY)';

try {
    // ============ KPIs ============
    $revenue = $pdo->query("
        SELECT COALESCE(SUM(total_amount),0) FROM orders 
        WHERE status NOT IN ('cancelled','returned') 
          AND payment_status = 'paid' $dateFilter
    ")->fetchColumn();

    $ordersCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE 1=1 $dateFilter")->fetchColumn();
    $avgOrder = $ordersCount > 0 ? $revenue / $ordersCount : 0;

    $productsSold = $pdo->query("
        SELECT COALESCE(SUM(oi.quantity),0) 
        FROM order_items oi 
        JOIN orders o ON oi.order_id = o.id 
        WHERE o.status NOT IN ('cancelled','returned') $dateFilter
    ")->fetchColumn();

    $pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
    $pendingReturns = $pdo->query("SELECT COUNT(*) FROM returns WHERE status = 'pending'")->fetchColumn();
    $lowStock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 15")->fetchColumn();
    $unpaidOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status IN ('unpaid','pending_verification')")->fetchColumn();

    // ============ DAILY REVENUE (for line chart) ============
    $days = $range === 'all' ? 30 : (int)$range;
    $dailyRevenue = $pdo->query("
        SELECT DATE(created_at) as day, COALESCE(SUM(total_amount),0) as revenue, COUNT(*) as orders
        FROM orders 
        WHERE status NOT IN ('cancelled','returned') 
          AND payment_status = 'paid' 
          AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
        GROUP BY DATE(created_at)
        ORDER BY day ASC
    ")->fetchAll();

    // Fill gaps for missing days
    $dailyData = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dailyData[$date] = ['day' => $date, 'revenue' => 0, 'orders' => 0];
    }
    foreach ($dailyRevenue as $row) {
        if (isset($dailyData[$row['day']])) {
            $dailyData[$row['day']]['revenue'] = (float)$row['revenue'];
            $dailyData[$row['day']]['orders'] = (int)$row['orders'];
        }
    }
    $dailyData = array_values($dailyData);

    // ============ CATEGORY BREAKDOWN (for doughnut) ============
    $revByCategory = $pdo->query("
        SELECT p.category, COALESCE(SUM(oi.quantity * oi.price),0) as revenue
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status NOT IN ('cancelled','returned')
        GROUP BY p.category
        ORDER BY revenue DESC
    ")->fetchAll();

    // ============ ORDER STATUS BREAKDOWN (for bar) ============
    $statusBreakdown = $pdo->query("
        SELECT status, COUNT(*) as cnt FROM orders GROUP BY status
    ")->fetchAll();

    // ============ TOP PRODUCTS ============
    $topProducts = $pdo->query("
        SELECT p.name, p.category, SUM(oi.quantity) as total_qty, SUM(oi.quantity * oi.price) as total_revenue
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status NOT IN ('cancelled','returned')
        GROUP BY p.id
        ORDER BY total_qty DESC
        LIMIT 10
    ")->fetchAll();

    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'range' => $range,
        'kpis' => [
            'revenue' => (float)$revenue,
            'orders' => (int)$ordersCount,
            'avg_order' => (float)$avgOrder,
            'products_sold' => (int)$productsSold,
            'pending_orders' => (int)$pendingOrders,
            'pending_returns' => (int)$pendingReturns,
            'low_stock' => (int)$lowStock,
            'unpaid_orders' => (int)$unpaidOrders
        ],
        'daily' => $dailyData,
        'categories' => $revByCategory,
        'statuses' => $statusBreakdown,
        'top_products' => $topProducts
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}