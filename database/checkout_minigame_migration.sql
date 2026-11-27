-- Sweet Choice: Complete Customer Checkout System & Sweet Run Mini-Game Discount Migration
-- Compatible with MySQL 5.7+
-- Additive migration only: preserves all existing users, products, carts, orders, and legacy campaign records.

CREATE TABLE IF NOT EXISTS checkout_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    public_token CHAR(64) NOT NULL,
    user_id INT NOT NULL,
    cart_id INT NOT NULL,
    status ENUM('active','completed','expired') NOT NULL DEFAULT 'active',
    eligible_subtotal INT NOT NULL DEFAULT 0 COMMENT 'Sum of product price x quantity (excludes customizations, delivery, discounts)',
    game_stage ENUM('pending','skipped','done') NOT NULL DEFAULT 'pending',
    game_discount_amount INT NOT NULL DEFAULT 0 COMMENT '0 or 200 JPY max per order',
    game_discount_status ENUM('none','earned','applied','voided') NOT NULL DEFAULT 'none',
    submit_token CHAR(64) NULL,
    order_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_public_token (public_token),
    UNIQUE KEY uq_order_id (order_id),
    KEY idx_user_status (user_id, status),
    KEY idx_cart_status (cart_id, status),
    CONSTRAINT fk_cs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cs_cart FOREIGN KEY (cart_id) REFERENCES cart(id) ON DELETE CASCADE,
    CONSTRAINT fk_cs_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS checkout_ad_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checkout_session_id INT NOT NULL,
    user_id INT NOT NULL,
    provider VARCHAR(30) NOT NULL DEFAULT 'demo',
    reward_txn_id CHAR(64) NOT NULL,
    sequence_no TINYINT NOT NULL COMMENT '1 or 2 max per checkout session',
    demo_completed TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pending','granted','rejected') NOT NULL DEFAULT 'pending',
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reward_txn (reward_txn_id),
    UNIQUE KEY uq_session_seq (checkout_session_id, sequence_no),
    KEY idx_ad_user (user_id),
    CONSTRAINT fk_ad_session FOREIGN KEY (checkout_session_id) REFERENCES checkout_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_ad_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS checkout_game_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checkout_session_id INT NOT NULL,
    user_id INT NOT NULL,
    attempt_number TINYINT NOT NULL COMMENT '1 (free), 2 (ad), 3 (ad)',
    attempt_type ENUM('free','demo_ad') NOT NULL,
    ad_reward_id INT NULL,
    attempt_token CHAR(64) NOT NULL,
    status ENUM('started','finished','rejected','abandoned') NOT NULL DEFAULT 'started',
    score INT NULL,
    is_winning TINYINT(1) NOT NULL DEFAULT 0,
    duration_seconds INT NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attempt_token (attempt_token),
    UNIQUE KEY uq_session_attempt (checkout_session_id, attempt_number),
    UNIQUE KEY uq_ad_reward (ad_reward_id),
    KEY idx_ga_user (user_id),
    CONSTRAINT fk_ga_session FOREIGN KEY (checkout_session_id) REFERENCES checkout_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_ga_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ga_ad FOREIGN KEY (ad_reward_id) REFERENCES checkout_ad_rewards(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add mini-game discount and checkout session link to orders table
ALTER TABLE orders
    ADD COLUMN game_discount_total INT NOT NULL DEFAULT 0 COMMENT 'JPY mini-game discount (max 200)' AFTER pairing_discount_total,
    ADD COLUMN checkout_session_id INT NULL AFTER game_discount_total,
    ADD UNIQUE KEY uq_orders_checkout_session (checkout_session_id);
