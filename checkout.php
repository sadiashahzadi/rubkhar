<?php
session_start();
require_once 'includes/db.php';

// Ensure a guest user exists to satisfy foreign key constraint in `orders`
try {
    $pdo->exec("INSERT IGNORE INTO users (id, name, email, password) VALUES (1, 'Guest User', 'guest@rubkhar.com', 'none')");
} catch(PDOException $e) {
    // Ignore if already exists or fails
}

// Mocking Cart Items for UI
$stmt = $pdo->query("SELECT * FROM products LIMIT 2");
$cart_items = $stmt->fetchAll();

$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price']; // Mock quantity 1
}

$success = isset($_GET['success']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$success) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $delivery = trim($_POST['delivery'] ?? 'standard');
    $payment = trim($_POST['payment'] ?? 'cod');
    $notes = trim($_POST['notes'] ?? '');

    if ($name && $phone && $address && $city && $province) {
        $full_address = "Name: $name\nPhone: $phone\nEmail: $email\nAddress: $address, $city, $province\nPayment: $payment\nNotes: $notes";
        
        $delivery_fee = ($delivery === 'express') ? 350 : (($subtotal >= 2000) ? 0 : 200);
        $total_amount = $subtotal + $delivery_fee;

        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status, shipping_address) VALUES (1, ?, 'pending', ?)");
            $stmt->execute([$total_amount, $full_address]);
            $order_id = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            foreach ($cart_items as $item) {
                $stmtItem->execute([$order_id, $item['id'], 1, $item['price']]);
            }

            $pdo->commit();
            $_SESSION['last_order_id'] = $order_id;
            header("Location: order-confirmation.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to place order: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}

include 'includes/header.php';

// Define standard delivery text based on subtotal
$standard_delivery_fee = ($subtotal >= 2000) ? 0 : 200;
$standard_delivery_text = ($standard_delivery_fee === 0) ? 'Free' : 'Rs. 200';
?>
<style>
/* Checkout CSS */
.checkout-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
    min-height: 60vh;
}

/* Progress Bar */
.progress-bar {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 50px;
    font-family: var(--font-heading);
}
.step {
    display: flex;
    align-items: center;
    color: #999;
}
.step.active {
    color: var(--maroon);
    font-weight: 600;
}
.step.completed {
    color: var(--gold);
}
.step-number {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #eee;
    display: flex;
    justify-content: center;
    align-items: center;
    margin-right: 10px;
    font-size: 0.95rem;
    font-weight: 600;
}
.step.active .step-number {
    background: var(--maroon);
    color: white;
}
.step.completed .step-number {
    background: var(--gold);
    color: white;
}
.step-divider {
    height: 2px;
    width: 60px;
    background: #eee;
    margin: 0 20px;
}

.checkout-layout {
    display: flex;
    gap: 40px;
    align-items: flex-start;
}

/* Left: Form */
.checkout-form-area {
    flex: 2;
    background: var(--white);
    padding: 35px;
    border-radius: 8px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.03);
    border: 1px solid #f5f5f5;
}
.form-section-title {
    font-family: var(--font-heading);
    color: var(--maroon);
    font-size: 1.5rem;
    margin-bottom: 25px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f5e4ea;
}
.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}
.form-group {
    flex: 1;
    display: flex;
    flex-direction: column;
}
.form-group label {
    margin-bottom: 8px;
    font-size: 0.95rem;
    color: #444;
    font-weight: 500;
}
.form-group label span {
    color: #e51e25;
}
.form-control {
    padding: 14px 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: var(--font-body);
    font-size: 0.95rem;
    outline: none;
    transition: border-color 0.3s;
}
.form-control:focus {
    border-color: var(--gold);
}
textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

/* Radio Buttons */
.radio-group {
    border: 1px solid #eee;
    border-radius: 4px;
    padding: 15px 20px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    cursor: pointer;
    transition: all 0.2s;
}
.radio-group:hover, .radio-group.selected {
    background: #fdf0f5;
    border-color: var(--maroon);
}
.radio-group input[type="radio"] {
    margin-right: 20px;
    accent-color: var(--maroon);
    width: 20px;
    height: 20px;
}
.radio-content {
    flex: 1;
}
.radio-title {
    font-weight: 600;
    color: #333;
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
}
.radio-desc {
    font-size: 0.85rem;
    color: #777;
}

