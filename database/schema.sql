-- Sweet Choice Database Schema
-- Japanese Dessert Shop Website
-- Character Set: utf8mb4

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS order_customizations;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS favorites;
DROP TABLE IF EXISTS product_customizations;
DROP TABLE IF EXISTS product_allergies;
DROP TABLE IF EXISTS product_ingredients;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS banners;
DROP TABLE IF EXISTS delivery_settings;
DROP TABLE IF EXISTS pickup_slots;
DROP TABLE IF EXISTS delivery_slots;
DROP TABLE IF EXISTS business_hours;
DROP TABLE IF EXISTS payment_methods;
DROP TABLE IF EXISTS shop_settings;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NULL,
    postal_code VARCHAR(20) NULL,
    address VARCHAR(255) NULL,
    apartment VARCHAR(100) NULL,
    role ENUM('customer', 'admin') DEFAULT 'customer',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories Table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_en VARCHAR(100) NOT NULL,
    name_ja VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description_en TEXT NULL,
    description_ja TEXT NULL,
    image VARCHAR(255) NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products Table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name_en VARCHAR(150) NOT NULL,
    name_ja VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    description_en TEXT NULL,
    description_ja TEXT NULL,
    price INT NOT NULL COMMENT 'Price in JPY',
    image VARCHAR(255) NULL,
    stock INT DEFAULT 50,
    availability ENUM('available', 'limited', 'sold_out') DEFAULT 'available',
    is_popular TINYINT(1) DEFAULT 0,
    tags VARCHAR(255) DEFAULT '' COMMENT 'Comma-separated tags for AI recommendation',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Ingredients Table
CREATE TABLE product_ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    ingredient_en VARCHAR(100) NOT NULL,
    ingredient_ja VARCHAR(100) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Allergies Table
CREATE TABLE product_allergies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    allergy_en VARCHAR(100) NOT NULL,
    allergy_ja VARCHAR(100) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Customizations Table
CREATE TABLE product_customizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    group_name_en VARCHAR(100) NOT NULL,
    group_name_ja VARCHAR(100) NOT NULL,
    option_name_en VARCHAR(100) NOT NULL,
    option_name_ja VARCHAR(100) NOT NULL,
    price_extra INT DEFAULT 0 COMMENT 'Extra price in JPY',
    is_default TINYINT(1) DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Banners Table
CREATE TABLE banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title_en VARCHAR(150) NOT NULL,
    title_ja VARCHAR(150) NOT NULL,
    subtitle_en VARCHAR(255) NULL,
    subtitle_ja VARCHAR(255) NULL,
    button_text_en VARCHAR(100) DEFAULT 'Explore Menu',
    button_text_ja VARCHAR(100) DEFAULT 'メニューを見る',
    link VARCHAR(255) DEFAULT 'category.php',
    image VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Favorites Table
CREATE TABLE favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_product (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cart Table
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    session_id VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cart Items Table
CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    customization_summary TEXT NULL,
    customization_price INT DEFAULT 0 COMMENT 'Total customization extra in JPY per unit',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_id) REFERENCES cart(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders Table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    user_id INT NULL,
    fulfillment_type ENUM('pickup', 'delivery') NOT NULL,
    pickup_date DATE NULL,
    pickup_time VARCHAR(20) NULL,
    delivery_date DATE NULL,
    delivery_time VARCHAR(20) NULL,
    distance_km DECIMAL(5,2) DEFAULT 0.00,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(150) NOT NULL,
    customer_phone VARCHAR(30) NOT NULL,
    postal_code VARCHAR(20) NULL,
    address VARCHAR(255) NULL,
    apartment VARCHAR(100) NULL,
    subtotal INT NOT NULL COMMENT 'JPY',
    customization_total INT DEFAULT 0 COMMENT 'JPY',
    delivery_fee INT DEFAULT 0 COMMENT 'JPY',
    service_fee INT DEFAULT 0 COMMENT 'JPY',
    grand_total INT NOT NULL COMMENT 'JPY',
    payment_method VARCHAR(50) NOT NULL,
    payment_status ENUM('pending', 'paid') DEFAULT 'paid',
    status ENUM('Order Received', 'Preparing', 'Ready for Pickup', 'Out for Delivery', 'Completed', 'Delivered', 'Cancelled') DEFAULT 'Order Received',
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Items Table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(150) NOT NULL,
    price INT NOT NULL COMMENT 'Base product price in JPY',
    quantity INT NOT NULL,
    customization_summary TEXT NULL,
    customization_price INT DEFAULT 0 COMMENT 'Customization total per unit',
    subtotal INT NOT NULL COMMENT 'Base + custom * qty',
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Customizations Table
CREATE TABLE order_customizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_item_id INT NOT NULL,
    group_name VARCHAR(100) NOT NULL,
    option_name VARCHAR(100) NOT NULL,
    price_extra INT DEFAULT 0 COMMENT 'JPY',
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Delivery Settings Table
CREATE TABLE delivery_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tier_label_en VARCHAR(100) NOT NULL,
    tier_label_ja VARCHAR(100) NOT NULL,
    min_km DECIMAL(4,1) NOT NULL,
    max_km DECIMAL(4,1) NOT NULL,
    fee INT NOT NULL COMMENT 'Fee in JPY',
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pickup Slots Table
CREATE TABLE pickup_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_time VARCHAR(20) NOT NULL,
    max_orders INT DEFAULT 10,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Delivery Slots Table
CREATE TABLE delivery_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_time VARCHAR(20) NOT NULL,
    max_orders INT DEFAULT 8,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Business Hours Table
CREATE TABLE business_hours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day_of_week VARCHAR(20) NOT NULL,
    day_order INT DEFAULT 0,
    opening_time TIME NOT NULL,
    closing_time TIME NOT NULL,
    is_closed TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment Methods Table
CREATE TABLE payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name_en VARCHAR(100) NOT NULL,
    name_ja VARCHAR(100) NOT NULL,
    description_en VARCHAR(255) NULL,
    description_ja VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reviews Table
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    order_id INT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT NOT NULL,
    is_approved TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shop Settings Table
CREATE TABLE shop_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
