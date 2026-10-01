<?php
/**
 * Sweet Choice - Product Details Page
 * Displays full dessert information, ingredients, allergies, customizations, reviews, and dynamic cart addition.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';

$pdo = getDB();
$productId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId <= 0) {
    header("Location: category.php");
    exit;
}

// Fetch product details
$stmt = $pdo->prepare("SELECT p.*, c.name_en AS category_name_en, c.name_ja AS category_name_ja 
                       FROM products p 
                       JOIN categories c ON p.category_id = c.id 
                       WHERE p.id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: category.php");
    exit;
}

// Fetch ingredients
$ingStmt = $pdo->prepare("SELECT * FROM product_ingredients WHERE product_id = ? ORDER BY id ASC");
$ingStmt->execute([$productId]);
$ingredients = $ingStmt->fetchAll();

// Fetch allergies
$algStmt = $pdo->prepare("SELECT * FROM product_allergies WHERE product_id = ? ORDER BY id ASC");
$algStmt->execute([$productId]);
$allergies = $algStmt->fetchAll();

// Fetch customizations grouped by group_name
$custStmt = $pdo->prepare("SELECT * FROM product_customizations WHERE product_id = ? ORDER BY id ASC");
$custStmt->execute([$productId]);
$rawCustomizations = $custStmt->fetchAll();

$customGroups = [];
foreach ($rawCustomizations as $cust) {
    $groupName = isJapanese() ? $cust['group_name_ja'] : $cust['group_name_en'];
    $customGroups[$groupName][] = $cust;
}

// Fetch approved reviews
$revStmt = $pdo->prepare("SELECT r.*, u.name AS user_name 
                          FROM reviews r 
                          JOIN users u ON r.user_id = u.id 
                          WHERE r.product_id = ? AND r.is_approved = 1 
                          ORDER BY r.created_at DESC");
$revStmt->execute([$productId]);
$reviews = $revStmt->fetchAll();

// Check if logged-in user has ordered this product and completed the order (for review eligibility)
$canReview = false;
$userId = $_SESSION['user_id'] ?? null;
if ($userId) {
    $checkOrderStmt = $pdo->prepare("SELECT 1 FROM orders o 
                                     JOIN order_items oi ON o.id = oi.order_id 
                                     WHERE o.user_id = ? AND oi.product_id = ? AND o.status IN ('Completed', 'Delivered') 
                                     LIMIT 1");
    $checkOrderStmt->execute([$userId, $productId]);
    if ($checkOrderStmt->fetch()) {
        $canReview = true;
    }
}

$pageTitle = getLocalized($product, 'name');
require_once __DIR__ . '/../includes/header.php';

$isFavorited = isProductFavorited($pdo, $productId);
$isSoldOut = ($product['availability'] === 'sold_out');
?>

<div class="container" style="padding: 40px 20px;">
    <!-- Breadcrumb -->
    <div style="font-size:0.88rem; color:var(--color-secondary-text); margin-bottom:24px;">
        <a href="index.php"><?= e(__('nav_home', 'Home')); ?></a> &gt; 
        <a href="category.php"><?= e(__('nav_category', 'Category')); ?></a> &gt; 
        <a href="category.php?category=<?= $product['category_id']; ?>"><?= e(getLocalized($product, 'category_name')); ?></a> &gt; 
        <span style="color:var(--color-deep-text); font-weight:600;"><?= e(getLocalized($product, 'name')); ?></span>
    </div>

    <div class="product-detail-layout">
        <!-- Left: Product Image -->
        <div class="product-gallery">
            <div class="product-gallery-main">
                <img src="../uploads/products/<?= e($product['image']); ?>" alt="<?= e(getLocalized($product, 'name')); ?>" onerror="this.src='../assets/images/logo_banner.jpg'">
            </div>
            <div style="margin-top:16px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <?= renderAvailabilityBadge($product['availability']); ?>
                    <?php if ($product['is_popular']): ?>
                        <span class="badge" style="background:#FFF0F2; color:var(--color-deep-plum);">POPULAR</span>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-secondary btn-sm btn-favorite <?= $isFavorited ? 'active' : ''; ?>" data-product-id="<?= $productId; ?>" style="position:static; width:auto; border-radius:var(--radius-full); padding:6px 16px;">
                    ♥ <?= $isFavorited ? e(__('remove_from_favorites', 'Favorited')) : e(__('add_to_favorites', 'Save to Favorites')); ?>
                </button>
            </div>
        </div>

        <!-- Right: Information & Customization Form -->
        <div class="product-info-panel">
            <div class="product-category-tag"><?= e(getLocalized($product, 'category_name')); ?></div>
            <h1><?= e(getLocalized($product, 'name')); ?></h1>

            <div class="price-stock-row">
                <div class="detail-price" id="dynamic-total-price"><?= formatPrice($product['price']); ?></div>
                <div style="font-size:0.9rem; color:var(--color-secondary-text);">
                    <?php if ($isSoldOut): ?>
                        <span style="color:var(--color-danger); font-weight:700;"><?= e(__('out_of_stock', 'Sold Out')); ?></span>
                    <?php else: ?>
                        <?= $product['stock']; ?> <?= e(__('stock_remaining', 'units left in stock')); ?>
                    <?php endif; ?>
                </div>
            </div>

            <p style="font-size:1.05rem; line-height:1.7; color:var(--color-secondary-text); margin-bottom:24px;">
                <?= e(getLocalized($product, 'description')); ?>
            </p>

            <!-- Ingredients -->
            <?php if (!empty($ingredients)): ?>
                <div class="ingredients-box">
                    <div class="box-title">🌿 <?= e(__('ingredients_heading', 'Artisanal Ingredients')); ?></div>
                    <div class="ingredient-tags">
                        <?php foreach ($ingredients as $ing): ?>
                            <span class="ingredient-tag"><?= e(isJapanese() ? $ing['ingredient_ja'] : $ing['ingredient_en']); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Allergy Information -->
            <div class="allergy-box">
                <div class="box-title">⚠️ <?= e(__('allergy_heading', 'Allergy Information')); ?></div>
                <?php if (!empty($allergies)): ?>
                    <div class="allergy-alert">
                        <strong><?= e(__('allergy_warning', 'Contains allergens: ')); ?></strong>
                        <?php 
                        $alList = array_map(function($a) {
                            return isJapanese() ? $a['allergy_ja'] : $a['allergy_en'];
                        }, $allergies);
                        echo e(implode(', ', $alList));
                        ?>
                    </div>
                <?php else: ?>
                    <p style="font-size:0.88rem; color:var(--color-secondary-text); margin:0;">
                        <?= e(__('allergy_none', 'No major common allergens reported.')); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Order & Customization Form -->
            <form action="cart.php" method="POST" id="product-order-form" data-base-price="<?= $product['price']; ?>">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= $productId; ?>">

                <?php if (!empty($customGroups)): ?>
                    <div class="customization-section">
                        <h3 style="font-size:1.1rem; margin-bottom:16px;">✨ <?= e(__('customization_heading', 'Customize Your Treat')); ?></h3>
                        <?php foreach ($customGroups as $groupName => $options): ?>
                            <div class="custom-group">
                                <div class="custom-group-title"><?= e($groupName); ?>:</div>
                                <div class="custom-options-row">
                                    <?php foreach ($options as $optIdx => $opt): 
                                        $optName = isJapanese() ? $opt['option_name_ja'] : $opt['option_name_en'];
                                        $extra = (int)$opt['price_extra'];
                                        $isChecked = ($opt['is_default'] || $optIdx === 0);
                                        $groupSlug = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($opt['group_name_en']));
                                    ?>
                                        <label class="custom-option-label">
                                            <input type="radio" 
                                                   name="custom[<?= e($opt['group_name_en']); ?>]" 
                                                   value="<?= e($opt['id']); ?>" 
                                                   class="custom-radio" 
                                                   data-extra-price="<?= $extra; ?>"
                                                   data-option-name="<?= e($optName); ?>"
                                                   <?= $isChecked ? 'checked' : ''; ?>>
                                            <span><?= e($optName); ?></span>
                                            <?php if ($extra > 0): ?>
                                                <span class="extra-price-tag">+<?= formatPrice($extra); ?></span>
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Quantity and Add to Cart Button -->
                <div style="display:flex; gap:16px; align-items:center; margin-top:24px;">
                    <?php if (!$isSoldOut): ?>
                        <div class="qty-control">
                            <button type="button" class="qty-btn qty-minus" aria-label="Decrease">&minus;</button>
                            <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock']; ?>" class="qty-input" readonly>
                            <button type="button" class="qty-btn qty-plus" aria-label="Increase">+</button>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg" style="flex:1;">
                            🛒 <?= e(__('add_to_cart', 'Add to Cart')); ?>
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary btn-lg btn-block" disabled style="opacity:0.6; cursor:not-allowed;">
                            <?= e(__('out_of_stock', 'Currently Sold Out')); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Reviews Section -->
    <div style="margin-top:60px; padding-top:40px; border-top:1px solid var(--color-border);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
            <h2 style="font-size:1.8rem;"><?= e(__('customer_reviews', 'Customer Reviews')); ?> (<?= count($reviews); ?>)</h2>
            <?php if ($canReview): ?>
                <a href="#review-form" class="btn btn-secondary btn-sm">✍️ <?= e(__('write_review', 'Write a Review')); ?></a>
            <?php endif; ?>
        </div>

        <?php if (empty($reviews)): ?>
            <p style="color:var(--color-secondary-text); font-style:italic;">
                <?= e(__('no_reviews_yet', 'No reviews yet for this dessert. Be the first to share your thoughts!')); ?>
            </p>
        <?php else: ?>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:20px;">
                <?php foreach ($reviews as $rev): ?>
                    <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:20px; box-shadow:var(--shadow-sm);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <div>
                                <strong><?= e($rev['user_name']); ?></strong>
                                <span class="badge badge-available" style="font-size:0.7rem; margin-left:6px;"><?= e(__('review_verified', 'Verified Buyer')); ?></span>
                            </div>
                            <div style="font-size:0.8rem; color:var(--color-secondary-text);">
                                <?= date('Y/m/d', strtotime($rev['created_at'])); ?>
                            </div>
                        </div>
                        <div style="margin-bottom:8px;">
                            <?= renderStars((float)$rev['rating']); ?>
                        </div>
                        <p style="font-size:0.92rem; color:var(--color-deep-text); line-height:1.6;">
                            <?= nl2br(e($rev['comment'])); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Eligible User Review Submission Form -->
        <?php if ($canReview): ?>
            <div id="review-form" style="margin-top:40px; background:#FAF6F7; border:1px solid var(--color-dusty-rose); border-radius:var(--radius-md); padding:26px;">
                <h3 style="margin-bottom:16px;">✍️ <?= e(__('write_review', 'Write a Review')); ?></h3>
                <form action="api/review.php" method="POST">
                    <input type="hidden" name="product_id" value="<?= $productId; ?>">
                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">

                    <div class="form-group">
                        <label class="form-label"><?= e(__('rating', 'Rating')); ?></label>
                        <select name="rating" class="form-control" style="width:200px;" required>
                            <option value="5">★★★★★ (5 - Excellent)</option>
                            <option value="4">★★★★☆ (4 - Very Good)</option>
                            <option value="3">★★★☆☆ (3 - Good)</option>
                            <option value="2">★★☆☆☆ (2 - Fair)</option>
                            <option value="1">★☆☆☆☆ (1 - Poor)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?= e(__('your_review', 'Your Review')); ?></label>
                        <textarea name="comment" rows="4" class="form-control" required placeholder="Share details of your experience with this confection..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary"><?= e(__('submit_review', 'Submit Review')); ?></button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
