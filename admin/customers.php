<?php
require_once 'includes/header.php';

$view_id = $_GET['view'] ?? 0;
$customer = null;
$orders = [];

if ($view_id) {
    // View Single Customer
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer'");
    $stmt->execute([$view_id]);
    $customer = $stmt->fetch();
    
    if ($customer) {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$view_id]);
        $orders = $stmt->fetchAll();
    }
} else {
    // List All Customers
    $stmt = $pdo->query("
        SELECT u.*, 
               COUNT(o.id) as total_orders, 
               SUM(o.total_amount) as total_spent 
        FROM users u 
        LEFT JOIN orders o ON u.id = o.user_id AND o.status != 'cancelled'
        WHERE u.role = 'customer' 
        GROUP BY u.id 
        ORDER BY u.created_at DESC
    ");
    $customers = $stmt->fetchAll();
}
?>

<?php if ($view_id && $customer): ?>

    <div style="display:flex; justify-content:space-between; margin-bottom: 20px;">
        <h3 class="section-title" style="margin:0; border:none;">Customer Details</h3>
        <a href="customers.php" class="btn" style="background:#eee; color:#333;"><i class="fas fa-arrow-left"></i> Back to Customers</a>
    </div>

    <div style="display:grid; grid-template-columns:1fr 2fr; gap:25px;">
        
        <!-- Profile Info -->
        <div class="card" style="align-self: flex-start;">
            <div style="text-align:center; padding-bottom: 20px; border-bottom: 1px solid #eee; margin-bottom: 20px;">
                <div style="width: 80px; height: 80px; background: var(--maroon); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-family: var(--font-heading); margin: 0 auto 15px;">
                    <?= strtoupper(substr($customer['name'], 0, 1)) ?>
                </div>
                <h3 style="margin:0; color:var(--maroon); font-family:var(--font-heading);"><?= htmlspecialchars($customer['name']) ?></h3>
                <p style="color:#777; margin:5px 0 0 0;">Customer ID: #<?= $customer['id'] ?></p>
            </div>
            <p><strong><i class="fas fa-envelope"></i> Email:</strong> <br><?= htmlspecialchars($customer['email']) ?></p>
            <?php if($customer['phone']): ?>
            <p><strong><i class="fas fa-phone-alt"></i> Phone:</strong> <br><?= htmlspecialchars($customer['phone']) ?></p>
            <?php endif; ?>
            <p><strong><i class="fas fa-calendar-alt"></i> Joined:</strong> <br><?= date('d M Y', strtotime($customer['created_at'] ?? 'now')) ?></p>
        </div>

        <!-- Order History -->
        <div class="card">
            <h3 class="section-title" style="margin-top:0;">Order History</h3>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($orders as $o): ?>
                        <tr>
                            <td><a href="orders.php?view=<?= $o['id'] ?>" style="color:var(--maroon); font-weight:bold; text-decoration:none;">#<?= str_pad($o['id'], 6, '0', STR_PAD_LEFT) ?></a></td>
                            <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                            <td>Rs. <?= number_format($o['total_amount']) ?></td>
                            <td><span class="badge badge-<?= strtolower($o['status']) ?>"><?= ucfirst($o['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($orders)): ?>
                        <tr><td colspan="4" style="text-align:center;">This customer has not placed any orders yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php else: ?>

    <h3 class="section-title">Customers Database</h3>
    <div class="card">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th style="text-align:center;">Total Orders</th>
                        <th style="text-align:right;">Total Spent</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($customers as $c): ?>
                    <tr>
                        <td>#<?= $c['id'] ?></td>
                        <td style="font-weight: 500; color: var(--maroon);"><?= htmlspecialchars($c['name']) ?></td>
                        <td><?= htmlspecialchars($c['email']) ?></td>
                        <td><?= htmlspecialchars($c['phone'] ?? '-') ?></td>
                        <td style="text-align:center;"><span class="badge" style="background:#eee; color:#333;"><?= $c['total_orders'] ?></span></td>
                        <td style="text-align:right; font-weight:600; color:var(--gold);">Rs. <?= number_format($c['total_spent'] ?? 0) ?></td>
                        <td style="text-align:center;">
                            <a href="customers.php?view=<?= $c['id'] ?>" class="btn btn-sm btn-gold"><i class="fas fa-eye"></i> View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($customers)): ?>
                        <tr><td colspan="7" style="text-align:center;">No customers found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
