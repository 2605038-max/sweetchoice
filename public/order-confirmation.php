<?php
/**
 * Sweet Choice - Order Confirmation Page
 *
 * Displays complete itemized receipt including Eligible Product Subtotal, Customization Fees,
 * Pairing Promotions, Mini-Game Discount, Service Fee, Delivery Fee, and Final Amount.
 * - Enforces order ownership check (logged-in owner, admin, recent session order, or verified lookup).
 * - Legacy post-order free-mini-cake campaign banner removed so rewards cannot stack.
 * - Read-only GET page: refreshing never reapplies discounts or creates duplicate orders.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/checkout.php';

$pdo = getDB();
$orderNumber = trim($_GET['order'] ?? '');

if ($orderNumber === '') {
    header('Location: index.php');
    exit;
}

$orderStmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ?");
$orderStmt->execute([$orderNumber]);
$order = $orderStmt->fetch();

if (!$order) {
    header('Location: index.php');
    exit;
}

// Ownership / Access Verification
$isOwner = isLoggedIn() && (isAdmin() || ((int)$order['user_id'] > 0 && (int)$order['user_id'] === (int)$_SESSION['user_id']));
$isRecentSessionOrder = in_array($orderNumber, $_SESSION['recent_order_numbers'] ?? [], true);
$isVerifiedLookup = in_array($orderNumber, $_SESSION['verified_lookup_orders'] ?? [], true);

if (!$isOwner && !$isRecentSessionOrder && !$isVerifiedLookup) {
    setFlash('info', __('order_access_denied', 'Please log in to view this order receipt.'));
    $_SESSION['intended_redirect'] = 'order-confirmation.php?order=' . urlencode($orderNumber);
    header('Location: login.php');
    exit;
}

// Fetch order items (with localized product names when product still exists)
$itemsStmt = $pdo->prepare("
    SELECT oi.*, p.image, p.name_en AS prod_name_en, p.name_ja AS prod_name_ja
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$itemsStmt->execute([$order['id']]);
$orderItems = $itemsStmt->fetchAll();

// Fetch localized payment method name
$pmStmt = $pdo->prepare("SELECT name_en, name_ja FROM payment_methods WHERE code = ? LIMIT 1");
$pmStmt->execute([$order['payment_method']]);
$pmRow = $pmStmt->fetch();
$paymentMethodLabel = $pmRow
    ? (isJapanese() ? $pmRow['name_ja'] : $pmRow['name_en'])
    : ucfirst(str_replace('_', ' ', $order['payment_method']));

$paymentStatusLabel = ($order['payment_status'] === 'paid')
    ? __('order_payment_status_paid', 'Paid (Simulated)')
    : __('order_payment_status_pending', 'Pending (Pay on Fulfillment)');

$pairingDiscountTotal = (int)($order['pairing_discount_total'] ?? 0);
$gameDiscountTotal = (int)($order['game_discount_total'] ?? 0);
$eligibleProductSubtotal = (int)$order['subtotal'] + $pairingDiscountTotal;
$minigameSkipped = ($eligibleProductSubtotal < MINIGAME_MIN_SUBTOTAL && $gameDiscountTotal === 0);

$pageTitle = __('order_confirmed_title', 'Order Confirmed') . ' - ' . $order['order_number'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container checkout-page-wrap">
    <div class="confirmation-content-wrap">

    <!-- Confirmation Card -->
    <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-lg); padding:40px; box-shadow:var(--shadow-md); text-align:center; margin-bottom:30px;">
        <div style="width:70px; height:70px; background:#EBF7E7; color:#265B1D; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:2rem; margin-bottom:16px;">
            ✓
        </div>
        <h1 style="font-size:2.1rem; margin-bottom:10px;"><?= e(__('order_confirmed_title', 'Thank You for Your Order!')); ?></h1>
        <p style="color:var(--color-secondary-text); font-size:1.02rem; margin-bottom:20px;">
            <?= e(__('order_confirmed_subtitle', 'Your confections are being prepared with love and precision.')); ?>
        </p>

        <div style="display:inline-flex; gap:14px; align-items:center; flex-wrap:wrap; justify-content:center; background:#FAF6F7; padding:12px 24px; border-radius:var(--radius-full); border:1px solid var(--color-dusty-rose);">
            <span><strong><?= e(__('order_number', 'Order Number')); ?>:</strong> <code style="font-size:1.05rem; color:var(--color-deep-plum);"><?= e($order['order_number']); ?></code></span>
            <span>&bull;</span>
            <span><?= renderOrderStatusBadge($order['status']); ?></span>
            <?php if ($gameDiscountTotal > 0): ?>
                <span>&bull;</span>
                <span class="badge" style="background:#EDF9EA; color:#265B1D; border:1px solid #B8E4AE;">🎮 <?= e(__('order_minigame_discount_badge', 'Sweet Run -¥200')); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Receipt Details Card -->
    <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:30px; box-shadow:var(--shadow-sm); margin-bottom:30px;">
        <h3 style="font-size:1.3rem; margin-bottom:20px; padding-bottom:10px; border-bottom:1px solid var(--color-border);">
            🧾 <?= isJapanese() ? 'ご注文明細' : 'Order Receipt'; ?>
        </h3>

        <!-- Fulfillment & Customer Info Grid -->
        <div class="form-row" style="margin-bottom:24px; font-size:0.94rem;">
            <div>
                <h4 style="font-size:1rem; color:var(--color-deep-plum); margin-bottom:8px;">
                    <?= $order['fulfillment_type'] === 'pickup' ? '🏬 ' . e(__('pickup_details', 'Pickup Details')) : '🚚 ' . e(__('delivery_details', 'Delivery Details')); ?>
                </h4>
                <p><strong><?= e(__('fulfillment_type', 'Method')); ?>:</strong>
                    <?= $order['fulfillment_type'] === 'delivery' ? e(__('delivery_option', 'Local Delivery')) : e(__('pickup_option', 'Store Pickup')); ?>
                </p>
                <p>
                    <strong><?= e(__('scheduled_time', 'Scheduled Time')); ?>:</strong>
                    <?= e($order['fulfillment_type'] === 'pickup' ? $order['pickup_date'] . ' (' . $order['pickup_time'] . ')' : $order['delivery_date'] . ' (' . $order['delivery_time'] . ')'); ?>
                </p>
                <?php if ($order['fulfillment_type'] === 'delivery'): ?>
                    <p><strong><?= e(__('street_address', 'Address')); ?>:</strong> <?= e($order['address']); ?> <?= e($order['apartment']); ?> (〒<?= e($order['postal_code']); ?>)</p>
                <?php else: ?>
                    <p><strong><?= e(__('order_boutique_address_label', 'Boutique Address')); ?>:</strong> <?= e(getShopSetting($pdo, 'shop_address', 'Ginza 4-2-11, Chuo-ku, Tokyo (Sweet Choice)')); ?></p>
                <?php endif; ?>
                <p><strong><?= e(__('order_date_label', 'Order Date')); ?>:</strong> <?= date('Y/m/d H:i', strtotime($order['created_at'])); ?></p>
            </div>

            <div>
                <h4 style="font-size:1rem; color:var(--color-deep-plum); margin-bottom:8px;">👤 <?= e(__('customer_info', 'Customer Information')); ?></h4>
                <p><strong><?= e(__('full_name', 'Name')); ?>:</strong> <?= e($order['customer_name']); ?></p>
                <p><strong><?= e(__('email_address', 'Email')); ?>:</strong> <?= e($order['customer_email']); ?></p>
                <p><strong><?= e(__('phone_number', 'Phone')); ?>:</strong> <?= e($order['customer_phone']); ?></p>
                <p><strong><?= e(__('payment_section', 'Payment')); ?>:</strong> <?= e($paymentMethodLabel); ?> (<?= e($paymentStatusLabel); ?>)</p>
            </div>
        </div>

        <?php if (!empty($order['notes'])): ?>
            <div style="background:#FFF9F6; padding:12px 16px; border-radius:var(--radius-sm); border-left:3px solid var(--color-warning); margin-bottom:24px; font-size:0.9rem;">
                <strong><?= e(__('order_notes', 'Order Notes')); ?>:</strong> <?= e($order['notes']); ?>
            </div>
        <?php endif; ?>

        <!-- Itemized Table -->
        <table class="cart-table" style="margin-bottom:24px;">
            <thead>
                <tr>
                    <th><?= e(__('item', 'Item')); ?></th>
                    <th style="text-align:center;"><?= e(__('quantity', 'Quantity')); ?></th>
                    <th style="text-align:right;"><?= e(__('price', 'Unit Price')); ?></th>
                    <th style="text-align:right;"><?= e(__('subtotal', 'Total')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orderItems as $item):
                    $displayName = isJapanese()
                        ? (!empty($item['prod_name_ja']) ? $item['prod_name_ja'] : $item['product_name'])
                        : (!empty($item['prod_name_en']) ? $item['prod_name_en'] : $item['product_name']);
                ?>
                    <tr>
                        <td>
                            <strong><?= e($displayName); ?></strong>
                            <?php if (!empty($item['discount_amount']) && (int)$item['discount_amount'] > 0): ?>
                                <span class="badge" style="background:#FFE6EB; color:#C44569; font-size:0.7rem; padding:1px 6px; margin-left:4px;">
                                    🌸 <?= e(__('pairing_discount_applied', 'Pairing discount applied')); ?> (-<?= formatPrice($item['discount_amount']); ?>)
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($item['customization_summary'])): ?>
                                <div style="font-size:0.8rem; color:var(--color-secondary-text); margin-top:2px;">
                                    <?= e($item['customization_summary']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;"><?= (int)$item['quantity']; ?></td>
                        <td style="text-align:right;">
                            <?php if (!empty($item['discount_amount']) && (int)$item['discount_amount'] > 0 && !empty($item['original_price'])): ?>
                                <span style="text-decoration:line-through; font-size:0.8rem; color:var(--color-secondary-text); margin-right:4px;">
                                    <?= formatPrice($item['original_price']); ?>
                                </span>
                            <?php endif; ?>
                            <?= formatPrice((int)$item['price'] + (int)$item['customization_price']); ?>
                        </td>
                        <td style="text-align:right; font-weight:700; color:var(--color-deep-plum);">
                            <?= formatPrice($item['subtotal']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals Breakdown -->
        <div style="max-width:360px; margin-left:auto; font-size:0.95rem;">
            <div class="summary-row">
                <span><?= e(__('checkout_eligible_subtotal', 'Eligible Product Subtotal')); ?>:</span>
                <span><?= formatPrice($eligibleProductSubtotal); ?></span>
            </div>
            <?php if ((int)$order['customization_total'] > 0): ?>
                <div class="summary-row">
                    <span><?= e(__('customization_fees', 'Customization Fees')); ?>:</span>
                    <span><?= formatPrice($order['customization_total']); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($pairingDiscountTotal > 0): ?>
                <div class="summary-row" style="color:#2E7D32; font-weight:600;">
                    <span>🌸 <?= e(__('checkout_other_promotions', 'Pairing Promotions')); ?>:</span>
                    <span>-<?= formatPrice($pairingDiscountTotal); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($gameDiscountTotal > 0): ?>
                <div class="summary-row" style="color:#2E7D32; font-weight:700;">
                    <span>🎮 <?= e(__('checkout_minigame_discount', 'Mini-Game Discount')); ?>:</span>
                    <span>-<?= formatPrice($gameDiscountTotal); ?></span>
                </div>
            <?php endif; ?>
            <?php if ((int)$order['delivery_fee'] > 0): ?>
                <div class="summary-row">
                    <span><?= e(__('delivery_fee', 'Delivery Fee')); ?>:</span>
                    <span><?= formatPrice($order['delivery_fee']); ?></span>
                </div>
            <?php endif; ?>
            <?php if ((int)$order['service_fee'] > 0): ?>
                <div class="summary-row">
                    <span><?= e(__('service_fee', 'Service Fee')); ?>:</span>
                    <span><?= formatPrice($order['service_fee']); ?></span>
                </div>
            <?php endif; ?>
            <div class="summary-row grand-total-row">
                <span><?= e(__('checkout_final_amount', 'Final Amount')); ?>:</span>
                <span><?= formatPrice($order['grand_total']); ?></span>
            </div>
        </div>
    </div>

    <!-- Bottom Actions -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <a href="category.php" class="btn btn-secondary">&larr; <?= e(__('continue_shopping', 'Continue Shopping')); ?></a>
        <a href="orders.php" class="btn btn-primary"><?= e(__('my_orders', 'My Orders')); ?> &rarr;</a>
    </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
