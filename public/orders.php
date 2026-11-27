<?php
/**
 * Sweet Choice - Customer Orders Page
 * Displays active and past orders with status tracking, mini-game discount badges, and links to receipts.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getDB();
$userId = $_SESSION['user_id'] ?? null;

$orders = [];
$lookupOrder = null;
$lookupError = null;

// Guest Order Lookup Form (preserves access to legacy guest orders)
if (isset($_GET['lookup_number']) && isset($_GET['lookup_email'])) {
    $num = trim($_GET['lookup_number']);
    $email = trim(strtolower($_GET['lookup_email']));

    $lookupStmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? AND LOWER(customer_email) = ?");
    $lookupStmt->execute([$num, $email]);
    $lookupOrder = $lookupStmt->fetch();

    if (!$lookupOrder) {
        $lookupError = __('order_guest_lookup_not_found', 'No order found matching this Order Number and Email address.');
    } else {
        $_SESSION['verified_lookup_orders'] = $_SESSION['verified_lookup_orders'] ?? [];
        if (!in_array($lookupOrder['order_number'], $_SESSION['verified_lookup_orders'], true)) {
            $_SESSION['verified_lookup_orders'][] = $lookupOrder['order_number'];
        }
    }
}

if ($userId) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll();
}

$pageTitle = __('my_orders', 'My Orders');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 40px 20px;">
    <div class="section-header" style="text-align:left; margin-bottom:24px;">
        <span class="section-tag"><?= e(__('nav_orders', 'Orders')); ?></span>
        <h1 class="section-title"><?= e(__('my_orders', 'My Orders')); ?></h1>
        <p class="section-subtitle" style="margin:0;">
            <?= isJapanese() ? 'ご注文の準備状況や配達状況をリアルタイムでご確認いただけます。' : 'Track the preparation and delivery progress of your sweet treats.'; ?>
        </p>
    </div>

    <!-- Logged-in Orders List -->
    <?php if ($userId): ?>
        <?php if (empty($orders)): ?>
            <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:50px 20px; text-align:center; box-shadow:var(--shadow-sm);">
                <h3><?= e(__('no_orders_found', 'You haven’t placed any orders yet.')); ?></h3>
                <p style="color:var(--color-secondary-text); margin:8px 0 20px 0;">
                    <?= e(__('order_no_orders_desc', 'Our pastry chefs are ready to craft something sweet for you.')); ?>
                </p>
                <a href="category.php" class="btn btn-primary"><?= e(__('cart_empty_cta', 'Discover delicious treats now')); ?></a>
            </div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:20px;">
                <?php foreach ($orders as $ord):
                    $fType = $ord['fulfillment_type'];
                    $scheduled = match($fType) {
                        'delivery' => trim(($ord['delivery_date'] ?? '') . ' ' . ($ord['delivery_time'] ?? '')),
                        'pickup' => trim(($ord['pickup_date'] ?? '') . ' ' . ($ord['pickup_time'] ?? '')),
                        default => isJapanese() ? '銀座店カフェ' : 'Ginza Boutique Café',
                    };
                    $gameDisc = (int)($ord['game_discount_total'] ?? 0);
                ?>
                    <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm); display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:16px;">
                        <div>
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:6px;">
                                <strong style="font-size:1.15rem; color:var(--color-deep-text);"><?= e($ord['order_number']); ?></strong>
                                <?= renderOrderStatusBadge($ord['status']); ?>
                                <?php if ($gameDisc > 0): ?>
                                    <span class="badge" style="background:#EDF9EA; color:#265B1D; border:1px solid #B8E4AE; font-size:0.74rem;">
                                        🎮 <?= e(__('checkout_minigame_discount', 'Mini-Game Discount')); ?> -<?= formatPrice($gameDisc); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:0.88rem; color:var(--color-secondary-text);">
                                <?= date('Y/m/d H:i', strtotime($ord['created_at'])); ?> &bull;
                                <span><?= e(formatFulfillmentLabel($fType)); ?></span> &bull;
                                <span><?= e($scheduled); ?></span>
                            </div>
                        </div>

                        <div style="display:flex; align-items:center; gap:20px;">
                            <div style="text-align:right;">
                                <div style="font-size:0.8rem; color:var(--color-secondary-text);"><?= e(__('checkout_final_amount', 'Final Amount')); ?></div>
                                <div style="font-size:1.3rem; font-weight:700; color:var(--color-deep-plum);"><?= formatPrice($ord['grand_total']); ?></div>
                            </div>
                            <a href="order-confirmation.php?order=<?= urlencode($ord['order_number']); ?>" class="btn btn-secondary btn-sm">
                                <?= e(__('view_order_details', 'View Receipt')); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Guest Lookup Card -->
        <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:30px; box-shadow:var(--shadow-sm); max-width:640px; margin-bottom:30px;">
            <h3 style="font-size:1.2rem; margin-bottom:12px;">🔍 <?= e(__('order_guest_lookup_title', 'Guest Order Lookup')); ?></h3>
            <p style="font-size:0.9rem; color:var(--color-secondary-text); margin-bottom:20px;">
                <?= e(__('order_guest_lookup_desc', 'Placed an order previously without an account? Enter your Order Number and Email address to view your receipt and status.')); ?>
            </p>

            <?php if ($lookupError): ?>
                <div class="alert alert-danger"><?= e($lookupError); ?></div>
            <?php endif; ?>

            <form action="orders.php" method="GET">
                <div class="form-group">
                    <label class="form-label"><?= e(__('order_number', 'Order Number')); ?> *</label>
                    <input type="text" name="lookup_number" class="form-control" placeholder="SWC-20261127-XXXXX" required value="<?= e($_GET['lookup_number'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e(__('email_address', 'Email Address')); ?> *</label>
                    <input type="email" name="lookup_email" class="form-control" placeholder="you@example.com" required value="<?= e($_GET['lookup_email'] ?? ''); ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-block"><?= e(__('order_guest_lookup_btn', 'Find My Order')); ?></button>
            </form>

            <div style="margin-top:20px; text-align:center; padding-top:16px; border-top:1px solid var(--color-border-light); font-size:0.9rem;">
                <?= e(__('already_have_account', 'Already have an account?')); ?>
                <a href="login.php" style="font-weight:700;"><?= e(__('order_guest_lookup_login_prompt', 'Log in to view all your past orders')); ?></a>
            </div>
        </div>

        <?php if ($lookupOrder): ?>
            <div style="background:#FFFFFF; border:2px solid var(--color-primary-pink); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm); max-width:640px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h4><?= e(__('order_guest_lookup_found', 'Order Found:')); ?> <?= e($lookupOrder['order_number']); ?></h4>
                    <?= renderOrderStatusBadge($lookupOrder['status']); ?>
                </div>
                <p style="margin-bottom:16px;"><?= e(__('checkout_final_amount', 'Final Amount')); ?>: <strong><?= formatPrice($lookupOrder['grand_total']); ?></strong></p>
                <a href="order-confirmation.php?order=<?= urlencode($lookupOrder['order_number']); ?>" class="btn btn-primary">
                    <?= e(__('order_view_complete_receipt', 'View Complete Receipt')); ?> &rarr;
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
