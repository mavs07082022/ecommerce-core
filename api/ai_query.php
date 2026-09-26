<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../ai/cohere_service.php';

header('Content-Type: application/json');

$query = $_POST['query'] ?? '';
if (!$query) {
    echo json_encode(['response' => 'Please provide a query.']);
    exit;
}

$products = $pdo->query("SELECT * FROM products WHERE stock > 0")->fetchAll();
echo json_encode(['response' => getProductRecommendation($query, $products)]);