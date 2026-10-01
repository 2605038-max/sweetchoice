<?php
/**
 * Sweet Choice - Core Helper Functions
 * Includes centralized currency formatting, cart, favorites, security, and rendering utilities.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Japanese Yen (JPY) Currency Formatter
 * Requirement: Japanese Yen (¥ / JPY) is the ONLY currency.
 * e.g. formatPrice(1280) => "¥1,280"
 */
function formatPrice($amount): string {
    $numericAmount = is_numeric($amount) ? (int)round((float)$amount) : 0;
    return '¥' . number_format($numericAmount);
}

/**
 * HTML Escaping / Sanitization Helper
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Safe Input Sanitization
 */
function sanitizeInput(mixed $data): mixed {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return is_string($data) ? trim($data) : $data;
}

/**
 * Generate or retrieve CSRF token
 */
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify submitted CSRF token
 */
function verifyCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set flash message
 */
function setFlash(string $type, string $message): void {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash messages
 */
function getFlashes(): array {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/**
 * Session ID identifier for guest carts
 */
function getCartSessionId(): string {
    if (empty($_SESSION['cart_session_id'])) {
        $_SESSION['cart_session_id'] = session_id() ?: bin2hex(random_bytes(16));
    }
    return $_SESSION['cart_session_id'];
}

/**
 * Retrieve or create active Cart ID from MySQL
 */
function getOrCreateCartId(PDO $pdo): int {
    $userId = $_SESSION['user_id'] ?? null;
    $sessionId = getCartSessionId();

    if ($userId) {
        // Try finding by user_id first
        $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId]);
        $cart = $stmt->fetch();
        if ($cart) {
            return (int)$cart['id'];
        }

        // Check if there was an existing guest cart for this session, associate with user
        $stmt = $pdo->prepare("SELECT id FROM cart WHERE session_id = ? AND user_id IS NULL ORDER BY id DESC LIMIT 1");
        $stmt->execute([$sessionId]);
        $guestCart = $stmt->fetch();
        if ($guestCart) {
            $stmt = $pdo->prepare("UPDATE cart SET user_id = ? WHERE id = ?");
            $stmt->execute([$userId, $guestCart['id']]);
            return (int)$guestCart['id'];
        }

        // Create new cart for user
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, session_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$userId, $sessionId]);
        return (int)$pdo->lastInsertId();
    } else {
        // Guest cart
        $stmt = $pdo->prepare("SELECT id FROM cart WHERE session_id = ? AND user_id IS NULL ORDER BY id DESC LIMIT 1");
        $stmt->execute([$sessionId]);
        $cart = $stmt->fetch();
        if ($cart) {
            return (int)$cart['id'];
        }

        $stmt = $pdo->prepare("INSERT INTO cart (user_id, session_id, created_at) VALUES (NULL, ?, NOW())");
        $stmt->execute([$sessionId]);
        return (int)$pdo->lastInsertId();
    }
}

/**
 * Get total quantity count of items in the current cart
 */
function getCartItemCount(PDO $pdo): int {
    $cartId = getOrCreateCartId($pdo);
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = ?");
    $stmt->execute([$cartId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Get total favorites count for current user
 */
function getFavoritesCount(PDO $pdo): int {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        $favs = $_SESSION['guest_favorites'] ?? [];
        return count($favs);
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Check if a product is favorited
 */
function isProductFavorited(PDO $pdo, int $productId, ?int $userId = null): bool {
    if (!$userId && isset($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
    }
    if (!$userId) {
        $favs = $_SESSION['guest_favorites'] ?? [];
        return in_array($productId, $favs, true);
    }
    $stmt = $pdo->prepare("SELECT 1 FROM favorites WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Retrieve shop setting from database
 */
function getShopSetting(PDO $pdo, string $key, string $default = ''): string {
    static $settingsCache = [];
    if (isset($settingsCache[$key])) {
        return $settingsCache[$key];
    }
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM shop_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        $settingsCache[$key] = ($val !== false) ? $val : $default;
        return $settingsCache[$key];
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Return formatted badge HTML for product availability
 */
function renderAvailabilityBadge(string $availability): string {
    switch ($availability) {
        case 'sold_out':
            return '<span class="badge badge-sold-out">' . e(__('out_of_stock', 'Sold Out')) . '</span>';
        case 'limited':
            return '<span class="badge badge-limited">' . e(__('limited_stock', 'Limited')) . '</span>';
        case 'available':
        default:
            return '<span class="badge badge-available">' . e(__('available', 'Available')) . '</span>';
    }
}

/**
 * Return stars HTML (1 to 5)
 */
function renderStars(float $rating): string {
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);

    $html = '<div class="star-rating" title="' . number_format($rating, 1) . ' / 5">';
    for ($i = 0; $i < $fullStars; $i++) {
        $html .= '<span class="star star-full">★</span>';
    }
    if ($halfStar) {
        $html .= '<span class="star star-half">★</span>';
    }
    for ($i = 0; $i < $emptyStars; $i++) {
        $html .= '<span class="star star-empty">☆</span>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Return order status badge HTML
 */
function renderOrderStatusBadge(string $status): string {
    $class = match($status) {
        'Completed', 'Delivered' => 'badge-status-completed',
        'Order Received' => 'badge-status-received',
        'Preparing' => 'badge-status-preparing',
        'Ready for Pickup', 'Out for Delivery' => 'badge-status-active',
        'Cancelled' => 'badge-status-cancelled',
        default => 'badge-status-default'
    };

    $statusKey = match($status) {
        'Order Received' => 'status_received',
        'Preparing' => 'status_preparing',
        'Ready for Pickup' => 'status_ready_pickup',
        'Out for Delivery' => 'status_out_delivery',
        'Completed' => 'status_completed',
        'Delivered' => 'status_delivered',
        'Cancelled' => 'status_cancelled',
        default => ''
    };

    $label = $statusKey ? __($statusKey, $status) : $status;
    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}
