<?php
session_start();
require_once 'includes/db.php';

// Get order ID from session or GET param for debugging
$order_id = isset($_SESSION['last_order_id']) ? $_SESSION['last_order_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if (!$order_id) {
    header("Location: shop.php");
    exit;
}

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id");
$stmt->execute([':id' => $order_id]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: shop.php");
    exit;
}

// Fetch order items
$stmtItem = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = :order_id");
$stmtItem->execute([':order_id' => $order_id]);
$order_items = $stmtItem->fetchAll();

// Parse the address field created during checkout
$address_lines = explode("\n", $order['shipping_address']);
$parsed = [
    'name' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'payment' => 'COD',
    'notes' => ''
];

foreach ($address_lines as $line) {
    if (strpos($line, 'Name: ') === 0) $parsed['name'] = str_replace('Name: ', '', $line);
    elseif (strpos($line, 'Phone: ') === 0) $parsed['phone'] = str_replace('Phone: ', '', $line);
    elseif (strpos($line, 'Email: ') === 0) $parsed['email'] = str_replace('Email: ', '', $line);
    elseif (strpos($line, 'Address: ') === 0) $parsed['address'] = str_replace('Address: ', '', $line);
    elseif (strpos($line, 'Payment: ') === 0) $parsed['payment'] = str_replace('Payment: ', '', $line);
    elseif (strpos($line, 'Notes: ') === 0) $parsed['notes'] = str_replace('Notes: ', '', $line);
}

// Format payment
$payment_display = 'Cash on Delivery (COD)';
if ($parsed['payment'] === 'jazzcash') $payment_display = 'JazzCash Mobile Account';
if ($parsed['payment'] === 'easypaisa') $payment_display = 'EasyPaisa';

// Calculate subtotal from items (assuming total_amount includes delivery)
$subtotal = 0;
foreach ($order_items as $item) {
    $subtotal += ($item['price'] * $item['quantity']);
}
$delivery_fee = $order['total_amount'] - $subtotal;

include 'includes/header.php';
?>
<style>
.confirmation-container {
    max-width: 800px;
    margin: 60px auto;
    padding: 0 20px;
}
.success-header {
    text-align: center;
    margin-bottom: 40px;
}
.check-icon {
    font-size: 5rem;
    color: #3bb54a;
    margin-bottom: 20px;
    animation: popIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes popIn {
    0% { transform: scale(0); opacity: 0; }
    80% { transform: scale(1.1); opacity: 1; }
    100% { transform: scale(1); opacity: 1; }
}
.success-title {
    font-family: var(--font-heading);
    color: var(--maroon);
    font-size: 2.5rem;
    margin-bottom: 15px;
}
.order-number {
    font-size: 1.1rem;
    color: #444;
    background: #f5f5f5;
    display: inline-block;
    padding: 10px 25px;
    border-radius: 30px;
    margin-bottom: 15px;
    font-weight: 500;
    letter-spacing: 1px;
}
.est-delivery {
    color: var(--gold);
    font-weight: 600;
    font-size: 1.1rem;
}

/* Order Details Grid */
.details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 40px;
}
.detail-card {
    background: var(--white);
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
}
.detail-card h3 {
    font-family: var(--font-heading);
    color: var(--maroon);
    font-size: 1.3rem;
    margin-bottom: 15px;
    border-bottom: 1px solid #f5e4ea;
    padding-bottom: 10px;
}
.detail-card p {
    color: #555;
    margin-bottom: 8px;
    line-height: 1.5;
}
.detail-card strong {
    color: #333;
}

/* Order Summary Table */
.summary-table-wrapper {
    background: var(--white);
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    margin-bottom: 40px;
}
.summary-table-wrapper h3 {
    font-family: var(--font-heading);
    color: var(--maroon);
    font-size: 1.5rem;
    margin-bottom: 20px;
}
.order-table {
    width: 100%;
    border-collapse: collapse;
}
.order-table th {
    text-align: left;
    padding: 12px 15px;
    background: #fdf0f5;
    color: var(--maroon);
    font-weight: 600;
}
.order-table td {
    padding: 15px;
    border-bottom: 1px solid #eee;
    color: #444;
}
.order-table tr:last-child td {
    border-bottom: none;
}
.totals-area {
    margin-top: 20px;
    border-top: 2px solid #f5e4ea;
    padding-top: 20px;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}
