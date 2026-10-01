<?php
/**
 * Sweet Choice - Admin Dashboard Overview
 */

$adminTitle = 'Dashboard Overview';
require_once __DIR__ . '/header.php';

// Quick order status update handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_status_update') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        $validStatuses = ['Order Received', 'Preparing', 'Ready for Pickup', 'Out for Delivery', 'Completed', 'Delivered', 'Cancelled'];

        if (in_array($newStatus, $validStatuses, true)) {
            $upd = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $upd->execute([$newStatus, $orderId]);
            setFlash('success', "Order #{$orderId} status updated to: {$newStatus}");
        }
    }
    header("Location: index.php");
    exit;
}

// Compute Dashboard Stats
$totalRevenue = (int)$pdo->query("SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE status NOT IN ('Cancelled')")->fetchColumn();
$totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();

// Fetch Recent Orders
$recentOrders = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 8")->fetchAll();

// Fetch Low Stock Products
$lowStock = $pdo->query("SELECT p.*, c.name_en AS category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.stock <= 10 ORDER BY p.stock ASC LIMIT 5")->fetchAll();
?>

<!-- Metric Stat Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Revenue (JPY)</div>
        <div class="stat-val"><?= formatPrice($totalRevenue); ?></div>
        <div style="font-size:0.8rem; color:var(--color-success);">✓ Paid & confirmed orders</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Orders Placed</div>
        <div class="stat-val"><?= number_format($totalOrders); ?></div>
        <div style="font-size:0.8rem; color:var(--color-secondary-text);">Boutique & Delivery</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Active Desserts</div>
        <div class="stat-val"><?= number_format($totalProducts); ?></div>
        <div style="font-size:0.8rem; color:var(--color-secondary-text);">Across 5 Categories</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Registered Customers</div>
        <div class="stat-val"><?= number_format($totalUsers); ?></div>
        <div style="font-size:0.8rem; color:var(--color-secondary-text);">Sweet Choice Members</div>
    </div>
</div>

<!-- Quick Actions Navigation Bar -->
<div class="admin-card" style="margin-bottom:30px; background:#FAF7F6;">
    <h3 style="font-size:1.1rem; margin-bottom:14px;">⚡ Management Quick Actions</h3>
    <div style="display:flex; flex-wrap:wrap; gap:12px;">
        <a href="products.php?action=new" class="btn btn-primary btn-sm">+ Add New Dessert</a>
        <a href="categories.php" class="btn btn-secondary btn-sm">Manage Categories</a>
        <a href="banners.php" class="btn btn-secondary btn-sm">Manage Hero Banners</a>
        <a href="orders.php" class="btn btn-secondary btn-sm">Process Orders</a>
        <a href="reviews.php" class="btn btn-secondary btn-sm">Moderate Reviews</a>
        <a href="settings.php" class="btn btn-secondary btn-sm">Delivery & Time Slots</a>
    </div>
</div>

<div style="display:grid; grid-template-columns:2fr 1fr; gap:26px;">
    <!-- Recent Orders Table -->
    <div class="admin-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <h3 style="font-size:1.25rem;">📦 Recent Orders</h3>
            <a href="orders.php" class="btn btn-secondary btn-sm">View All Orders &rarr;</a>
        </div>

        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Type & Schedule</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentOrders)): ?>
                        <tr><td colspan="6" style="text-align:center; color:var(--color-secondary-text);">No orders found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $ro): 
                            $sched = $ro['fulfillment_type'] === 'pickup' ? $ro['pickup_date'] . ' ' . $ro['pickup_time'] : $ro['delivery_date'] . ' ' . $ro['delivery_time'];
                        ?>
                            <tr>
                                <td>
                                    <strong><a href="orders.php?view=<?= $ro['id']; ?>" style="color:var(--color-deep-plum);"><?= e($ro['order_number']); ?></a></strong>
                                    <div style="font-size:0.75rem; color:var(--color-secondary-text);"><?= date('M d, H:i', strtotime($ro['created_at'])); ?></div>
                                </td>
                                <td>
                                    <div><?= e($ro['customer_name']); ?></div>
                                    <div style="font-size:0.75rem; color:var(--color-secondary-text);"><?= e($ro['customer_phone']); ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= $ro['fulfillment_type'] === 'pickup' ? 'badge-available' : 'badge-limited'; ?>">
                                        <?= ucfirst($ro['fulfillment_type']); ?>
                                    </span>
                                    <div style="font-size:0.75rem; color:var(--color-secondary-text); margin-top:2px;"><?= e($sched); ?></div>
                                </td>
                                <td style="font-weight:700; color:var(--color-deep-plum);">
                                    <?= formatPrice($ro['grand_total']); ?>
                                </td>
                                <td>
                                    <form action="index.php" method="POST" style="margin:0;">
                                        <input type="hidden" name="action" value="quick_status_update">
                                        <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
                                        <input type="hidden" name="order_id" value="<?= $ro['id']; ?>">
                                        <select name="status" class="form-control" style="font-size:0.8rem; padding:4px 8px; width:auto;" onchange="this.form.submit();">
                                            <?php
                                            $allStatuses = ['Order Received', 'Preparing', 'Ready for Pickup', 'Out for Delivery', 'Completed', 'Delivered', 'Cancelled'];
                                            foreach ($allStatuses as $st): ?>
                                                <option value="<?= $st; ?>" <?= $ro['status'] === $st ? 'selected' : ''; ?>><?= $st; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <a href="orders.php?view=<?= $ro['id']; ?>" class="btn btn-secondary btn-sm" style="padding:4px 10px; font-size:0.75rem;">Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right: Low Stock & Store Info -->
    <div>
        <div class="admin-card">
            <h3 style="font-size:1.15rem; margin-bottom:14px; color:var(--color-danger);">⚠️ Inventory Alerts</h3>
            <?php if (empty($lowStock)): ?>
                <p style="font-size:0.88rem; color:var(--color-secondary-text);">All desserts have adequate inventory.</p>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <?php foreach ($lowStock as $ls): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--color-border-light); padding-bottom:8px; font-size:0.88rem;">
                            <div>
                                <strong><?= e($ls['name_en']); ?></strong>
                                <div style="font-size:0.75rem; color:var(--color-secondary-text);"><?= e($ls['category_name']); ?></div>
                            </div>
                            <div style="text-align:right;">
                                <span class="badge <?= $ls['stock'] <= 0 ? 'badge-sold-out' : 'badge-limited'; ?>">
                                    <?= $ls['stock']; ?> left
                                </span>
                                <div style="margin-top:4px;">
                                    <a href="products.php?action=edit&id=<?= $ls['id']; ?>" style="font-size:0.75rem;">Edit Stock &rarr;</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="admin-card" style="background:#FAF8F7;">
            <h3 style="font-size:1.15rem; margin-bottom:12px;">📍 Store Information</h3>
            <p style="font-size:0.85rem; color:var(--color-secondary-text); line-height:1.7;">
                <strong>Ginza Flagship Boutique</strong><br>
                Currency: <strong>Japanese Yen (¥ / JPY)</strong><br>
                Service Fee: <strong><?= formatPrice(getShopSetting($pdo, 'service_fee', '150')); ?></strong><br>
                Store Hours: <strong><?= e(getShopSetting($pdo, 'business_hours_display', '10:00 AM - 8:00 PM')); ?></strong>
            </p>
            <div style="margin-top:14px;">
                <a href="settings.php" class="btn btn-secondary btn-sm btn-block">Edit Shop Settings &rarr;</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
