<?php
/**
 * Sweet Choice - Admin Store Settings & Slots Management
 * Distance delivery fee tiers, service fee, pickup/delivery slots, business hours, and payment methods.
 */

$adminTitle = 'Store Settings';
require_once __DIR__ . '/header.php';

// Handle POST Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Session expired. Please try again.');
    } else {
        $section = $_POST['section'] ?? '';

        // 1. Service Fee & Shop Info
        if ($section === 'general') {
            $serviceFee = max(0, (int)($_POST['service_fee'] ?? 150));
            $shopName = trim($_POST['shop_name'] ?? 'Sweet Choice');
            $shopPhone = trim($_POST['shop_phone'] ?? '');
            $shopEmail = trim($_POST['shop_email'] ?? '');
            $shopAddress = trim($_POST['shop_address'] ?? '');
            $shopHoursDisplay = trim($_POST['business_hours_display'] ?? '');

            $upd = $pdo->prepare("INSERT INTO shop_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $upd->execute(['service_fee', $serviceFee, $serviceFee]);
            $upd->execute(['shop_name', $shopName, $shopName]);
            $upd->execute(['shop_phone', $shopPhone, $shopPhone]);
            $upd->execute(['shop_email', $shopEmail, $shopEmail]);
            $upd->execute(['shop_address', $shopAddress, $shopAddress]);
            $upd->execute(['business_hours_display', $shopHoursDisplay, $shopHoursDisplay]);

            setFlash('success', 'General store settings updated.');
        }

        // 2. Delivery Tiers
        if ($section === 'delivery_tiers') {
            $tierFees = $_POST['tier_fee'] ?? [];
            $tierActive = $_POST['tier_active'] ?? [];

            foreach ($tierFees as $tierId => $fee) {
                $tierId = (int)$tierId;
                $feeVal = max(0, (int)$fee);
                $activeVal = isset($tierActive[$tierId]) ? 1 : 0;
                $updTier = $pdo->prepare("UPDATE delivery_settings SET fee = ?, is_active = ? WHERE id = ?");
                $updTier->execute([$feeVal, $activeVal, $tierId]);
            }
            setFlash('success', 'Distance delivery tiers updated.');
        }

        // 3. Add Slot
        if ($section === 'add_slot') {
            $type = $_POST['slot_type'] ?? 'pickup';
            $slotTime = trim($_POST['slot_time'] ?? '');
            $maxOrders = max(1, (int)($_POST['max_orders'] ?? 10));

            if (!empty($slotTime)) {
                $table = ($type === 'pickup') ? 'pickup_slots' : 'delivery_slots';
                $ins = $pdo->prepare("INSERT INTO {$table} (slot_time, max_orders, is_active) VALUES (?, ?, 1)");
                $ins->execute([$slotTime, $maxOrders]);
                setFlash('success', "New {$type} time slot added: {$slotTime}");
            }
        }

        // 4. Toggle Payment Method
        if ($section === 'payments') {
            $activePay = $_POST['pay_active'] ?? [];
            $allPm = $pdo->query("SELECT id FROM payment_methods")->fetchAll();
            foreach ($allPm as $pm) {
                $status = isset($activePay[$pm['id']]) ? 1 : 0;
                $pdo->prepare("UPDATE payment_methods SET is_active = ? WHERE id = ?")->execute([$status, $pm['id']]);
            }
            setFlash('success', 'Payment methods updated.');
        }

        // 5. Business Hours
        if ($section === 'hours') {
            $openTimes = $_POST['open_time'] ?? [];
            $closeTimes = $_POST['close_time'] ?? [];
            $isClosed = $_POST['is_closed'] ?? [];

            foreach ($openTimes as $dayId => $open) {
                $close = $closeTimes[$dayId] ?? '20:00:00';
                $closedVal = isset($isClosed[$dayId]) ? 1 : 0;
                $pdo->prepare("UPDATE business_hours SET opening_time = ?, closing_time = ?, is_closed = ? WHERE id = ?")
                    ->execute([$open, $close, $closedVal, (int)$dayId]);
            }
            setFlash('success', 'Business operating hours updated.');
        }
    }
    header("Location: settings.php");
    exit;
}

// Handle Slot Deletions
if (isset($_GET['delete_slot']) && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $slotId = (int)$_GET['delete_slot'];
    $slotType = $_GET['type'] ?? 'pickup';
    $table = ($slotType === 'pickup') ? 'pickup_slots' : 'delivery_slots';
    $pdo->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$slotId]);
    setFlash('info', 'Slot removed.');
    header("Location: settings.php");
    exit;
}

