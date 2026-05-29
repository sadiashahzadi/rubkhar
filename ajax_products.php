<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

// Get Inputs
$categories = isset($_POST['categories']) ? $_POST['categories'] : [];
$max_price = isset($_POST['max_price']) ? (int)$_POST['max_price'] : 50000;
$sizes = isset($_POST['sizes']) && $_POST['sizes'] !== '' ? explode(',', $_POST['sizes']) : [];
$colors = isset($_POST['colors']) && $_POST['colors'] !== '' ? explode(',', $_POST['colors']) : [];
$sort = isset($_POST['sort']) ? $_POST['sort'] : 'newest';
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;

if ($page < 1) $page = 1;
$per_page = 12; // 12 products per page
$offset = ($page - 1) * $per_page;

// Build Base Query
$query = "SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.price <= :max_price";
$params = [':max_price' => $max_price];

// Filter by Category
if (!empty($categories)) {
    $placeholders = [];
    foreach ($categories as $k => $cat_id) {
        $key = ":cat_$k";
        $placeholders[] = $key;
        $params[$key] = (int)$cat_id;
    }
    $query .= " AND p.category_id IN (" . implode(',', $placeholders) . ")";
}

// NOTE on Sizes and Colors:
// These attributes are included in the UI per user request, but the underlying MySQL `products` 
// table currently does not have `sizes` and `colors` columns yet. 
// When the schema is expanded, you can append `AND p.size IN (...)` here. 

// Sort Options
if ($sort == 'price_asc') {
    $query .= " ORDER BY p.price ASC";
} elseif ($sort == 'price_desc') {
    $query .= " ORDER BY p.price DESC";
} elseif ($sort == 'name_asc') {
    $query .= " ORDER BY p.name ASC";
} else {
    $query .= " ORDER BY p.created_at DESC"; // newest
}

// Get Total Count for Pagination
$countQuery = preg_replace('/SELECT p\.\*, c\.name as category_name/i', 'SELECT COUNT(*) as total', $query);
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRow = $countStmt->fetch();
$totalItems = $totalRow['total'];
$totalPages = ceil($totalItems / $per_page);

// Add Pagination Limits
$query .= " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Generate HTML for Products Grid
$html = '';
if (count($products) > 0) {
    foreach ($products as $product) {
        $price = number_format($product['price']);
        // Mock a sale badge for cheaper items just to show the UI
        $saleBadge = ($product['price'] <= 3500) ? '<div class="sale-badge">SALE</div>' : '';
        
        $html .= '
        <div class="product-card">
            <div class="product-img">
                ' . $saleBadge . '
                <div class="wishlist-btn"><i class="fas fa-heart"></i></div>
                <i class="fas fa-image" style="font-size: 4rem; color: #ddd;"></i>
            </div>
            <div class="product-info">
                <div class="product-name" title="' . htmlspecialchars($product['name']) . '">' . htmlspecialchars($product['name']) . '</div>
                <div class="product-price">Rs. ' . $price . '</div>
                <button class="add-cart-btn"><i class="fas fa-shopping-cart"></i> Add to Cart</button>
            </div>
        </div>';
    }
} else {
    $html = '<div style="grid-column: 1/-1; text-align: center; padding: 60px; font-size: 1.1rem; color: #777;">No products found matching your criteria. Try adjusting your filters.</div>';
}

// Generate HTML for Pagination
$pagination = '';
if ($totalPages > 1) {
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $page) ? 'active' : '';
        $pagination .= '<div class="page-btn ' . $activeClass . '" data-page="' . $i . '">' . $i . '</div>';
    }
}

// Return JSON Response
echo json_encode([
    'html' => $html,
    'pagination' => $pagination,
    'count' => $totalItems
]);
exit;
