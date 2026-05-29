<?php
session_start();
require_once 'includes/db.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_to'] = 'my-account.php';
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';
$active_tab = isset($_POST['tab']) ? $_POST['tab'] : 'orders';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $active_tab = 'profile';
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
            if ($stmt->execute([$name, $email, $phone, $user_id])) {
                $_SESSION['user_name'] = $name;
                $success_msg = "Profile updated successfully.";
            }
        } catch(PDOException $e) {
            $error_msg = "Failed to update profile. Email might be in use.";
        }
    }
    elseif ($action === 'change_password') {
        $active_tab = 'password';
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if ($new !== $confirm) {
            $error_msg = "New passwords do not match.";
        } elseif (strlen($new) < 6) {
            $error_msg = "Password must be at least 6 characters.";
        } else {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $hash = $stmt->fetchColumn();
            
            if (password_verify($current, $hash)) {
                $new_hash = password_hash($new, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$new_hash, $user_id]);
                $success_msg = "Password changed successfully.";
            } else {
                $error_msg = "Current password is incorrect.";
            }
        }
    }
    elseif ($action === 'remove_wishlist') {
        $active_tab = 'wishlist';
        $wish_id = $_POST['wishlist_id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE id = ? AND user_id = ?");
        $stmt->execute([$wish_id, $user_id]);
        $success_msg = "Item removed from your wishlist.";
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Fetch Latest User Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch Orders
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();

// Pre-fetch Order Items for the Modal
$order_items = [];
if (count($orders) > 0) {
    $order_ids = array_column($orders, 'id');
    // Create IN placeholders
    $in = str_repeat('?,', count($order_ids) - 1) . '?';
    $stmtItems = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id IN ($in)");
    $stmtItems->execute($order_ids);
    $items = $stmtItems->fetchAll();
    foreach ($items as $item) {
        $order_items[$item['order_id']][] = $item;
    }
}

// Fetch Wishlist (joining products)
$stmt = $pdo->prepare("SELECT w.id as wish_id, p.* FROM wishlist w JOIN products p ON w.product_id = p.id WHERE w.user_id = ? ORDER BY w.created_at DESC");
$stmt->execute([$user_id]);
$wishlist = $stmt->fetchAll();

include 'includes/header.php';
?>

<style>
/* My Account Layout */
.account-container {
    max-width: 1200px;
    margin: 50px auto;
    padding: 0 20px;
    display: flex;
    gap: 40px;
    min-height: 60vh;
}

/* Sidebar Styling */
.account-sidebar {
    width: 280px;
    background: var(--white);
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    border: 1px solid #f5f5f5;
    overflow: hidden;
    flex-shrink: 0;
    align-self: flex-start;
}
.user-profile-header {
    background: #fdf0f5; /* Light pink brand accent */
    padding: 30px 20px;
    text-align: center;
    border-bottom: 2px solid #f5e4ea;
}
.user-avatar {
    width: 80px;
    height: 80px;
    background: var(--maroon);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-family: var(--font-heading);
    margin: 0 auto 15px;
    box-shadow: 0 4px 10px rgba(139, 26, 74, 0.2);
}
.user-profile-header h3 {
    color: var(--maroon);
    font-family: var(--font-heading);
    font-size: 1.3rem;
    margin-bottom: 5px;
}
.user-profile-header p {
    color: #777;
    font-size: 0.9rem;
}

.sidebar-menu {
    list-style: none;
    padding: 10px 0;
    margin: 0;
}
.sidebar-menu li {
    cursor: pointer;
    padding: 16px 25px;
    color: #555;
    font-weight: 500;
    transition: all 0.3s;
    border-left: 4px solid transparent;
    display: flex;
    align-items: center;
    border-bottom: 1px solid #f9f9f9;
}
.sidebar-menu li i {
    width: 25px;
    color: var(--gold);
    font-size: 1.1rem;
}
.sidebar-menu li:hover, .sidebar-menu li.active {
    background: #fcfcfc;
    color: var(--maroon);
    border-left-color: var(--maroon);
}
.sidebar-menu li.logout {
    color: #e51e25;
}
.sidebar-menu li.logout i {
    color: #e51e25;
}
.sidebar-menu li.logout:hover {
    background: #fff0f0;
    border-left-color: #e51e25;
}

/* Main Content Area */
.account-content {
    flex: 1;
    background: var(--white);
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    border: 1px solid #f5f5f5;
    padding: 40px;
}
.tab-content {
    display: none;
    animation: fadeIn 0.4s;
}
.tab-content.active {
    display: block;
}
.content-title {
    font-family: var(--font-heading);
    color: var(--maroon);
    font-size: 1.8rem;
    margin-bottom: 30px;
    border-bottom: 2px solid #f5e4ea;
    padding-bottom: 15px;
}

/* Alerts */
.alert { padding: 15px; border-radius: 6px; margin-bottom: 25px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
.alert-success { background: #e8f8f5; color: #2ecc71; border: 1px solid #a3e4d7; }
.alert-error { background: #fff0f0; color: #e74c3c; border: 1px solid #ffcccc; }

/* Orders Table */
.table-responsive { overflow-x: auto; }
.orders-table {
    width: 100%;
    border-collapse: collapse;
}
.orders-table th {
    background: #fdf0f5;
    color: var(--maroon);
    padding: 15px;
    text-align: left;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.9rem;
    letter-spacing: 0.5px;
}
.orders-table td {
    padding: 18px 15px;
    border-bottom: 1px solid #eee;
    color: #444;
    vertical-align: middle;
}
.orders-table tr:hover td {
    background: #fafafa;
}

/* Status Badges */
.status-badge {
    padding: 6px 14px;
    border-radius: 30px;
    font-size: 0.85rem;
    font-weight: 600;
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-transform: capitalize;
}
.status-pending { background: #f39c12; }
.status-processing { background: #3498db; }
.status-shipped { background: #e67e22; }
.status-delivered { background: #2ecc71; }
.status-cancelled { background: #e74c3c; }

.btn-view {
    padding: 8px 18px;
    background: transparent;
    border: 1px solid var(--maroon);
    color: var(--maroon);
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}
.btn-view:hover {
    background: var(--maroon);
    color: white;
}

/* Forms */
.account-form { max-width: 500px; }
.form-group { margin-bottom: 22px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #444; }
.form-control { width: 100%; padding: 14px 15px; border: 1px solid #ddd; border-radius: 4px; font-family: var(--font-body); outline: none; transition: 0.3s; }
.form-control:focus { border-color: var(--gold); }
.btn-save { padding: 14px 30px; background: var(--maroon); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; }
.btn-save:hover { background: #6a1338; }

/* Wishlist Grid */
.wishlist-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); 
    gap: 25px; 
}
.wishlist-card { 
    border: 1px solid #eee; 
    border-radius: 8px; 
    padding: 15px; 
    text-align: center; 
    position: relative; 
    transition: transform 0.3s, box-shadow 0.3s;
}
.wishlist-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}
.wishlist-card img { 
    width: 100%; 
    height: 200px; 
    object-fit: cover; 
    border-radius: 4px; 
    margin-bottom: 12px; 
}
.wishlist-card h4 { color: var(--maroon); font-size: 1.05rem; margin-bottom: 8px; font-family: var(--font-heading); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wishlist-card .price { color: #444; font-weight: 600; margin-bottom: 15px; }
.btn-remove-wish { 
    position: absolute; 
    top: -10px; 
    right: -10px; 
    background: #fff; 
    color: #e74c3c; 
    border: 1px solid #eee;
    width: 35px; 
    height: 35px; 
    border-radius: 50%; 
    box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
    cursor: pointer; 
    transition: 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.btn-remove-wish:hover { background: #fff0f0; transform: scale(1.1); }
.btn-add-cart-wish {
    width: 100%;
    padding: 10px;
    background: var(--gold);
    color: white;
    border: none;
    border-radius: 4px;
    font-weight: 600;
    cursor: pointer;
    text-transform: uppercase;
}

/* Modal Styling */
.modal-overlay { 
    display: none; 
    position: fixed; 
    top: 0; left: 0; width: 100%; height: 100%; 
    background: rgba(0,0,0,0.6); 
    z-index: 1000; 
    justify-content: center; 
    align-items: center; 
    backdrop-filter: blur(3px);
}
.modal-overlay.active { display: flex; animation: fadeIn 0.3s; }
.modal-content { 
    background: white; 
    width: 90%; 
    max-width: 650px; 
    border-radius: 8px; 
    padding: 35px; 
    position: relative; 
    max-height: 90vh; 
    overflow-y: auto; 
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}
.close-modal { 
    position: absolute; 
    top: 25px; 
    right: 25px; 
    font-size: 1.5rem; 
    cursor: pointer; 
    color: #999; 
    transition: 0.3s;
}
.close-modal:hover { color: var(--maroon); transform: scale(1.1); }
.modal-title { 
    font-family: var(--font-heading); 
    color: var(--maroon); 
    font-size: 1.6rem; 
    margin-bottom: 25px; 
    border-bottom: 2px solid #f5e4ea; 
    padding-bottom: 15px; 
}

/* Empty States */
.empty-state { text-align: center; padding: 40px 20px; color: #777; }
.empty-state i { font-size: 4rem; color: #eee; margin-bottom: 15px; }

@media (max-width: 900px) {
    .account-container { flex-direction: column; }
    .account-sidebar { width: 100%; }
}
</style>

<main class="account-container">

    <!-- Sidebar -->
    <aside class="account-sidebar">
        <div class="user-profile-header">
            <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <h3><?= htmlspecialchars($user['name']) ?></h3>
            <p><?= htmlspecialchars($user['email']) ?></p>
        </div>
        <ul class="sidebar-menu">
            <li class="<?= $active_tab == 'orders' ? 'active' : '' ?>" onclick="switchTab('orders', this)">
                <i class="fas fa-box"></i> My Orders
            </li>
            <li class="<?= $active_tab == 'profile' ? 'active' : '' ?>" onclick="switchTab('profile', this)">
                <i class="fas fa-user-edit"></i> Profile Settings
            </li>
            <li class="<?= $active_tab == 'addresses' ? 'active' : '' ?>" onclick="switchTab('addresses', this)">
                <i class="fas fa-map-marker-alt"></i> Saved Addresses
            </li>
            <li class="<?= $active_tab == 'wishlist' ? 'active' : '' ?>" onclick="switchTab('wishlist', this)">
                <i class="fas fa-heart"></i> My Wishlist
            </li>
            <li class="<?= $active_tab == 'password' ? 'active' : '' ?>" onclick="switchTab('password', this)">
                <i class="fas fa-lock"></i> Change Password
            </li>
            <li class="logout" onclick="window.location.href='my-account.php?logout=1'">
                <i class="fas fa-sign-out-alt"></i> Logout
            </li>
        </ul>
    </aside>

    <!-- Main Content -->
    <div class="account-content">
        
        <?php if ($success_msg): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success_msg ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error_msg ?></div>
        <?php endif; ?>

        <!-- Orders Tab -->
        <div id="orders" class="tab-content <?= $active_tab == 'orders' ? 'active' : '' ?>">
            <h2 class="content-title">Order History</h2>
            
            <?php if (count($orders) > 0): ?>
                <div class="table-responsive">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): 
                                // Determine icon based on status
                                $statusIcon = 'fa-clock';
                                if($order['status'] == 'processing') $statusIcon = 'fa-cogs';
                                if($order['status'] == 'shipped') $statusIcon = 'fa-truck';
                                if($order['status'] == 'delivered') $statusIcon = 'fa-check-circle';
                                if($order['status'] == 'cancelled') $statusIcon = 'fa-times-circle';
                            ?>
                            <tr>
                                <td><strong>#<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></strong></td>
                                <td><?= date('d M Y', strtotime($order['created_at'])) ?></td>
                                <td style="font-weight: 500;">Rs. <?= number_format($order['total_amount']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($order['status']) ?>">
                                        <i class="fas <?= $statusIcon ?>"></i> <?= htmlspecialchars(ucfirst($order['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn-view" onclick="viewOrder(<?= $order['id'] ?>, '<?= date('d M Y', strtotime($order['created_at'])) ?>', <?= $order['total_amount'] ?>, '<?= $order['status'] ?>')">
                                        View Details
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No Orders Yet</h3>
                    <p>You haven't placed any orders with us yet. Start exploring our elegant collection!</p>
                    <a href="shop.php" class="btn-save" style="display:inline-block; margin-top: 15px; text-decoration: none;">Start Shopping</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Profile Tab -->
        <div id="profile" class="tab-content <?= $active_tab == 'profile' ? 'active' : '' ?>">
            <h2 class="content-title">Profile Settings</h2>
            
            <form class="account-form" method="POST">
                <input type="hidden" name="action" value="update_profile">
                <input type="hidden" name="tab" value="profile">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                </div>
                
                <button type="submit" class="btn-save">Save Changes</button>
            </form>
        </div>

        <!-- Addresses Tab -->
        <div id="addresses" class="tab-content <?= $active_tab == 'addresses' ? 'active' : '' ?>">
            <h2 class="content-title">Saved Addresses</h2>
            <div class="empty-state">
                <i class="fas fa-map-marked-alt"></i>
                <h3>No Fixed Addresses</h3>
                <p>Your shipping addresses are securely saved during checkout on your individual orders. You don't need to manage them here.</p>
            </div>
        </div>

        <!-- Wishlist Tab -->
        <div id="wishlist" class="tab-content <?= $active_tab == 'wishlist' ? 'active' : '' ?>">
            <h2 class="content-title">My Wishlist</h2>
            
            <?php if (count($wishlist) > 0): ?>
                <div class="wishlist-grid">
                    <?php foreach ($wishlist as $item): ?>
                    <div class="wishlist-card">
                        <!-- Form to Remove from wishlist -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="remove_wishlist">
                            <input type="hidden" name="wishlist_id" value="<?= $item['wish_id'] ?>">
                            <button type="submit" class="btn-remove-wish" title="Remove"><i class="fas fa-times"></i></button>
                        </form>
                        
                        <a href="product.php?id=<?= $item['id'] ?>" style="text-decoration:none;">
                            <img src="https://via.placeholder.com/200x200?text=Product" alt="<?= htmlspecialchars($item['name']) ?>">
                            <h4><?= htmlspecialchars($item['name']) ?></h4>
                        </a>
                        <div class="price">Rs. <?= number_format($item['price']) ?></div>
                        <button class="btn-add-cart-wish"><i class="fas fa-shopping-cart"></i> Add to Cart</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="far fa-heart"></i>
                    <h3>Your Wishlist is Empty</h3>
                    <p>Found something you like? Click the heart icon on any product to save it here for later.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Change Password Tab -->
        <div id="password" class="tab-content <?= $active_tab == 'password' ? 'active' : '' ?>">
            <h2 class="content-title">Change Password</h2>
            
            <form class="account-form" method="POST">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="tab" value="password">
                
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                </div>
                
                <button type="submit" class="btn-save">Update Password</button>
            </form>
        </div>

    </div>
</main>

<!-- Order Details Modal -->
<div class="modal-overlay" id="orderModal">
    <div class="modal-content">
        <i class="fas fa-times close-modal" onclick="closeOrderModal()"></i>
        <h3 class="modal-title" id="modal-order-title">Order Details</h3>
        
        <div style="margin-bottom: 20px; color: #555;">
            <p><strong>Date:</strong> <span id="modal-order-date"></span></p>
            <p><strong>Status:</strong> <span id="modal-order-status" style="text-transform: capitalize;"></span></p>
        </div>

        <table class="orders-table" style="margin-bottom: 20px;">
            <thead>
                <tr>
                    <th>Item</th>
                    <th style="text-align:center;">Qty</th>
                    <th style="text-align:right;">Price</th>
                </tr>
            </thead>
            <tbody id="modal-items-body">
                <!-- Injected via JS -->
            </tbody>
        </table>

        <div style="text-align: right; font-size: 1.2rem; font-weight: 600; color: var(--maroon);">
            Total Amount: <span id="modal-order-total"></span>
        </div>
    </div>
</div>

<script>
// JSON string containing all user order items pre-fetched from PHP
const orderItemsData = <?= json_encode($order_items) ?>;

// Tab Switcher
function switchTab(tabId, element) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    // Remove active class from menu
    document.querySelectorAll('.sidebar-menu li').forEach(li => li.classList.remove('active'));
    
    // Show selected tab
    document.getElementById(tabId).classList.add('active');
    element.classList.add('active');
}

// Order Modal Functions
function viewOrder(orderId, date, total, status) {
    document.getElementById('modal-order-title').textContent = 'Order #' + String(orderId).padStart(6, '0');
    document.getElementById('modal-order-date').textContent = date;
    document.getElementById('modal-order-status').textContent = status;
    document.getElementById('modal-order-total').textContent = 'Rs. ' + Number(total).toLocaleString();
    
    const tbody = document.getElementById('modal-items-body');
    tbody.innerHTML = '';
    
    const items = orderItemsData[orderId] || [];
    
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">No items found.</td></tr>';
    } else {
        items.forEach(item => {
            const tr = document.createElement('tr');
            const totalItemPrice = item.price * item.quantity;
            
            tr.innerHTML = `
                <td style="font-weight:500;">${item.name}</td>
                <td style="text-align:center;">${item.quantity}</td>
                <td style="text-align:right;">Rs. ${Number(totalItemPrice).toLocaleString()}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    document.getElementById('orderModal').classList.add('active');
}

function closeOrderModal() {
    document.getElementById('orderModal').classList.remove('active');
}

// Close modal if clicked outside content
document.getElementById('orderModal').addEventListener('click', function(e) {
    if(e.target === this) {
        closeOrderModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
