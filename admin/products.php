<?php
require_once 'includes/header.php';

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle Delete
if ($action == 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    header("Location: products.php?success=deleted");
    exit;
}

// Ensure columns exist for advanced features
try {
    $pdo->exec("ALTER TABLE products ADD COLUMN sizes VARCHAR(255) NULL AFTER stock");
    $pdo->exec("ALTER TABLE products ADD COLUMN colors VARCHAR(255) NULL AFTER sizes");
    $pdo->exec("ALTER TABLE products ADD COLUMN featured TINYINT(1) DEFAULT 0 AFTER colors");
} catch(PDOException $e) {}

// Handle Form Submit (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($action == 'add' || $action == 'edit')) {
    $name = $_POST['name'] ?? '';
    $category_id = $_POST['category_id'] ?? 0;
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $sale_price = !empty($_POST['sale_price']) ? $_POST['sale_price'] : null;
    $stock = $_POST['stock'] ?? 0;
    $sizes = $_POST['sizes'] ?? '';
    $colors = $_POST['colors'] ?? '';
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    // Image Upload Logic
    if (!is_dir('../assets/images/products')) {
        mkdir('../assets/images/products', 0777, true);
    }
    
    $image_url = $_POST['current_image'] ?? '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $filename = time() . '_' . basename($_FILES['image']['name']);
        $target = '../assets/images/products/' . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $image_url = 'assets/images/products/' . $filename;
        }
    }
    
    if ($action == 'add') {
        $stmt = $pdo->prepare("INSERT INTO products (category_id, name, description, price, sale_price, image_url, stock, sizes, colors, featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$category_id, $name, $description, $price, $sale_price, $image_url, $stock, $sizes, $colors, $featured]);
        header("Location: products.php?success=added");
        exit;
    } else {
        $id = $_POST['product_id'];
        $stmt = $pdo->prepare("UPDATE products SET category_id=?, name=?, description=?, price=?, sale_price=?, image_url=?, stock=?, sizes=?, colors=?, featured=? WHERE id=?");
        $stmt->execute([$category_id, $name, $description, $price, $sale_price, $image_url, $stock, $sizes, $colors, $featured, $id]);
        header("Location: products.php?success=updated");
        exit;
    }
}

if (isset($_GET['success'])) {
    if ($_GET['success'] == 'deleted') $success = "Product deleted successfully.";
    if ($_GET['success'] == 'added') $success = "Product added successfully.";
    if ($_GET['success'] == 'updated') $success = "Product updated successfully.";
}

// Fetch Categories for form
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmt->fetchAll();

if ($action == 'list'):
    $stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
    $products = $stmt->fetchAll();
?>
    <div style="display:flex; justify-content:space-between; margin-bottom: 20px;">
        <h3 class="section-title" style="margin:0; border:none;">Manage Products</h3>
        <a href="products.php?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Product</a>
    </div>

    <?php if ($success): ?>
        <div style="background:#e8f8f5; color:#2ecc71; padding:15px; border-radius:4px; margin-bottom:20px; border:1px solid #a3e4d7;">
            <i class="fas fa-check-circle"></i> <?= $success ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Featured</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($products as $p): ?>
                    <tr>
                        <td>
                            <?php if($p['image_url']): ?>
                                <img src="../<?= htmlspecialchars($p['image_url']) ?>" alt="Product" width="50" height="50" style="object-fit:cover; border-radius:4px;">
                            <?php else: ?>
                                <div style="width:50px; height:50px; background:#eee; border-radius:4px; display:flex; align-items:center; justify-content:center; color:#999;"><i class="fas fa-image"></i></div>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: 500; color: var(--maroon);"><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= htmlspecialchars($p['category_name']) ?></td>
                        <td>
                            Rs. <?= number_format($p['price']) ?>
                            <?php if($p['sale_price']): ?>
                                <br><small style="color:var(--gold);">Sale: Rs. <?= number_format($p['sale_price']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($p['stock'] < 5): ?>
                                <span style="color:#e74c3c; font-weight:bold;"><?= $p['stock'] ?> (Low)</span>
                            <?php else: ?>
                                <?= $p['stock'] ?>
                            <?php endif; ?>
                        </td>
                        <td><?= isset($p['featured']) && $p['featured'] ? '<span class="badge badge-delivered">Yes</span>' : '-' ?></td>
                        <td>
                            <a href="products.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-sm btn-gold" title="Edit"><i class="fas fa-edit"></i></a>
                            <a href="products.php?action=delete&id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this product?');" title="Delete"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($action == 'add' || $action == 'edit'): 
    $p = null;
    if ($action == 'edit') {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $p = $stmt->fetch();
    }
?>
    <div class="card">
        <h3 class="section-title"><?= $action == 'add' ? 'Add New Product' : 'Edit Product' ?></h3>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="product_id" value="<?= $p['id'] ?? '' ?>">
            <input type="hidden" name="current_image" value="<?= $p['image_url'] ?? '' ?>">
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                <div>
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($p['name'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">Category *</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">Select Category</option>
                        <?php foreach($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (isset($p['category_id']) && $p['category_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Regular Price (Rs) *</label>
                    <input type="number" name="price" class="form-control" required value="<?= $p['price'] ?? '' ?>">
                </div>
                <div>
                    <label class="form-label">Sale Price (Rs) (Optional)</label>
                    <input type="number" name="sale_price" class="form-control" value="<?= $p['sale_price'] ?? '' ?>">
                </div>
                <div>
                    <label class="form-label">Stock Quantity *</label>
                    <input type="number" name="stock" class="form-control" required value="<?= $p['stock'] ?? '10' ?>">
                </div>
                <div>
                    <label class="form-label">Product Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <?php if(isset($p['image_url']) && $p['image_url']): ?>
                        <small style="color:#777;">Current: <img src="../<?= $p['image_url'] ?>" height="30" style="vertical-align:middle;"></small>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="form-label">Sizes (Comma separated)</label>
                    <input type="text" name="sizes" class="form-control" placeholder="XS, S, M, L, XL" value="<?= htmlspecialchars($p['sizes'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">Colors (Comma separated)</label>
                    <input type="text" name="colors" class="form-control" placeholder="Maroon, Gold, Black" value="<?= htmlspecialchars($p['colors'] ?? '') ?>">
                </div>
            </div>
            
            <div style="margin-top: 15px;">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="5" required><?= htmlspecialchars($p['description'] ?? '') ?></textarea>
            </div>
            
            <div style="margin-top: 15px; display:flex; align-items:center; gap:10px;">
                <input type="checkbox" name="featured" id="featured" <?= (isset($p['featured']) && $p['featured']) ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:var(--maroon);">
                <label for="featured" style="font-weight:500; cursor:pointer; color:#444;">Mark as Featured Product (Shows on Homepage)</label>
            </div>
            
            <div style="margin-top: 30px; border-top:1px solid #eee; padding-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Product</button>
                <a href="products.php" class="btn" style="background:#eee; color:#333; margin-left:10px;">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
