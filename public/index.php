<?php
/**
 * Sweet Choice - Home Page
 * Displays Hero Slider, AI Dessert Recommendation, Categories, Popular Items, Seasonal Feature, and Footer.
 */

$pageTitle = 'Home';
require_once __DIR__ . '/../includes/header.php';

// 1. Fetch active banners from MySQL
$bannerStmt = $pdo->query("SELECT * FROM banners WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
$banners = $bannerStmt->fetchAll();

// 2. Fetch categories from MySQL
$catStmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 5");
$categories = $catStmt->fetchAll();

// 3. Fetch popular products from MySQL
$popStmt = $pdo->query("SELECT p.*, c.name_en AS category_name_en, c.name_ja AS category_name_ja 
                        FROM products p 
                        JOIN categories c ON p.category_id = c.id 
                        WHERE p.is_popular = 1 AND p.availability != 'sold_out' 
                        ORDER BY p.id ASC LIMIT 4");
$popularProducts = $popStmt->fetchAll();

// 4. Fetch limited/seasonal products
$seasonStmt = $pdo->query("SELECT p.*, c.name_en AS category_name_en, c.name_ja AS category_name_ja 
                           FROM products p 
                           JOIN categories c ON p.category_id = c.id 
                           WHERE p.availability = 'limited' 
                           ORDER BY p.id ASC LIMIT 3");
$seasonalProducts = $seasonStmt->fetchAll();
?>

<!-- 1. Hero Banner Slider -->
<section class="hero-slider-section">
    <div class="slider-container">
        <div class="slides-wrapper">
            <?php foreach ($banners as $index => $banner): ?>
                <div class="hero-slide">
                    <img src="../uploads/banners/<?= e($banner['image']); ?>" alt="<?= e(getLocalized($banner, 'title')); ?>" class="slide-bg-img" onerror="this.src='../assets/images/logo_banner.jpg'">
                    <div class="slide-overlay"></div>
                    <div class="slide-content">
                        <span class="slide-badge">SWEET CHOICE GINZA</span>
                        <h1 class="slide-title"><?= e(getLocalized($banner, 'title')); ?></h1>
                        <p class="slide-subtitle"><?= e(getLocalized($banner, 'subtitle')); ?></p>
                        <a href="<?= e($banner['link']); ?>" class="btn btn-primary btn-lg">
                            <?= e(getLocalized($banner, 'button_text')); ?> &rarr;
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (count($banners) > 1): ?>
            <div class="slider-controls">
                <button type="button" class="slider-arrow slider-prev" aria-label="Previous Slide">&larr;</button>
                <div class="slider-dots"></div>
                <button type="button" class="slider-arrow slider-next" aria-label="Next Slide">&rarr;</button>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- 2. AI Dessert Recommendation Section (HIGH PRIORITY) -->
<section class="ai-recommendation-section" id="ai-recommendation-section"
    data-lang-view-details="<?= e(__('view_details', 'View Details')); ?>"
    data-lang-add-to-cart="<?= e(__('add_to_cart', 'Add to Cart')); ?>"
    data-lang-sold-out="<?= e(__('out_of_stock', 'Sold Out')); ?>"
    data-lang-ai-sommelier="AI Sommelier"
    data-lang-loading="<?= e(__('ai_loading', 'Analyzing flavor profiles & pairing desserts...')); ?>"
    data-lang-choose-again="<?= isJapanese() ? 'もう一度選ぶ' : 'Choose Again'; ?>"
    data-lang-no-match="<?= isJapanese() ? '条件に一致するデザートが見つかりませんでした。別の気分や味を選んでみてください！' : 'No desserts found matching this pairing. Try another mood or flavor!'; ?>">
    <div class="container ai-section-container">
        <div class="section-header">
            <span class="section-tag">✨ <?= e(__('ai_section_badge', 'Interactive AI Sommelier')); ?></span>
            <h2 class="section-title"><?= e(__('ai_title', 'What are you craving today?')); ?></h2>
            <p class="section-subtitle"><?= e(__('ai_subtitle', 'Select your current mood and favorite flavor note to get an instant tailored recommendation.')); ?></p>
        </div>

        <!-- Step 1: Selection Form Card (Single-column, full-width within container) -->
        <div class="ai-recommendation-card ai-selection-card" id="ai-selection-card">
            <!-- Mood Picker -->
            <div class="ai-filter-group">
                <label class="ai-label">
                    <span>💭</span> <?= e(__('ai_mood_label', 'How are you feeling?')); ?>
                </label>
                <div class="ai-pill-grid">
                    <?php
                    $moods = [
                        'happy' => __('mood_happy', 'Happy'),
                        'sad' => __('mood_sad', 'Sad'),
                        'stressed' => __('mood_stressed', 'Stressed'),
                        'tired' => __('mood_tired', 'Tired'),
                        'romantic' => __('mood_romantic', 'Romantic'),
                        'celebrating' => __('mood_celebrating', 'Celebrating'),
                        'relaxed' => __('mood_relaxed', 'Relaxed'),
                        'energetic' => __('mood_energetic', 'Energetic')
                    ];
                    $firstMood = true;
                    foreach ($moods as $moodKey => $moodLabel): ?>
                        <button type="button" class="ai-pill ai-mood-pill <?= $firstMood ? 'active' : ''; ?>" data-mood="<?= $moodKey; ?>">
                            <?= e($moodLabel); ?>
                        </button>
                    <?php $firstMood = false; endforeach; ?>
                </div>
            </div>

            <!-- Taste Picker -->
            <div class="ai-filter-group">
                <label class="ai-label">
                    <span>🍓</span> <?= e(__('ai_taste_label', 'What flavor profile are you craving?')); ?>
                </label>
                <div class="ai-pill-grid">
                    <?php
                    $tastes = [
                        'cake' => __('taste_cake', 'Cake'),
                        'chocolate' => __('taste_chocolate', 'Chocolate'),
                        'fruit' => __('taste_fruit', 'Fruit'),
                        'creamy' => __('taste_creamy', 'Creamy'),
                        'cookie' => __('taste_cookie', 'Cookie'),
                        'pudding' => __('taste_pudding', 'Pudding'),
                        'very_sweet' => __('taste_very_sweet', 'Very Sweet'),
                        'light_sweetness' => __('taste_light_sweetness', 'Light Sweetness')
                    ];
                    $firstTaste = true;
                    foreach ($tastes as $tasteKey => $tasteLabel): ?>
                        <button type="button" class="ai-pill ai-taste-pill <?= $firstTaste ? 'active' : ''; ?>" data-taste="<?= $tasteKey; ?>">
                            <?= e($tasteLabel); ?>
                        </button>
                    <?php $firstTaste = false; endforeach; ?>
                </div>
            </div>

            <div class="ai-form-actions">
                <button type="button" id="ai-find-btn" class="btn btn-primary btn-block ai-submit-btn">
                    ✨ <?= e(__('ai_button_find', 'Find My Perfect Dessert')); ?>
                </button>
            </div>
        </div>

        <!-- Step 2: Recommendation Result Card (Initially Hidden Below the Form) -->
        <div id="ai-result-section" class="ai-recommendation-card ai-result-card-wrapper" style="display: none;">
            <div class="ai-result-header">
                <div>
                    <span class="ai-result-badge">AI Sommelier Pairing</span>
                    <h3 class="ai-result-heading"><?= e(__('ai_result_title', 'AI Recommended For You')); ?></h3>
                </div>
                <button type="button" id="ai-choose-again-btn" class="btn btn-secondary btn-sm ai-choose-again-btn">
                    <?= isJapanese() ? '↺ もう一度選ぶ' : '↺ Choose Again'; ?>
                </button>
            </div>

            <div id="ai-result-container" class="ai-result-area">
                <!-- Dynamically populated by recommendation.js -->
            </div>
        </div>
    </div>
</section>

<!-- 3. Product Categories Section -->
<section class="categories-section">
    <div class="container">
        <div class="section-header">
            <span class="section-tag"><?= e(__('categories_title', 'Dessert Categories')); ?></span>
            <h2 class="section-title"><?= isJapanese() ? 'パティシエ自慢のコレクション' : 'Explore Our 5 Artisan Collections'; ?></h2>
            <p class="section-subtitle"><?= e(__('categories_subtitle', 'Explore our handcrafted delicacies across 5 artisan collections')); ?></p>
        </div>

        <div class="category-grid">
            <?php foreach ($categories as $cat): ?>
                <a href="category.php?category=<?= $cat['id']; ?>" class="category-card">
                    <div class="category-img-wrap">
                        <img src="../uploads/products/<?= e($cat['image'] ?: 'cat_cakes.jpg'); ?>" alt="<?= e(getLocalized($cat, 'name')); ?>" onerror="this.src='../assets/images/logo_banner.jpg'">
                    </div>
                    <div class="category-info">
                        <h3 class="category-name"><?= e(getLocalized($cat, 'name')); ?></h3>
                        <p class="category-desc"><?= e(getLocalized($cat, 'description')); ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 4. Popular Items Section -->
<section class="popular-section">
    <div class="container">
        <div class="section-header">
            <span class="section-tag"><?= e(__('popular_title', 'Popular Items')); ?></span>
            <h2 class="section-title"><?= isJapanese() ? '当店自慢のベストセラー' : 'Customer Favorite Confections'; ?></h2>
            <p class="section-subtitle"><?= e(__('popular_subtitle', 'Customer favorites baked fresh with passion and precision')); ?></p>
        </div>

        <div class="product-grid">
            <?php foreach ($popularProducts as $prod): 
                $isFav = isProductFavorited($pdo, (int)$prod['id']);
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
                            <?php if ($prod['availability'] === 'sold_out'): ?>
                                <span class="badge badge-sold-out"><?= e(__('out_of_stock', 'Sold Out')); ?></span>
                            <?php else: ?>
                                <form action="cart.php" method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?= $prod['id']; ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="btn btn-secondary btn-sm"><?= e(__('add_to_cart', 'Add to Cart')); ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align:center; margin-top:36px;">
            <a href="category.php" class="btn btn-primary btn-lg"><?= e(__('view_all', 'View All')); ?> &rarr;</a>
        </div>
    </div>
</section>

<!-- 5. Promotional & Seasonal Section -->
<section style="padding:60px 0; background:var(--color-cream); border-top:1px solid var(--color-border); border-bottom:1px solid var(--color-border);">
    <div class="container">
        <div class="section-header">
            <span class="section-tag" style="background:#FFFFFF;"><?= e(__('seasonal_title', 'Seasonal Special Collection')); ?></span>
            <h2 class="section-title"><?= isJapanese() ? '日本の四季を閉じ込めた限定スイーツ' : 'Limited Spring Edition Confections'; ?></h2>
            <p class="section-subtitle"><?= e(__('seasonal_subtitle', 'Limited edition treats inspired by Japan’s four distinct seasons')); ?></p>
        </div>

        <div class="product-grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));">
            <?php foreach ($seasonalProducts as $seasonProd): ?>
                <div class="product-card" style="border:1.5px solid var(--color-dusty-rose);">
                    <div class="product-img-wrapper">
                        <div class="product-badges">
                            <span class="badge badge-limited"><?= e(__('limited_stock', 'Limited')); ?></span>
                        </div>
                        <a href="product.php?id=<?= $seasonProd['id']; ?>">
                            <img src="../uploads/products/<?= e($seasonProd['image']); ?>" alt="<?= e(getLocalized($seasonProd, 'name')); ?>" onerror="this.src='../assets/images/logo_banner.jpg'">
                        </a>
                    </div>
                    <div class="product-body">
                        <div>
                            <div class="product-category-tag"><?= e(getLocalized($seasonProd, 'category_name')); ?></div>
                            <h3 class="product-title">
                                <a href="product.php?id=<?= $seasonProd['id']; ?>"><?= e(getLocalized($seasonProd, 'name')); ?></a>
                            </h3>
                            <p class="product-desc"><?= e(getLocalized($seasonProd, 'description')); ?></p>
                        </div>
                        <div class="product-footer">
                            <div class="product-price"><?= formatPrice($seasonProd['price']); ?></div>
                            <a href="product.php?id=<?= $seasonProd['id']; ?>" class="btn btn-primary btn-sm"><?= e(__('view_details', 'View Details')); ?></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
