<?php
/**
 * Sweet Choice - Category & Product Catalog Page
 * Filter by category, keyword search, price/name sorting, favorites, and quick add-to-cart.
 */

$pageTitle = 'Dessert Menu';
require_once __DIR__ . '/../includes/header.php';

// Retrieve filter query parameters safely
$categoryId = isset($_GET['category']) && is_numeric($_GET['category']) ? (int)$_GET['category'] : 0;
$search = trim($_GET['search'] ?? '');
$sort = trim($_GET['sort'] ?? 'featured');

// Fetch all active categories for navigation tabs
$categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();

// Build SQL query with prepared parameters
$sql = "SELECT p.*, c.name_en AS category_name_en, c.name_ja AS category_name_ja 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE 1=1";
$params = [];

if ($categoryId > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $categoryId;
}

if (!empty($search)) {
    $sql .= " AND (p.name_en LIKE ? OR p.name_ja LIKE ? OR p.description_en LIKE ? OR p.description_ja LIKE ? OR p.tags LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

// Sorting logic
switch ($sort) {
    case 'price_low':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'name':
        $sql .= isJapanese() ? " ORDER BY p.name_ja ASC" : " ORDER BY p.name_en ASC";
        break;
    case 'featured':
    default:
        $sql .= " ORDER BY p.is_popular DESC, p.id ASC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<div class="container" style="padding: 40px 20px;">
    <!-- Page Header -->
    <div class="section-header" style="text-align:left; margin-bottom:30px;">
        <span class="section-tag"><?= e(__('nav_category', 'Category')); ?></span>
        <h1 class="section-title"><?= isJapanese() ? '手作りスイーツ メニュー一覧' : 'Handcrafted Dessert Catalog'; ?></h1>
        <p class="section-subtitle" style="margin:0;">
            <?= isJapanese() ? 'すべてのスイーツはご注文後にパティシエが最高の状態でお届け・お渡しいたします。' : 'All creations are baked fresh daily using pure ingredients and authentic Japanese techniques.'; ?>
        </p>
    </div>

    <!-- Filter & Search Toolbar -->
    <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:20px; margin-bottom:36px; box-shadow:var(--shadow-sm);">
        <form method="GET" action="category.php" style="display:flex; flex-wrap:wrap; gap:16px; align-items:center; justify-content:space-between;">
            <!-- Category Tabs / Buttons -->
            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                <a href="category.php?<?= http_build_query(array_merge($_GET, ['category' => 0])); ?>" 
                   class="btn btn-sm <?= $categoryId === 0 ? 'btn-primary' : 'btn-cream'; ?>">
                   <?= e(__('all_categories', 'All Categories')); ?>
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="category.php?<?= http_build_query(array_merge($_GET, ['category' => $cat['id']])); ?>" 
                       class="btn btn-sm <?= $categoryId === (int)$cat['id'] ? 'btn-primary' : 'btn-cream'; ?>">
                       <?= e(getLocalized($cat, 'name')); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Search & Sort Row -->
            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <!-- Search input -->
                <div style="position:relative; min-width:240px;">
                    <input type="text" name="search" value="<?= e($search); ?>" 
                           placeholder="<?= e(__('search_placeholder', 'Search desserts...')); ?>" 
                           class="form-control" style="padding-right:32px;">
                    <?php if ($categoryId > 0): ?>
                        <input type="hidden" name="category" value="<?= $categoryId; ?>">
                    <?php endif; ?>
                </div>

                <!-- Sort select -->
                <select name="sort" class="form-control" style="width:auto; cursor:pointer;" onchange="this.form.submit();">
                    <option value="featured" <?= $sort === 'featured' ? 'selected' : ''; ?>><?= e(__('sort_featured', 'Featured')); ?></option>
                    <option value="price_low" <?= $sort === 'price_low' ? 'selected' : ''; ?>><?= e(__('sort_price_low', 'Price: Low to High')); ?></option>
                    <option value="price_high" <?= $sort === 'price_high' ? 'selected' : ''; ?>><?= e(__('sort_price_high', 'Price: High to Low')); ?></option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : ''; ?>><?= e(__('sort_name', 'Name (A to Z)')); ?></option>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <?php if (!empty($search) || $categoryId > 0 || $sort !== 'featured'): ?>
                    <a href="category.php" class="btn btn-secondary btn-sm">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Product Grid -->
    <?php if (empty($products)): ?>
        <div style="text-align:center; padding:60px 20px; background:#FAF6F6; border-radius:var(--radius-md);">
            <div style="font-size:3rem; margin-bottom:12px;">🍰</div>
            <h3><?= e(__('no_products_found', 'No desserts found matching your criteria.')); ?></h3>
            <p style="color:var(--color-secondary-text); margin-top:8px;">Try clearing your search terms or picking another category.</p>
            <a href="category.php" class="btn btn-primary" style="margin-top:16px;">View All Desserts</a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $prod): 
                $isFav = isProductFavorited($pdo, (int)$prod['id']);
                $isSoldOut = ($prod['availability'] === 'sold_out');
            ?>
                <div class="product-card">
                    <div class="product-img-wrapper">
                        <div class="product-badges">
                            <?= renderAvailabilityBadge($prod['availability']); ?>
                            <?php if ($prod['is_popular']): ?>
                                <span class="badge" style="background:#FFF0F2; color:var(--color-deep-plum);">POPULAR</span>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn-favorite <?= $isFav ? 'active' : ''; ?>" data-product-id="<?= $prod['id']; ?>" title="Favorite">
                            ♥
                        </button>
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
                                <?php if ($isSoldOut): ?>
                                    <span class="badge badge-sold-out" style="align-self:center;"><?= e(__('out_of_stock', 'Sold Out')); ?></span>
                                <?php else: ?>
                                    <form action="cart.php" method="POST" style="margin:0;">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="product_id" value="<?= $prod['id']; ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" class="btn btn-primary btn-sm"><?= e(__('quick_add', '+ Cart')); ?></button>
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
