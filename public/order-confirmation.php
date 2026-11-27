<?php
/**
 * Sweet Choice - Order Confirmation Page
 * Shows itemized receipt, order status, pickup/delivery details, and totals in JPY.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';

$pdo = getDB();
$orderNumber = trim($_GET['order'] ?? '');

if (empty($orderNumber)) {
    header("Location: index.php");
    exit;
}

$orderStmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ?");
$orderStmt->execute([$orderNumber]);
$order = $orderStmt->fetch();

if (!$order) {
    header("Location: index.php");
    exit;
}

// If qualifying order (>= 3000), set as active verified order for mini-game campaign
if ((int)$order['grand_total'] >= 3000 && $order['status'] !== 'Cancelled') {
    $_SESSION['verified_order_number'] = $order['order_number'];
}

// Fetch order items
$itemsStmt = $pdo->prepare("SELECT oi.*, p.image FROM order_items oi 
                            LEFT JOIN products p ON oi.product_id = p.id 
                            WHERE oi.order_id = ?");
$itemsStmt->execute([$order['id']]);
$orderItems = $itemsStmt->fetchAll();

$pageTitle = 'Order Confirmed - ' . $order['order_number'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 40px 20px; max-width:860px;">
    <!-- Confirmation Card -->
    <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-lg); padding:40px; box-shadow:var(--shadow-md); text-align:center; margin-bottom:30px;">
        <div style="width:70px; height:70px; background:#EBF7E7; color:#265B1D; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:2rem; margin-bottom:16px;">
            ✓
        </div>
        <h1 style="font-size:2.2rem; margin-bottom:10px;"><?= e(__('order_confirmed_title', 'Thank You for Your Order!')); ?></h1>
        <p style="color:var(--color-secondary-text); font-size:1.05rem; margin-bottom:20px;">
            <?= e(__('order_confirmed_subtitle', 'Your confections are being prepared with love and precision.')); ?>
        </p>

        <div style="display:inline-flex; gap:16px; align-items:center; background:#FAF6F7; padding:12px 24px; border-radius:var(--radius-full); border:1px solid var(--color-dusty-rose);">
            <span><strong><?= e(__('order_number', 'Order Number')); ?>:</strong> <code style="font-size:1.1rem; color:var(--color-deep-plum);"><?= e($order['order_number']); ?></code></span>
            <span>&bull;</span>
            <span><?= renderOrderStatusBadge($order['status']); ?></span>
        </div>
    </div>

    <?php if ((int)$order['grand_total'] >= 3000 && $order['status'] !== 'Cancelled'): ?>
        <!-- Mini Game Campaign Banner -->
        <div style="background: linear-gradient(135deg, #FFF9F5 0%, #FFF2EC 100%); border: 2px dashed #E59A84; border-radius: 16px; padding: 24px 20px; text-align: center; margin-bottom: 30px; box-shadow: 0 4px 16px rgba(166, 111, 85, 0.12);">
            <div style="font-size: 2.2rem; margin-bottom: 6px;">🎂 🏃 🎁</div>
            <h3 style="font-size: 1.35rem; color: #8C3E26; font-weight: 800; margin-bottom: 8px; font-family: 'Playfair Display', serif;">
                <?= isJapanese() ? '【¥3,000以上ご購入者限定】特製ミニケーキ チャレンジ！' : '【Exclusive Bonus】Mini Cake Game Challenge!'; ?>
            </h3>
            <p style="color: #604638; font-size: 0.94rem; max-width: 620px; margin: 0 auto 16px auto; line-height: 1.6;">
                <?= isJapanese() 
                    ? 'Sweet Choice で¥3,000以上お買い上げいただいたお客様への特別プレゼント！<br>走るケーキゲーム（Sweet Run）で<strong>2,000点</strong>を達成すると、本注文（#' . e($order['order_number']) . '）に<strong>「パティシエ特製ミニケーキ（無料 ¥0）」</strong>を自動追加いたします！（最大3回挑戦可能）' 
                    : 'As a thank-you for your purchase of ¥3,000 or more, score 2,000 points in Sweet Run to get a Free Mini Artisan Cake (¥0) added to your order (#' . e($order['order_number']) . ')!'; ?>
            </p>
            <a href="game/index.html?order=<?= urlencode($order['order_number']); ?>" class="btn" style="background: #A66F55; color: #FFFFFF; font-weight: 800; padding: 12px 28px; border-radius: 25px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 1rem; box-shadow: 0 4px 14px rgba(166, 111, 85, 0.35); transition: transform 0.2s ease;">
                🍰 <?= isJapanese() ? '今すぐミニゲームに挑戦する（残り 3/3回）' : 'Play Sweet Run Now (3 tries)'; ?> &rarr;
            </a>
        </div>
    <?php endif; ?>

    <!-- Receipt Details Card -->
    <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:30px; box-shadow:var(--shadow-sm); margin-bottom:30px;">
        <h3 style="font-size:1.3rem; margin-bottom:20px; padding-bottom:10px; border-bottom:1px solid var(--color-border);">
            🧾 <?= isJapanese() ? 'ご注文明細' : 'Order Receipt'; ?>
        </h3>

        <!-- Fulfillment & Customer Info Grid -->
        <?php
            $fType = $order['fulfillment_type'];
            $fIcon = match($fType) {
                'dine_in' => '🍰 ',
                'delivery' => '🚚 ',
                default => '🏬 ',
            };
            $fHeading = match($fType) {
                'dine_in' => __('dine_in_details', 'Dine-in Details'),
                'delivery' => __('delivery_details', 'Delivery Details'),
                default => __('pickup_details', 'Pickup Details'),
            };
        ?>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px; font-size:0.95rem;">
            <div>
                <h4 style="font-size:1rem; color:var(--color-deep-plum); margin-bottom:8px;">
                    <?= $fIcon . e($fHeading); ?>
                </h4>
                <p><strong><?= e(__('fulfillment_type', 'Method')); ?>:</strong> <?= e(formatFulfillmentLabel($fType)); ?></p>
                <?php if ($fType === 'pickup'): ?>
                    <p>
                        <strong><?= e(__('scheduled_time', 'Scheduled Time')); ?>:</strong>
                        <?= e($order['pickup_date'] . ' (' . $order['pickup_time'] . ')'); ?>
                    </p>
                    <p><strong>Boutique Address:</strong> Ginza 4-2-11, Chuo-ku, Tokyo (Sweet Choice)</p>
                <?php elseif ($fType === 'delivery'): ?>
                    <p>
                        <strong><?= e(__('scheduled_time', 'Scheduled Time')); ?>:</strong>
                        <?= e($order['delivery_date'] . ' (' . $order['delivery_time'] . ')'); ?>
                    </p>
                    <p><strong><?= e(__('street_address', 'Address')); ?>:</strong> <?= e($order['address']); ?> <?= e($order['apartment']); ?> (〒<?= e($order['postal_code']); ?>)</p>
                <?php else: ?>
                    <p><strong>Boutique Café:</strong> Ginza 4-2-11, Chuo-ku, Tokyo (Sweet Choice)</p>
                <?php endif; ?>
            </div>

            <div>
                <h4 style="font-size:1rem; color:var(--color-deep-plum); margin-bottom:8px;">👤 <?= e(__('customer_info', 'Customer Information')); ?></h4>
                <p><strong><?= e(__('full_name', 'Name')); ?>:</strong> <?= e($order['customer_name']); ?></p>
                <p><strong><?= e(__('email_address', 'Email')); ?>:</strong> <?= e($order['customer_email']); ?></p>
                <p><strong><?= e(__('phone_number', 'Phone')); ?>:</strong> <?= e($order['customer_phone']); ?></p>
                <p>
                    <strong><?= e(__('payment_section', 'Payment')); ?>:</strong>
                    <?= e(formatPaymentMethodLabel($order['payment_method'], $order['payment_provider'] ?? null)); ?>
                    (<?= ucfirst($order['payment_status']); ?>)
                </p>
                <?php if ($order['payment_method'] === 'online_pay' && !empty($order['payment_provider'])): ?>
                    <p><strong><?= e(__('payment_provider', 'Payment Provider')); ?>:</strong> <?= e($order['payment_provider']); ?></p>
                <?php endif; ?>
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
                <?php foreach ($orderItems as $item): ?>
                    <tr>
                        <td>
                            <strong><?= e($item['product_name']); ?></strong>
                            <?php if (!empty($item['discount_amount']) && $item['discount_amount'] > 0): ?>
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
                        <td style="text-align:center;"><?= $item['quantity']; ?></td>
                        <td style="text-align:right;">
                            <?php if (!empty($item['discount_amount']) && $item['discount_amount'] > 0 && !empty($item['original_price'])): ?>
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
        <div style="max-width:320px; margin-left:auto; font-size:0.95rem;">
            <div class="summary-row">
                <span><?= e(__('product_subtotal', 'Subtotal')); ?>:</span>
                <span><?= formatPrice($order['subtotal']); ?></span>
            </div>
            <?php if ($order['customization_total'] > 0): ?>
                <div class="summary-row">
                    <span><?= e(__('customization_fees', 'Customization')); ?>:</span>
                    <span><?= formatPrice($order['customization_total']); ?></span>
                </div>
            <?php endif; ?>
            <?php if ((int)$order['service_fee'] > 0): ?>
                <div class="summary-row">
                    <span><?= e(__('service_fee', 'Service Fee')); ?>:</span>
                    <span><?= formatPrice($order['service_fee']); ?></span>
                </div>
            <?php endif; ?>
            <?php if ((int)$order['delivery_fee'] > 0): ?>
                <div class="summary-row">
                    <span><?= e(__('delivery_fee', 'Delivery Fee')); ?>:</span>
                    <span><?= formatPrice($order['delivery_fee']); ?></span>
                </div>
            <?php endif; ?>
            <div class="summary-row grand-total-row">
                <span><?= e(__('grand_total', 'Grand Total')); ?>:</span>
                <span><?= formatPrice($order['grand_total']); ?></span>
            </div>
        </div>
    </div>

    <!-- Bottom Actions -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <a href="category.php" class="btn btn-secondary">&larr; <?= e(__('continue_shopping', 'Continue Shopping')); ?></a>
        <a href="orders.php" class="btn btn-primary"> <?= e(__('my_orders', 'View All Orders')); ?></a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
