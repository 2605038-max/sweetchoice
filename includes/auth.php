<?php
/**
 * Sweet Choice - Authentication and Authorization
 * Secure user session management, password hashing, and role verification.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

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
 * Attempt user authentication
 */
function loginUser(PDO $pdo, string $email, string $password, bool $adminOnly = false): array {
    $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    if ($adminOnly && $user['role'] !== 'admin') {
        return ['success' => false, 'error' => 'Access denied. Administrator privileges required.'];
    }

    // Set session securely
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];

    // Merge guest cart with user cart if any exists
    if (!empty($_SESSION['cart_session_id'])) {
        $sessionId = $_SESSION['cart_session_id'];
        $stmtCart = $pdo->prepare("SELECT id FROM cart WHERE session_id = ? AND (user_id IS NULL OR user_id = ?)");
        $stmtCart->execute([$sessionId, $user['id']]);
        $existingCart = $stmtCart->fetch();
        if ($existingCart) {
            $stmtUpdate = $pdo->prepare("UPDATE cart SET user_id = ? WHERE id = ?");
            $stmtUpdate->execute([$user['id'], $existingCart['id']]);
        }
    }

    return ['success' => true, 'user' => $user];
}

/**
 * Register a new customer
 */
function registerUser(PDO $pdo, array $data): array {
    $name = trim($data['name'] ?? '');
    $email = trim(strtolower($data['email'] ?? ''));
    $password = $data['password'] ?? '';
    $phone = trim($data['phone'] ?? '');
    $postal_code = trim($data['postal_code'] ?? '');
    $address = trim($data['address'] ?? '');
    $apartment = trim($data['apartment'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        return ['success' => false, 'error' => 'Please fill in all required fields (Name, Email, Password).'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Please provide a valid email address.'];
    }

    if (strlen($password) < 6) {
        return ['success' => false, 'error' => 'Password must be at least 6 characters long.'];
    }

    // Check if email already registered
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'An account with this email address already exists.'];
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $insertStmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, postal_code, address, apartment, role, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, 'customer', NOW())");
    $insertStmt->execute([$name, $email, $hashedPassword, $phone, $postal_code, $address, $apartment]);
    $userId = (int)$pdo->lastInsertId();

    // Log the user in
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role'] = 'customer';

    return ['success' => true, 'user_id' => $userId];
}

/**
 * Log out current user
 */
function logoutUser(): void {
    $_SESSION['user_id'] = null;
    $_SESSION['user_name'] = null;
    $_SESSION['user_email'] = null;
    $_SESSION['user_role'] = null;
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role']);
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
