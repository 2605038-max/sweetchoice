<?php
/**
 * Sweet Choice - Authentication and Authorization
 * Secure user session management, password hashing, role verification, and guest-to-account cart merging.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/language.php';

/**
 * Check if a customer or admin is currently logged in
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Check if the logged-in user is an administrator
 */
function isAdmin(): bool {
    return isLoggedIn() && (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
}

/**
 * Get logged-in user's profile array
 */
function getCurrentUser(PDO $pdo): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT id, name, email, phone, postal_code, address, apartment, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

/**
 * Safely merge any active guest cart into the authenticated customer's account cart.
 * - Preserves existing account cart items (fixes orphaned-cart bug).
 * - Merges duplicate items (same product, customization, and pairing discount).
 * - Validates product availability and caps quantities to live stock.
 * - Leaves guest cart untouched if login/registration fails (only called after auth succeeds).
 */
function mergeGuestCartIntoUser(PDO $pdo, int $userId): array {
    $sessionId = $_SESSION['cart_session_id'] ?? '';
    if ($sessionId === '') {
        return ['merged' => false, 'items_moved' => 0, 'adjusted' => false];
    }

    $inTransaction = $pdo->inTransaction();
    try {
        if (!$inTransaction) {
            $pdo->beginTransaction();
        }

        // Find active guest cart for this session
        $guestStmt = $pdo->prepare("SELECT id FROM cart WHERE session_id = ? AND user_id IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $guestStmt->execute([$sessionId]);
        $guestCart = $guestStmt->fetch();

        if (!$guestCart) {
            if (!$inTransaction) {
                $pdo->commit();
            }
            return ['merged' => false, 'items_moved' => 0, 'adjusted' => false];
        }

        $guestCartId = (int)$guestCart['id'];

        // Fetch guest cart items with live stock/availability
        $gItemsStmt = $pdo->prepare("
            SELECT ci.*, p.stock, p.availability
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            WHERE ci.cart_id = ?
        ");
        $gItemsStmt->execute([$guestCartId]);
        $guestItems = $gItemsStmt->fetchAll();

        // Find existing account cart for this user
        $userCartStmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $userCartStmt->execute([$userId]);
        $userCart = $userCartStmt->fetch();

        $itemsMoved = 0;
        $adjusted = false;

        if (!$userCart) {
            // User has no account cart yet: validate items in guest cart and assign it to the user
            foreach ($guestItems as $gItem) {
                $stock = (int)$gItem['stock'];
                if ($gItem['availability'] === 'sold_out' || $stock <= 0) {
                    $del = $pdo->prepare("DELETE FROM cart_items WHERE id = ?");
                    $del->execute([$gItem['id']]);
                    $adjusted = true;
                    continue;
                }
                $qty = (int)$gItem['quantity'];
                $capped = min($stock, max(1, $qty));
                if ($capped !== $qty) {
                    $updQty = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
                    $updQty->execute([$capped, $gItem['id']]);
                    $adjusted = true;
                }
                $itemsMoved++;
            }

            $assignStmt = $pdo->prepare("UPDATE cart SET user_id = ?, updated_at = NOW() WHERE id = ?");
            $assignStmt->execute([$userId, $guestCartId]);
        } else {
            // User already has an account cart: merge guest items into existing account cart
            $userCartId = (int)$userCart['id'];

            foreach ($guestItems as $gItem) {
                $stock = (int)$gItem['stock'];
                if ($gItem['availability'] === 'sold_out' || $stock <= 0) {
                    $adjusted = true;
                    continue;
                }

                $gQty = max(1, (int)$gItem['quantity']);

                // Check for identical product + customization + pairing discount in user cart
                $matchStmt = $pdo->prepare("
                    SELECT id, quantity FROM cart_items
                    WHERE cart_id = ?
                      AND product_id = ?
                      AND customization_summary <=> ?
                      AND pairing_product_id <=> ?
                      AND discount_amount = ?
                    LIMIT 1
                ");
                $matchStmt->execute([
                    $userCartId,
                    (int)$gItem['product_id'],
                    $gItem['customization_summary'],
                    $gItem['pairing_product_id'],
                    (int)($gItem['discount_amount'] ?? 0)
                ]);
                $existing = $matchStmt->fetch();

                if ($existing) {
                    $combined = (int)$existing['quantity'] + $gQty;
                    $capped = min($stock, $combined);
                    if ($capped < $combined) {
                        $adjusted = true;
                    }
                    $upd = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
                    $upd->execute([$capped, (int)$existing['id']]);
                } else {
                    $capped = min($stock, $gQty);
                    if ($capped < $gQty) {
                        $adjusted = true;
                    }
                    $move = $pdo->prepare("UPDATE cart_items SET cart_id = ?, quantity = ? WHERE id = ?");
                    $move->execute([$userCartId, $capped, (int)$gItem['id']]);
                }
                $itemsMoved++;
            }

            // Delete the merged guest cart (cascades any remaining sold-out/duplicate rows)
            $delGuest = $pdo->prepare("DELETE FROM cart WHERE id = ?");
            $delGuest->execute([$guestCartId]);

            $touchUser = $pdo->prepare("UPDATE cart SET updated_at = NOW() WHERE id = ?");
            $touchUser->execute([$userCartId]);
        }

        if (!$inTransaction) {
            $pdo->commit();
        }

        return ['merged' => ($itemsMoved > 0), 'items_moved' => $itemsMoved, 'adjusted' => $adjusted];
    } catch (Exception $e) {
        if (!$inTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Cart merge error for user {$userId}: " . $e->getMessage());
        return ['merged' => false, 'items_moved' => 0, 'adjusted' => false];
    }
}

/**
 * Attempt user authentication
 */
function loginUser(PDO $pdo, string $email, string $password, bool $adminOnly = false): array {
    $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->execute([trim(strtolower($email))]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'error' => __('auth_invalid_credentials', 'Invalid email or password.')];
    }

    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'error' => __('auth_invalid_credentials', 'Invalid email or password.')];
    }

    if ($adminOnly && $user['role'] !== 'admin') {
        return ['success' => false, 'error' => __('auth_admin_required', 'Access denied. Administrator privileges required.')];
    }

    // Set session securely
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];

    // Safely merge any guest cart into customer's account cart
    $mergeResult = ['merged' => false, 'items_moved' => 0, 'adjusted' => false];
    if (!$adminOnly) {
        $mergeResult = mergeGuestCartIntoUser($pdo, (int)$user['id']);
    }

    return ['success' => true, 'user' => $user, 'cart_merge' => $mergeResult];
}

/**
 * Register a new customer
 */
function registerUser(PDO $pdo, array $data): array {
    $name = trim($data['name'] ?? '');
    $email = trim(strtolower($data['email'] ?? ''));
    $password = $data['password'] ?? '';
    $passwordConfirm = $data['password_confirm'] ?? null;
    $phone = trim($data['phone'] ?? '');
    $postal_code = trim($data['postal_code'] ?? '');
    $address = trim($data['address'] ?? '');
    $apartment = trim($data['apartment'] ?? '');

    if ($name === '' || $email === '' || $password === '' || $phone === '' || $postal_code === '' || $address === '') {
        return ['success' => false, 'error' => __('auth_required_fields', 'Please fill in all required fields.')];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => __('auth_invalid_email', 'Please provide a valid email address.')];
    }

    if (strlen($password) < 6) {
        return ['success' => false, 'error' => __('auth_password_min', 'Password must be at least 6 characters long.')];
    }

    if ($passwordConfirm !== null && $password !== $passwordConfirm) {
        return ['success' => false, 'error' => __('auth_password_mismatch', 'Passwords do not match.')];
    }

    // Check if email already registered
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => __('auth_email_exists', 'An account with this email address already exists.')];
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $insertStmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, postal_code, address, apartment, role, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, 'customer', NOW())");
    $insertStmt->execute([$name, $email, $hashedPassword, $phone, $postal_code, $address, $apartment]);
    $userId = (int)$pdo->lastInsertId();

    // Log the user in securely
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role'] = 'customer';

    // Preserve and transfer any guest cart items into the new customer's account
    $mergeResult = mergeGuestCartIntoUser($pdo, $userId);

    return ['success' => true, 'user_id' => $userId, 'cart_merge' => $mergeResult];
}

/**
 * Log out current user
 */
function logoutUser(): void {
    unset(
        $_SESSION['user_id'],
        $_SESSION['user_name'],
        $_SESSION['user_email'],
        $_SESSION['user_role'],
        $_SESSION['cart_session_id'],
        $_SESSION['intended_redirect']
    );
    session_regenerate_id(true);
}

/**
 * Require user login guard
 */
function requireLogin(string $redirect = 'login.php'): void {
    if (!isLoggedIn()) {
        $_SESSION['intended_redirect'] = $_SERVER['REQUEST_URI'];
        header("Location: " . $redirect);
        exit;
    }
}

/**
 * Require administrator role guard
 */
function requireAdmin(string $redirect = '../admin/login.php'): void {
    if (!isAdmin()) {
        header("Location: " . $redirect);
        exit;
    }
}
