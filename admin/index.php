<?php
require_once 'includes/header.php';

// 1. Get Stats
// Total Orders
$stmt = $pdo->query("SELECT COUNT(*) FROM orders");
$total_orders = $stmt->fetchColumn();

// Total Revenue
$stmt = $pdo->query("SELECT SUM(total_amount) FROM orders");
$total_revenue = $stmt->fetchColumn() ?: 0;

// Total Customers
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'");
$total_customers = $stmt->fetchColumn();

// Low Stock (< 5)
$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 5");
$low_stock = $stmt->fetchColumn();

// 2. Recent Orders Table
$stmt = $pdo->query("
    SELECT o.*, u.name as customer_name 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$recent_orders = $stmt->fetchAll();

// 3. Sales Chart Data (Last 7 days)
$chart_labels = [];
$chart_data = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('M d', strtotime($date));
    
    $stmt = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE DATE(created_at) = ? AND status != 'cancelled'");
    $stmt->execute([$date]);
    $sum = $stmt->fetchColumn() ?: 0;
    $chart_data[] = $sum;
}
?>
<style>
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}
.stat-card {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    border: 1px solid #eee;
    display: flex;
    align-items: center;
    gap: 20px;
}
.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
}
.stat-info h4 {
    margin: 0;
    color: #777;
    font-size: 0.95rem;
    font-weight: 500;
}
.stat-info p {
    margin: 5px 0 0 0;
    font-size: 1.8rem;
    font-family: var(--font-heading);
    color: var(--maroon);
    font-weight: 600;
}

.chart-container {
    background: #fff;
    border-radius: 8px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    border: 1px solid #eee;
    margin-bottom: 30px;
}
.section-title {
    font-family: var(--font-heading);
    color: var(--maroon);
    font-size: 1.3rem;
    margin-bottom: 20px;
    border-bottom: 1px solid #f5e4ea;
    padding-bottom: 10px;
}
</style>

<div class="dashboard-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--gold);"><i class="fas fa-shopping-bag"></i></div>
        <div class="stat-info">
            <h4>Total Orders</h4>
            <p><?= number_format($total_orders) ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #2ecc71;"><i class="fas fa-money-bill-wave"></i></div>
        <div class="stat-info">
            <h4>Total Revenue</h4>
            <p>Rs. <?= number_format($total_revenue) ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #3498db;"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <h4>Customers</h4>
            <p><?= number_format($total_customers) ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #e74c3c;"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-info">
            <h4>Low Stock Items</h4>
            <p><?= number_format($low_stock) ?></p>
        </div>
    </div>
</div>

<div class="chart-container">
    <h3 class="section-title">Sales Overview (Last 7 Days)</h3>
    <canvas id="salesChart" height="80"></canvas>
</div>

<div class="card">
    <h3 class="section-title" style="border:none; margin-bottom:15px;">Recent Orders</h3>
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
                <?php foreach($recent_orders as $order): ?>
                <tr>
                    <td><strong>#<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></strong></td>
                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                    <td><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></td>
                    <td style="font-weight: 500; color:var(--maroon);">Rs. <?= number_format($order['total_amount']) ?></td>
                    <td><span class="badge badge-<?= strtolower($order['status']) ?>"><?= ucfirst($order['status']) ?></span></td>
                    <td><a href="orders.php?view=<?= $order['id'] ?>" class="btn btn-sm btn-gold">View</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($recent_orders)): ?>
                <tr><td colspan="6" style="text-align: center;">No orders found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const ctx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: 'Revenue (Rs)',
            data: <?= json_encode($chart_data) ?>,
            borderColor: '#8B1A4A',
            backgroundColor: 'rgba(139, 26, 74, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { 
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rs ' + value;
                    }
                }
            }
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
