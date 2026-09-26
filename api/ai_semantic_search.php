<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../ai/cohere_service.php';
header('Content-Type: application/json');

$query = trim($_POST['query'] ?? '');
if (!$query) {
    echo json_encode(['success' => false, 'results' => []]);
    exit;
}

$results = semanticSearchProducts($query, $pdo, 6);

$out = [];
foreach ($results as $r) {
    $p = $r['product'];
    $out[] = [
        'id' => $p['product_id'],
        'name' => $p['name'],
        'category' => $p['category'],
        'price' => (float)$p['price'],
        'score' => round($r['score'] * 100)
    ];
}

echo json_encode(['success' => true, 'results' => $out]);