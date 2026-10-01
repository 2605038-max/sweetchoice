<?php
/**
 * Sweet Choice - Checkout Page
 * Supports Pickup & Delivery options, time slots, distance-based delivery tiers, service fee, and mock payment.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getDB();
$cartId = getOrCreateCartId($pdo);

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

// Calculate totals
$productSubtotal = 0;
$customizationTotal = 0;
foreach ($cartItems as $item) {
    $productSubtotal += ((int)$item['price'] * (int)$item['quantity']);
    $customizationTotal += ((int)$item['customization_price'] * (int)$item['quantity']);
}

$serviceFee = (int)getShopSetting($pdo, 'service_fee', '150');

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
$paymentMethods = $payMethodsStmt->fetchAll();

// Pre-fill user profile info if logged in
$user = getCurrentUser($pdo);

// Handle Order Placement POST
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token expired. Please refresh and try again.';
    }

    $fulfillment = $_POST['fulfillment_type'] ?? 'pickup';
    $name = trim($_POST['customer_name'] ?? '');
    $email = trim(strtolower($_POST['customer_email'] ?? ''));
    $phone = trim($_POST['customer_phone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'credit_card';

    $pickupDate = $_POST['pickup_date'] ?? null;
    $pickupTime = $_POST['pickup_time'] ?? null;
    $deliveryDate = $_POST['delivery_date'] ?? null;
    $deliveryTime = $_POST['delivery_time'] ?? null;
    $postalCode = trim($_POST['postal_code'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $apartment = trim($_POST['apartment'] ?? '');
    $distanceTierId = (int)($_POST['distance_tier_id'] ?? 0);

    if (empty($name) || empty($email) || empty($phone)) {
        $errors[] = 'Please fill in your name, email, and phone number.';
    }

    $deliveryFee = 0;
    $distanceKm = 0.0;

    if ($fulfillment === 'pickup') {
        if (empty($pickupDate) || empty($pickupTime)) {
            $errors[] = 'Please select a pickup date and time slot.';
        }
    } else {
        // Delivery
        if (empty($postalCode) || empty($address)) {
            $errors[] = 'Please provide a full delivery postal code and street address.';
        }
        if (empty($deliveryDate) || empty($deliveryTime)) {
            $errors[] = 'Please select a delivery date and time slot.';
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

    if (empty($errors)) {
        $grandTotal = $productSubtotal + $customizationTotal + $serviceFee + $deliveryFee;
        $orderNumber = 'SWC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $userId = $user ? (int)$user['id'] : null;

        try {
            $pdo->beginTransaction();

            // 1. Insert into orders
            $orderInsert = $pdo->prepare("INSERT INTO orders 
                (order_number, user_id, fulfillment_type, pickup_date, pickup_time, delivery_date, delivery_time, distance_km, customer_name, customer_email, customer_phone, postal_code, address, apartment, subtotal, customization_total, delivery_fee, service_fee, grand_total, payment_method, payment_status, status, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', 'Order Received', ?, NOW())");
            
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
                $productSubtotal,
                $customizationTotal,
                $deliveryFee,
                $serviceFee,
                $grandTotal,
                $paymentMethod,
                $notes
            ]);
            $orderId = (int)$pdo->lastInsertId();

            // 2. Insert order items & customizations
            $itemInsert = $pdo->prepare("INSERT INTO order_items 
                (order_id, product_id, product_name, price, quantity, customization_summary, customization_price, subtotal)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            $customInsert = $pdo->prepare("INSERT INTO order_customizations 
                (order_item_id, group_name, option_name, price_extra) VALUES (?, ?, ?, ?)");

            foreach ($cartItems as $cItem) {
                $lineSubtotal = ((int)$cItem['price'] + (int)$cItem['customization_price']) * (int)$cItem['quantity'];
                $itemInsert->execute([
                    $orderId,
                    $cItem['product_id'],
                    $cItem['name_en'],
                    $cItem['price'],
                    $cItem['quantity'],
                    $cItem['customization_summary'],
                    $cItem['customization_price'],
                    $lineSubtotal
                ]);
                $orderItemId = (int)$pdo->lastInsertId();

                // If customization string exists, record customization record
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

$pageTitle = __('checkout_title', 'Checkout');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 40px 20px;">
    <div class="section-header" style="text-align:left; margin-bottom:24px;">
        <span class="section-tag"><?= e(__('checkout_title', 'Checkout')); ?></span>
        <h1 class="section-title"><?= isJapanese() ? 'ご注文・お受取り手続き' : 'Fulfillment & Checkout'; ?></h1>
    </div>

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
          data-subtotal="<?= $productSubtotal; ?>" 
          data-custom="<?= $customizationTotal; ?>" 
          data-service-fee="<?= $serviceFee; ?>">
        <input type="hidden" name="action" value="place_order">
        <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
        <input type="hidden" name="fulfillment_type" id="fulfillment-type-input" value="pickup">

        <div class="checkout-layout">
            <!-- Left Column: Forms -->
            <div>
                <!-- 1. Fulfillment Toggle (Pickup vs Delivery) -->
                <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:24px; margin-bottom:24px; box-shadow:var(--shadow-sm);">
                    <h3 style="font-size:1.15rem; margin-bottom:16px;">📦 <?= e(__('fulfillment_choice', 'Fulfillment Option')); ?></h3>
                    
                    <div class="fulfillment-tabs">
                        <div class="tab-btn active" id="tab-pickup">
                            <div class="tab-btn-title">🏬 <?= e(__('pickup_option', 'Store Pickup')); ?></div>
                            <div class="tab-btn-desc"><?= e(__('pickup_desc', 'Pick up at our Ginza boutique with zero delivery fees.')); ?></div>
                        </div>
                        <div class="tab-btn" id="tab-delivery">
                            <div class="tab-btn-title">🚚 <?= e(__('delivery_option', 'Local Delivery')); ?></div>
                            <div class="tab-btn-desc"><?= e(__('delivery_desc', 'Hand-delivered to your door in chilled bags.')); ?></div>
                        </div>
                    </div>

                    <!-- Pickup Details Section -->
                    <div id="pickup-details-section">
                        <h4 style="font-size:0.95rem; margin-bottom:12px; color:var(--color-deep-plum);">
                            <?= e(__('pickup_details', 'Pickup Date & Time')); ?>
                        </h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><?= e(__('select_date', 'Pickup Date')); ?> *</label>
                                <input type="date" name="pickup_date" class="form-control" 
                                       min="<?= date('Y-m-d'); ?>" 
                                       value="<?= date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?= e(__('select_time', 'Pickup Time Slot')); ?> *</label>
                                <select name="pickup_time" class="form-control" required>
                                    <?php foreach ($pickupSlots as $slot): ?>
                                        <option value="<?= e($slot['slot_time']); ?>"><?= e($slot['slot_time']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <p style="font-size:0.82rem; color:var(--color-secondary-text);">
                            📍 Pickup Location: <strong>Ginza 4-2-11, Chuo-ku, Tokyo (Sweet Choice Flagship)</strong>
                        </p>
                    </div>

                    <!-- Delivery Details Section -->
                    <div id="delivery-details-section" style="display:none;">
                        <h4 style="font-size:0.95rem; margin-bottom:12px; color:var(--color-deep-plum);">
                            <?= e(__('delivery_details', 'Delivery Details & Address')); ?>
                        </h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><?= e(__('select_date', 'Delivery Date')); ?> *</label>
                                <input type="date" name="delivery_date" class="form-control" 
                                       min="<?= date('Y-m-d'); ?>" 
                                       value="<?= date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?= e(__('select_time', 'Delivery Time Slot')); ?> *</label>
                                <select name="delivery_time" class="form-control">
                                    <?php foreach ($deliverySlots as $dSlot): ?>
                                        <option value="<?= e($dSlot['slot_time']); ?>"><?= e($dSlot['slot_time']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Distance Tiers -->
                        <div class="form-group">
                            <label class="form-label"><?= e(__('distance_tier', 'Estimated Delivery Distance Area')); ?> *</label>
                            <select name="distance_tier_id" id="distance-tier-select" class="form-control">
                                <?php foreach ($deliveryTiers as $tier): ?>
                                    <option value="<?= $tier['id']; ?>" data-fee="<?= $tier['fee']; ?>">
                                        <?= e(isJapanese() ? $tier['tier_label_ja'] : $tier['tier_label_en']); ?> &mdash; <?= formatPrice($tier['fee']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><?= e(__('postal_code', 'Postal Code')); ?> (e.g. 104-0061) *</label>
                                <input type="text" name="postal_code" class="form-control" value="<?= e($user['postal_code'] ?? ''); ?>" placeholder="104-0061">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?= e(__('apartment_unit', 'Apartment / Building')); ?></label>
                                <input type="text" name="apartment" class="form-control" value="<?= e($user['apartment'] ?? ''); ?>" placeholder="Mansion Ginza #302">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?= e(__('street_address', 'Street Address')); ?> *</label>
                            <input type="text" name="address" class="form-control" value="<?= e($user['address'] ?? ''); ?>" placeholder="Tokyo, Chuo-ku, Ginza 4-2-11">
                        </div>
                    </div>
                </div>

                <!-- 2. Customer Contact Information -->
                <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:24px; margin-bottom:24px; box-shadow:var(--shadow-sm);">
                    <h3 style="font-size:1.15rem; margin-bottom:16px;">👤 <?= e(__('customer_info', 'Customer Information')); ?></h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><?= e(__('full_name', 'Full Name')); ?> *</label>
                            <input type="text" name="customer_name" class="form-control" value="<?= e($user['name'] ?? ''); ?>" required placeholder="Taro Yamada">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= e(__('phone_number', 'Phone Number')); ?> *</label>
                            <input type="tel" name="customer_phone" class="form-control" value="<?= e($user['phone'] ?? ''); ?>" required placeholder="090-1234-5678">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e(__('email_address', 'Email Address')); ?> *</label>
                        <input type="email" name="customer_email" class="form-control" value="<?= e($user['email'] ?? ''); ?>" required placeholder="yamada@example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e(__('order_notes', 'Special Instructions / Notes')); ?></label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="Allergies, birthday candles, delivery instructions..."></textarea>
                    </div>
                </div>

                <!-- 3. Payment Method & Mock Payment Form -->
                <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm);">
                    <h3 style="font-size:1.15rem; margin-bottom:16px;">💳 <?= e(__('payment_section', 'Payment Method')); ?></h3>

                    <div style="margin-bottom:18px;">
                        <?php foreach ($paymentMethods as $pIdx => $pm): ?>
                            <label class="custom-option-label" style="display:flex; margin-bottom:10px; width:100%;">
                                <input type="radio" name="payment_method" value="<?= e($pm['code']); ?>" <?= $pIdx === 0 ? 'checked' : ''; ?>>
                                <div style="margin-left:8px;">
                                    <strong><?= e(isJapanese() ? $pm['name_ja'] : $pm['name_en']); ?></strong>
                                    <div style="font-size:0.8rem; color:var(--color-secondary-text);">
                                        <?= e(isJapanese() ? $pm['description_ja'] : $pm['description_en']); ?>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Realistic Mock Card Inputs -->
                    <div id="mock-card-form" style="background:#FAF7F6; padding:18px; border-radius:var(--radius-sm); border:1px solid var(--color-border);">
                        <div class="form-group">
                            <label class="form-label"><?= e(__('card_number', 'Card Number')); ?></label>
                            <input type="text" class="form-control" placeholder="4242 &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; 4242" maxlength="19" value="4242 •••• •••• 4242">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><?= e(__('card_exp', 'MM / YY')); ?></label>
                                <input type="text" class="form-control" placeholder="12/28" maxlength="5" value="12/28">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?= e(__('card_cvv', 'CVV')); ?></label>
                                <input type="password" class="form-control" placeholder="123" maxlength="4" value="123">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label"><?= e(__('cardholder_name', 'Cardholder Name')); ?></label>
                            <input type="text" class="form-control" placeholder="TARO YAMADA" value="TARO YAMADA">
                        </div>
                    </div>

                    <div style="margin-top:12px; font-size:0.82rem; color:var(--color-secondary-text); background:#FFF8F0; padding:10px 14px; border-radius:var(--radius-sm); border-left:3px solid var(--color-warning);">
                        ℹ️ <?= e(__('payment_simulation_note', 'Simulation Mode: Educational project. No real payment will be charged.')); ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Order Summary -->
            <div>
                <div class="summary-card">
                    <h3 class="summary-title"><?= e(__('order_summary', 'Order Summary')); ?></h3>

                    <!-- Mini items breakdown -->
                    <div style="margin-bottom:18px; max-height:220px; overflow-y:auto; padding-right:6px;">
                        <?php foreach ($cartItems as $cItem): 
                            $lineSub = ((int)$cItem['price'] + (int)$cItem['customization_price']) * (int)$cItem['quantity'];
                        ?>
                            <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.88rem;">
                                <div>
                                    <strong><?= e(getLocalized($cItem, 'name')); ?></strong> &times; <?= $cItem['quantity']; ?>
                                    <?php if ($cItem['customization_summary']): ?>
                                        <div style="font-size:0.75rem; color:var(--color-secondary-text);"><?= e($cItem['customization_summary']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div style="font-weight:600;"><?= formatPrice($lineSub); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="summary-row">
                        <span><?= e(__('product_subtotal', 'Product Subtotal')); ?>:</span>
                        <span><?= formatPrice($productSubtotal); ?></span>
                    </div>

                    <?php if ($customizationTotal > 0): ?>
                        <div class="summary-row">
                            <span><?= e(__('customization_fees', 'Customization Fees')); ?>:</span>
                            <span><?= formatPrice($customizationTotal); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="summary-row">
                        <span><?= e(__('service_fee', 'Service Fee')); ?>:</span>
                        <span><?= formatPrice($serviceFee); ?></span>
                    </div>

                    <div class="summary-row" id="checkout-delivery-fee-row" style="display:none;">
                        <span><?= e(__('delivery_fee', 'Delivery Fee')); ?>:</span>
                        <span id="checkout-delivery-fee"><?= formatPrice(0); ?></span>
                    </div>

                    <div class="summary-row grand-total-row">
                        <span><?= e(__('grand_total', 'Grand Total')); ?>:</span>
                        <span id="checkout-grand-total"><?= formatPrice($productSubtotal + $customizationTotal + $serviceFee); ?></span>
                    </div>

                    <div style="margin-top:24px;">
                        <button type="submit" class="btn btn-primary btn-block btn-lg">
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
