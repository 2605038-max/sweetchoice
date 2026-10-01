<?php
/**
 * Sweet Choice - Shopping Cart Page
 * Database-backed shopping cart with customization breakdown, quantity controls, and fee calculation.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';

$pdo = getDB();
$cartId = getOrCreateCartId($pdo);

// Handle POST actions: Add, Update, Remove
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $customSelections = $_POST['custom'] ?? [];

        // Verify product exists and is available
        $prodStmt = $pdo->prepare("SELECT id, name_en, name_ja, price, stock, availability FROM products WHERE id = ?");
        $prodStmt->execute([$productId]);
        $prod = $prodStmt->fetch();

        if ($prod && $prod['availability'] !== 'sold_out') {
            // Process selected customizations
            $customSummary = [];
            $extraPricePerUnit = 0;

            if (!empty($customSelections) && is_array($customSelections)) {
                $placeholders = implode(',', array_fill(0, count($customSelections), '?'));
                $cStmt = $pdo->prepare("SELECT * FROM product_customizations WHERE id IN ($placeholders) AND product_id = ?");
                $cParams = array_values($customSelections);
                $cParams[] = $productId;
                $cStmt->execute($cParams);
                $selectedCustoms = $cStmt->fetchAll();

                foreach ($selectedCustoms as $c) {
                    $optName = isJapanese() ? $c['option_name_ja'] : $c['option_name_en'];
                    $grpName = isJapanese() ? $c['group_name_ja'] : $c['group_name_en'];
                    $customSummary[] = $grpName . ': ' . $optName . ($c['price_extra'] > 0 ? " (+¥{$c['price_extra']})" : "");
                    $extraPricePerUnit += (int)$c['price_extra'];
                }
            }

            $summaryStr = !empty($customSummary) ? implode(' | ', $customSummary) : null;

            // Check if identical item with same customizations already exists in this cart
            $existingStmt = $pdo->prepare("SELECT id, quantity FROM cart_items 
                                           WHERE cart_id = ? AND product_id = ? AND (customization_summary = ? OR (customization_summary IS NULL AND ? IS NULL))");
            $existingStmt->execute([$cartId, $productId, $summaryStr, $summaryStr]);
            $existing = $existingStmt->fetch();

            if ($existing) {
                $newQty = min((int)$prod['stock'], $existing['quantity'] + $qty);
                $upd = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
                $upd->execute([$newQty, $existing['id']]);
            } else {
                $ins = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity, customization_summary, customization_price, created_at) 
                                      VALUES (?, ?, ?, ?, ?, NOW())");
                $ins->execute([$cartId, $productId, min($qty, (int)$prod['stock']), $summaryStr, $extraPricePerUnit]);
            }

            setFlash('success', isJapanese() ? '商品をカートに追加しました。' : 'Added dessert to your cart!');
        }
        header("Location: cart.php");
        exit;
    }

    if ($action === 'update') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $newQty = max(1, (int)($_POST['quantity'] ?? 1));

        $upd = $pdo->prepare("UPDATE cart_items ci 
                              JOIN products p ON ci.product_id = p.id 
                              SET ci.quantity = LEAST(?, p.stock) 
                              WHERE ci.id = ? AND ci.cart_id = ?");
        $upd->execute([$newQty, $itemId, $cartId]);
        header("Location: cart.php");
        exit;
    }

    if ($action === 'remove') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $del = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND cart_id = ?");
        $del->execute([$itemId, $cartId]);
        setFlash('info', isJapanese() ? '商品をカートから削除しました。' : 'Item removed from your cart.');
        header("Location: cart.php");
        exit;
    }
}

// Fetch all cart items with product details
$cartItemsStmt = $pdo->prepare("SELECT ci.*, p.name_en, p.name_ja, p.price, p.image, p.stock, p.availability, 
                                       c.name_en AS category_name_en, c.name_ja AS category_name_ja 
                                FROM cart_items ci 
                                JOIN products p ON ci.product_id = p.id 
                                JOIN categories c ON p.category_id = c.id 
                                WHERE ci.cart_id = ? 
                                ORDER BY ci.id DESC");
$cartItemsStmt->execute([$cartId]);
$items = $cartItemsStmt->fetchAll();

// Calculate subtotal, customization total, and service fee
$productSubtotal = 0;
$customizationTotal = 0;

foreach ($items as $item) {
    $productSubtotal += ((int)$item['price'] * (int)$item['quantity']);
    $customizationTotal += ((int)$item['customization_price'] * (int)$item['quantity']);
}

$serviceFee = (int)getShopSetting($pdo, 'service_fee', '150');
$grandTotal = $productSubtotal + $customizationTotal + $serviceFee;

$pageTitle = __('cart_title', 'Shopping Cart');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 40px 20px;">
    <div class="section-header" style="text-align:left; margin-bottom:24px;">
        <span class="section-tag"><?= e(__('nav_cart', 'Cart')); ?></span>
        <h1 class="section-title"><?= e(__('cart_title', 'Shopping Cart')); ?></h1>
    </div>

    <?php if (empty($items)): ?>
        <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:60px 20px; text-align:center; box-shadow:var(--shadow-sm);">
            <div style="font-size:3.5rem; margin-bottom:16px;">🧁</div>
            <h2><?= e(__('cart_empty', 'Your dessert cart is empty.')); ?></h2>
            <p style="color:var(--color-secondary-text); margin:12px 0 24px 0;">
                <?= isJapanese() ? '職人が丹精込めて作ったできたてスイーツをぜひご覧ください。' : 'Discover artisanal Japanese confections fresh from our Tokyo boutique.'; ?>
            </p>
            <a href="category.php" class="btn btn-primary btn-lg"><?= e(__('cart_empty_cta', 'Discover delicious treats now')); ?> &rarr;</a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <!-- Items Table -->
            <div class="cart-table-wrapper">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th><?= e(__('item', 'Item')); ?></th>
                            <th><?= e(__('price', 'Price')); ?></th>
                            <th style="text-align:center;"><?= e(__('quantity', 'Quantity')); ?></th>
                            <th style="text-align:right;"><?= e(__('subtotal', 'Subtotal')); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): 
                            $unitTotal = (int)$item['price'] + (int)$item['customization_price'];
                            $lineSubtotal = $unitTotal * (int)$item['quantity'];
                        ?>
                            <tr>
                                <td>
                                    <div class="cart-product-cell">
                                        <img src="../uploads/products/<?= e($item['image']); ?>" alt="<?= e(getLocalized($item, 'name')); ?>" class="cart-item-thumb" onerror="this.src='../assets/images/logo_banner.jpg'">
                                        <div>
                                            <div class="cart-item-title">
                                                <a href="product.php?id=<?= $item['product_id']; ?>"><?= e(getLocalized($item, 'name')); ?></a>
                                            </div>
                                            <?php if (!empty($item['customization_summary'])): ?>
                                                <div class="cart-item-custom-list">
                                                    <?= e($item['customization_summary']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?= formatPrice($item['price']); ?></div>
                                    <?php if ($item['customization_price'] > 0): ?>
                                        <div style="font-size:0.8rem; color:var(--color-deep-plum);">+<?= formatPrice($item['customization_price']); ?> (opt)</div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <form action="cart.php" method="POST" style="display:inline-flex; align-items:center;">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="item_id" value="<?= $item['id']; ?>">
                                        <div class="qty-control" style="transform:scale(0.85);">
                                            <button type="button" class="qty-btn qty-minus">&minus;</button>
                                            <input type="number" name="quantity" value="<?= $item['quantity']; ?>" min="1" max="<?= $item['stock']; ?>" class="qty-input" onchange="this.form.submit();">
                                            <button type="button" class="qty-btn qty-plus">+</button>
                                        </div>
                                    </form>
                                </td>
                                <td style="text-align:right; font-weight:700; color:var(--color-deep-plum);">
                                    <?= formatPrice($lineSubtotal); ?>
                                </td>
                                <td style="text-align:right;">
                                    <form action="cart.php" method="POST" onsubmit="return confirm('Remove this item?');" style="margin:0;">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="item_id" value="<?= $item['id']; ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" style="padding:4px 10px; border-radius:var(--radius-full); font-size:0.75rem;" title="Remove">✕</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <a href="category.php" class="btn btn-secondary btn-sm">&larr; <?= e(__('continue_shopping', 'Continue Shopping')); ?></a>
                    <div style="font-size:0.85rem; color:var(--color-secondary-text);">
                        🌸 <?= e(__('free_delivery_tip', 'Distance tiers: 0-3km: ¥300 | 3-5km: ¥500 | 5-10km: ¥800')); ?>
                    </div>
                </div>
            </div>

            <!-- Order Summary Card -->
            <div class="summary-card">
                <h3 class="summary-title"><?= e(__('order_summary', 'Order Summary')); ?></h3>

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

                <div class="summary-row" style="color:var(--color-secondary-text); font-size:0.85rem;">
                    <span><?= e(__('delivery_fee', 'Delivery Fee')); ?>:</span>
                    <span><?= isJapanese() ? '注文手続き時に計算' : 'Calculated at checkout'; ?></span>
                </div>

                <div class="summary-row grand-total-row">
                    <span><?= e(__('grand_total', 'Subtotal Preview')); ?>:</span>
                    <span><?= formatPrice($grandTotal); ?></span>
                </div>

                <div style="margin-top:24px;">
                    <a href="checkout.php" class="btn btn-primary btn-block btn-lg">
                        <?= e(__('proceed_to_checkout', 'Proceed to Checkout')); ?> &rarr;
                    </a>
                </div>

                <p style="font-size:0.75rem; text-align:center; color:var(--color-secondary-text); margin-top:14px;">
                    <?= e(__('all_prices_jpy', 'All prices in Japanese Yen (¥ / JPY) including tax.')); ?>
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
