<?php
session_start();
require_once 'includes/db.php';

// Check if user explicitly wants to empty the cart for testing via ?empty=1
$is_empty = isset($_GET['empty']);

// Mocking Cart Items for UI Demonstration
// In a real application, we would query the `cart` table joining with `products` table
$cart_items = [];
if (!$is_empty) {
    $stmt = $pdo->query("SELECT * FROM products LIMIT 2");
    $mock_products = $stmt->fetchAll();

    if (count($mock_products) > 0) {
        $cart_items[] = [
            'id' => $mock_products[0]['id'],
            'name' => $mock_products[0]['name'],
            'price' => $mock_products[0]['price'],
            'image' => 'https://via.placeholder.com/150x150?text=Product+1',
            'size' => 'M',
            'color' => 'Maroon',
            'quantity' => 2
        ];
        if (isset($mock_products[1])) {
            $cart_items[] = [
                'id' => $mock_products[1]['id'],
                'name' => $mock_products[1]['name'],
                'price' => $mock_products[1]['price'],
                'image' => 'https://via.placeholder.com/150x150?text=Product+2',
                'size' => 'L',
                'color' => 'Gold',
                'quantity' => 1
            ];
        }
    } else {
        $is_empty = true;
    }
}

include 'includes/header.php';
?>
<style>
/* Cart Page CSS */
.cart-page-container {
    max-width: 1200px;
    margin: 50px auto;
    padding: 0 20px;
    min-height: 50vh;
}
.cart-title {
    font-family: var(--font-heading);
    color: var(--maroon);
    font-size: 2.5rem;
    margin-bottom: 10px;
    text-align: center;
}
.cart-subtitle {
    text-align: center;
    color: #777;
    margin-bottom: 40px;
}
.cart-layout {
    display: flex;
    gap: 40px;
    align-items: flex-start;
}

/* Left: Table */
.cart-items-container {
    flex: 2;
    background: var(--white);
    border-radius: 8px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.03);
    overflow: hidden;
    border: 1px solid #f5f5f5;
}
.cart-table {
    width: 100%;
    border-collapse: collapse;
}
.cart-table th {
    background: #fdf0f5; /* light pink from brand */
    color: var(--maroon);
    text-align: left;
    padding: 18px 20px;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.9rem;
    letter-spacing: 0.5px;
}
.cart-table td {
    padding: 25px 20px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}
.cart-table tr:last-child td {
    border-bottom: none;
}

.product-cell {
    display: flex;
    align-items: center;
    gap: 20px;
}
.product-cell img {
    width: 90px;
    height: 90px;
    object-fit: cover;
    border-radius: 6px;
    background: #f9f9f9;
}
.product-info h4 {
    color: var(--maroon);
    margin-bottom: 5px;
    font-family: var(--font-heading);
    font-size: 1.2rem;
}
.product-info p {
    color: #777;
    font-size: 0.85rem;
    margin-bottom: 3px;
}