.total-row {
    display: flex;
    justify-content: space-between;
    width: 320px;
    margin-bottom: 10px;
    color: #555;
    font-size: 1.05rem;
}
.total-row.grand-total {
    font-size: 1.4rem;
    color: var(--maroon);
    font-weight: 600;
    border-top: 1px solid #eee;
    padding-top: 15px;
    margin-top: 5px;
}

/* Actions */
.action-buttons {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 30px;
    margin-bottom: 50px;
}
.btn-track {
    padding: 16px 35px;
    background: var(--gold);
    color: white;
    border-radius: 4px;
    text-decoration: none;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    transition: background 0.3s;
    box-shadow: 0 4px 10px rgba(201, 150, 62, 0.2);
}
.btn-track:hover { background: #b58532; }
.btn-shop {
    padding: 14px 35px;
    background: transparent;
    color: var(--maroon);
    border: 2px solid var(--maroon);
    border-radius: 4px;
    text-decoration: none;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    transition: all 0.3s;
}
.btn-shop:hover {
    background: var(--maroon);
    color: white;
}

@media (max-width: 768px) {
    .details-grid { grid-template-columns: 1fr; }
    .action-buttons { flex-direction: column; }
    .btn-track, .btn-shop { text-align: center; }
    .total-row { width: 100%; }
}
</style>

<main class="confirmation-container">
    
    <div class="success-header">
        <i class="fas fa-check-circle check-icon"></i>
        <h1 class="success-title">Shukriya! Aapka order place ho gaya</h1>
        <div class="order-number">Order ID: #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></div>
        <p class="est-delivery"><i class="fas fa-truck"></i> Estimated Delivery: 3-5 Business Days</p>
    </div>

    <div class="details-grid">
        <div class="detail-card">
            <h3><i class="fas fa-map-marker-alt"></i> Delivery Address</h3>
            <p><strong><?= htmlspecialchars($parsed['name']) ?></strong></p>
            <p><?= htmlspecialchars($parsed['address']) ?></p>
            <p style="margin-top: 10px;"><i class="fas fa-phone-alt"></i> <?= htmlspecialchars($parsed['phone']) ?></p>
            <?php if($parsed['email']): ?>
                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($parsed['email']) ?></p>
            <?php endif; ?>
        </div>
        
        <div class="detail-card">
            <h3><i class="fas fa-money-check-alt"></i> Payment Details</h3>
            <p style="font-size: 1.15rem; font-weight: 600; color: #333; margin-top: 10px;">
                <?= htmlspecialchars($payment_display) ?>
            </p>
            <p style="margin-top: 12px; font-size: 0.95rem; color: #666;">
                <?php if($parsed['payment'] === 'cod'): ?>
                    Please keep the exact change ready for the delivery rider to ensure a smooth handover.
                <?php else: ?>
                    Status: <span style="color: var(--gold); font-weight: 600;"><i class="fas fa-hourglass-half"></i> Pending Verification</span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="summary-table-wrapper">
        <h3>Order Summary</h3>
        <div style="overflow-x: auto;">
            <table class="order-table">
                <thead>
                    <tr>
                        <th>Item Description</th>
                        <th style="text-align: center; width: 100px;">Qty</th>
                        <th style="text-align: right; width: 150px;">Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($order_items as $item): ?>
                    <tr>
                        <td style="font-weight: 500; color: var(--maroon);"><?= htmlspecialchars($item['name']) ?></td>
                        <td style="text-align: center;"><?= $item['quantity'] ?></td>
                        <td style="text-align: right; font-weight: 500;">Rs. <?= number_format($item['price'] * $item['quantity']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="totals-area">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>Rs. <?= number_format($subtotal) ?></span>
            </div>
            <div class="total-row">
                <span>Delivery Charges:</span>
                <span><?= $delivery_fee > 0 ? 'Rs. ' . number_format($delivery_fee) : 'Free' ?></span>
            </div>
            <div class="total-row grand-total">
                <span>Total Amount:</span>
                <span>Rs. <?= number_format($order['total_amount']) ?></span>
            </div>
        </div>
    </div>

    <div class="action-buttons">
        <a href="#" class="btn-track">Track Your Order</a>
        <a href="shop.php" class="btn-shop">Continue Shopping</a>
    </div>

</main>

<?php include 'includes/footer.php'; ?>