/* Right: Summary */
.checkout-summary {
    flex: 1;
    background: #fdfdfd;
    padding: 30px;
    border-radius: 8px;
    border: 1px solid #eee;
    box-shadow: 0 2px 15px rgba(0,0,0,0.03);
    position: sticky;
    top: 20px;
}
.summary-item {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    align-items: center;
}
.summary-item img {
    width: 65px;
    height: 65px;
    object-fit: cover;
    border-radius: 4px;
    background: #f5f5f5;
}
.summary-item-info {
    flex: 1;
}
.summary-item-title {
    font-weight: 600;
    color: #333;
    font-size: 0.95rem;
    margin-bottom: 4px;
}
.summary-item-price {
    color: var(--gold);
    font-size: 0.95rem;
    font-weight: 500;
}
.summary-totals {
    border-top: 1px solid #eee;
    padding-top: 20px;
    margin-top: 25px;
}
.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    color: #555;
    font-size: 0.95rem;
}
.summary-row.total {
    border-top: 2px solid #f5e4ea;
    padding-top: 15px;
    margin-top: 10px;
    font-size: 1.35rem;
    color: var(--maroon);
    font-weight: 600;
}
.btn-place-order {
    width: 100%;
    padding: 18px;
    background: var(--maroon);
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 1.1rem;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 1px;
    cursor: pointer;
    margin-top: 25px;
    transition: background 0.3s;
    box-shadow: 0 4px 10px rgba(139, 26, 74, 0.2);
}
.btn-place-order:hover {
    background: #6a1338;
}

/* Success Message */
.success-container {
    text-align: center;
    padding: 80px 20px;
}
.success-container i {
    font-size: 5rem;
    color: #3bb54a;
    margin-bottom: 25px;
}
.success-container h2 {
    font-family: var(--font-heading);
    color: var(--maroon);
    font-size: 2.8rem;
    margin-bottom: 15px;
}
.success-container p {
    color: #555;
    font-size: 1.1rem;
    margin-bottom: 35px;
}
.btn-home {
    display: inline-block;
    padding: 14px 35px;
    background: var(--gold);
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    transition: background 0.3s;
}
.btn-home:hover {
    background: #b58532;
}

/* Error Alert */
.error-alert {
    background: #fff0f0;
    color: #e51e25;
    padding: 15px 20px;
    border-radius: 4px;
    border: 1px solid #ffcccc;
    margin-bottom: 30px;
    font-weight: 500;
}

@media (max-width: 900px) {
    .checkout-layout {
        flex-direction: column-reverse;
    }
    .checkout-form-area, .checkout-summary {
        width: 100%;
    }
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .step-divider {
        width: 30px;
        margin: 0 10px;
    }
}
</style>