.qty-control {
    display: flex;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 100px;
    overflow: hidden;
}
.qty-btn {
    width: 30px;
    background: #fdfdfd;
    border: none;
    cursor: pointer;
    font-size: 1.1rem;
    transition: background 0.2s;
}
.qty-btn:hover { background: #eee; }
.qty-input {
    width: 40px;
    text-align: center;
    border: none;
    border-left: 1px solid #ddd;
    border-right: 1px solid #ddd;
    font-family: var(--font-body);
    font-size: 1rem;
    outline: none;
}

.price-text {
    font-weight: 600;
    color: #444;
}
.total-text {
    font-weight: 600;
    color: var(--gold);
    font-size: 1.1rem;
}

.remove-btn {
    color: #e51e25;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.3rem;
    transition: transform 0.2s, color 0.2s;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}
.remove-btn:hover {
    transform: scale(1.1);
    background: #fff0f0;
}

/* Right: Summary */
.order-summary {
    flex: 1;
    background: #fdfdfd;
    padding: 30px;
    border-radius: 8px;
    border: 1px solid #eee;
    box-shadow: 0 2px 15px rgba(0,0,0,0.03);
    position: sticky;
    top: 20px;
}
.summary-title {
    font-family: var(--font-heading);
    color: var(--maroon);
    font-size: 1.6rem;
    margin-bottom: 25px;
    border-bottom: 2px solid #f5e4ea;
    padding-bottom: 15px;
}
.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 18px;
    color: #555;
    font-size: 1.05rem;
}
.summary-row.total {
    border-top: 2px solid #f5e4ea;
    padding-top: 18px;
    margin-top: 5px;
    font-size: 1.4rem;
    color: var(--maroon);
    font-weight: 600;
}
.checkout-btn {
    width: 100%;
    padding: 16px;
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
.checkout-btn:hover {
    background: #6a1338;
}
.coupon-box {
    display: flex;
    margin-top: 35px;
}
.coupon-box input {
    flex: 1;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-right: none;
    border-radius: 4px 0 0 4px;
    outline: none;
    font-family: var(--font-body);
}
.coupon-box input:focus {
    border-color: var(--gold);
}
.coupon-box button {
    background: var(--gold);
    color: white;
    border: none;
    padding: 0 20px;
    border-radius: 0 4px 4px 0;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.3s;
}
.coupon-box button:hover {
    background: #b58532;
}

/* Empty State */
.empty-cart {
    text-align: center;
    padding: 80px 20px;
    display: <?= $is_empty ? 'block' : 'none' ?>;
}
.empty-cart-icon {
    font-size: 6rem;
    color: #eed5e0;
    margin-bottom: 25px;
}
.empty-cart h2 {
    font-family: var(--font-heading);
    color: var(--maroon);
    font-size: 2.2rem;
    margin-bottom: 15px;
}
.empty-cart p {
    color: #777;
    margin-bottom: 35px;
    font-size: 1.1rem;
}
.btn-shop {
    display: inline-block;
    background: var(--gold);
    color: white;
    padding: 14px 35px;
    text-decoration: none;
    border-radius: 4px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    transition: background 0.3s;
}
.btn-shop:hover {
    background: #b58532;
}

/* Mobile Responsive */
@media (max-width: 900px) {
    .cart-layout {
        flex-direction: column;
    }
    .cart-items-container, .order-summary {
        width: 100%;
    }
    
    /* Responsive Table Magic */
    .cart-table thead {
        display: none;
    }
    .cart-table, .cart-table tbody, .cart-table tr, .cart-table td {
        display: block;
        width: 100%;
    }
    .cart-table tr {
        margin-bottom: 20px;
        border-bottom: 5px solid #f9f9f9;
        position: relative;
    }
    .cart-table td {
        text-align: right;
        padding-left: 50%;
        position: relative;
        border-bottom: 1px solid #f5f5f5;
        padding-top: 15px;
        padding-bottom: 15px;
    }
    .cart-table td::before {
        content: attr(data-label);
        position: absolute;
        left: 20px;
        width: 45%;
        text-align: left;
        font-weight: 600;
        color: #888;
        font-size: 0.9rem;
        text-transform: uppercase;
        top: 50%;
        transform: translateY(-50%);
    }
    .cart-table td.product-col {
        padding-left: 20px;
        text-align: left;
    }
    .cart-table td.product-col::before {
        display: none;
    }
    .cart-table td:last-child {
        text-align: center;
        padding-left: 20px;
    }
    .cart-table td:last-child::before {
        display: none;
    }
    .qty-control {
        margin-left: auto;
    }
}
</style>

<main class="cart-page-container">
    
    <div id="cart-content" style="display: <?= $is_empty ? 'none' : 'block' ?>;">
        <h1 class="cart-title">Your Shopping Cart</h1>
        <p class="cart-subtitle">Review your items and proceed to checkout.</p>

        <div class="cart-layout">
            <!-- Left: Items Table -->
            <div class="cart-items-container">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Product Details</th>
                            <th style="text-align: center;">Price</th>
                            <th style="text-align: center;">Quantity</th>
                            <th style="text-align: center;">Total</th>
                            <th style="text-align: center;">Remove</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($cart_items as $item): ?>
                        <tr class="cart-row" data-price="<?= $item['price'] ?>">
                            <td class="product-col">
                                <div class="product-cell">
                                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                    <div class="product-info">
                                        <h4><?= htmlspecialchars($item['name']) ?></h4>
                                        <p>Size: <?= htmlspecialchars($item['size']) ?></p>
                                        <p>Color: <?= htmlspecialchars($item['color']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Price" class="price-text" style="text-align: center;">
                                Rs. <?= number_format($item['price']) ?>
                            </td>
                            <td data-label="Quantity" style="text-align: center;">
                                <div class="qty-control" style="margin: 0 auto;">
                                    <button class="qty-btn" onclick="changeQty(this, -1)">-</button>
                                    <input type="number" class="qty-input" value="<?= $item['quantity'] ?>" min="1" readonly>
                                    <button class="qty-btn" onclick="changeQty(this, 1)">+</button>
                                </div>
                            </td>
                            <td data-label="Total" class="total-text item-total" style="text-align: center;">
                                Rs. <?= number_format($item['price'] * $item['quantity']) ?>
                            </td>
                            <td style="text-align: center;">
                                <button class="remove-btn" onclick="removeItem(this)" title="Remove Item"><i class="fas fa-trash-alt"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Right: Order Summary -->
            <div class="order-summary">
                <h3 class="summary-title">Order Summary</h3>
                
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span id="subtotal-display">Rs. 0</span>
                </div>
                <div class="summary-row">
                    <span>Delivery Charges <br><small style="color: #999; font-size: 0.8rem;">(Free above Rs. 2000)</small></span>
                    <span id="delivery-display">Rs. 200</span>
                </div>
                
                <div class="coupon-box">
                    <input type="text" placeholder="Promo Code">
                    <button type="button">Apply</button>
                </div>

                <div class="summary-row total">
                    <span>Total Amount</span>
                    <span id="total-display">Rs. 0</span>
                </div>

                <button class="checkout-btn">Proceed to Checkout</button>
            </div>
        </div>
    </div>

    <!-- Empty State -->
    <div class="empty-cart" id="empty-cart-state">
        <div class="empty-cart-icon">
            <i class="fas fa-shopping-bag"></i>
        </div>
        <h2>Your Cart is Empty!</h2>
        <p>Looks like you haven't added anything to your cart yet. Let's find something elegant for you.</p>
        <a href="shop.php" class="btn-shop">Start Shopping</a>
    </div>

</main>

<script>
// Format currency utility
const formatCurrency = (num) => 'Rs. ' + num.toLocaleString();

function calculateTotals() {
    let subtotal = 0;
    const rows = document.querySelectorAll('.cart-row');
    
    rows.forEach(row => {
        const price = parseFloat(row.getAttribute('data-price'));
        const qty = parseInt(row.querySelector('.qty-input').value);
        const rowTotal = price * qty;
        
        row.querySelector('.item-total').textContent = formatCurrency(rowTotal);
        subtotal += rowTotal;
    });

    // Check for empty cart
    if (rows.length === 0) {
        document.getElementById('cart-content').style.display = 'none';
        document.getElementById('empty-cart-state').style.display = 'block';
        return;
    }

    const deliveryElem = document.getElementById('delivery-display');
    let deliveryFee = 0;
    
    if (subtotal >= 2000) {
        deliveryElem.textContent = 'Free';
        deliveryElem.style.color = 'var(--gold)';
        deliveryElem.style.fontWeight = '600';
    } else {
        deliveryFee = 200;
        deliveryElem.textContent = formatCurrency(deliveryFee);
        deliveryElem.style.color = '#555';
        deliveryElem.style.fontWeight = 'normal';
    }

    const total = subtotal + deliveryFee;

    document.getElementById('subtotal-display').textContent = formatCurrency(subtotal);
    document.getElementById('total-display').textContent = formatCurrency(total);
}

function changeQty(btn, delta) {
    const input = btn.parentElement.querySelector('.qty-input');
    let val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    input.value = val;
    calculateTotals();
}

function removeItem(btn) {
    // Add a fade out animation before removing
    const row = btn.closest('tr');
    row.style.transition = "opacity 0.3s";
    row.style.opacity = "0";
    
    setTimeout(() => {
        row.remove();
        calculateTotals();
    }, 300);
}

// Initial calculation on page load
document.addEventListener('DOMContentLoaded', calculateTotals);
</script>

<?php include 'includes/footer.php'; ?>
