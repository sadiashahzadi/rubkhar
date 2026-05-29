<?php
require_once 'includes/header.php';

$success = '';

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['order_id']) && isset($_POST['status'])) {
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$_POST['status'], $_POST['order_id']]);
    $success = "Order status updated successfully.";
}

// Fetch orders for list
$status_filter = $_GET['status'] ?? 'all';

$query = "SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id";
if ($status_filter != 'all') {
    $query .= " WHERE o.status = :status";
}
$query .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($query);
if ($status_filter != 'all') $stmt->execute([':status' => $status_filter]);
else $stmt->execute();
$orders = $stmt->fetchAll();

// If viewing a specific order
$view_id = $_GET['view'] ?? 0;
$order = null;
$order_items = [];
if ($view_id) {
    $stmt = $pdo->prepare("SELECT o.*, u.name, u.email, u.phone as uphone FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->execute([$view_id]);
    $order = $stmt->fetch();
    
    if ($order) {
        $stmt = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $stmt->execute([$view_id]);
        $order_items = $stmt->fetchAll();
    }
}
?>

<style>
.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}
.tab {
    padding: 10px 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #555;
    font-weight: 500;
    transition: all 0.3s;
}
.tab:hover {
    background: #fdf0f5;
    border-color: var(--maroon);
}
.tab.active {
    background: var(--maroon);
    color: white;
    border-color: var(--maroon);
}
.status-select {
    padding: 6px;
    border-radius: 4px;
    border: 1px solid #ddd;
    outline: none;
    font-family: var(--font-body);
}
</style>

<?php if ($view_id && $order): ?>
    <div style="display:flex; justify-content:space-between; margin-bottom: 20px;">
        <h3 class="section-title" style="margin:0; border:none;">Order #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></h3>
        <a href="orders.php" class="btn" style="background:#eee; color:#333;"><i class="fas fa-arrow-left"></i> Back to Orders</a>
    </div>

    <div style="display:grid; grid-template-columns:2fr 1fr; gap:25px;">
        <div class="card">
            <h4 style="margin-top:0; color:var(--maroon); border-bottom:2px solid #f5e4ea; padding-bottom:12px;"><i class="fas fa-shopping-bag"></i> Ordered Items</h4>
            <table class="admin-table" style="margin-top: 15px;">
                <thead>
                    <tr><th>Item</th><th style="text-align:center;">Qty</th><th style="text-align:right;">Price</th></tr>
                </thead>
                <tbody>
                    <?php foreach($order_items as $item): ?>
                    <tr>
                        <td style="font-weight: 500;"><?= htmlspecialchars($item['name']) ?></td>
                        <td style="text-align:center;"><?= $item['quantity'] ?></td>
                        <td style="text-align:right;">Rs. <?= number_format($item['price'] * $item['quantity']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="text-align:right; font-size:1.3rem; font-weight:600; color:var(--maroon); margin-top:25px; padding-top:15px; border-top:1px solid #eee;">
                Grand Total: Rs. <?= number_format($order['total_amount']) ?>
            </div>
        </div>
        
        <div style="display:flex; flex-direction:column; gap:20px;">
            <div class="card" style="background: #fdf0f5; border-color: #f5e4ea;">
                <h4 style="margin-top:0; color:var(--maroon); border-bottom:1px solid rgba(139,26,74,0.1); padding-bottom:10px;"><i class="fas fa-sync-alt"></i> Update Status</h4>
                <form method="POST">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <select name="status" class="form-control" style="margin-bottom:15px; border-color: var(--maroon);">
                        <option value="pending" <?= $order['status']=='pending'?'selected':'' ?>>Pending</option>
                        <option value="processing" <?= $order['status']=='processing'?'selected':'' ?>>Processing</option>
                        <option value="shipped" <?= $order['status']=='shipped'?'selected':'' ?>>Shipped</option>
                        <option value="delivered" <?= $order['status']=='delivered'?'selected':'' ?>>Delivered</option>
                        <option value="cancelled" <?= $order['status']=='cancelled'?'selected':'' ?>>Cancelled</option>
                    </select>
                    <button type="submit" class="btn btn-primary" style="width:100%;">Save Status</button>
                </form>
            </div>
            
            <div class="card">
                <h4 style="margin-top:0; color:var(--maroon); border-bottom:1px solid #eee; padding-bottom:10px;"><i class="fas fa-address-card"></i> Customer Details</h4>
                <div style="line-height: 1.6; color:#444;">
                    <p style="margin:5px 0;"><strong>Name:</strong> <?= htmlspecialchars($order['name']) ?></p>
                    <p style="margin:5px 0;"><strong>Email:</strong> <?= htmlspecialchars($order['email']) ?></p>
                    <div style="margin-top:15px; padding-top:15px; border-top:1px dashed #ddd;">
                        <strong>Shipping Data Provided:</strong><br>
                        <div style="background:#f9f9f9; padding:10px; border-radius:4px; margin-top:5px;">
                            <?= nl2br(htmlspecialchars($order['shipping_address'])) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>

    <div class="tabs">
        <a href="orders.php?status=all" class="tab <?= $status_filter=='all'?'active':'' ?>">All Orders</a>
        <a href="orders.php?status=pending" class="tab <?= $status_filter=='pending'?'active':'' ?>">Pending</a>
        <a href="orders.php?status=processing" class="tab <?= $status_filter=='processing'?'active':'' ?>">Processing</a>
        <a href="orders.php?status=shipped" class="tab <?= $status_filter=='shipped'?'active':'' ?>">Shipped</a>
        <a href="orders.php?status=delivered" class="tab <?= $status_filter=='delivered'?'active':'' ?>">Delivered</a>
        <a href="orders.php?status=cancelled" class="tab <?= $status_filter=='cancelled'?'active':'' ?>">Cancelled</a>
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
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($orders as $o): ?>
                    <tr>
                        <td><strong>#<?= str_pad($o['id'], 6, '0', STR_PAD_LEFT) ?></strong></td>
                        <td><?= htmlspecialchars($o['customer_name']) ?></td>
                        <td><?= date('d M Y, h:i A', strtotime($o['created_at'])) ?></td>
                        <td style="font-weight: 500; color:var(--maroon);">Rs. <?= number_format($o['total_amount']) ?></td>
                        <td>
                            <form method="POST" style="display:inline-flex; gap:10px; align-items:center; margin:0;">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <select name="status" class="status-select" onchange="this.form.submit()">
                                    <option value="pending" <?= $o['status']=='pending'?'selected':'' ?>>Pending</option>
                                    <option value="processing" <?= $o['status']=='processing'?'selected':'' ?>>Processing</option>
                                    <option value="shipped" <?= $o['status']=='shipped'?'selected':'' ?>>Shipped</option>
                                    <option value="delivered" <?= $o['status']=='delivered'?'selected':'' ?>>Delivered</option>
                                    <option value="cancelled" <?= $o['status']=='cancelled'?'selected':'' ?>>Cancelled</option>
                                </select>
                            </form>
                        </td>
                        <td><a href="orders.php?view=<?= $o['id'] ?>" class="btn btn-sm btn-gold"><i class="fas fa-eye"></i> View</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($orders)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 30px;">No orders found for this filter.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
