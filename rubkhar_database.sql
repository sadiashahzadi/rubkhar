-- Database creation
CREATE DATABASE IF NOT EXISTS rubkhar_db;
USE rubkhar_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'customer') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stock INT DEFAULT 0,
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    shipping_address TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Order Items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- Cart table
CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Wishlist table
CREATE TABLE IF NOT EXISTS wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Reviews table
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Coupons table
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    discount_percentage DECIMAL(5, 2) NOT NULL,
    expiry_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Newsletter table
CREATE TABLE IF NOT EXISTS newsletter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert Sample Data: 6 Categories
INSERT INTO categories (name, slug, description) VALUES
('Women''s Clothing', 'womens-clothing', 'Elegant and stylish clothing for women.'),
('Jewelry', 'jewelry', 'Luxurious and beautiful jewelry pieces.'),
('Abayas', 'abayas', 'Premium quality abayas with elegant designs.'),
('Gift Items', 'gift-items', 'Perfect gifts for your loved ones.'),
('Accessories', 'accessories', 'Fashionable accessories to complete your look.'),
('Beauty & Perfumes', 'beauty-perfumes', 'Authentic fragrances and beauty products.');

-- Insert Sample Data: 15 Products
INSERT INTO products (category_id, name, slug, description, price, stock, image_url) VALUES
(1, 'Maroon Silk Dress', 'maroon-silk-dress', 'A beautiful maroon silk dress perfect for evening wear.', 4500.00, 50, 'maroon-silk-dress.jpg'),
(1, 'Embroidered Lawn Suit', 'embroidered-lawn-suit', 'Light pink summer lawn suit with intricate embroidery.', 3500.00, 100, 'lawn-suit.jpg'),
(1, 'Gold Zari Chiffon Saree', 'gold-zari-chiffon-saree', 'Elegant chiffon saree with gold zari work.', 8500.00, 20, 'gold-saree.jpg'),
(2, 'Gold Plated Necklace Set', 'gold-plated-necklace-set', 'Stunning gold plated necklace with matching earrings.', 2500.00, 30, 'necklace-set.jpg'),
(2, 'Pearl Drop Earrings', 'pearl-drop-earrings', 'Classic pearl drop earrings for an elegant look.', 1200.00, 45, 'pearl-earrings.jpg'),
(2, 'Kundan Bridal Set', 'kundan-bridal-set', 'Heavy kundan bridal set for special occasions.', 15000.00, 5, 'kundan-set.jpg'),
(3, 'Classic Black Nida Abaya', 'classic-black-nida-abaya', 'Simple yet elegant black nida abaya for daily wear.', 3200.00, 60, 'black-abaya.jpg'),
(3, 'Maroon Velvet Abaya', 'maroon-velvet-abaya', 'Luxurious maroon velvet abaya with stone work.', 5500.00, 25, 'maroon-abaya.jpg'),
(3, 'White Chiffon Layered Abaya', 'white-chiffon-layered-abaya', 'Beautiful white chiffon layered abaya.', 4800.00, 35, 'white-abaya.jpg'),
(4, 'Luxury Gift Hamper', 'luxury-gift-hamper', 'A curated box of luxury items for gifting.', 5000.00, 15, 'gift-hamper.jpg'),
(4, 'Scented Candle Set', 'scented-candle-set', 'Set of 3 premium scented candles in decorative jars.', 1800.00, 40, 'candle-set.jpg'),
(5, 'Maroon Leather Handbag', 'maroon-leather-handbag', 'Premium quality maroon leather handbag.', 4000.00, 25, 'maroon-handbag.jpg'),
(5, 'Silk Scarf', 'silk-scarf', 'Printed pure silk scarf.', 1500.00, 50, 'silk-scarf.jpg'),
(6, 'Oud Al Lail Perfume', 'oud-al-lail-perfume', 'Long-lasting premium Arabian oud perfume.', 6500.00, 20, 'oud-perfume.jpg'),
(6, 'Floral Rose Body Mist', 'floral-rose-body-mist', 'Refreshing floral rose body mist.', 1200.00, 60, 'body-mist.jpg');
