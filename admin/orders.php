<?php
/**
 * Sweet Choice - Admin Orders Management
 * Track customer orders, update delivery/pickup status, and inspect itemized receipts.
 */

$adminTitle = 'Manage Orders';
require_once __DIR__ . '/header.php';

$viewId = isset($_GET['view']) && is_numeric($_GET['view']) ? (int)$_GET['view'] : 0;
$filterStatus = trim($_GET['status'] ?? 'all');

// Handle Order Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        $valid = ['Order Received', 'Preparing', 'Ready for Pickup', 'Out for Delivery', 'Completed', 'Delivered', 'Cancelled'];

        if (in_array($newStatus, $valid, true)) {
            $upd = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([$newStatus, $orderId]);
            setFlash('success', "Order #{$orderId} status changed to '{$newStatus}'.");
        }
    }
    header("Location: orders.php" . ($viewId ? "?view={$viewId}" : ""));
    exit;
}

// If viewing specific order
$selectedOrder = null;
$selectedItems = [];
if ($viewId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$viewId]);
    $selectedOrder = $stmt->fetch();

    if ($selectedOrder) {
        $itemStmt = $pdo->prepare("SELECT oi.*, p.image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $itemStmt->execute([$viewId]);
        $selectedItems = $itemStmt->fetchAll();
    }
}

// Build query for orders list
$sql = "SELECT * FROM orders WHERE 1=1";
$params = [];
if ($filterStatus !== 'all') {
    $sql .= " AND status = ?";
    $params[] = $filterStatus;
}
$sql .= " ORDER BY id DESC";

$ordersStmt = $pdo->prepare($sql);
$ordersStmt->execute($params);
$orders = $ordersStmt->fetchAll();
?>

<?php if ($selectedOrder): 
    $isDelivery = ($selectedOrder['fulfillment_type'] === 'delivery');
