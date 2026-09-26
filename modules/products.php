<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../ai/cohere_service.php';
requireProductManager();

$pageTitle = 'Products — E-Commerce Core';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name     = trim($_POST['name'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $price    = (float)($_POST['price'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $stock    = (int)($_POST['stock'] ?? 0);

        if (!$category && $name) {
            $category = autoCategorize($name, $desc);
            $aiSource = cohereHasKey() ? 'AI' : 'local keyword engine';
            $msgExtra = " Category auto-detected as '{$category}' via {$aiSource}.";
        }

        $imageUrl = $_POST['existing_image'] ?? null;
        if (!empty($_FILES['image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $newName = uniqid('prod_') . '.' . $ext;
                $dest = __DIR__ . '/../assets/uploads/' . $newName;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                    $imageUrl = 'assets/uploads/' . $newName;
                }
            }
        }

        try {
            if ($action === 'create') {
                $pdo->prepare("INSERT INTO products (name, description, price, category, stock, image_url) VALUES (?,?,?,?,?,?)")
                    ->execute([$name, $desc, $price, $category, $stock, $imageUrl]);
                $msg = "Product '{$name}' added.";
                if (isset($msgExtra)) $msg .= $msgExtra;
            } else {
                $id = (int)$_POST['id'];
                $pdo->prepare("UPDATE products SET name=?, description=?, price=?, category=?, stock=?, image_url=? WHERE id=?")
                    ->execute([$name, $desc, $price, $category, $stock, $imageUrl, $id]);
                $msg = "Product updated.";
                if (isset($msgExtra)) $msg .= $msgExtra;
            }
        } catch (PDOException $ex) {
            $msg = "Error: " . $ex->getMessage();
        }
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM products WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = "Product deleted.";
    }
}

$products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();

$searchResults = null;
if (!empty($_GET['ai_search'])) {
    $all = $pdo->query("SELECT * FROM products")->fetchAll();
    $searchResults = rerankProducts($_GET['ai_search'], $all, 6);
}

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
                    <h2>Product & Store Management</h2>
                    <p>Manage catalog, pricing, and inventory</p>
                </div>
            </div>
            <button onclick="openCreate()" class="btn btn-primary">+ Add Product</button>
        </header>

        <div class="page-body">
            <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

            <div class="ai-banner">
                <h3>🔍 AI Semantic Search (Rerank)</h3>
                <p>Find products by describing what you want</p>
                <form method="GET" class="ai-form">
                    <input type="text" name="ai_search" value="<?= e($_GET['ai_search'] ?? '') ?>" placeholder="e.g. 'something warm for winter'">
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>
                <?php if ($searchResults !== null): ?>
                    <div class="ai-response">
                        <?php if (empty($searchResults)): ?>
                            No matches found.
                        <?php else: ?>
                            Found <?= count($searchResults) ?> result(s):
                            <?php foreach ($searchResults as $r): ?>
                                • <?= e($r['product']['name']) ?> — <?= round($r['score'] * 100) ?>% match
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr><th>Image</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <?php if ($p['image_url']): ?>
                                        <img src="<?= baseUrl($p['image_url']) ?>" style="width:44px;height:44px;border-radius:8px;object-fit:cover;">
                                    <?php else: ?>
                                        <div style="width:44px;height:44px;border-radius:8px;background:#eff6ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-weight:800;"><?= strtoupper(substr($p['name'],0,1)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><strong style="color:#111827;"><?= e($p['name']) ?></strong></td>
                                <td><span class="badge badge-blue"><?= e($p['category']) ?></span></td>
                                <td>₱<?= number_format($p['price'], 2) ?></td>
                                <td><?= (int)$p['stock'] ?></td>
                                <td>
                                    <button onclick='editProduct(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="btn btn-secondary btn-sm">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this product?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<div id="productModal" class="modal-overlay" style="display:none;">
    <div class="modal">
        <h3 id="modalTitle">Add Product</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="formId">
            <input type="hidden" name="existing_image" id="formExistingImage">
            <div class="form-group"><label class="form-label">Name *</label><input type="text" name="name" id="f_name" class="form-input" required></div>
            <div class="form-group"><label class="form-label">Description</label><textarea name="description" id="f_desc" class="form-textarea"></textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group"><label class="form-label">Price (₱) *</label><input type="number" step="0.01" name="price" id="f_price" class="form-input" required></div>
                <div class="form-group"><label class="form-label">Stock *</label><input type="number" name="stock" id="f_stock" class="form-input" required></div>
            </div>
            <div class="form-group"><label class="form-label">Category <span style="color:#9ca3af;font-weight:400;">(leave blank → AI auto-categorizes)</span></label><input type="text" name="category" id="f_cat" class="form-input" placeholder="AI will decide..."></div>
            <div class="form-group"><label class="form-label">Product Image</label><input type="file" name="image" accept="image/*" class="form-input"></div>
            <div class="modal-actions">
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreate() {
    document.getElementById('modalTitle').textContent = 'Add Product';
    document.getElementById('formAction').value = 'create';
    document.getElementById('formId').value = '';
    document.querySelector('#productModal form').reset();
    document.getElementById('formExistingImage').value = '';
    document.getElementById('productModal').style.display = 'flex';
}
function editProduct(p) {
    document.getElementById('modalTitle').textContent = 'Edit Product';
    document.getElementById('formAction').value = 'update';
    document.getElementById('formId').value = p.id;
    document.getElementById('f_name').value = p.name;
    document.getElementById('f_desc').value = p.description || '';
    document.getElementById('f_price').value = p.price;
    document.getElementById('f_stock').value = p.stock;
    document.getElementById('f_cat').value = p.category || '';
    document.getElementById('formExistingImage').value = p.image_url || '';
    document.getElementById('productModal').style.display = 'flex';
}
function closeModal() { document.getElementById('productModal').style.display = 'none'; }
</script>