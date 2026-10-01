<?php
/**
 * Sweet Choice - Customer Favorites Page
 * View saved favorites, remove items, or quickly add them directly to the shopping cart.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getDB();
$userId = $_SESSION['user_id'] ?? null;

// Handle manual query parameter toggle fallback
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $toggleId = (int)$_GET['toggle'];
    if ($userId) {
        $chk = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND product_id = ?");
        $chk->execute([$userId, $toggleId]);
        if ($f = $chk->fetch()) {
            $del = $pdo->prepare("DELETE FROM favorites WHERE id = ?");
            $del->execute([$f['id']]);
        } else {
            $ins = $pdo->prepare("INSERT INTO favorites (user_id, product_id, created_at) VALUES (?, ?, NOW())");
            $ins->execute([$userId, $toggleId]);
        }
    } else {
        if (!isset($_SESSION['guest_favorites'])) $_SESSION['guest_favorites'] = [];
        $idx = array_search($toggleId, $_SESSION['guest_favorites']);
        if ($idx !== false) {
            unset($_SESSION['guest_favorites'][$idx]);
            $_SESSION['guest_favorites'] = array_values($_SESSION['guest_favorites']);
        } else {
            $_SESSION['guest_favorites'][] = $toggleId;
        }
    }
    header("Location: favorites.php");
    exit;
}

// Fetch favorite products
$favoriteProducts = [];

if ($userId) {
    $stmt = $pdo->prepare("SELECT p.*, c.name_en AS category_name_en, c.name_ja AS category_name_ja 
                           FROM favorites f 
                           JOIN products p ON f.product_id = p.id 
                           JOIN categories c ON p.category_id = c.id 
                           WHERE f.user_id = ? 
                           ORDER BY f.created_at DESC");
    $stmt->execute([$userId]);
    $favoriteProducts = $stmt->fetchAll();
} else {
    $favIds = $_SESSION['guest_favorites'] ?? [];
    if (!empty($favIds)) {
        $placeholders = implode(',', array_fill(0, count($favIds), '?'));
        $stmt = $pdo->prepare("SELECT p.*, c.name_en AS category_name_en, c.name_ja AS category_name_ja 
                               FROM products p 
                               JOIN categories c ON p.category_id = c.id 
                               WHERE p.id IN ($placeholders)");
        $stmt->execute($favIds);
        $favoriteProducts = $stmt->fetchAll();
    }
}

$pageTitle = __('favorites_title', 'My Favorites');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 40px 20px;">
    <div class="section-header" style="text-align:left; margin-bottom:24px;">
        <span class="section-tag"><?= e(__('nav_favorites', 'Favorites')); ?></span>
        <h1 class="section-title"><?= e(__('favorites_title', 'My Favorite Desserts')); ?></h1>
        <p class="section-subtitle" style="margin:0;">
            <?= isJapanese() ? 'お気に入りに保存したスイーツをいつでも簡単にご注文いただけます。' : 'Your curated collection of dream desserts, ready for easy reordering.'; ?>
        </p>
    </div>

    <?php if (empty($favoriteProducts)): ?>
        <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:50px 20px; text-align:center; box-shadow:var(--shadow-sm);">
            <div style="font-size:3rem; margin-bottom:12px;">🤍</div>
            <h3><?= e(__('no_favorites', 'You have no saved favorites yet.')); ?></h3>
            <p style="color:var(--color-secondary-text); margin:8px 0 20px 0;">Browse our artisanal cakes, purin puddings, and treats and tap the heart icon!</p>
            <a href="category.php" class="btn btn-primary"><?= e(__('cart_empty_cta', 'Explore Desserts')); ?></a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($favoriteProducts as $prod): 
                $isSoldOut = ($prod['availability'] === 'sold_out');
            ?>
                <div class="product-card">
                    <div class="product-img-wrapper">
                        <div class="product-badges">
                            <?= renderAvailabilityBadge($prod['availability']); ?>
                        </div>
                        <a href="favorites.php?toggle=<?= $prod['id']; ?>" class="btn-favorite active" title="Remove from favorites">
                            ♥
                        </a>
                        <a href="product.php?id=<?= $prod['id']; ?>">
                            <img src="../uploads/products/<?= e($prod['image']); ?>" alt="<?= e(getLocalized($prod, 'name')); ?>" onerror="this.src='../assets/images/logo_banner.jpg'">
                        </a>
                    </div>

                    <div class="product-body">
                        <div>
                            <div class="product-category-tag"><?= e(getLocalized($prod, 'category_name')); ?></div>
                            <h3 class="product-title">
                                <a href="product.php?id=<?= $prod['id']; ?>"><?= e(getLocalized($prod, 'name')); ?></a>
                            </h3>
                            <p class="product-desc"><?= e(getLocalized($prod, 'description')); ?></p>
                        </div>

                        <div class="product-footer">
                            <div class="product-price"><?= formatPrice($prod['price']); ?></div>
                            <div style="display:flex; gap:8px;">
                                <a href="product.php?id=<?= $prod['id']; ?>" class="btn btn-secondary btn-sm"><?= e(__('view_details', 'Details')); ?></a>
                                <?php if (!$isSoldOut): ?>
                                    <form action="cart.php" method="POST" style="margin:0;">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="product_id" value="<?= $prod['id']; ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" class="btn btn-primary btn-sm"><?= e(__('add_to_cart', '+ Cart')); ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