?>
    <!-- Detailed Order Receipt View -->
    <div class="admin-card" style="max-width:900px; margin:0 auto 30px auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:12px; border-bottom:1px solid var(--color-border);">
            <div>
                <h2 style="font-size:1.4rem;">Order #<?= e($selectedOrder['order_number']); ?></h2>
                <div style="font-size:0.85rem; color:var(--color-secondary-text);">Placed on <?= date('Y-m-d H:i', strtotime($selectedOrder['created_at'])); ?></div>
            </div>
            <a href="orders.php" class="btn btn-secondary btn-sm">&larr; Back to Orders List</a>
        </div>

        <!-- Status update form -->
        <div style="background:#FAF6F7; border:1px solid var(--color-dusty-rose); border-radius:var(--radius-sm); padding:16px 20px; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <strong>Current Status:</strong>
                <?= renderOrderStatusBadge($selectedOrder['status']); ?>
            </div>
            <form action="orders.php?view=<?= $selectedOrder['id']; ?>" method="POST" style="display:flex; align-items:center; gap:10px; margin:0;">
                <input type="hidden" name="action" value="update_order_status">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
                <input type="hidden" name="order_id" value="<?= $selectedOrder['id']; ?>">
                <select name="status" class="form-control" style="width:auto;">
                    <?php
                    $allStatuses = ['Order Received', 'Preparing', 'Ready for Pickup', 'Out for Delivery', 'Completed', 'Delivered', 'Cancelled'];
                    foreach ($allStatuses as $st): ?>
                        <option value="<?= $st; ?>" <?= $selectedOrder['status'] === $st ? 'selected' : ''; ?>><?= $st; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Update Status</button>
            </form>
        </div>

        <!-- Customer & Schedule Breakdown -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px; font-size:0.95rem;">
            <div style="background:#FFFDFD; border:1px solid var(--color-border); padding:16px; border-radius:var(--radius-sm);">
                <h4 style="color:var(--color-deep-plum); margin-bottom:10px;">👤 Customer Contact</h4>
                <p><strong>Name:</strong> <?= e($selectedOrder['customer_name']); ?></p>
                <p><strong>Email:</strong> <?= e($selectedOrder['customer_email']); ?></p>
                <p><strong>Phone:</strong> <?= e($selectedOrder['customer_phone']); ?></p>
                <p><strong>Payment:</strong> <?= ucfirst(str_replace('_', ' ', $selectedOrder['payment_method'])); ?> (<?= ucfirst($selectedOrder['payment_status']); ?>)</p>
            </div>

            <div style="background:#FFFDFD; border:1px solid var(--color-border); padding:16px; border-radius:var(--radius-sm);">
                <h4 style="color:var(--color-deep-plum); margin-bottom:10px;">
                    <?= $isDelivery ? '🚚 Delivery Details' : '🏬 Store Pickup Details'; ?>
                </h4>
                <p><strong>Fulfillment Method:</strong> <?= ucfirst($selectedOrder['fulfillment_type']); ?></p>
                <p>
                    <strong>Scheduled Date & Time:</strong><br>
                    <?= e($isDelivery ? $selectedOrder['delivery_date'] . ' @ ' . $selectedOrder['delivery_time'] : $selectedOrder['pickup_date'] . ' @ ' . $selectedOrder['pickup_time']); ?>
                </p>
                <?php if ($isDelivery): ?>
                    <p><strong>Address:</strong> <?= e($selectedOrder['address']); ?> <?= e($selectedOrder['apartment']); ?> (〒<?= e($selectedOrder['postal_code']); ?>)</p>
                    <p><strong>Distance Tier:</strong> <?= $selectedOrder['distance_km']; ?> km</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($selectedOrder['notes'])): ?>
            <div style="background:#FFF9F6; border-left:3px solid var(--color-warning); padding:12px; border-radius:var(--radius-sm); margin-bottom:24px; font-size:0.9rem;">
                <strong>Special Notes:</strong> <?= e($selectedOrder['notes']); ?>
            </div>
        <?php endif; ?>

        <!-- Items Table -->
        <table class="admin-table" style="margin-bottom:20px;">
            <thead>
                <tr>
                    <th>Item</th>
                    <th style="text-align:center;">Qty</th>
                    <th style="text-align:right;">Unit Price (JPY)</th>
                    <th style="text-align:right;">Line Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($selectedItems as $item): ?>
                    <tr>
                        <td>
                            <strong><?= e($item['product_name']); ?></strong>
                            <?php if ($item['customization_summary']): ?>
                                <div style="font-size:0.8rem; color:var(--color-secondary-text); margin-top:2px;">
                                    <?= e($item['customization_summary']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;"><?= $item['quantity']; ?></td>
                        <td style="text-align:right;"><?= formatPrice((int)$item['price'] + (int)$item['customization_price']); ?></td>
                        <td style="text-align:right; font-weight:700; color:var(--color-deep-plum);"><?= formatPrice($item['subtotal']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals summary -->
        <div style="max-width:320px; margin-left:auto; font-size:0.95rem;">
            <div class="summary-row">
                <span>Product Subtotal:</span>
                <span><?= formatPrice($selectedOrder['subtotal']); ?></span>
            </div>
            <?php if ($selectedOrder['customization_total'] > 0): ?>
                <div class="summary-row">
                    <span>Customization Total:</span>
                    <span><?= formatPrice($selectedOrder['customization_total']); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($selectedOrder['delivery_fee'] > 0): ?>
                <div class="summary-row">
                    <span>Delivery Fee:</span>
                    <span><?= formatPrice($selectedOrder['delivery_fee']); ?></span>
                </div>
            <?php endif; ?>
            <div class="summary-row">
                <span>Service Fee:</span>
                <span><?= formatPrice($selectedOrder['service_fee']); ?></span>
            </div>
            <div class="summary-row grand-total-row">
                <span>Grand Total:</span>
                <span><?= formatPrice($selectedOrder['grand_total']); ?></span>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Orders List Overview -->
<div class="admin-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:14px;">
        <div>
            <h2 style="font-size:1.4rem;">📦 Order Management (<?= count($orders); ?>)</h2>
            <p style="font-size:0.85rem; color:var(--color-secondary-text);">Filter and process customer dessert orders in real time.</p>
        </div>

        <!-- Filter tabs -->
        <div style="display:flex; gap:6px; flex-wrap:wrap;">
            <?php
            $statuses = ['all' => 'All Orders', 'Order Received' => 'Received', 'Preparing' => 'Preparing', 'Ready for Pickup' => 'Ready', 'Out for Delivery' => 'Out', 'Completed' => 'Completed', 'Delivered' => 'Delivered'];
            foreach ($statuses as $stKey => $stLabel): ?>
                <a href="orders.php?status=<?= urlencode($stKey); ?>" class="btn btn-sm <?= $filterStatus === $stKey ? 'btn-primary' : 'btn-cream'; ?>">
                    <?= $stLabel; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Fulfillment</th>
                    <th>Grand Total (JPY)</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--color-secondary-text);">No orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $ord): 
                        $sched = $ord['fulfillment_type'] === 'pickup' ? $ord['pickup_date'] . ' ' . $ord['pickup_time'] : $ord['delivery_date'] . ' ' . $ord['delivery_time'];
                    ?>
                        <tr>
                            <td>
                                <strong><a href="orders.php?view=<?= $ord['id']; ?>"><?= e($ord['order_number']); ?></a></strong>
                            </td>
                            <td><?= date('Y/m/d H:i', strtotime($ord['created_at'])); ?></td>
                            <td>
                                <div><?= e($ord['customer_name']); ?></div>
                                <div style="font-size:0.75rem; color:var(--color-secondary-text);"><?= e($ord['customer_phone']); ?></div>
                            </td>
                            <td>
                                <span class="badge <?= $ord['fulfillment_type'] === 'pickup' ? 'badge-available' : 'badge-limited'; ?>">
                                    <?= ucfirst($ord['fulfillment_type']); ?>
                                </span>
                                <div style="font-size:0.75rem; color:var(--color-secondary-text); margin-top:2px;"><?= e($sched); ?></div>
                            </td>
                            <td style="font-weight:700; color:var(--color-deep-plum);"><?= formatPrice($ord['grand_total']); ?></td>
                            <td><?= renderOrderStatusBadge($ord['status']); ?></td>
                            <td>
                                <a href="orders.php?view=<?= $ord['id']; ?>" class="btn btn-secondary btn-sm" style="padding:4px 10px; font-size:0.75rem;">View Receipt &rarr;</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