// Fetch all current settings
$deliveryTiers = $pdo->query("SELECT * FROM delivery_settings ORDER BY min_km ASC")->fetchAll();
$pickupSlots = $pdo->query("SELECT * FROM pickup_slots ORDER BY id ASC")->fetchAll();
$deliverySlots = $pdo->query("SELECT * FROM delivery_slots ORDER BY id ASC")->fetchAll();
$businessHours = $pdo->query("SELECT * FROM business_hours ORDER BY day_order ASC")->fetchAll();
$paymentMethods = $pdo->query("SELECT * FROM payment_methods ORDER BY id ASC")->fetchAll();

$serviceFee = getShopSetting($pdo, 'service_fee', '150');
$shopName = getShopSetting($pdo, 'shop_name', 'Sweet Choice');
$shopPhone = getShopSetting($pdo, 'shop_phone', '+81 3-5555-0199');
$shopEmail = getShopSetting($pdo, 'shop_email', 'contact@sweetchoice.jp');
$shopAddress = getShopSetting($pdo, 'shop_address', 'Ginza 4-2-11, Chuo-ku, Tokyo 104-0061, Japan');
$shopHoursDisplay = getShopSetting($pdo, 'business_hours_display', 'Mon - Sun: 10:00 AM - 8:00 PM');
?>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:26px;">
    <!-- 1. General & Service Fee Settings -->
    <div class="admin-card">
        <h3 style="font-size:1.2rem; margin-bottom:18px;">⚙️ Store & Service Fee Settings</h3>
        <form action="settings.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
            <input type="hidden" name="section" value="general">

            <div class="form-group">
                <label class="form-label">Service Fee (Japanese Yen ¥ / JPY) *</label>
                <input type="number" name="service_fee" class="form-control" min="0" value="<?= e($serviceFee); ?>" required>
                <div style="font-size:0.75rem; color:var(--color-secondary-text); margin-top:4px;">Packaging & handling fee applied to each order.</div>
            </div>

            <div class="form-group">
                <label class="form-label">Store Brand Name</label>
                <input type="text" name="shop_name" class="form-control" value="<?= e($shopName); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Customer Support Phone</label>
                    <input type="text" name="shop_phone" class="form-control" value="<?= e($shopPhone); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Support Email</label>
                    <input type="email" name="shop_email" class="form-control" value="<?= e($shopEmail); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Store Address (Ginza Boutique)</label>
                <input type="text" name="shop_address" class="form-control" value="<?= e($shopAddress); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Public Business Hours Display</label>
                <input type="text" name="business_hours_display" class="form-control" value="<?= e($shopHoursDisplay); ?>">
            </div>

            <button type="submit" class="btn btn-primary">Save General Settings</button>
        </form>
    </div>

    <!-- 2. Distance-Based Delivery Fee Tiers -->
    <div class="admin-card">
        <h3 style="font-size:1.2rem; margin-bottom:18px;">🚚 Distance-Based Delivery Tiers</h3>
        <p style="font-size:0.85rem; color:var(--color-secondary-text); margin-bottom:16px;">
            Configure tiered delivery pricing in Japanese Yen (¥ / JPY) based on customer distance.
        </p>

        <form action="settings.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
            <input type="hidden" name="section" value="delivery_tiers">

            <table class="admin-table" style="margin-bottom:18px;">
                <thead>
                    <tr>
                        <th>Distance Range</th>
                        <th>Delivery Fee (JPY)</th>
                        <th>Active</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deliveryTiers as $tier): ?>
                        <tr>
                            <td>
                                <strong><?= e($tier['tier_label_en']); ?></strong>
                                <div style="font-size:0.75rem; color:var(--color-secondary-text);"><?= e($tier['tier_label_ja']); ?></div>
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <span>¥</span>
                                    <input type="number" name="tier_fee[<?= $tier['id']; ?>]" value="<?= $tier['fee']; ?>" min="0" step="50" class="form-control" style="width:110px;">
                                </div>
                            </td>
                            <td>
                                <input type="checkbox" name="tier_active[<?= $tier['id']; ?>]" value="1" <?= $tier['is_active'] ? 'checked' : ''; ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <button type="submit" class="btn btn-primary">Update Delivery Fees</button>
        </form>
    </div>

    <!-- 3. Pickup Slots -->
    <div class="admin-card">
        <h3 style="font-size:1.2rem; margin-bottom:14px;">🏬 Boutique Pickup Slots</h3>
        <p style="font-size:0.85rem; color:var(--color-secondary-text); margin-bottom:14px;">Hourly pickup windows available during business hours.</p>

        <table class="admin-table" style="margin-bottom:18px;">
            <thead>
                <tr>
                    <th>Time Slot</th>
                    <th>Max Orders</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pickupSlots as $ps): ?>
                    <tr>
                        <td><strong><?= e($ps['slot_time']); ?></strong></td>
                        <td><?= $ps['max_orders']; ?> orders/hr</td>
                        <td>
                            <a href="settings.php?delete_slot=<?= $ps['id']; ?>&type=pickup&csrf_token=<?= getCsrfToken(); ?>" 
                               class="btn btn-secondary btn-sm" style="padding:2px 8px; font-size:0.75rem; color:var(--color-danger);"
                               onclick="return confirm('Remove pickup slot?');">Remove</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Add Slot Form -->
        <form action="settings.php" method="POST" style="display:flex; gap:10px; align-items:center;">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
            <input type="hidden" name="section" value="add_slot">
            <input type="hidden" name="slot_type" value="pickup">
            <input type="text" name="slot_time" class="form-control" placeholder="20:00 - 21:00" required style="width:160px;">
            <input type="number" name="max_orders" class="form-control" placeholder="10" value="10" min="1" style="width:90px;">
            <button type="submit" class="btn btn-secondary btn-sm">+ Add Slot</button>
        </form>
    </div>

    <!-- 4. Delivery Slots -->
    <div class="admin-card">
        <h3 style="font-size:1.2rem; margin-bottom:14px;">🚚 Local Delivery Slots</h3>
        <p style="font-size:0.85rem; color:var(--color-secondary-text); margin-bottom:14px;">Scheduled delivery windows for courier dispatch.</p>

        <table class="admin-table" style="margin-bottom:18px;">
            <thead>
                <tr>
                    <th>Time Window</th>
                    <th>Max Orders</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deliverySlots as $ds): ?>
                    <tr>
                        <td><strong><?= e($ds['slot_time']); ?></strong></td>
                        <td><?= $ds['max_orders']; ?> orders/window</td>
                        <td>
                            <a href="settings.php?delete_slot=<?= $ds['id']; ?>&type=delivery&csrf_token=<?= getCsrfToken(); ?>" 
                               class="btn btn-secondary btn-sm" style="padding:2px 8px; font-size:0.75rem; color:var(--color-danger);"
                               onclick="return confirm('Remove delivery slot?');">Remove</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Add Delivery Slot Form -->
        <form action="settings.php" method="POST" style="display:flex; gap:10px; align-items:center;">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
            <input type="hidden" name="section" value="add_slot">
            <input type="hidden" name="slot_type" value="delivery">
            <input type="text" name="slot_time" class="form-control" placeholder="20:30 - 21:30" required style="width:160px;">
            <input type="number" name="max_orders" class="form-control" placeholder="8" value="8" min="1" style="width:90px;">
            <button type="submit" class="btn btn-secondary btn-sm">+ Add Slot</button>
        </form>
    </div>

    <!-- 5. Payment Methods Management -->
    <div class="admin-card">
        <h3 style="font-size:1.2rem; margin-bottom:14px;">💳 Payment Methods</h3>
        <p style="font-size:0.85rem; color:var(--color-secondary-text); margin-bottom:14px;">Toggle customer checkout payment methods.</p>

        <form action="settings.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
            <input type="hidden" name="section" value="payments">

            <?php foreach ($paymentMethods as $pm): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--color-border-light);">
                    <div>
                        <strong><?= e($pm['name_en']); ?></strong> (<?= e($pm['name_ja']); ?>)
                        <div style="font-size:0.8rem; color:var(--color-secondary-text);"><?= e($pm['description_en']); ?></div>
                    </div>
                    <div>
                        <input type="checkbox" name="pay_active[<?= $pm['id']; ?>]" value="1" <?= $pm['is_active'] ? 'checked' : ''; ?>>
                    </div>
                </div>
            <?php endforeach; ?>

            <div style="margin-top:16px;">
                <button type="submit" class="btn btn-primary">Update Payment Methods</button>
            </div>
        </form>
    </div>

    <!-- 6. Business Operating Hours -->
    <div class="admin-card">
        <h3 style="font-size:1.2rem; margin-bottom:14px;">🕒 Weekly Business Hours</h3>
        <form action="settings.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
            <input type="hidden" name="section" value="hours">

            <table class="admin-table" style="margin-bottom:16px;">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Opening Time</th>
                        <th>Closing Time</th>
                        <th>Closed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($businessHours as $bh): ?>
                        <tr>
                            <td><strong><?= e($bh['day_of_week']); ?></strong></td>
                            <td>
                                <input type="time" name="open_time[<?= $bh['id']; ?>]" value="<?= e($bh['opening_time']); ?>" class="form-control" style="width:120px;">
                            </td>
                            <td>
                                <input type="time" name="close_time[<?= $bh['id']; ?>]" value="<?= e($bh['closing_time']); ?>" class="form-control" style="width:120px;">
                            </td>
                            <td>
                                <input type="checkbox" name="is_closed[<?= $bh['id']; ?>]" value="1" <?= $bh['is_closed'] ? 'checked' : ''; ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <button type="submit" class="btn btn-primary">Save Business Hours</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
