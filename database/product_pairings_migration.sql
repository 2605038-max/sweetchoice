-- Product Pairings Migration
-- Implements table and settings for Recommended Dessert & Drink Pairing with Inventory-Based Discounts

-- 1. Create product_pairings table
CREATE TABLE IF NOT EXISTS product_pairings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    paired_product_id INT NOT NULL,
    custom_discount_percent INT NULL DEFAULT NULL COMMENT 'NULL inherits global setting, 0 disables discount, >0 custom %',
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pairing (product_id, paired_product_id),
    INDEX idx_product (product_id),
    INDEX idx_paired (paired_product_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (paired_product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add pairing columns to cart_items if not exists
SET @exist_cart_pairing = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cart_items' AND COLUMN_NAME = 'pairing_product_id');
SET @sql_cart1 = IF(@exist_cart_pairing = 0, 'ALTER TABLE cart_items ADD COLUMN pairing_product_id INT NULL DEFAULT NULL AFTER customization_price', 'SELECT 1');
PREPARE stmt_cart1 FROM @sql_cart1;
EXECUTE stmt_cart1;
DEALLOCATE PREPARE stmt_cart1;

SET @exist_cart_pct = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cart_items' AND COLUMN_NAME = 'discount_percent');
SET @sql_cart2 = IF(@exist_cart_pct = 0, 'ALTER TABLE cart_items ADD COLUMN discount_percent INT DEFAULT 0 AFTER pairing_product_id', 'SELECT 1');
PREPARE stmt_cart2 FROM @sql_cart2;
EXECUTE stmt_cart2;
DEALLOCATE PREPARE stmt_cart2;

SET @exist_cart_amt = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cart_items' AND COLUMN_NAME = 'discount_amount');
SET @sql_cart3 = IF(@exist_cart_amt = 0, 'ALTER TABLE cart_items ADD COLUMN discount_amount INT DEFAULT 0 AFTER discount_percent', 'SELECT 1');
PREPARE stmt_cart3 FROM @sql_cart3;
EXECUTE stmt_cart3;
DEALLOCATE PREPARE stmt_cart3;

-- 3. Add pairing columns to order_items if not exists
SET @exist_order_orig = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'original_price');
SET @sql_ord1 = IF(@exist_order_orig = 0, 'ALTER TABLE order_items ADD COLUMN original_price INT NULL DEFAULT NULL AFTER price', 'SELECT 1');
PREPARE stmt_ord1 FROM @sql_ord1;
EXECUTE stmt_ord1;
DEALLOCATE PREPARE stmt_ord1;

SET @exist_order_disc = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'discount_amount');
SET @sql_ord2 = IF(@exist_order_disc = 0, 'ALTER TABLE order_items ADD COLUMN discount_amount INT DEFAULT 0 AFTER original_price', 'SELECT 1');
PREPARE stmt_ord2 FROM @sql_ord2;
EXECUTE stmt_ord2;
DEALLOCATE PREPARE stmt_ord2;

SET @exist_order_pair = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'pairing_product_id');
SET @sql_ord3 = IF(@exist_order_pair = 0, 'ALTER TABLE order_items ADD COLUMN pairing_product_id INT NULL DEFAULT NULL AFTER discount_amount', 'SELECT 1');
PREPARE stmt_ord3 FROM @sql_ord3;
EXECUTE stmt_ord3;
DEALLOCATE PREPARE stmt_ord3;

-- 4. Add pairing_discount_total to orders if not exists
SET @exist_orders_disc = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'pairing_discount_total');
SET @sql_orders1 = IF(@exist_orders_disc = 0, 'ALTER TABLE orders ADD COLUMN pairing_discount_total INT DEFAULT 0 AFTER customization_total', 'SELECT 1');
PREPARE stmt_orders1 FROM @sql_orders1;
EXECUTE stmt_orders1;
DEALLOCATE PREPARE stmt_orders1;

-- 5. Insert shop settings for pairing discount
INSERT INTO shop_settings (setting_key, setting_value, description)
VALUES 
    ('pairing_discount_percent', '10', 'Default discount percentage for recommended product pairing (inventory-based)'),
    ('pairing_inventory_threshold', '20', 'Minimum stock threshold for recommended product to qualify for inventory discount'),
    ('pairing_discount_enabled', '1', 'Global master switch for pairing discounts (1 = enabled, 0 = disabled)')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    description = VALUES(description);
