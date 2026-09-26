<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/ai/cohere_service.php';

echo "<h2>Setup Script</h2>";

// ---- Accounts ----
$accounts = [
    ['username' => 'admin',    'password' => 'admin123',    'email' => 'admin@ecommerce.com',    'full_name' => 'System Admin',   'role' => 'admin'],
    ['username' => 'pm',       'password' => 'pm123',       'email' => 'pm@ecommerce.com',       'full_name' => 'Product Manager', 'role' => 'product_manager'],
    ['username' => 'customer', 'password' => 'customer123', 'email' => 'customer@test.com',      'full_name' => 'John Doe',       'role' => 'customer'],
];

echo "<h3>User Accounts</h3>";
foreach ($accounts as $a) {
    $hash = password_hash($a['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$a['username']]);
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE users SET password=?, email=?, full_name=?, role=?, status='active', email_verified=1, profile_completed=1 WHERE username=?")
            ->execute([$hash, $a['email'], $a['full_name'], $a['role'], $a['username']]);
        echo "✅ Updated: {$a['username']} / {$a['password']} ({$a['role']})<br>";
    } else {
        $pdo->prepare("INSERT INTO users (username, email, password, full_name, role, status, email_verified, profile_completed) VALUES (?,?,?,?,?,'active',1,1)")
            ->execute([$a['username'], $a['email'], $hash, $a['full_name'], $a['role']]);
        echo "✅ Created: {$a['username']} / {$a['password']} ({$a['role']})<br>";
    }
}

// ---- Product Embeddings ----
echo "<h3>Product Embeddings</h3>";
if (!cohereHasKey()) {
    echo "⚠️ Cohere API key not configured — skipping embeddings.<br>";
} else {
    $products = $pdo->query("SELECT id, name, description, category FROM products")->fetchAll();
    foreach ($products as $p) {
        $text = $p['name'] . '. ' . $p['description'] . '. Category: ' . $p['category'];
        $vec = getEmbedding($text, 'search_document');
        if ($vec) {
            $pdo->prepare("
                INSERT INTO product_embeddings (product_id, embedding)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE embedding = VALUES(embedding)
            ")->execute([$p['id'], json_encode($vec)]);
            echo "✅ Embedding for: {$p['name']}<br>";
        } else {
            echo "❌ Failed embedding for: {$p['name']}<br>";
        }
    }
}

echo "<br><b>Verification:</b><br>";
foreach ($pdo->query("SELECT username, password, role FROM users")->fetchAll() as $u) {
    $test = ['admin'=>'admin123','pm'=>'pm123','customer'=>'customer123'][$u['username']] ?? null;
    if ($test) {
        echo "{$u['username']} ({$u['role']}): " . (password_verify($test, $u['password']) ? '✅' : '❌') . "<br>";
    }
}
echo "<br>Embeddings in DB: " . $pdo->query("SELECT COUNT(*) FROM product_embeddings")->fetchColumn() . "<br>";
echo "<br><b>Delete this file now, then go to login.php</b>";