<?php
/**
 * Sweet Choice - Checkout Entry Router
 *
 * Enforces the required customer flow:
 *   Cart (guest OK) -> Login/Register (merge cart) -> Eligible? -> Mini-Game (Sweet Run) -> Payment -> Confirmation
 *
 * - Guests are redirected to login.php?from=checkout while preserving their guest cart intact.
 * - Logged-in customers have their cart totals computed server-side (product price x quantity ONLY).
 * - Orders >= ¥3,000 proceed to checkout-game.php (Sweet Run mini-game).
 * - Orders < ¥3,000 skip the mini-game automatically with a bilingual explanation and go to checkout.php (Payment).
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/checkout.php';

$pdo = getDB();

// 1. Require login or registration only at checkout entry (guest cart stays untouched!)
if (!isLoggedIn()) {
    $_SESSION['intended_redirect'] = 'checkout-start.php';
    header('Location: login.php?from=checkout');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$cartId = getOrCreateCartId($pdo);
$totals = calculateCartTotals($pdo, $cartId);

if (empty($totals['items'])) {
    header('Location: cart.php');
    exit;
}

// 2. Create or synchronize active checkout session on the server
$checkoutSession = getOrCreateCheckoutSession($pdo, $userId, $cartId, $totals);

if (!empty($checkoutSession['discount_just_voided'])) {
    setFlash('info', __('checkout_discount_voided', 'Your cart subtotal dropped below ¥3,000, so the mini-game discount was removed.'));
}

// 3. Route based on server-calculated eligible product subtotal (product price x quantity ONLY)
if ($totals['is_minigame_eligible']) {
    if ($checkoutSession['game_stage'] === 'done') {
        header('Location: checkout.php');
        exit;
    }
    header('Location: checkout-game.php');
    exit;
}

// Not eligible (< ¥3,000 product subtotal): skip mini-game automatically with clear bilingual notice
setFlash('info', __('minigame_not_qualified_msg', 'Your order does not meet the ¥3,000 minimum purchase requirement (product subtotal only). Proceeding directly to payment settings.'));
header('Location: checkout.php');
exit;
