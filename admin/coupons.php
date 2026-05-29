<?php
require_once 'includes/header.php';

// Ensure coupons schema exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        discount_type ENUM('percentage', 'fixed') DEFAULT 'fixed',
        discount_value DECIMAL(10,2) NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch(PDOException $e) {}

$action = $_GET['action'] ?? 'list';
$success = '';
$error = '';

if ($action == 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    header("Location: coupons.php?success=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($action == 'add' || $action == 'edit')) {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $discount_type = $_POST['discount_type'] ?? 'fixed';
    $discount_value = $_POST['discount_value'] ?? 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    try {
        if ($action == 'add') {
            $stmt = $pdo->prepare("INSERT INTO coupons (code, discount_type, discount_value, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$code, $discount_type, $discount_value, $is_active]);
            header("Location: coupons.php?success=added");
            exit;
        } else {
            $id = $_POST['coupon_id'];
            $stmt = $pdo->prepare("UPDATE coupons SET code=?, discount_type=?, discount_value=?, is_active=? WHERE id=?");
            $stmt->execute([$code, $discount_type, $discount_value, $is_active, $id]);
            header("Location: coupons.php?success=updated");
            exit;
        }
    } catch(PDOException $e) {
        $error = "Error saving coupon. The code might already exist.";
    }
}

if (isset($_GET['success'])) {
    if ($_GET['success'] == 'deleted') $success = "Coupon deleted successfully.";
    if ($_GET['success'] == 'added') $success = "Coupon created successfully.";
    if ($_GET['success'] == 'updated') $success = "Coupon updated successfully.";
}

if ($action == 'list'):
    $stmt = $pdo->query("SELECT * FROM coupons ORDER BY id DESC");
    $coupons = $stmt->fetchAll();
?>
    <div style="display:flex; justify-content:space-between; margin-bottom: 20px;">
        <h3 class="section-title" style="margin:0; border:none;">Manage Discount Coupons</h3>
        <a href="coupons.php?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> Create New Coupon</a>
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
                        <th>Coupon Code</th>
                        <th>Discount Value</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($coupons as $c): ?>
                    <tr>
                        <td><span style="background:var(--light-pink); color:var(--maroon); padding:6px 15px; border-radius:4px; font-weight:bold; letter-spacing:1px; border:1px dashed var(--maroon);"><?= htmlspecialchars($c['code']) ?></span></td>
                        <td>
                            <?php if($c['discount_type'] == 'percentage'): ?>
                                <?= floatval($c['discount_value']) ?>% OFF
                            <?php else: ?>
                                Rs. <?= number_format($c['discount_value']) ?> OFF
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($c['is_active']): ?>
                                <span class="badge badge-delivered"><i class="fas fa-check"></i> Active</span>
                            <?php else: ?>
                                <span class="badge badge-cancelled"><i class="fas fa-times"></i> Disabled</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="coupons.php?action=edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-gold" title="Edit"><i class="fas fa-edit"></i></a>
                            <a href="coupons.php?action=delete&id=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this coupon permanently?');" title="Delete"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($coupons)): ?>
                        <tr><td colspan="4" style="text-align:center;">No coupons created yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($action == 'add' || $action == 'edit'): 
    $c = null;
    if ($action == 'edit') {
        $stmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $c = $stmt->fetch();
    }
?>
    <div class="card" style="max-width: 500px;">
        <h3 class="section-title"><?= $action == 'add' ? 'Create Discount Coupon' : 'Edit Coupon' ?></h3>
        
        <?php if ($error): ?>
            <div style="background:#fff0f0; color:#e74c3c; padding:15px; border-radius:4px; margin-bottom:20px; border:1px solid #ffcccc;">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="coupon_id" value="<?= $c['id'] ?? '' ?>">
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">Coupon Code *</label>
                <input type="text" name="code" class="form-control" required placeholder="e.g. SUMMER20" value="<?= htmlspecialchars($c['code'] ?? '') ?>" style="text-transform: uppercase;">
                <small style="color:#777;">Customers will enter this code at checkout.</small>
            </div>
            
            <div style="display:flex; gap:20px; margin-bottom: 20px;">
                <div style="flex:1;">
                    <label class="form-label">Discount Type *</label>
                    <select name="discount_type" class="form-control" required>
                        <option value="fixed" <?= (isset($c['discount_type']) && $c['discount_type'] == 'fixed') ? 'selected' : '' ?>>Fixed Amount (Rs)</option>
                        <option value="percentage" <?= (isset($c['discount_type']) && $c['discount_type'] == 'percentage') ? 'selected' : '' ?>>Percentage (%)</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label class="form-label">Discount Value *</label>
                    <input type="number" step="0.01" name="discount_value" class="form-control" required value="<?= $c['discount_value'] ?? '' ?>">
                </div>
            </div>
            
            <div style="margin-top: 15px; display:flex; align-items:center; gap:10px; background:#fdfdfd; padding:15px; border:1px solid #eee; border-radius:4px;">
                <input type="checkbox" name="is_active" id="is_active" <?= (!isset($c['is_active']) || $c['is_active']) ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:var(--maroon);">
                <label for="is_active" style="font-weight:500; cursor:pointer; color:#333; margin:0;">Enable Coupon Code</label>
            </div>
            
            <div style="margin-top: 30px; border-top:1px solid #eee; padding-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Coupon</button>
                <a href="coupons.php" class="btn" style="background:#eee; color:#333; margin-left:10px;">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
