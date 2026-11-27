-- Checkout Fulfillment, Payment Methods & Service Fee Migration
-- Adds Dine-in fulfillment support, payment_provider tracking, and updated payment method labels.

-- 1. Update orders.fulfillment_type ENUM to support dine_in, pickup, delivery
ALTER TABLE orders
    MODIFY COLUMN fulfillment_type ENUM('dine_in', 'pickup', 'delivery') NOT NULL;

-- 2. Add payment_provider column to orders if it does not already exist
SET @exist_payment_provider = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'payment_provider'
);
SET @sql_add_provider = IF(
    @exist_payment_provider = 0,
    'ALTER TABLE orders ADD COLUMN payment_provider VARCHAR(50) NULL DEFAULT NULL AFTER payment_method',
    'SELECT 1'
);
PREPARE stmt_add_provider FROM @sql_add_provider;
EXECUTE stmt_add_provider;
DEALLOCATE PREPARE stmt_add_provider;

-- 3. Update payment_methods records for Credit / Debit Card, Online Payment (PayPay / au PAY / Rakuten Pay), and Cash (Dine-in only)
UPDATE payment_methods
SET name_en = 'Credit / Debit Card',
    name_ja = 'クレジットカード / デビットカード',
    description_en = 'Visa, Mastercard, JCB, American Express',
    description_ja = 'Visa, Mastercard, JCB, Amex等対応',
    is_active = 1
WHERE code = 'credit_card';

UPDATE payment_methods
SET name_en = 'Online Payment',
    name_ja = 'オンライン決済',
    description_en = 'PayPay, au PAY, Rakuten Pay',
    description_ja = 'PayPay / au PAY / 楽天ペイ',
    is_active = 1
WHERE code = 'online_pay';

UPDATE payment_methods
SET code = 'cash',
    name_en = 'Cash',
    name_ja = '現金',
    description_en = 'Pay with cash in store (Dine-in only)',
    description_ja = '店内飲食時に現金でお支払いいただけます（店内飲食限定）',
    is_active = 1
WHERE code IN ('cash_on_fulfillment', 'cash');

-- Ensure the three payment methods exist if any were missing
INSERT IGNORE INTO payment_methods (code, name_en, name_ja, description_en, description_ja, is_active) VALUES
('credit_card', 'Credit / Debit Card', 'クレジットカード / デビットカード', 'Visa, Mastercard, JCB, American Express', 'Visa, Mastercard, JCB, Amex等対応', 1),
('online_pay', 'Online Payment', 'オンライン決済', 'PayPay, au PAY, Rakuten Pay', 'PayPay / au PAY / 楽天ペイ', 1),
('cash', 'Cash', '現金', 'Pay with cash in store (Dine-in only)', '店内飲食時に現金でお支払いいただけます（店内飲食限定）', 1);

-- 4. Update shop_settings description for service_fee to clarify Local Delivery only
UPDATE shop_settings
SET description = 'Service fee applied to Local Delivery orders only (in JPY)'
WHERE setting_key = 'service_fee';
