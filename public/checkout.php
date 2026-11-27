<?php
/**
 * Sweet Choice - Checkout Payment & Fulfillment Settings Page
 *
 * Requires authenticated user + active checkout session.
 * - Preserves all existing Pickup & Delivery options, time slots, DB distance tiers, service fee, and payment methods.
 * - Applies server-verified Mini-Game Discount (¥200 max, applied once, never negative).
 * - Preserves earned discount across payment validation retries.
 * - Prevents duplicate order submissions via single-use submit_token + DB transaction lock.
 * - Never stores raw credit card numbers or CVV.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pairings.php';
require_once __DIR__ . '/../includes/checkout.php';

$pdo = getDB();

// 1. Mandatory Login/Registration at Checkout (guest cart preserved intact)
if (!isLoggedIn()) {
    $_SESSION['intended_redirect'] = 'checkout-start.php';
    header('Location: login.php?from=checkout');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$cartId = getOrCreateCartId($pdo);
$totals = calculateCartTotals($pdo, $cartId);
$cartItems = $totals['items'];

if (empty($cartItems)) {
    header('Location: cart.php');
    exit;
}

$checkoutSession = getOrCreateCheckoutSession($pdo, $userId, $cartId, $totals);

if (!empty($checkoutSession['discount_just_voided'])) {
    setFlash('info', __('checkout_discount_voided', 'Your cart subtotal dropped below ¥3,000, so the mini-game discount was removed.'));
}

// If order is eligible and customer hasn't visited the mini-game stage yet (GET), route them to Mini-Game first
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $totals['is_minigame_eligible'] && $checkoutSession['game_stage'] === 'pending') {
    header('Location: checkout-game.php');
    exit;
}

$productSubtotal = $totals['eligible_product_subtotal'];
$pairingDiscountTotal = $totals['pairing_discount_total'];
$effectiveProductSubtotal = $totals['effective_product_subtotal'];
$customizationTotal = $totals['customization_total'];
$serviceFee = $totals['service_fee'];
$isMinigameEligible = $totals['is_minigame_eligible'];
$gameDiscountAmount = ($isMinigameEligible && $checkoutSession['game_discount_status'] === 'earned')
    ? min(MINIGAME_DISCOUNT_AMOUNT, (int)$checkoutSession['game_discount_amount'])
    : 0;

// Fetch Delivery Distance Tiers, Pickup Slots, Delivery Slots, and Payment Methods from MySQL
$deliveryTiers = $pdo->query("SELECT * FROM delivery_settings WHERE is_active = 1 ORDER BY min_km ASC")->fetchAll();
$pickupSlots = $pdo->query("SELECT * FROM pickup_slots WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
$deliverySlots = $pdo->query("SELECT * FROM delivery_slots WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
$paymentMethods = $pdo->query("SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY id ASC")->fetchAll();

// Pre-fill user profile info
$user = getCurrentUser($pdo);

// Preserve submitted values on validation retry
$postedFulfillment = ($_POST['fulfillment_type'] ?? 'pickup') === 'delivery' ? 'delivery' : 'pickup';
$postedPaymentMethod = $_POST['payment_method'] ?? ($paymentMethods[0]['code'] ?? 'credit_card');
$postedDistanceTierId = (int)($_POST['distance_tier_id'] ?? ($deliveryTiers[0]['id'] ?? 0));

// Handle Order Placement POST
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = __('auth_csrf_expired', 'Session expired. Please refresh and try again.');
    }

    $submittedToken = trim($_POST['submit_token'] ?? '');
    if ($submittedToken === '' || empty($checkoutSession['submit_token']) || !hash_equals($checkoutSession['submit_token'], $submittedToken)) {
        $errors[] = __('checkout_err_duplicate_submit', 'This order has already been submitted. Please check your Order History.');
    }

    $fulfillment = $postedFulfillment;
    $name = trim($_POST['customer_name'] ?? '');
    $email = trim(strtolower($_POST['customer_email'] ?? ''));
    $phone = trim($_POST['customer_phone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? 'credit_card');

    $pickupDate = $_POST['pickup_date'] ?? null;
    $pickupTime = $_POST['pickup_time'] ?? null;
    $deliveryDate = $_POST['delivery_date'] ?? null;
    $deliveryTime = $_POST['delivery_time'] ?? null;
    $postalCode = trim($_POST['postal_code'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $apartment = trim($_POST['apartment'] ?? '');
    $distanceTierId = $postedDistanceTierId;

    if ($name === '' || $email === '' || $phone === '') {
        $errors[] = __('checkout_err_contact', 'Please fill in your name, email, and phone number.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = __('auth_invalid_email', 'Please provide a valid email address.');
    }

    // Validate payment method against active DB payment methods
    $validPmCodes = array_column($paymentMethods, 'code');
    if (!empty($validPmCodes) && !in_array($paymentMethod, $validPmCodes, true)) {
        $paymentMethod = $validPmCodes[0];
    }

    $deliveryFee = 0;
    $distanceKm = 0.0;

    if ($fulfillment === 'pickup') {
        if (empty($pickupDate) || empty($pickupTime)) {
            $errors[] = __('checkout_err_pickup_slot', 'Please select a pickup date and time slot.');
        }
    } else {
        if ($postalCode === '' || $address === '') {
            $errors[] = __('checkout_err_delivery_addr', 'Please provide a full delivery postal code and street address.');
        }
        if (empty($deliveryDate) || empty($deliveryTime)) {
            $errors[] = __('checkout_err_delivery_slot', 'Please select a delivery date and time slot.');
        }

        // Resolve delivery fee strictly from database delivery_settings
        $tierFound = false;
        foreach ($deliveryTiers as $tier) {
            if ((int)$tier['id'] === $distanceTierId) {
                $deliveryFee = (int)$tier['fee'];
                $distanceKm = (float)$tier['max_km'];
                $tierFound = true;
                break;
            }
        }
        if (!$tierFound && !empty($deliveryTiers)) {
            $deliveryFee = (int)$deliveryTiers[0]['fee'];
            $distanceKm = (float)$deliveryTiers[0]['max_km'];
        }
    }

    if ($totals['has_stock_issue']) {
        $errors[] = __('checkout_err_stock', 'Some items in your cart are out of stock or exceed available inventory. Please review your cart.');
    }

    if (empty($errors)) {
        $orderNumber = 'SWC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        try {
            $pdo->beginTransaction();

            // 1. Lock checkout session to prevent duplicate submission or double discount application
            $lockStmt = $pdo->prepare("
                SELECT id, status, order_id, submit_token, game_discount_amount, game_discount_status
                FROM checkout_sessions
                WHERE id = ? AND user_id = ?
                FOR UPDATE
            ");
            $lockStmt->execute([$checkoutSession['id'], $userId]);
            $lockedSession = $lockStmt->fetch();

            if (
                !$lockedSession ||
                $lockedSession['status'] !== 'active' ||
                !empty($lockedSession['order_id']) ||
                empty($lockedSession['submit_token']) ||
                !hash_equals($lockedSession['submit_token'], $submittedToken)
            ) {
                $pdo->rollBack();
                $errors[] = __('checkout_err_duplicate_submit', 'This order has already been submitted. Please check your Order History.');
            } else {
                // 2. Recompute live totals and verify stock inside transaction
                $freshTotals = calculateCartTotals($pdo, $cartId);
                if (empty($freshTotals['items']) || $freshTotals['has_stock_issue']) {
                    $pdo->rollBack();
                    $errors[] = __('checkout_err_stock', 'Some items in your cart are out of stock or exceed available inventory.');
                } else {
                    $appliedGameDiscount = (
                        $freshTotals['is_minigame_eligible'] &&
                        $lockedSession['game_discount_status'] === 'earned'
                    ) ? min(MINIGAME_DISCOUNT_AMOUNT, max(0, (int)$lockedSession['game_discount_amount'])) : 0;

                    $grandTotal = calculateFinalPayableTotal($freshTotals, $deliveryFee, $appliedGameDiscount);

                    // Cash on pickup/delivery stays 'pending'; simulated card/online pay marked 'paid'
                    $paymentStatus = ($paymentMethod === 'cash_on_fulfillment') ? 'pending' : 'paid';

                    // 3. Insert order
                    $orderInsert = $pdo->prepare("
                        INSERT INTO orders
                            (order_number, user_id, fulfillment_type, pickup_date, pickup_time, delivery_date, delivery_time, distance_km,
                             customer_name, customer_email, customer_phone, postal_code, address, apartment,
                             subtotal, customization_total, pairing_discount_total, game_discount_total, checkout_session_id,
                             delivery_fee, service_fee, grand_total, payment_method, payment_status, status, notes, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Order Received', ?, NOW())
                    ");

                    $orderInsert->execute([
                        $orderNumber,
                        $userId,
                        $fulfillment,
                        $fulfillment === 'pickup' ? $pickupDate : null,
                        $fulfillment === 'pickup' ? $pickupTime : null,
                        $fulfillment === 'delivery' ? $deliveryDate : null,
                        $fulfillment === 'delivery' ? $deliveryTime : null,
                        $distanceKm,
                        $name,
                        $email,
                        $phone,
                        $postalCode,
                        $address,
                        $apartment,
                        $freshTotals['effective_product_subtotal'],
                        $freshTotals['customization_total'],
                        $freshTotals['pairing_discount_total'],
                        $appliedGameDiscount,
                        (int)$lockedSession['id'],
                        $deliveryFee,
                        $freshTotals['service_fee'],
                        $grandTotal,
                        $paymentMethod,
                        $paymentStatus,
                        $notes,
                    ]);
                    $orderId = (int)$pdo->lastInsertId();

                    // 4. Insert order items, customizations, and decrement stock atomically
                    $itemInsert = $pdo->prepare("
                        INSERT INTO order_items
                            (order_id, product_id, product_name, price, original_price, discount_amount, pairing_product_id, quantity, customization_summary, customization_price, subtotal)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $customInsert = $pdo->prepare("
                        INSERT INTO order_customizations (order_item_id, group_name, option_name, price_extra)
                        VALUES (?, ?, ?, ?)
                    ");
                    $updStock = $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?");

                    foreach ($freshTotals['items'] as $cItem) {
                        $unitDisc = (int)($cItem['discount_amount'] ?? 0);
                        $unitFinalPrice = max(0, (int)$cItem['price'] - $unitDisc);
                        $lineSubtotal = ($unitFinalPrice + (int)$cItem['customization_price']) * (int)$cItem['quantity'];

                        $itemInsert->execute([
                            $orderId,
                            $cItem['product_id'],
                            $cItem['name_en'],
                            $unitFinalPrice,
                            $cItem['price'],
                            $unitDisc,
                            $cItem['pairing_product_id'] ?? null,
                            $cItem['quantity'],
                            $cItem['customization_summary'],
                            $cItem['customization_price'],
                            $lineSubtotal,
                        ]);
                        $orderItemId = (int)$pdo->lastInsertId();

                        if (!empty($cItem['customization_summary'])) {
                            $parts = explode(' | ', $cItem['customization_summary']);
                            foreach ($parts as $part) {
                                $subParts = explode(': ', $part, 2);
                                $gName = trim($subParts[0] ?? 'Option');
                                $oName = trim($subParts[1] ?? '');
                                $customInsert->execute([$orderItemId, $gName, $oName, 0]);
                            }
                        }

                        $updStock->execute([(int)$cItem['quantity'], (int)$cItem['product_id']]);
                    }

                    // 5. Mark checkout session completed & link order_id (prevents reuse & double submit)
                    $finalDiscStatus = $appliedGameDiscount > 0 ? 'applied' : $lockedSession['game_discount_status'];
                    $closeSession = $pdo->prepare("
                        UPDATE checkout_sessions
                        SET status = 'completed',
                            game_stage = 'done',
                            game_discount_amount = ?,
                            game_discount_status = ?,
                            order_id = ?,
                            submit_token = NULL,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $closeSession->execute([$appliedGameDiscount, $finalDiscStatus, $orderId, (int)$lockedSession['id']]);

                    // 6. Clear cart items
                    $clearCart = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?");
                    $clearCart->execute([$cartId]);

                    $pdo->commit();

                    // Remember recently placed order for confirmation view
                    $_SESSION['recent_order_numbers'] = $_SESSION['recent_order_numbers'] ?? [];
                    $_SESSION['recent_order_numbers'][] = $orderNumber;

                    header('Location: order-confirmation.php?order=' . urlencode($orderNumber));
                    exit;
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Checkout place_order error: ' . $e->getMessage());
            $errors[] = __('checkout_err_unexpected', 'An unexpected error occurred while placing your order. Please try again.');
        }
    }
}

$initialDeliveryFee = 0;
if ($postedFulfillment === 'delivery' && !empty($deliveryTiers)) {
    foreach ($deliveryTiers as $tier) {
        if ((int)$tier['id'] === $postedDistanceTierId) {
            $initialDeliveryFee = (int)$tier['fee'];
            break;
        }
    }
}
$initialFinalTotal = calculateFinalPayableTotal($totals, $initialDeliveryFee, $gameDiscountAmount);

$pageTitle = __('checkout_payment_title', 'Payment & Fulfillment Settings');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container checkout-page-wrap">
    <div class="checkout-page-header">
        <div>
            <span class="section-tag"><?= e(__('step_payment', 'Payment')); ?></span>
            <h1 class="section-title checkout-page-title"><?= e(__('checkout_payment_title', 'Payment & Fulfillment Settings')); ?></h1>
        </div>
        <div class="checkout-header-actions">
            <?php if ($isMinigameEligible): ?>
                <a href="checkout-game.php" class="btn btn-secondary btn-sm">&larr; <?= e(__('checkout_back_to_game', 'Back to Mini-Game')); ?></a>
            <?php endif; ?>
            <a href="cart.php" class="btn btn-secondary btn-sm">&larr; <?= e(__('checkout_back_to_cart', 'Back to Cart')); ?></a>
        </div>
    </div>

    <?php if ($gameDiscountAmount > 0): ?>
        <div class="alert alert-success" style="margin-bottom:20px;">
            🎉 <strong><?= e(__('minigame_congrats_discount', 'Congratulations! You earned a ¥200 discount!')); ?></strong>
        </div>
    <?php elseif (!$isMinigameEligible): ?>
        <div class="alert alert-info" style="margin-bottom:20px;">
            ℹ️ <?= e(__('minigame_not_qualified_msg', 'Your order does not meet the ¥3,000 minimum purchase requirement (product subtotal only).')); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul style="margin-left:20px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="checkout.php" method="POST" id="checkout-form"
          data-subtotal="<?= $effectiveProductSubtotal; ?>"
          data-custom="<?= $customizationTotal; ?>"
          data-service-fee="<?= $serviceFee; ?>"
          data-game-discount="<?= $gameDiscountAmount; ?>">
        <input type="hidden" name="action" value="place_order">
        <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
        <input type="hidden" name="submit_token" value="<?= e($checkoutSession['submit_token'] ?? ''); ?>">
        <input type="hidden" name="fulfillment_type" id="fulfillment-type-input" value="<?= e($postedFulfillment); ?>">

        <div class="checkout-layout">
            <!-- Left Column: Forms -->
            <div>
                <!-- 1. Fulfillment Toggle (Pickup vs Delivery) -->
                <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:24px; margin-bottom:24px; box-shadow:var(--shadow-sm);">
                    <h3 style="font-size:1.15rem; margin-bottom:16px;">📦 <?= e(__('fulfillment_choice', 'Fulfillment Option')); ?></h3>

                    <div class="fulfillment-tabs">
                        <div class="tab-btn <?= $postedFulfillment === 'pickup' ? 'active' : ''; ?>" id="tab-pickup">
                            <div class="tab-btn-title">🏬 <?= e(__('pickup_option', 'Store Pickup')); ?></div>
                            <div class="tab-btn-desc"><?= e(__('pickup_desc', 'Pick up at our Ginza boutique with zero delivery fees.')); ?></div>
                        </div>
                        <div class="tab-btn <?= $postedFulfillment === 'delivery' ? 'active' : ''; ?>" id="tab-delivery">
                            <div class="tab-btn-title">🚚 <?= e(__('delivery_option', 'Local Delivery')); ?></div>
                            <div class="tab-btn-desc"><?= e(__('delivery_desc', 'Hand-delivered to your door in chilled bags.')); ?></div>
                        </div>
                    </div>

                    <!-- Pickup Details Section -->
                    <div id="pickup-details-section" style="display:<?= $postedFulfillment === 'pickup' ? 'block' : 'none'; ?>;">
                        <h4 style="font-size:0.95rem; margin-bottom:12px; color:var(--color-deep-plum);">
                            <?= e(__('pickup_details', 'Pickup Date & Time')); ?>
                        </h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><?= e(__('select_date', 'Pickup Date')); ?> *</label>
                                <input type="date" name="pickup_date" class="form-control"
                                       min="<?= date('Y-m-d'); ?>"
                                       value="<?= e($_POST['pickup_date'] ?? date('Y-m-d')); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?= e(__('select_time', 'Pickup Time Slot')); ?> *</label>
                                <select name="pickup_time" class="form-control">
                                    <?php foreach ($pickupSlots as $slot): ?>
                                        <option value="<?= e($slot['slot_time']); ?>" <?= (($_POST['pickup_time'] ?? '') === $slot['slot_time']) ? 'selected' : ''; ?>>
                                            <?= e($slot['slot_time']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <p style="font-size:0.82rem; color:var(--color-secondary-text);">
                            📍 <?= e(__('checkout_pickup_location', 'Pickup Location:')); ?>
                            <strong><?= e(getShopSetting($pdo, 'shop_address', 'Ginza 4-2-11, Chuo-ku, Tokyo (Sweet Choice)')); ?></strong>
                        </p>
                    </div>

                    <!-- Delivery Details Section -->
                    <div id="delivery-details-section" style="display:<?= $postedFulfillment === 'delivery' ? 'block' : 'none'; ?>;">
                        <h4 style="font-size:0.95rem; margin-bottom:12px; color:var(--color-deep-plum);">
                            <?= e(__('delivery_details', 'Delivery Details & Address')); ?>
                        </h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><?= e(__('select_date', 'Delivery Date')); ?> *</label>
                                <input type="date" name="delivery_date" class="form-control"
                                       min="<?= date('Y-m-d'); ?>"
                                       value="<?= e($_POST['delivery_date'] ?? date('Y-m-d')); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?= e(__('select_time', 'Delivery Time Slot')); ?> *</label>
                                <select name="delivery_time" class="form-control">
                                    <?php foreach ($deliverySlots as $dSlot): ?>
                                        <option value="<?= e($dSlot['slot_time']); ?>" <?= (($_POST['delivery_time'] ?? '') === $dSlot['slot_time']) ? 'selected' : ''; ?>>
                                            <?= e($dSlot['slot_time']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Distance Tiers from DB -->
                        <div class="form-group">
                            <label class="form-label"><?= e(__('distance_tier', 'Estimated Delivery Distance Area')); ?> *</label>
                            <select name="distance_tier_id" id="distance-tier-select" class="form-control">
                                <?php foreach ($deliveryTiers as $tier): ?>
                                    <option value="<?= $tier['id']; ?>" data-fee="<?= $tier['fee']; ?>" <?= ((int)$tier['id'] === $postedDistanceTierId) ? 'selected' : ''; ?>>
                                        <?= e(isJapanese() ? $tier['tier_label_ja'] : $tier['tier_label_en']); ?> &mdash; <?= formatPrice($tier['fee']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><?= e(__('postal_code', 'Postal Code')); ?> *</label>
                                <input type="text" name="postal_code" class="form-control" value="<?= e($_POST['postal_code'] ?? ($user['postal_code'] ?? '')); ?>" placeholder="104-0061">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?= e(__('apartment_unit', 'Apartment / Building')); ?></label>
                                <input type="text" name="apartment" class="form-control" value="<?= e($_POST['apartment'] ?? ($user['apartment'] ?? '')); ?>" placeholder="<?= isJapanese() ? 'メゾン銀座 302号室' : 'Maison Ginza #302'; ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?= e(__('street_address', 'Street Address')); ?> *</label>
                            <input type="text" name="address" class="form-control" value="<?= e($_POST['address'] ?? ($user['address'] ?? '')); ?>" placeholder="<?= isJapanese() ? '東京都中央区銀座4-2-11' : 'Tokyo, Chuo-ku, Ginza 4-2-11'; ?>">
                        </div>
                    </div>
                </div>

                <!-- 2. Customer Contact Information -->
                <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:24px; margin-bottom:24px; box-shadow:var(--shadow-sm);">
                    <h3 style="font-size:1.15rem; margin-bottom:16px;">👤 <?= e(__('customer_info', 'Customer Information')); ?></h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><?= e(__('full_name', 'Full Name')); ?> *</label>
                            <input type="text" name="customer_name" class="form-control" value="<?= e($_POST['customer_name'] ?? ($user['name'] ?? '')); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= e(__('phone_number', 'Phone Number')); ?> *</label>
                            <input type="tel" name="customer_phone" class="form-control" value="<?= e($_POST['customer_phone'] ?? ($user['phone'] ?? '')); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e(__('email_address', 'Email Address')); ?> *</label>
                        <input type="email" name="customer_email" class="form-control" value="<?= e($_POST['customer_email'] ?? ($user['email'] ?? '')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e(__('order_notes', 'Special Instructions / Notes')); ?></label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="<?= e(__('checkout_notes_placeholder', 'Allergies, birthday candles, delivery instructions...')); ?>"><?= e($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- 3. Payment Method & Simulated Card Form (Never stores card number or CVV) -->
                <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm);">
                    <h3 style="font-size:1.15rem; margin-bottom:16px;">💳 <?= e(__('payment_section', 'Payment Method')); ?></h3>

                    <div style="margin-bottom:18px;">
                        <?php foreach ($paymentMethods as $pm): ?>
                            <label class="custom-option-label" style="display:flex; margin-bottom:10px; width:100%;">
                                <input type="radio" name="payment_method" value="<?= e($pm['code']); ?>" <?= ($postedPaymentMethod === $pm['code']) ? 'checked' : ''; ?>>
                                <div style="margin-left:8px;">
                                    <strong><?= e(isJapanese() ? $pm['name_ja'] : $pm['name_en']); ?></strong>
                                    <div style="font-size:0.8rem; color:var(--color-secondary-text);">
                                        <?= e(isJapanese() ? $pm['description_ja'] : $pm['description_en']); ?>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Simulated Card Inputs (intentionally no name attributes so card/CVV are never submitted or stored) -->
                    <div id="mock-card-form" style="background:#FAF7F6; padding:18px; border-radius:var(--radius-sm); border:1px solid var(--color-border);">
                        <div class="form-group">
                            <label class="form-label"><?= e(__('card_number', 'Card Number')); ?></label>
                            <input type="text" class="form-control" placeholder="4242 &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; 4242" maxlength="19" value="4242 •••• •••• 4242" autocomplete="off">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><?= e(__('card_exp', 'MM / YY')); ?></label>
                                <input type="text" class="form-control" placeholder="12/28" maxlength="5" value="12/28" autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?= e(__('card_cvv', 'CVV')); ?></label>
                                <input type="password" class="form-control" placeholder="123" maxlength="4" value="123" autocomplete="off">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label"><?= e(__('cardholder_name', 'Cardholder Name')); ?></label>
                            <input type="text" class="form-control" placeholder="TARO YAMADA" value="<?= e(strtoupper($user['name'] ?? 'CUSTOMER')); ?>" autocomplete="off">
                        </div>
                    </div>

                    <div style="margin-top:12px; font-size:0.82rem; color:var(--color-secondary-text); background:#FFF8F0; padding:10px 14px; border-radius:var(--radius-sm); border-left:3px solid var(--color-warning);">
                        ℹ️ <?= e(__('payment_simulation_note', 'Simulation Mode: Educational project. No real payment will be charged.')); ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Order Summary & Price Breakdown -->
            <div>
                <div class="summary-card">
                    <h3 class="summary-title"><?= e(__('order_summary', 'Order Summary')); ?></h3>

                    <!-- Itemized preview -->
                    <div style="margin-bottom:18px; max-height:220px; overflow-y:auto; padding-right:6px;">
                        <?php foreach ($cartItems as $cItem):
                            $unitDisc = (int)($cItem['discount_amount'] ?? 0);
                            $unitNet = max(0, (int)$cItem['price'] - $unitDisc);
                            $lineSub = ($unitNet + (int)$cItem['customization_price']) * (int)$cItem['quantity'];
                        ?>
                            <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.88rem;">
                                <div>
                                    <strong><?= e(getLocalized($cItem, 'name')); ?></strong> &times; <?= $cItem['quantity']; ?>
                                    <?php if ($unitDisc > 0): ?>
                                        <span class="badge" style="background:#FFE6EB; color:#C44569; font-size:0.7rem; padding:1px 6px;">
                                            🌸 <?= sprintf(e(__('pairing_discount_badge', '%d%% OFF')), $cItem['discount_percent']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($cItem['customization_summary'])): ?>
                                        <div style="font-size:0.75rem; color:var(--color-secondary-text);"><?= e($cItem['customization_summary']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div style="font-weight:600;">
                                    <?php if ($unitDisc > 0): ?>
                                        <span style="text-decoration:line-through; font-size:0.75rem; color:#888; margin-right:4px;"><?= formatPrice(((int)$cItem['price'] + (int)$cItem['customization_price']) * (int)$cItem['quantity']); ?></span>
                                    <?php endif; ?>
                                    <?= formatPrice($lineSub); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="summary-row">
                        <span><?= e(__('checkout_eligible_subtotal', 'Eligible Product Subtotal')); ?>:</span>
                        <span><?= formatPrice($productSubtotal); ?></span>
                    </div>

                    <?php if ($customizationTotal > 0): ?>
                        <div class="summary-row">
                            <span><?= e(__('customization_fees', 'Customization Fees')); ?>:</span>
                            <span><?= formatPrice($customizationTotal); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($pairingDiscountTotal > 0): ?>
                        <div class="summary-row" style="color:#2E7D32; font-weight:600;">
                            <span>🌸 <?= e(__('checkout_other_promotions', 'Pairing Promotions')); ?>:</span>
                            <span>-<?= formatPrice($pairingDiscountTotal); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($gameDiscountAmount > 0): ?>
                        <div class="summary-row" style="color:#2E7D32; font-weight:700; background:#EDF9EA; padding:6px 10px; border-radius:6px; margin:6px -10px;">
                            <span>🎮 <?= e(__('checkout_minigame_discount', 'Mini-Game Discount')); ?>:</span>
                            <span>-<?= formatPrice($gameDiscountAmount); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="summary-row" id="checkout-delivery-fee-row" style="display:<?= $postedFulfillment === 'delivery' ? 'flex' : 'none'; ?>;">
                        <span><?= e(__('delivery_fee', 'Delivery Fee')); ?>:</span>
                        <span id="checkout-delivery-fee"><?= formatPrice($initialDeliveryFee); ?></span>
                    </div>

                    <div class="summary-row grand-total-row">
                        <span><?= e(__('checkout_final_amount', 'Final Amount')); ?>:</span>
                        <span id="checkout-grand-total"><?= formatPrice($initialFinalTotal); ?></span>
                    </div>

                    <div style="margin-top:24px;">
                        <button type="submit" class="btn btn-primary btn-block btn-lg" id="btn-place-order">
                            🔒 <?= e(__('place_order', 'Place Order')); ?>
                        </button>
                    </div>

                    <p style="font-size:0.75rem; text-align:center; color:var(--color-secondary-text); margin-top:14px;">
                        <?= e(__('all_prices_jpy', 'All prices in Japanese Yen (¥ / JPY) including tax.')); ?>
                    </p>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