<main class="checkout-container">

    <?php if ($success): ?>
        <div class="progress-bar">
            <div class="step completed"><div class="step-number"><i class="fas fa-check"></i></div> Cart</div>
            <div class="step-divider"></div>
            <div class="step completed"><div class="step-number"><i class="fas fa-check"></i></div> Checkout</div>
            <div class="step-divider"></div>
            <div class="step active"><div class="step-number">3</div> Done</div>
        </div>

        <div class="success-container">
            <i class="fas fa-check-circle"></i>
            <h2>Thank You!</h2>
            <p>Your order has been placed successfully. We've sent a confirmation email to you.</p>
            <a href="shop.php" class="btn-home">Continue Shopping</a>
        </div>

    <?php else: ?>

        <div class="progress-bar">
            <div class="step completed"><div class="step-number"><i class="fas fa-check"></i></div> Cart</div>
            <div class="step-divider"></div>
            <div class="step active"><div class="step-number">2</div> Checkout</div>
            <div class="step-divider"></div>
            <div class="step"><div class="step-number">3</div> Done</div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="checkout-form" class="checkout-layout">
            
            <!-- Left Form Area -->
            <div class="checkout-form-area">
                
                <h3 class="form-section-title">1. Shipping Details</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name <span>*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="Jane Doe">
                    </div>
                    <div class="form-group">
                        <label>Phone Number <span>*</span></label>
                        <input type="tel" name="phone" class="form-control" required placeholder="03xx xxxxxxx">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="Optional for order updates">
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Street Address <span>*</span></label>
                    <input type="text" name="address" class="form-control" required placeholder="House number, Street, Area">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>City <span>*</span></label>
                        <select name="city" class="form-control" required>
                            <option value="">Select City</option>
                            <option value="Karachi">Karachi</option>
                            <option value="Lahore">Lahore</option>
                            <option value="Islamabad">Islamabad</option>
                            <option value="Rawalpindi">Rawalpindi</option>
                            <option value="Faisalabad">Faisalabad</option>
                            <option value="Multan">Multan</option>
                            <option value="Peshawar">Peshawar</option>
                            <option value="Quetta">Quetta</option>
                            <option value="Sialkot">Sialkot</option>
                            <option value="Gujranwala">Gujranwala</option>
                            <option value="Hyderabad">Hyderabad</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Province <span>*</span></label>
                        <select name="province" class="form-control" required>
                            <option value="">Select Province</option>
                            <option value="Punjab">Punjab</option>
                            <option value="Sindh">Sindh</option>
                            <option value="KPK">Khyber Pakhtunkhwa</option>
                            <option value="Balochistan">Balochistan</option>
                            <option value="GB">Gilgit-Baltistan</option>
                            <option value="AJK">AJK</option>
                        </select>
                    </div>
                </div>

                <h3 class="form-section-title" style="margin-top: 40px;">2. Delivery Method</h3>
                
                <label class="radio-group selected" id="delivery-standard">
                    <input type="radio" name="delivery" value="standard" checked onclick="updateDelivery('standard')">
                    <div class="radio-content">
                        <span class="radio-title">Standard Delivery <span><?= $standard_delivery_text ?></span></span>
                        <span class="radio-desc">2-4 business days across Pakistan.</span>
                    </div>
                </label>

                <label class="radio-group" id="delivery-express">
                    <input type="radio" name="delivery" value="express" onclick="updateDelivery('express')">
                    <div class="radio-content">
                        <span class="radio-title">Express Delivery <span>Rs. 350</span></span>
                        <span class="radio-desc">1-2 business days guaranteed.</span>
                    </div>
                </label>

                <h3 class="form-section-title" style="margin-top: 40px;">3. Payment Method</h3>
                
                <label class="radio-group selected" onclick="highlightRadio(this, 'payment')">
                    <input type="radio" name="payment" value="cod" checked>
                    <div class="radio-content">
                        <span class="radio-title">Cash on Delivery (COD)</span>
                        <span class="radio-desc">Pay directly to the rider upon receiving your order.</span>
                    </div>
                </label>
                
                <label class="radio-group" onclick="highlightRadio(this, 'payment')">
                    <input type="radio" name="payment" value="jazzcash">
                    <div class="radio-content">
                        <span class="radio-title">JazzCash Mobile Account</span>
                        <span class="radio-desc">You will be redirected to JazzCash portal securely.</span>
                    </div>
                </label>
                
                <label class="radio-group" onclick="highlightRadio(this, 'payment')">
                    <input type="radio" name="payment" value="easypaisa">
                    <div class="radio-content">
                        <span class="radio-title">EasyPaisa</span>
                        <span class="radio-desc">Pay instantly using your EasyPaisa app.</span>
                    </div>
                </label>

                <div class="form-group" style="margin-top: 30px;">
                    <label>Order Notes (Optional)</label>
                    <textarea name="notes" class="form-control" placeholder="Any special instructions for delivery?"></textarea>
                </div>

            </div>

            <!-- Right Summary -->
            <div class="checkout-summary">
                <h3 style="font-family: var(--font-heading); color: var(--maroon); margin-bottom: 20px;">Your Order</h3>
                
                <div style="max-height: 300px; overflow-y: auto; padding-right: 10px; margin-bottom: 20px;">
                    <?php foreach ($cart_items as $item): ?>
                    <div class="summary-item">
                        <img src="https://via.placeholder.com/100x100?text=Item" alt="<?= htmlspecialchars($item['name']) ?>">
                        <div class="summary-item-info">
                            <div class="summary-item-title"><?= htmlspecialchars($item['name']) ?></div>
                            <div style="font-size: 0.85rem; color: #777;">Qty: 1</div>
                            <div class="summary-item-price">Rs. <?= number_format($item['price']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="summary-totals">
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span>Rs. <?= number_format($subtotal) ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Delivery</span>
                        <span id="summary-delivery"><?= $standard_delivery_text ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total</span>
                        <span id="summary-total">Rs. <?= number_format($subtotal + $standard_delivery_fee) ?></span>
                    </div>
                </div>

                <button type="submit" class="btn-place-order" id="submit-btn"><i class="fas fa-lock"></i> Place Order</button>
            </div>
            
        </form>

    <?php endif; ?>
</main>

<script>
// Data from PHP for JS calculations
const subtotal = <?= $subtotal ?>;
const standardFee = <?= $standard_delivery_fee ?>;
const expressFee = 350;

function updateDelivery(type) {
    const deliverySpan = document.getElementById('summary-delivery');
    const totalSpan = document.getElementById('summary-total');
    
    // Highlight UI
    document.getElementById('delivery-standard').classList.remove('selected');
    document.getElementById('delivery-express').classList.remove('selected');
    document.getElementById('delivery-' + type).classList.add('selected');

    // Update totals
    let fee = 0;
    if (type === 'standard') {
        fee = standardFee;
        deliverySpan.textContent = (fee === 0) ? 'Free' : ('Rs. ' + fee);
    } else {
        fee = expressFee;
        deliverySpan.textContent = 'Rs. ' + fee;
    }
    
    totalSpan.textContent = 'Rs. ' + (subtotal + fee).toLocaleString();
}

function highlightRadio(labelElement, groupName) {
    // Find all labels for this radio group
    const inputs = document.querySelectorAll(`input[name="${groupName}"]`);
    inputs.forEach(input => {
        input.closest('.radio-group').classList.remove('selected');
    });
    labelElement.classList.add('selected');
}

// Form Validation Before Submit
document.getElementById('checkout-form')?.addEventListener('submit', function(e) {
    const btn = document.getElementById('submit-btn');
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Processing...';
    btn.style.opacity = '0.8';
    btn.disabled = true;
    
    // Allow form to submit naturally
});
</script>

<?php include 'includes/footer.php'; ?>
