<?php
require_once 'includes/db.php';

// Fetch New Arrivals (8 latest products)
$stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.created_at DESC LIMIT 8");
$new_arrivals = $stmt->fetchAll();

// Fetch Best Sellers (Using id ASC as a placeholder for top selling products)
$stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.id ASC LIMIT 8");
$best_sellers = $stmt->fetchAll();

include 'includes/header.php';
?>

<style>
    /* Base Page Styles */
    main {
        width: 100%;
        overflow-x: hidden;
    }
    
    .section-title {
        text-align: center;
        font-family: var(--font-heading);
        font-size: 2.5rem;
        color: var(--maroon);
        margin: 60px 0 40px;
    }
    
    .btn {
        display: inline-block;
        padding: 12px 25px;
        background-color: var(--gold);
        color: var(--white);
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: background 0.3s;
    }
    .btn:hover {
        background-color: #b58532;
        color: var(--white);
    }
    .btn-maroon {
        background-color: var(--maroon);
    }
    .btn-maroon:hover {
        background-color: #6a1338;
    }

    /* 1. Hero Slider */
    .hero-slider {
        position: relative;
        height: 70vh;
        min-height: 500px;
        width: 100%;
        overflow: hidden;
        background-color: var(--light-pink);
    }
    .slide {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        transition: opacity 1s ease-in-out;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }
    .slide.active {
        opacity: 1;
        z-index: 1;
    }
    .slide-content {
        color: var(--white);
        z-index: 2;
        padding: 20px;
    }
    .slide-content h2 {
        font-family: var(--font-heading);
        font-size: 4rem;
        margin-bottom: 15px;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.6);
    }
    .slide-content p {
        font-size: 1.2rem;
        margin-bottom: 30px;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.6);
    }
    
    /* 2. Shop by Category */
    .category-section {
        padding: 0 40px;
    }
    .category-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    .category-card {
        position: relative;
        height: 350px;
        border-radius: 8px;
        overflow: hidden;
        display: block;
        background-color: #ddd;
    }
    .category-card .placeholder-bg {
        width: 100%;
        height: 100%;
        transition: transform 0.5s;
    }
    .category-card:hover .placeholder-bg {
        transform: scale(1.1);
    }
    .category-card .overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        padding: 25px 20px;
        background: linear-gradient(transparent, rgba(0,0,0,0.8));
        color: var(--white);
    }
    .category-card h3 {
        font-family: var(--font-heading);
        font-size: 1.8rem;
    }

    /* 3. & 5. Products Grid */
    .products-section {
        padding: 0 40px;
    }
    .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 30px;
    }
    .product-card {
        background: var(--white);
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transition: transform 0.3s, box-shadow 0.3s;
        text-align: center;
        padding-bottom: 20px;
    }
    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    .product-img {
        height: 300px;
        background-color: #f9f9f9;
        display: flex;
        justify-content: center;
        align-items: center;
        position: relative;
        color: #ddd;
    }
    .product-img i {
        font-size: 5rem;
    }
    .product-info {
        padding: 20px 15px;
    }
    .product-category {
        color: #888;
        font-size: 0.85rem;
        text-transform: uppercase;
        margin-bottom: 8px;
        letter-spacing: 1px;
    }
    .product-name {
        font-family: var(--font-heading);
        font-size: 1.3rem;
        color: var(--maroon);
        margin-bottom: 12px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .product-price {
        font-weight: 600;
        color: var(--gold);
        font-size: 1.2rem;
        margin-bottom: 15px;
    }

    /* 4. Promo Banner */
    .promo-banner {
        margin: 80px 0;
        height: 400px;
        /* Using a maroon gradient overlay */
        background: linear-gradient(rgba(139, 26, 74, 0.85), rgba(139, 26, 74, 0.85)), url('https://via.placeholder.com/1920x600?text=Abaya+Collection') center/cover;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: var(--white);
        padding: 0 20px;
    }
    .promo-banner h2 {
        font-family: var(--font-heading);
        font-size: 3.5rem;
        margin-bottom: 15px;
        color: var(--gold);
    }
    .promo-banner p {
        font-size: 1.2rem;
        margin-bottom: 30px;
    }

    /* 6. Why Choose Us */
    .features-section {
        background-color: var(--white);
        padding: 70px 40px;
        margin-top: 80px;
    }
    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 30px;
        text-align: center;
    }
    .feature-card i {
        font-size: 3.5rem;
        color: var(--gold);
        margin-bottom: 20px;
    }
    .feature-card h4 {
        font-family: var(--font-heading);
        color: var(--maroon);
        font-size: 1.3rem;
        margin-bottom: 10px;
    }
    .feature-card p {
        color: #666;
        font-size: 0.95rem;
    }

    /* 7. Newsletter */
    .newsletter-section {
        background-color: var(--light-pink);
        padding: 80px 20px;
        text-align: center;
    }
    .newsletter-section h2 {
        font-family: var(--font-heading); 
        color: var(--maroon); 
        font-size: 2.5rem;
        margin-bottom: 15px;
    }
    .newsletter-section p {
        color: #555;
        margin-bottom: 30px;
    }
    .newsletter-form {
        max-width: 550px;
        margin: 0 auto;
        display: flex;
    }
    .newsletter-form input {
        flex: 1;
        padding: 15px 20px;
        border: 1px solid #ddd;
        border-right: none;
        border-radius: 4px 0 0 4px;
        font-family: var(--font-body);
        outline: none;
        font-size: 1rem;
    }
    .newsletter-form input:focus {
        border-color: var(--gold);
    }
    .newsletter-form button {
        border-radius: 0 4px 4px 0;
    }

    /* 8. Instagram */
    .instagram-section {
        padding: 80px 40px;
        text-align: center;
    }
    .instagram-section h2 {
        font-family: var(--font-heading); 
        color: var(--maroon); 
        font-size: 2.5rem;
    }
    .insta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-top: 40px;
    }
    .insta-img {
        height: 200px;
        background-color: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ccc;
        transition: transform 0.3s;
        cursor: pointer;
    }
    .insta-img:hover {
        transform: scale(1.05);
        color: var(--gold);
    }
    .insta-img i {
        font-size: 3rem;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .slide-content h2 { font-size: 2.5rem; }
        .promo-banner h2 { font-size: 2rem; }
        .category-section, .products-section, .features-section, .instagram-section {
            padding: 0 20px;
        }
        .newsletter-form {
            flex-direction: column;
        }
        .newsletter-form input {
            border-right: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .newsletter-form button {
            border-radius: 4px;
            width: 100%;
        }
    }
</style>

<main>
    <!-- 1. Hero Slider -->
    <section class="hero-slider">
        <!-- Slide 1 -->
        <div class="slide slide-1 active" style="background: linear-gradient(rgba(139, 26, 74, 0.4), rgba(139, 26, 74, 0.4)), #eed5e0;">
            <div class="slide-content">
                <h2>Summer Collection</h2>
                <p>Up to 30% Off on Women Clothing</p>
                <a href="/category.php?slug=womens-clothing" class="btn">Shop Now</a>
            </div>
        </div>
        <!-- Slide 2 -->
        <div class="slide slide-2" style="background: linear-gradient(rgba(201, 150, 62, 0.4), rgba(201, 150, 62, 0.4)), #f9ecc8;">
            <div class="slide-content">
                <h2>Elegant Jewelry</h2>
                <p>Complete your look with our luxurious pieces</p>
                <a href="/category.php?slug=jewelry" class="btn">Discover More</a>
            </div>
        </div>
        <!-- Slide 3 -->
        <div class="slide slide-3" style="background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), #d1b4c3;">
            <div class="slide-content">
                <h2>Premium Abayas</h2>
                <p>Modesty meets elegance in our new collection</p>
                <a href="/category.php?slug=abayas" class="btn btn-maroon">Explore Collection</a>
            </div>
        </div>
    </section>

    <!-- 2. Shop by Category -->
    <section class="category-section">
        <h2 class="section-title">Shop by Category</h2>
        <div class="category-grid">
            <a href="/category.php?slug=womens-clothing" class="category-card">
                <div class="placeholder-bg" style="background-color: #eed5e0;"></div>
                <div class="overlay">
                    <h3>Women Clothing</h3>
                </div>
            </a>
            <a href="/category.php?slug=jewelry" class="category-card">
                <div class="placeholder-bg" style="background-color: #f9ecc8;"></div>
                <div class="overlay">
                    <h3>Jewelry</h3>
                </div>
            </a>
            <a href="/category.php?slug=abayas" class="category-card">
                <div class="placeholder-bg" style="background-color: #333;"></div>
                <div class="overlay">
                    <h3>Abayas</h3>
                </div>
            </a>
            <a href="/category.php?slug=gift-items" class="category-card">
                <div class="placeholder-bg" style="background-color: #d1b4c3;"></div>
                <div class="overlay">
                    <h3>Gift Items</h3>
                </div>
            </a>
        </div>
    </section>

    <!-- 3. New Arrivals -->
    <section class="products-section">
        <h2 class="section-title">New Arrivals</h2>
        <div class="product-grid">
            <?php foreach ($new_arrivals as $product): ?>
                <div class="product-card">
                    <div class="product-img">
                        <i class="fas fa-image"></i> <!-- Placeholder for product image -->
                    </div>
                    <div class="product-info">
                        <div class="product-category"><?= htmlspecialchars($product['category_name']) ?></div>
                        <div class="product-name" title="<?= htmlspecialchars($product['name']) ?>">
                            <?= htmlspecialchars($product['name']) ?>
                        </div>
                        <div class="product-price">Rs. <?= number_format($product['price']) ?></div>
                        <button class="btn btn-maroon" style="width: 100%;"><i class="fas fa-shopping-cart"></i> Add to Cart</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 4. Promo Banner -->
    <section class="promo-banner">
        <div>
            <h2>The Royal Abaya Collection</h2>
            <p>Experience true luxury and elegance with our new premium arrivals.</p>
            <a href="/category.php?slug=abayas" class="btn">Shop The Collection</a>
        </div>
    </section>

    <!-- 5. Best Sellers -->
    <section class="products-section">
        <h2 class="section-title">Best Sellers</h2>
        <div class="product-grid">
            <?php foreach ($best_sellers as $product): ?>
                <div class="product-card">
                    <div class="product-img">
                        <i class="fas fa-image"></i>
                    </div>
                    <div class="product-info">
                        <div class="product-category"><?= htmlspecialchars($product['category_name']) ?></div>
                        <div class="product-name" title="<?= htmlspecialchars($product['name']) ?>">
                            <?= htmlspecialchars($product['name']) ?>
                        </div>
                        <div class="product-price">Rs. <?= number_format($product['price']) ?></div>
                        <button class="btn btn-maroon" style="width: 100%;"><i class="fas fa-shopping-cart"></i> Add to Cart</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 6. Why Choose Us -->
    <section class="features-section">
        <div class="features-grid">
            <div class="feature-card">
                <i class="fas fa-shipping-fast"></i>
                <h4>Free Delivery</h4>
                <p>On orders above Rs. 2000</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-undo"></i>
                <h4>Easy Returns</h4>
                <p>7 Days return policy</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-certificate"></i>
                <h4>100% Original</h4>
                <p>Authentic products guaranteed</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-lock"></i>
                <h4>Secure Payments</h4>
                <p>Safe & encrypted checkout</p>
            </div>
        </div>
    </section>

    <!-- 7. Newsletter Signup -->
    <section class="newsletter-section">
        <h2>Join The Rubkhar Family</h2>
        <p>Subscribe to get special offers, free giveaways, and once-in-a-lifetime deals.</p>
        <form class="newsletter-form" action="/subscribe.php" method="POST">
            <input type="email" placeholder="Enter your email address" required>
            <button type="submit" class="btn">Subscribe</button>
        </form>
    </section>

    <!-- 8. Instagram Section -->
    <section class="instagram-section">
        <h2>Follow Us @rubkhar</h2>
        <div class="insta-grid">
            <div class="insta-img"><i class="fab fa-instagram"></i></div>
            <div class="insta-img"><i class="fab fa-instagram"></i></div>
            <div class="insta-img"><i class="fab fa-instagram"></i></div>
            <div class="insta-img"><i class="fab fa-instagram"></i></div>
            <div class="insta-img"><i class="fab fa-instagram"></i></div>
            <div class="insta-img"><i class="fab fa-instagram"></i></div>
        </div>
    </section>

</main>

<script>
    // Hero Slider Auto-Slide Logic
    document.addEventListener('DOMContentLoaded', () => {
        const slides = document.querySelectorAll('.hero-slider .slide');
        let currentSlide = 0;
        
        if (slides.length > 0) {
            function nextSlide() {
                slides[currentSlide].classList.remove('active');
                currentSlide = (currentSlide + 1) % slides.length;
                slides[currentSlide].classList.add('active');
            }
            
            // Auto-slide every 4 seconds
            setInterval(nextSlide, 4000);
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
