<?php
require_once 'includes/header.php';

$action = $_GET['action'] ?? 'list';
$success = '';

if ($action == 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    header("Location: categories.php?success=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($action == 'add' || $action == 'edit')) {
    $name = $_POST['name'] ?? '';
    $image_url = $_POST['current_image'] ?? '';
    
    if (!is_dir('../assets/images/categories')) {
        mkdir('../assets/images/categories', 0777, true);
    }
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $filename = time() . '_' . basename($_FILES['image']['name']);
        $target = '../assets/images/categories/' . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $image_url = 'assets/images/categories/' . $filename;
        }
    }
    
    if ($action == 'add') {
        $stmt = $pdo->prepare("INSERT INTO categories (name, image_url) VALUES (?, ?)");
        $stmt->execute([$name, $image_url]);
        header("Location: categories.php?success=added");
        exit;
    } else {
        $id = $_POST['category_id'];
        $stmt = $pdo->prepare("UPDATE categories SET name=?, image_url=? WHERE id=?");
        $stmt->execute([$name, $image_url, $id]);
        header("Location: categories.php?success=updated");
        exit;
    }
}

if (isset($_GET['success'])) {
    if ($_GET['success'] == 'deleted') $success = "Category deleted successfully.";
    if ($_GET['success'] == 'added') $success = "Category added successfully.";
    if ($_GET['success'] == 'updated') $success = "Category updated successfully.";
}

if ($action == 'list'):
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();
?>
    <div style="display:flex; justify-content:space-between; margin-bottom: 20px;">
        <h3 class="section-title" style="margin:0; border:none;">Manage Categories</h3>
        <a href="categories.php?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Category</a>
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
                        <th>Category Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($categories as $c): ?>
                    <tr>
                        <td>
                            <?php if($c['image_url']): ?>
                                <img src="../<?= htmlspecialchars($c['image_url']) ?>" alt="Category" width="50" height="50" style="object-fit:cover; border-radius:4px;">
                            <?php else: ?>
                                <div style="width:50px; height:50px; background:#eee; border-radius:4px; display:flex; align-items:center; justify-content:center; color:#999;"><i class="fas fa-image"></i></div>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: 500; color: var(--maroon);"><?= htmlspecialchars($c['name']) ?></td>
                        <td>
                            <a href="categories.php?action=edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-gold" title="Edit"><i class="fas fa-edit"></i></a>
                            <a href="categories.php?action=delete&id=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this category?');" title="Delete"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($action == 'add' || $action == 'edit'): 
    $c = null;
    if ($action == 'edit') {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $c = $stmt->fetch();
    }
?>
    <div class="card" style="max-width: 600px;">
        <h3 class="section-title"><?= $action == 'add' ? 'Add New Category' : 'Edit Category' ?></h3>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="category_id" value="<?= $c['id'] ?? '' ?>">
            <input type="hidden" name="current_image" value="<?= $c['image_url'] ?? '' ?>">
            
            <div class="form-group">
                <label class="form-label">Category Name *</label>
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($c['name'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Category Image (Banner/Thumbnail)</label>
                <input type="file" name="image" class="form-control" accept="image/*">
                <?php if(isset($c['image_url']) && $c['image_url']): ?>
                    <div style="margin-top: 10px;">
                        <small style="color:#777;">Current Image:</small><br>
                        <img src="../<?= $c['image_url'] ?>" height="60" style="border-radius:4px; margin-top:5px;">
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 30px; border-top:1px solid #eee; padding-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Category</button>
                <a href="categories.php" class="btn" style="background:#eee; color:#333; margin-left:10px;">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
