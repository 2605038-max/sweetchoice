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

    <!-- Receipt Details Card -->
    <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:30px; box-shadow:var(--shadow-sm); margin-bottom:30px;">
        <h3 style="font-size:1.3rem; margin-bottom:20px; padding-bottom:10px; border-bottom:1px solid var(--color-border);">
            🧾 <?= isJapanese() ? 'ご注文明細' : 'Order Receipt'; ?>
        </h3>

        <!-- Fulfillment & Customer Info Grid -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px; font-size:0.95rem;">
            <div>
                <h4 style="font-size:1rem; color:var(--color-deep-plum); margin-bottom:8px;">
                    <?= $order['fulfillment_type'] === 'pickup' ? '🏬 ' . e(__('pickup_details', 'Pickup Details')) : '🚚 ' . e(__('delivery_details', 'Delivery Details')); ?>
                </h4>
                <p><strong><?= e(__('fulfillment_type', 'Method')); ?>:</strong> <?= ucfirst($order['fulfillment_type']); ?></p>
                <p>
                    <strong><?= e(__('scheduled_time', 'Scheduled Time')); ?>:</strong> 
                    <?= e($order['fulfillment_type'] === 'pickup' ? $order['pickup_date'] . ' (' . $order['pickup_time'] . ')' : $order['delivery_date'] . ' (' . $order['delivery_time'] . ')'); ?>
                </p>
                <?php if ($order['fulfillment_type'] === 'delivery'): ?>
                    <p><strong><?= e(__('street_address', 'Address')); ?>:</strong> <?= e($order['address']); ?> <?= e($order['apartment']); ?> (〒<?= e($order['postal_code']); ?>)</p>
                <?php else: ?>
                    <p><strong>Boutique Address:</strong> Ginza 4-2-11, Chuo-ku, Tokyo (Sweet Choice)</p>
                <?php endif; ?>
            </div>

            <div>
                <h4 style="font-size:1rem; color:var(--color-deep-plum); margin-bottom:8px;">👤 <?= e(__('customer_info', 'Customer Information')); ?></h4>
                <p><strong><?= e(__('full_name', 'Name')); ?>:</strong> <?= e($order['customer_name']); ?></p>
                <p><strong><?= e(__('email_address', 'Email')); ?>:</strong> <?= e($order['customer_email']); ?></p>
                <p><strong><?= e(__('phone_number', 'Phone')); ?>:</strong> <?= e($order['customer_phone']); ?></p>
                <p><strong><?= e(__('payment_section', 'Payment')); ?>:</strong> <?= ucfirst(str_replace('_', ' ', $order['payment_method'])); ?> (<?= ucfirst($order['payment_status']); ?>)</p>
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
                            <?php if (!empty($item['customization_summary'])): ?>
                                <div style="font-size:0.8rem; color:var(--color-secondary-text); margin-top:2px;">
                                    <?= e($item['customization_summary']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;"><?= $item['quantity']; ?></td>
                        <td style="text-align:right;">
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
            <?php if ($order['delivery_fee'] > 0): ?>
                <div class="summary-row">
                    <span><?= e(__('delivery_fee', 'Delivery Fee')); ?>:</span>
                    <span><?= formatPrice($order['delivery_fee']); ?></span>
                </div>
            <?php endif; ?>
            <div class="summary-row">
                <span><?= e(__('service_fee', 'Service Fee')); ?>:</span>
                <span><?= formatPrice($order['service_fee']); ?></span>
            </div>
            <div class="summary-row grand-total-row">
                <span><?= e(__('grand_total', 'Grand Total')); ?>:</span>
                <span><?= formatPrice($order['grand_total']); ?></span>
            </div>
        </div>
    </div>

    <!-- Bottom Actions -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <a href="category.php" class="btn btn-secondary">&larr; <?= e(__('continue_shopping', 'Continue Shopping')); ?></a>
        <a href="orders.php" class="btn btn-primary">📋 <?= e(__('my_orders', 'View All Orders')); ?></a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
