<?php
/**
 * Sweet Choice - Checkout Page
 * Supports Dine-in (店内), Store Pickup, and Local Delivery with dynamic payment methods,
 * card/online payment validation, and fulfillment-based service fee calculation.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pairings.php';

$pdo = getDB();
$cartId = getOrCreateCartId($pdo);

// Revalidate all paired discounts in cart against live inventory before processing checkout
revalidateCartPairingDiscounts($pdo, $cartId);

// Fetch cart items
$cartStmt = $pdo->prepare("SELECT ci.*, p.name_en, p.name_ja, p.price, p.image, p.stock 
                          FROM cart_items ci 
                          JOIN products p ON ci.product_id = p.id 
                          WHERE ci.cart_id = ?");
$cartStmt->execute([$cartId]);
$cartItems = $cartStmt->fetchAll();

if (empty($cartItems)) {
    header("Location: cart.php");
    exit;
}

// Calculate totals taking pairing discounts into account
$productSubtotal = 0;
$pairingDiscountTotal = 0;
$customizationTotal = 0;
foreach ($cartItems as $item) {
    $qty = (int)$item['quantity'];
    $unitDisc = (int)($item['discount_amount'] ?? 0);
    $productSubtotal += ((int)$item['price'] * $qty);
    $pairingDiscountTotal += ($unitDisc * $qty);
    $customizationTotal += ((int)$item['customization_price'] * $qty);
}

$effectiveProductSubtotal = max(0, $productSubtotal - $pairingDiscountTotal);
// Service fee applies ONLY to Local Delivery
$configuredServiceFee = (int)getShopSetting($pdo, 'service_fee', '150');

// Fetch Delivery Distance Tiers from MySQL
$tiersStmt = $pdo->query("SELECT * FROM delivery_settings WHERE is_active = 1 ORDER BY min_km ASC");
$deliveryTiers = $tiersStmt->fetchAll();

// Fetch Pickup Slots from MySQL
$pickupSlotsStmt = $pdo->query("SELECT * FROM pickup_slots WHERE is_active = 1 ORDER BY id ASC");
$pickupSlots = $pickupSlotsStmt->fetchAll();

// Fetch Delivery Slots from MySQL
$deliverySlotsStmt = $pdo->query("SELECT * FROM delivery_slots WHERE is_active = 1 ORDER BY id ASC");
$deliverySlots = $deliverySlotsStmt->fetchAll();

// Fetch Payment Methods from MySQL
$payMethodsStmt = $pdo->query("SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY id ASC");
$rawPaymentMethods = $payMethodsStmt->fetchAll();

// Normalize legacy code if needed while preserving DB configuration
$paymentMethods = [];
foreach ($rawPaymentMethods as $pm) {
    if ($pm['code'] === 'cash_on_fulfillment') {
        $pm['code'] = 'cash';
    }
    $paymentMethods[] = $pm;
}

// Online payment providers (none selected by default)
$onlineProviders = getOnlinePaymentProviders();

// Pre-fill user profile info if logged in
$user = getCurrentUser($pdo);

// Form state defaults (fulfillment, payment method, provider, and card fields start unselected/empty)
$selectedFulfillment = '';
$selectedPaymentMethod = '';
$selectedPaymentProvider = '';
$formName = $user['name'] ?? '';
$formEmail = $user['email'] ?? '';
$formPhone = $user['phone'] ?? '';
$formNotes = '';
$formPickupDate = date('Y-m-d');
$formPickupTime = $pickupSlots[0]['slot_time'] ?? '';
$formDeliveryDate = date('Y-m-d');
$formDeliveryTime = $deliverySlots[0]['slot_time'] ?? '';
$formDistanceTierId = !empty($deliveryTiers) ? (int)$deliveryTiers[0]['id'] : 0;
$formPostalCode = $user['postal_code'] ?? '';
$formAddress = $user['address'] ?? '';
$formApartment = $user['apartment'] ?? '';

// Handle Order Placement POST
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = isJapanese()
            ? 'セキュリティトークンの有効期限が切れました。ページを再読み込みしてください。'
            : 'Security token expired. Please refresh and try again.';
    }

    $fulfillment = trim($_POST['fulfillment_type'] ?? '');
    $name = trim($_POST['customer_name'] ?? '');
    $email = trim(strtolower($_POST['customer_email'] ?? ''));
    $phone = trim($_POST['customer_phone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $paymentProvider = trim($_POST['payment_provider'] ?? '');

    if ($paymentMethod === 'cash_on_fulfillment') {
        $paymentMethod = 'cash';
    }

    $pickupDate = trim($_POST['pickup_date'] ?? '');
    $pickupTime = trim($_POST['pickup_time'] ?? '');
    $deliveryDate = trim($_POST['delivery_date'] ?? '');
    $deliveryTime = trim($_POST['delivery_time'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $apartment = trim($_POST['apartment'] ?? '');
    $distanceTierId = (int)($_POST['distance_tier_id'] ?? 0);

    // Preserve non-sensitive form inputs on validation error (NEVER preserve card fields)
    $validFulfillments = ['dine_in', 'pickup', 'delivery'];
    $selectedFulfillment = in_array($fulfillment, $validFulfillments, true) ? $fulfillment : '';
    $formName = $name;
    $formEmail = $email;
    $formPhone = $phone;
    $formNotes = $notes;
    if ($pickupDate !== '') $formPickupDate = $pickupDate;
    if ($pickupTime !== '') $formPickupTime = $pickupTime;
    if ($deliveryDate !== '') $formDeliveryDate = $deliveryDate;
    if ($deliveryTime !== '') $formDeliveryTime = $deliveryTime;
    if ($distanceTierId > 0) $formDistanceTierId = $distanceTierId;
    $formPostalCode = $postalCode;
    $formAddress = $address;
    $formApartment = $apartment;

    // 1. Validate Fulfillment Option
    if (!in_array($fulfillment, $validFulfillments, true)) {
        $errors[] = isJapanese()
            ? 'お受け取り方法（店内・店頭受取・デリバリー）を選択してください。'
            : 'Please select a fulfillment option (Dine-in, Store Pickup, or Local Delivery).';
    }

    // 2. Validate Customer Contact Info
    if (empty($name) || empty($email) || empty($phone)) {
        $errors[] = isJapanese()
            ? 'お名前、メールアドレス、お電話番号を入力してください。'
            : 'Please fill in your name, email, and phone number.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = isJapanese()
            ? '有効なメールアドレスを入力してください。'
            : 'Please enter a valid email address.';
    }

    // 3. Validate Fulfillment-Specific Details & Fees
    $deliveryFee = 0;
    $distanceKm = 0.0;
    $appliedServiceFee = 0;

    if ($fulfillment === 'dine_in') {
        // Dine-in: no delivery fee, NO service fee
        $appliedServiceFee = 0;
        $deliveryFee = 0;
        $distanceKm = 0.0;
    } elseif ($fulfillment === 'pickup') {
        // Store Pickup: no delivery fee, NO service fee
        $appliedServiceFee = 0;
        $deliveryFee = 0;
        $distanceKm = 0.0;
        if (empty($pickupDate) || empty($pickupTime)) {
            $errors[] = isJapanese()
                ? '店頭受取の日付と時間帯を選択してください。'
                : 'Please select a pickup date and time slot.';
        }
    } elseif ($fulfillment === 'delivery') {
        // Local Delivery: service fee AND distance-based delivery fee apply
        $appliedServiceFee = $configuredServiceFee;
        if (empty($postalCode) || empty($address)) {
            $errors[] = isJapanese()
                ? '配達先の郵便番号とご住所を入力してください。'
                : 'Please provide a full delivery postal code and street address.';
        }
        if (empty($deliveryDate) || empty($deliveryTime)) {
            $errors[] = isJapanese()
                ? '配達希望日と時間帯を選択してください。'
                : 'Please select a delivery date and time slot.';
        }

        // Find selected distance tier
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

    // 4. Validate Payment Method against Selected Fulfillment Type
    $allowedMethods = getAllowedPaymentMethodsForFulfillment($fulfillment);
    if (empty($paymentMethod)) {
        $errors[] = isJapanese()
            ? 'お支払い方法を選択してください。'
            : 'Please select a payment method.';
    } elseif (!empty($allowedMethods) && !in_array($paymentMethod, $allowedMethods, true)) {
        $errors[] = isJapanese()
            ? '選択されたお受け取り方法では、このお支払い方法はご利用いただけません。'
            : 'The selected payment method is not available for the chosen fulfillment option.';
    } else {
        $selectedPaymentMethod = $paymentMethod;
    }

    // 5. Validate Payment Method Specific Inputs
    $savedPaymentProvider = null;

    if ($paymentMethod === 'credit_card' && in_array('credit_card', $allowedMethods, true)) {
        // Read card inputs strictly for validation; NEVER store, log, or re-render them
        $cardholderName = trim($_POST['cardholder_name'] ?? '');
        $cardNumberRaw  = preg_replace('/[\s\-]+/', '', trim($_POST['card_number'] ?? ''));
        $cardExpiry     = trim($_POST['card_expiry'] ?? '');
        $cardCvv        = trim($_POST['card_cvv'] ?? '');

        if (mb_strlen($cardholderName) < 2) {
            $errors[] = isJapanese()
                ? 'カード名義人を入力してください。'
                : 'Please enter the cardholder name.';
        }
        if (!preg_match('/^\d{13,19}$/', $cardNumberRaw)) {
            $errors[] = isJapanese()
                ? '有効なカード番号（13〜19桁の数字）を入力してください。'
                : 'Please enter a valid card number (13 to 19 digits).';
        }
        if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $cardExpiry, $expMatch)) {
            $errors[] = isJapanese()
                ? '有効期限は MM/YY 形式で入力してください（例: 08/28）。'
                : 'Please enter a valid expiration date in MM/YY format (e.g. 08/28).';
        } else {
            $expMonth = (int)$expMatch[1];
            $expYear = 2000 + (int)$expMatch[2];
            $curYear = (int)date('Y');
            $curMonth = (int)date('n');
            if ($expYear < $curYear || ($expYear === $curYear && $expMonth < $curMonth)) {
                $errors[] = isJapanese()
                    ? 'カードの有効期限が切れています。'
                    : 'The card expiration date has passed.';
            }
        }
        if (!preg_match('/^\d{3,4}$/', $cardCvv)) {
            $errors[] = isJapanese()
                ? '有効なセキュリティコード（CVV：3〜4桁の数字）を入力してください。'
                : 'Please enter a valid CVV (3 or 4 digits).';
        }

        // Explicitly discard sensitive card variables from memory
        unset($cardholderName, $cardNumberRaw, $cardExpiry, $cardCvv);
    } elseif ($paymentMethod === 'online_pay' && in_array('online_pay', $allowedMethods, true)) {
        if (empty($paymentProvider) || !array_key_exists($paymentProvider, $onlineProviders)) {
            $errors[] = isJapanese()
                ? 'オンライン決済サービス（PayPay、au PAY、Rakuten Pay）のいずれかを選択してください。'
                : 'Please select an online payment provider (PayPay, au PAY, or Rakuten Pay).';
        } else {
            $savedPaymentProvider = $paymentProvider;
            $selectedPaymentProvider = $paymentProvider;
        }
    }

    if (empty($errors)) {
        // Double-check strict service fee rule: ONLY Local Delivery gets service fee & delivery fee
        if ($fulfillment !== 'delivery') {
            $appliedServiceFee = 0;
            $deliveryFee = 0;
            $distanceKm = 0.0;
        }

        $grandTotal = $effectiveProductSubtotal + $customizationTotal + $appliedServiceFee + $deliveryFee;
        $orderNumber = 'SWC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $userId = $user ? (int)$user['id'] : null;
        $paymentStatus = ($paymentMethod === 'cash') ? 'pending' : 'paid';

        try {
            $pdo->beginTransaction();

            // 1. Insert into orders (never storing any sensitive card data)
            $orderInsert = $pdo->prepare("INSERT INTO orders 
                (order_number, user_id, fulfillment_type, pickup_date, pickup_time, delivery_date, delivery_time, distance_km, customer_name, customer_email, customer_phone, postal_code, address, apartment, subtotal, customization_total, pairing_discount_total, delivery_fee, service_fee, grand_total, payment_method, payment_provider, payment_status, status, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Order Received', ?, NOW())");

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
                $fulfillment === 'delivery' ? $postalCode : null,
                $fulfillment === 'delivery' ? $address : null,
                $fulfillment === 'delivery' ? $apartment : null,
                $effectiveProductSubtotal,
                $customizationTotal,
                $pairingDiscountTotal,
                $deliveryFee,
                $appliedServiceFee,
                $grandTotal,
                $paymentMethod,
                $savedPaymentProvider,
                $paymentStatus,
                $notes
            ]);
            $orderId = (int)$pdo->lastInsertId();

            // 2. Insert order items & customizations
            $itemInsert = $pdo->prepare("INSERT INTO order_items 
                (order_id, product_id, product_name, price, original_price, discount_amount, pairing_product_id, quantity, customization_summary, customization_price, subtotal)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $customInsert = $pdo->prepare("INSERT INTO order_customizations 
                (order_item_id, group_name, option_name, price_extra) VALUES (?, ?, ?, ?)");

            foreach ($cartItems as $cItem) {
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
                    $lineSubtotal
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

                // Decrement stock
                $updStock = $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?");
                $updStock->execute([$cItem['quantity'], $cItem['product_id']]);
            }

            // 3. Clear cart items
            $clearCart = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?");
            $clearCart->execute([$cartId]);

            $pdo->commit();

            // Redirect to Order Confirmation
            header("Location: order-confirmation.php?order=" . urlencode($orderNumber));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "An unexpected error occurred while placing your order: " . $e->getMessage();
        }
    }
}

// Compute initial summary numbers for server-side render (strictly NO service fee unless delivery)
$initialServiceFee = ($selectedFulfillment === 'delivery') ? $configuredServiceFee : 0;
$initialDeliveryFee = 0;
if ($selectedFulfillment === 'delivery' && !empty($deliveryTiers)) {
    foreach ($deliveryTiers as $tier) {
        if ((int)$tier['id'] === $formDistanceTierId) {
            $initialDeliveryFee = (int)$tier['fee'];
            break;
        }
    }
}
$initialGrandTotal = $effectiveProductSubtotal + $customizationTotal + $initialServiceFee + $initialDeliveryFee;

$pageTitle = __('checkout_title', 'Checkout');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 40px 20px;">
    <div class="section-header" style="text-align:left; margin-bottom:24px;">
        <span class="section-tag"><?= e(__('checkout_title', 'Checkout')); ?></span>
        <h1 class="section-title"><?= isJapanese() ? 'ご注文・お受取り手続き' : 'Fulfillment & Checkout'; ?></h1>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" id="checkout-server-error">
            <ul style="margin-left:20px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="alert alert-danger" id="checkout-client-error" style="display:none;" role="alert">
        <ul id="checkout-client-error-list" style="margin-left:20px; width:100%;"></ul>
    </div>

    <form action="checkout.php" method="POST" id="checkout-form" novalidate
          data-subtotal="<?= $effectiveProductSubtotal; ?>"
          data-custom="<?= $customizationTotal; ?>"
          data-service-fee="<?= $configuredServiceFee; ?>"
          data-lang="<?= e(getCurrentLang()); ?>"
          data-label-dine-in="<?= e(__('dine_in_option', 'Dine-in (店内)')); ?>"
          data-label-pickup="<?= e(__('pickup_option', 'Store Pickup')); ?>"
          data-label-delivery="<?= e(__('delivery_option', 'Local Delivery')); ?>"
          data-label-not-selected="<?= e(__('not_selected', 'Not selected')); ?>">
        <input type="hidden" name="action" value="place_order">
        <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">

        <div class="checkout-layout">
            <!-- Left Column: Forms -->
            <div>
                <!-- 1. Fulfillment Options (Dine-in, Store Pickup, Local Delivery) -->
                <div class="checkout-card">
                    <h3 class="checkout-card-title">
                        📦 <?= e(__('fulfillment_choice', 'Fulfillment Option')); ?> <span style="color:var(--color-danger);">*</span>
                    </h3>

                    <div class="fulfillment-tabs fulfillment-tabs-3" role="radiogroup" aria-label="<?= e(__('fulfillment_choice', 'Fulfillment Option')); ?>">
                        <label class="tab-btn fulfillment-option-card <?= $selectedFulfillment === 'dine_in' ? 'active' : ''; ?>" id="tab-dine-in" data-fulfillment="dine_in">
                            <input type="radio" name="fulfillment_type" value="dine_in" class="fulfillment-radio" <?= $selectedFulfillment === 'dine_in' ? 'checked' : ''; ?>>
                            <div class="tab-btn-title">🍰 <?= e(__('dine_in_option', 'Dine-in (店内)')); ?></div>
                            <div class="tab-btn-desc"><?= e(__('dine_in_desc', 'Enjoy freshly crafted desserts in our Ginza salon with zero service or delivery fees.')); ?></div>
                            <div class="tab-btn-fee-badge tab-fee-free"><?= isJapanese() ? 'サービス料 ¥0' : 'No Service Fee'; ?></div>
                        </label>

                        <label class="tab-btn fulfillment-option-card <?= $selectedFulfillment === 'pickup' ? 'active' : ''; ?>" id="tab-pickup" data-fulfillment="pickup">
                            <input type="radio" name="fulfillment_type" value="pickup" class="fulfillment-radio" <?= $selectedFulfillment === 'pickup' ? 'checked' : ''; ?>>
                            <div class="tab-btn-title">🏬 <?= e(__('pickup_option', 'Store Pickup')); ?></div>
                            <div class="tab-btn-desc"><?= e(__('pickup_desc', 'Pick up your freshly packaged order at our Ginza boutique with zero service or delivery fees.')); ?></div>
                            <div class="tab-btn-fee-badge tab-fee-free"><?= isJapanese() ? 'サービス料 ¥0' : 'No Service Fee'; ?></div>
                        </label>

                        <label class="tab-btn fulfillment-option-card <?= $selectedFulfillment === 'delivery' ? 'active' : ''; ?>" id="tab-delivery" data-fulfillment="delivery">
                            <input type="radio" name="fulfillment_type" value="delivery" class="fulfillment-radio" <?= $selectedFulfillment === 'delivery' ? 'checked' : ''; ?>>
                            <div class="tab-btn-title">🚚 <?= e(__('delivery_option', 'Local Delivery')); ?></div>
                            <div class="tab-btn-desc"><?= e(__('delivery_desc', 'Hand-delivered to your door in temperature-controlled confectionery bags.')); ?></div>
                            <div class="tab-btn-fee-badge tab-fee-delivery">+<?= formatPrice($configuredServiceFee); ?> <?= isJapanese() ? 'サービス料 & 配送料' : 'Service & Delivery Fee'; ?></div>
                        </label>
                    </div>

                    <!-- Dine-in Details Section -->
                    <div id="dine-in-details-section" class="fulfillment-details-panel" style="display:<?= $selectedFulfillment === 'dine_in' ? 'block' : 'none'; ?>;">
                        <h4 class="fulfillment-details-heading">
                            🍰 <?= e(__('dine_in_details', 'Dine-in Details')); ?>
                        </h4>
                        <div class="dine-in-info-box">
                            <p style="margin-bottom:6px;">
                                📍 <strong>Sweet Choice Ginza Boutique Café</strong> &mdash; Ginza 4-2-11, Chuo-ku, Tokyo
                            </p>
                            <p style="font-size:0.85rem; color:var(--color-secondary-text); margin:0;">
                                <?= e(__('dine_in_location_note', 'Enjoy your order at our Ginza Boutique Café: Ginza 4-2-11, Chuo-ku, Tokyo. No service fee applies.')); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Store Pickup Details Section -->
                    <div id="pickup-details-section" class="fulfillment-details-panel" style="display:<?= $selectedFulfillment === 'pickup' ? 'block' : 'none'; ?>;">
                        <h4 class="fulfillment-details-heading">
                            🏬 <?= e(__('pickup_details', 'Pickup Date & Time')); ?>
                        </h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="pickup-date"><?= e(__('select_date', 'Pickup Date')); ?> *</label>
                                <input type="date" id="pickup-date" name="pickup_date" class="form-control"
                                       min="<?= date('Y-m-d'); ?>"
                                       value="<?= e($formPickupDate); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="pickup-time"><?= e(__('select_time', 'Pickup Time Slot')); ?> *</label>
                                <select id="pickup-time" name="pickup_time" class="form-control">
                                    <?php foreach ($pickupSlots as $slot): ?>
                                        <option value="<?= e($slot['slot_time']); ?>" <?= $formPickupTime === $slot['slot_time'] ? 'selected' : ''; ?>>
                                            <?= e($slot['slot_time']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <p style="font-size:0.82rem; color:var(--color-secondary-text); margin:0;">
                            📍 <?= isJapanese() ? '受取店舗:' : 'Pickup Location:'; ?> <strong>Ginza 4-2-11, Chuo-ku, Tokyo (Sweet Choice Flagship)</strong>
                        </p>
                    </div>

                    <!-- Local Delivery Details Section -->
                    <div id="delivery-details-section" class="fulfillment-details-panel" style="display:<?= $selectedFulfillment === 'delivery' ? 'block' : 'none'; ?>;">
                        <h4 class="fulfillment-details-heading">
                            🚚 <?= e(__('delivery_details', 'Delivery Details & Address')); ?>
                        </h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="delivery-date"><?= e(__('select_date', 'Delivery Date')); ?> *</label>
                                <input type="date" id="delivery-date" name="delivery_date" class="form-control"
                                       min="<?= date('Y-m-d'); ?>"
                                       value="<?= e($formDeliveryDate); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="delivery-time"><?= e(__('select_time', 'Delivery Time Slot')); ?> *</label>
                                <select id="delivery-time" name="delivery_time" class="form-control">
                                    <?php foreach ($deliverySlots as $dSlot): ?>
                                        <option value="<?= e($dSlot['slot_time']); ?>" <?= $formDeliveryTime === $dSlot['slot_time'] ? 'selected' : ''; ?>>
                                            <?= e($dSlot['slot_time']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Distance Tiers -->
                        <div class="form-group">
                            <label class="form-label" for="distance-tier-select"><?= e(__('distance_tier', 'Estimated Delivery Distance Area')); ?> *</label>
                            <select name="distance_tier_id" id="distance-tier-select" class="form-control">
                                <?php foreach ($deliveryTiers as $tier): ?>
                                    <option value="<?= $tier['id']; ?>" data-fee="<?= (int)$tier['fee']; ?>" <?= (int)$tier['id'] === $formDistanceTierId ? 'selected' : ''; ?>>
                                        <?= e(isJapanese() ? $tier['tier_label_ja'] : $tier['tier_label_en']); ?> &mdash; <?= formatPrice($tier['fee']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="postal-code"><?= e(__('postal_code', 'Postal Code')); ?> (e.g. 104-0061) *</label>
                                <input type="text" id="postal-code" name="postal_code" class="form-control" value="<?= e($formPostalCode); ?>" placeholder="104-0061">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="apartment"><?= e(__('apartment_unit', 'Apartment / Building')); ?></label>
                                <input type="text" id="apartment" name="apartment" class="form-control" value="<?= e($formApartment); ?>" placeholder="Mansion Ginza #302">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="street-address"><?= e(__('street_address', 'Street Address')); ?> *</label>
                            <input type="text" id="street-address" name="address" class="form-control" value="<?= e($formAddress); ?>" placeholder="Tokyo, Chuo-ku, Ginza 4-2-11">
                        </div>
                    </div>
                </div>

                <!-- 2. Customer Contact Information -->
                <div class="checkout-card">
                    <h3 class="checkout-card-title">👤 <?= e(__('customer_info', 'Customer Information')); ?></h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="customer-name"><?= e(__('full_name', 'Full Name')); ?> *</label>
                            <input type="text" id="customer-name" name="customer_name" class="form-control" value="<?= e($formName); ?>" required placeholder="Taro Yamada">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="customer-phone"><?= e(__('phone_number', 'Phone Number')); ?> *</label>
                            <input type="tel" id="customer-phone" name="customer_phone" class="form-control" value="<?= e($formPhone); ?>" required placeholder="090-1234-5678">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="customer-email"><?= e(__('email_address', 'Email Address')); ?> *</label>
                        <input type="email" id="customer-email" name="customer_email" class="form-control" value="<?= e($formEmail); ?>" required placeholder="yamada@example.com">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" for="order-notes"><?= e(__('order_notes', 'Special Instructions / Notes')); ?></label>
                        <textarea id="order-notes" name="notes" rows="2" class="form-control" placeholder="<?= isJapanese() ? 'アレルギー、誕生日キャンドル、配達時のご指示など...' : 'Allergies, birthday candles, delivery instructions...'; ?>"><?= e($formNotes); ?></textarea>
                    </div>
                </div>

                <!-- 3. Dynamic Payment Method Section -->
                <div class="checkout-card" style="margin-bottom:0;">
                    <h3 class="checkout-card-title">
                        💳 <?= e(__('payment_section', 'Payment Method')); ?> <span style="color:var(--color-danger);">*</span>
                    </h3>

                    <!-- Prompt shown when no fulfillment option is selected yet -->
                    <div id="payment-select-fulfillment-msg" class="checkout-info-banner" style="display:<?= empty($selectedFulfillment) ? 'block' : 'none'; ?>;">
                        ℹ️ <?= e(__('select_fulfillment_prompt', 'Please select a fulfillment option above to view available payment methods.')); ?>
                    </div>

                    <!-- Payment Method Options List -->
                    <div id="payment-methods-list" style="display:<?= empty($selectedFulfillment) ? 'none' : 'block'; ?>; margin-bottom:18px;">
                        <?php foreach ($paymentMethods as $pm):
                            $pmCode = $pm['code'];
                            $isCash = ($pmCode === 'cash');
                            $isAvailableNow = !empty($selectedFulfillment) && in_array($pmCode, getAllowedPaymentMethodsForFulfillment($selectedFulfillment), true);
                            $isChecked = ($selectedPaymentMethod === $pmCode && $isAvailableNow);
                        ?>
                            <label class="custom-option-label payment-method-option <?= $isChecked ? 'selected' : ''; ?>"
                                   data-payment-code="<?= e($pmCode); ?>"
                                   data-dine-in-only="<?= $isCash ? '1' : '0'; ?>"
                                   style="display:<?= $isAvailableNow ? 'flex' : 'none'; ?>; margin-bottom:10px; width:100%;">
                                <input type="radio"
                                       name="payment_method"
                                       value="<?= e($pmCode); ?>"
                                       class="payment-method-radio"
                                       <?= $isChecked ? 'checked' : ''; ?>
                                       <?= $isAvailableNow ? '' : 'disabled'; ?>>
                                <div style="margin-left:8px;">
                                    <strong><?= e(isJapanese() ? $pm['name_ja'] : $pm['name_en']); ?></strong>
                                    <div style="font-size:0.8rem; color:var(--color-secondary-text);">
                                        <?= e(isJapanese() ? $pm['description_ja'] : $pm['description_en']); ?>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>

                        <!-- Clear helper note when Cash is not available (Store Pickup / Local Delivery) -->
                        <div id="cash-unavailable-note" class="checkout-subtle-note" style="display:<?= in_array($selectedFulfillment, ['pickup', 'delivery'], true) ? 'block' : 'none'; ?>;">
                            ℹ️ <?= e(__('cash_dine_in_only_note', 'Cash payment is only available for Dine-in (店内) orders.')); ?>
                        </div>
                    </div>

                    <!-- Credit / Debit Card Form (Empty by default, NO pre-filled values, never saved to DB) -->
                    <div id="card-payment-form" class="payment-subpanel" style="display:<?= $selectedPaymentMethod === 'credit_card' ? 'block' : 'none'; ?>;">
                        <h4 class="payment-subpanel-title">💳 <?= e(__('card_details_title', 'Credit / Debit Card Information')); ?></h4>

                        <div class="form-group">
                            <label class="form-label" for="cardholder-name"><?= e(__('cardholder_name', 'Cardholder Name')); ?> *</label>
                            <input type="text"
                                   id="cardholder-name"
                                   name="cardholder_name"
                                   class="form-control"
                                   placeholder="<?= isJapanese() ? '例: TARO YAMADA' : 'e.g. TARO YAMADA'; ?>"
                                   autocomplete="off"
                                   value="">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="card-number"><?= e(__('card_number', 'Card Number')); ?> *</label>
                            <input type="text"
                                   id="card-number"
                                   name="card_number"
                                   class="form-control"
                                   placeholder="1234 5678 9012 3456"
                                   maxlength="19"
                                   inputmode="numeric"
                                   autocomplete="off"
                                   value="">
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" for="card-expiry"><?= e(__('card_exp', 'Expiration Date (MM/YY)')); ?> *</label>
                                <input type="text"
                                       id="card-expiry"
                                       name="card_expiry"
                                       class="form-control"
                                       placeholder="MM/YY (e.g. 08/28)"
                                       maxlength="5"
                                       inputmode="numeric"
                                       autocomplete="off"
                                       value="">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" for="card-cvv"><?= e(__('card_cvv', 'CVV')); ?> *</label>
                                <input type="password"
                                       id="card-cvv"
                                       name="card_cvv"
                                       class="form-control"
                                       placeholder="<?= isJapanese() ? '3〜4桁（例: 123）' : '3 or 4 digits (e.g. 123)'; ?>"
                                       maxlength="4"
                                       inputmode="numeric"
                                       autocomplete="new-password"
                                       value="">
                            </div>
                        </div>

                        <div class="payment-security-note">
                            🔒 <?= e(__('card_security_note', 'Demo checkout: Card details are validated only and are never stored in our database.')); ?>
                        </div>
                    </div>

                    <!-- Online Payment Providers Panel (All 3 initially unselected) -->
                    <div id="online-payment-form" class="payment-subpanel" style="display:<?= $selectedPaymentMethod === 'online_pay' ? 'block' : 'none'; ?>;">
                        <h4 class="payment-subpanel-title">📱 <?= e(__('online_payment_select_provider', 'Select Online Payment Provider')); ?> <span style="color:var(--color-danger);">*</span></h4>

                        <div class="demo-payment-disclaimer">
                            <strong><?= isJapanese() ? 'デモ表示:' : 'Demo Notice:'; ?></strong>
                            <?= e(__('online_payment_demo_notice', 'Demonstration Payment Details: These are sample payment details for demonstration only — not actual payment credentials. No real payment will be charged.')); ?>
                        </div>

                        <div class="online-provider-grid" role="radiogroup" aria-label="<?= e(__('payment_provider', 'Payment Provider')); ?>">
                            <?php foreach ($onlineProviders as $providerName => $demoInfoText):
                                $isProviderChecked = ($selectedPaymentMethod === 'online_pay' && $selectedPaymentProvider === $providerName);
                            ?>
                                <label class="online-provider-card <?= $isProviderChecked ? 'active' : ''; ?>" data-provider="<?= e($providerName); ?>">
                                    <div class="online-provider-header">
                                        <input type="radio"
                                               name="payment_provider"
                                               value="<?= e($providerName); ?>"
                                               class="online-provider-radio"
                                               <?= $isProviderChecked ? 'checked' : ''; ?>>
                                        <span class="online-provider-name"><?= e($providerName); ?></span>
                                        <span class="demo-badge">DEMO</span>
                                    </div>
                                    <div class="online-provider-demo-text"><?= e($demoInfoText); ?></div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div id="online-provider-selected-box" class="online-provider-selected-box" style="display:<?= !empty($selectedPaymentProvider) ? 'block' : 'none'; ?>;">
                            <span id="online-provider-selected-text">
                                <?php if (!empty($selectedPaymentProvider) && isset($onlineProviders[$selectedPaymentProvider])): ?>
                                    ✓ <?= e($onlineProviders[$selectedPaymentProvider]); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Cash Payment Info Panel (Dine-in only) -->
                    <div id="cash-payment-info" class="payment-subpanel" style="display:<?= ($selectedPaymentMethod === 'cash' && $selectedFulfillment === 'dine_in') ? 'block' : 'none'; ?>;">
                        <h4 class="payment-subpanel-title">💴 <?= isJapanese() ? '現金払い（店内飲食）' : 'Cash Payment (Dine-in)'; ?></h4>
                        <p style="font-size:0.86rem; color:var(--color-secondary-text); margin:0;">
                            <?= isJapanese()
                                ? 'お食事後に店内レジにて現金でお支払いください。サービス料はかかりません。'
                                : 'Please pay with cash at our Ginza salon counter during your dine-in visit. No service fee applies.'; ?>
                        </p>
                    </div>

                    <div style="margin-top:14px; font-size:0.82rem; color:var(--color-secondary-text); background:#FFF8F0; padding:10px 14px; border-radius:var(--radius-sm); border-left:3px solid var(--color-warning);">
                        ℹ️ <?= e(__('payment_simulation_note', 'Simulation Mode: Educational project. No real payment will be charged.')); ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Order Summary -->
            <div>
                <div class="summary-card">
                    <h3 class="summary-title"><?= e(__('order_summary', 'Order Summary')); ?></h3>

                    <!-- Selected Fulfillment & Payment Indicators -->
                    <div class="summary-row summary-fulfillment-row" style="padding-bottom:4px; margin-bottom:6px;">
                        <span><?= e(__('fulfillment_type', 'Fulfillment Method')); ?>:</span>
                        <strong id="checkout-fulfillment-display" style="color:var(--color-deep-plum);">
                            <?= !empty($selectedFulfillment) ? e(formatFulfillmentLabel($selectedFulfillment)) : e(__('not_selected', 'Not selected')); ?>
                        </strong>
                    </div>
                    <div class="summary-row summary-fulfillment-row">
                        <span><?= e(__('payment_section', 'Payment Method')); ?>:</span>
                        <strong id="checkout-payment-display" style="color:var(--color-text-primary); text-align:right;">
                            <?= !empty($selectedPaymentMethod) ? e(formatPaymentMethodLabel($selectedPaymentMethod, $selectedPaymentProvider ?: null)) : e(__('not_selected', 'Not selected')); ?>
                        </strong>
                    </div>

                    <!-- Mini items breakdown -->
                    <div style="margin-bottom:18px; max-height:220px; overflow-y:auto; padding-right:6px; border-top:1px solid var(--color-border-light); padding-top:12px;">
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
                                    <?php if ($cItem['customization_summary']): ?>
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
                        <span><?= e(__('product_subtotal', 'Product Subtotal')); ?>:</span>
                        <span><?= formatPrice($productSubtotal); ?></span>
                    </div>

                    <?php if ($pairingDiscountTotal > 0): ?>
                        <div class="summary-row" style="color:#2E7D32; font-weight:600;">
                            <span>🌸 <?= e(__('pairing_cart_discount_label', 'Pairing Discount')); ?>:</span>
                            <span>-<?= formatPrice($pairingDiscountTotal); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($customizationTotal > 0): ?>
                        <div class="summary-row">
                            <span><?= e(__('customization_fees', 'Customization Fees')); ?>:</span>
                            <span><?= formatPrice($customizationTotal); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Service Fee: ONLY displayed and added for Local Delivery -->
                    <div class="summary-row" id="checkout-service-fee-row" style="display:<?= $selectedFulfillment === 'delivery' ? 'flex' : 'none'; ?>;">
                        <span><?= e(__('service_fee', 'Service Fee')); ?>:</span>
                        <span id="checkout-service-fee"><?= formatPrice($initialServiceFee); ?></span>
                    </div>

                    <!-- Delivery Fee: ONLY displayed and added for Local Delivery -->
                    <div class="summary-row" id="checkout-delivery-fee-row" style="display:<?= $selectedFulfillment === 'delivery' ? 'flex' : 'none'; ?>;">
                        <span><?= e(__('delivery_fee', 'Delivery Fee')); ?>:</span>
                        <span id="checkout-delivery-fee"><?= formatPrice($initialDeliveryFee); ?></span>
                    </div>

                    <div class="summary-row grand-total-row">
                        <span><?= e(__('grand_total', 'Grand Total')); ?>:</span>
                        <span id="checkout-grand-total"><?= formatPrice($initialGrandTotal); ?></span>
                    </div>

                    <div style="margin-top:24px;">
                        <button type="submit" class="btn btn-primary btn-block btn-lg" id="place-order-btn">
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
