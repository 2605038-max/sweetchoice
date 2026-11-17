<?php
/**
 * Sweet Choice - Professional Japanese Artisan Patisserie Storefront
 * Handcrafted Japanese Desserts & Confections E-Commerce Platform
 */

$pageTitle = 'Home';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/pairings.php';

// 1. Fetch active hero banners from MySQL
$bannerStmt = $pdo->query("SELECT * FROM banners WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
$banners = $bannerStmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch categories from MySQL (All active categories)
$catStmt = $pdo->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.availability != 'sold_out') AS active_count
    FROM categories c 
    WHERE c.is_active = 1 
    ORDER BY c.sort_order ASC, c.id ASC
");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Smart Special Offers: Products with dynamic inventory-based discounts
$pairingSettings = getPairingSettings($pdo);
$threshold = $pairingSettings['threshold'];

$specialOffersStmt = $pdo->prepare("
    SELECT p.*, c.name_en AS category_name_en, c.name_ja AS category_name_ja, c.slug AS category_slug
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.stock > ? AND p.availability != 'sold_out'
    ORDER BY (p.stock - ?) DESC, p.is_popular DESC, p.id ASC
    LIMIT 4
");
$specialOffersStmt->execute([$threshold, $threshold]);
$specialOffers = $specialOffersStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($specialOffers as &$sp) {
    $sp['discount_info'] = calculateInventoryDiscount((int)$sp['price'], (int)$sp['stock'], $pairingSettings);
}
unset($sp);

// 4. Inventory-Based Product Pairings: Curated Signature Dessert + Drink Combinations
$showcasePairingSets = [];
$primaryDessertIds = [1, 2, 5]; // Strawberry Shortcake, Belgian Chocolate Cake, Premium Macaron
foreach ($primaryDessertIds as $dessertId) {
    $mainStmt = $pdo->prepare("
        SELECT p.*, c.name_en AS category_name_en, c.name_ja AS category_name_ja, c.slug AS category_slug
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.id = ? AND p.availability != 'sold_out'
    ");
    $mainStmt->execute([$dessertId]);
    $mainDessert = $mainStmt->fetch(PDO::FETCH_ASSOC);

    if ($mainDessert) {
        $pairedDrinks = getProductPairings($pdo, $dessertId, 1);
        if (!empty($pairedDrinks)) {
            $drink = $pairedDrinks[0];
            $regularCombined = (int)$mainDessert['price'] + (int)$drink['original_price'];
            $discountedCombined = (int)$mainDessert['price'] + (int)$drink['discounted_price'];
            $savings = max(0, $regularCombined - $discountedCombined);

            $showcasePairingSets[] = [
                'dessert' => $mainDessert,
                'drink' => $drink,
                'regular_total' => $regularCombined,
                'combo_total' => $discountedCombined,
                'savings' => $savings
            ];
        }
    }
}

// 5. Seasonal & Limited Edition Campaign (Items from category 6 or limited availability)
$seasonStmt = $pdo->query("
    SELECT p.*, c.name_en AS category_name_en, c.name_ja AS category_name_ja, c.slug AS category_slug
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE (c.slug = 'seasonal' OR p.availability = 'limited') AND p.availability != 'sold_out'
    ORDER BY p.is_popular DESC, p.id ASC 
    LIMIT 3
");
$seasonalProducts = $seasonStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- 1. Hero Section -->
<section class="hero-slider-section">
    <div class="slider-container">
        <div class="slides-wrapper">
            <?php foreach ($banners as $index => $banner): ?>
                <div class="hero-slide">
                    <img src="../uploads/banners/<?= e($banner['image']); ?>" alt="<?= e(getLocalized($banner, 'title')); ?>" class="slide-bg-img" onerror="this.src='../assets/images/logo_banner.jpg'">
                    <div class="slide-overlay"></div>
                    <div class="slide-content">
                        <span class="slide-atelier-badge">ATELIER GINZA TOKYO</span>
                        <h1 class="slide-title"><?= e(getLocalized($banner, 'title') ?: __('hero_title', 'Discover Your Perfect Sweet Moment.')); ?></h1>
                        <p class="slide-subtitle"><?= e(getLocalized($banner, 'subtitle') ?: __('hero_subtitle', 'Handcrafted desserts, thoughtfully selected to make every moment special.')); ?></p>
                        
                        <div class="hero-cta-group">
                            <a href="category.php" class="btn btn-primary btn-lg">
                                <?= e(__('hero_cta_explore', 'Explore Our Collection')); ?>
                            </a>
                            <a href="#ai-recommendation-section" class="btn btn-secondary btn-lg">
                                <?= e(__('hero_cta_sommelier', 'Find Your Perfect Dessert')); ?>
                            </a>
                        </div>
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

<!-- Brand Values Bar -->
<div class="brand-values-strip">
    <div class="container values-container">
        <div class="value-item">
            <span class="value-accent">01</span>
            <div>
                <strong><?= isJapanese() ? '北海道産 純生クリーム' : '100% Hokkaido Pure Dairy'; ?></strong>
                <p><?= isJapanese() ? '雑味のない繊細で軽やかな口どけ' : 'Ethereal, cloud-soft milk textures'; ?></p>
            </div>
        </div>
        <div class="value-divider"></div>
        <div class="value-item">
            <span class="value-accent">02</span>
            <div>
                <strong><?= isJapanese() ? '京都宇治 石臼挽き抹茶' : 'Kyoto Ceremonial Matcha'; ?></strong>
                <p><?= isJapanese() ? '伝統の石臼で丹念に挽かれた芳醇な香り' : 'Single-origin traditional stone ground'; ?></p>
            </div>
        </div>
        <div class="value-divider"></div>
        <div class="value-item">
            <span class="value-accent">03</span>
            <div>
                <strong><?= isJapanese() ? '毎朝アトリエで手作り' : 'Daily Fresh Boutique Baking'; ?></strong>
                <p><?= isJapanese() ? '注文に合わせて最高の状態でお届け' : 'Small artisanal batches daily'; ?></p>
            </div>
        </div>
        <div class="value-divider"></div>
        <div class="value-item">
            <span class="value-accent">04</span>
            <div>
                <strong><?= isJapanese() ? '銀座本店受取＆指定配達' : 'Ginza Pickup & Local Delivery'; ?></strong>
                <p><?= isJapanese() ? '時間帯指定・丁寧な温度管理配送' : 'Precise temperature-controlled service'; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- 2. Smart Special Offers (High Priority Business Concept) -->
<?php if (!empty($specialOffers)): ?>
<section class="smart-offers-section">
    <div class="container">
        <div class="section-header">
            <span class="section-tag section-tag-natural"><?= e(__('special_offers_tag', 'Smart Special Offers')); ?></span>
            <h2 class="section-title"><?= e(__('special_offers_title', 'Freshly Baked with Dynamic Savings')); ?></h2>
            <p class="section-subtitle"><?= e(__('special_offers_subtitle', 'Enjoy special inventory-based savings on our handcrafted favorites while daily allocations last.')); ?></p>
        </div>

        <div class="product-grid">
            <?php foreach ($specialOffers as $spItem): 
                $disc = $spItem['discount_info'];
                $isFav = isProductFavorited($pdo, (int)$spItem['id']);
            ?>
                <div class="product-card smart-offer-card">
                    <div class="product-img-wrapper">
                        <div class="product-badges">
                            <?php if ($disc['is_eligible']): ?>
                                <span class="badge badge-discount">-<?= $disc['discount_percent']; ?>% OFF</span>
                                <span class="badge badge-promo"><?= e(__('special_offers_badge', 'Smart Offer')); ?></span>
                            <?php else: ?>
                                <?= renderAvailabilityBadge($spItem['availability']); ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn-favorite <?= $isFav ? 'active' : ''; ?>" data-product-id="<?= $spItem['id']; ?>" title="<?= e(__('add_to_favorites', 'Favorite')); ?>">
                            ♥
                        </button>
                        <a href="product.php?id=<?= $spItem['id']; ?>" class="product-img-link">
                            <img src="../uploads/products/<?= e($spItem['image']); ?>" alt="<?= e(getLocalized($spItem, 'name')); ?>" onerror="this.src='../assets/images/logo_banner.jpg'">
                        </a>
                    </div>

                    <div class="product-body">
                        <div>
                            <div class="product-category-tag"><?= e(getLocalized($spItem, 'category_name')); ?></div>
                            <h3 class="product-title">
                                <a href="product.php?id=<?= $spItem['id']; ?>"><?= e(getLocalized($spItem, 'name')); ?></a>
                            </h3>
                            <p class="product-desc"><?= e(getLocalized($spItem, 'description')); ?></p>
                        </div>

                        <div class="product-stock-indicator">
                            <span class="stock-dot"></span>
                            <span class="stock-text"><?= (int)$spItem['stock']; ?> <?= e(__('units_available', 'units in stock')); ?></span>
                        </div>

                        <div class="product-footer">
                            <div class="price-container">
                                <?php if ($disc['is_eligible']): ?>
                                    <span class="price-original"><?= formatPrice($disc['original_price']); ?></span>
                                    <span class="product-price price-discounted"><?= formatPrice($disc['final_price']); ?></span>
                                <?php else: ?>
                                    <span class="product-price"><?= formatPrice($spItem['price']); ?></span>
                                <?php endif; ?>
                            </div>

                            <form action="cart.php" method="POST" class="add-to-cart-form">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?= $spItem['id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <?= e(__('add_to_cart', 'Add to Cart')); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- 3. Explore Our Collections -->
<section class="collections-section">
    <div class="container">
        <div class="section-header">
            <span class="section-tag"><?= e(__('collections_tag', 'Curated Collections')); ?></span>
            <h2 class="section-title"><?= e(__('collections_title', 'Explore Our Collections')); ?></h2>
            <p class="section-subtitle"><?= e(__('collections_subtitle', 'From airy sponge cakes to delicate Parisian macarons, each piece is handcrafted daily.')); ?></p>
        </div>

        <div class="collections-grid">
            <?php foreach ($categories as $cat): 
                $fallbackImg = 'cat_' . ($cat['slug'] ?? 'cakes') . '.jpg';
                $catImg = !empty($cat['image']) ? $cat['image'] : $fallbackImg;
            ?>
                <a href="category.php?category=<?= $cat['id']; ?>" class="collection-card">
                    <div class="collection-img-wrap">
                        <img src="../uploads/products/<?= e($catImg); ?>" alt="<?= e(getLocalized($cat, 'name')); ?>" onerror="this.src='../assets/images/logo_banner.jpg'">
                        <div class="collection-overlay"></div>
                    </div>
                    <div class="collection-info">
                        <span class="collection-count"><?= (int)($cat['active_count'] ?? 0); ?> <?= isJapanese() ? '品目' : 'Selections'; ?></span>
                        <h3 class="collection-name"><?= e(getLocalized($cat, 'name')); ?></h3>
                        <p class="collection-desc"><?= e(getLocalized($cat, 'description')); ?></p>
                        <span class="collection-link-text"><?= isJapanese() ? 'メニューを見る &rarr;' : 'Explore Collection &rarr;'; ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 4. Smart Dessert Recommendations (AI Sommelier) -->
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
            <span class="section-tag section-tag-natural"><?= e(__('ai_section_badge', 'Interactive AI Sommelier')); ?></span>
            <h2 class="section-title"><?= e(__('ai_title', 'What are you craving today?')); ?></h2>
            <p class="section-subtitle"><?= e(__('ai_subtitle', 'Select your current mood and favorite flavor note to get an instant tailored recommendation.')); ?></p>
        </div>

        <!-- Step 1: Selection Form Card -->
        <div class="ai-recommendation-card ai-selection-card" id="ai-selection-card">
            <!-- Mood Picker -->
            <div class="ai-filter-group">
                <label class="ai-label">
                    <span class="label-badge">Step 1</span>
                    <?= e(__('ai_mood_label', 'How are you feeling?')); ?>
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
                    <span class="label-badge">Step 2</span>
                    <?= e(__('ai_taste_label', 'What flavor profile are you craving?')); ?>
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
                <button type="button" id="ai-find-btn" class="btn btn-primary ai-submit-btn">
                    <?= e(__('ai_button_find', 'Find My Perfect Dessert')); ?>
                </button>
            </div>
        </div>

        <!-- Step 2: Recommendation Result Card (Initially Hidden Below the Form) -->
        <div id="ai-result-section" class="ai-recommendation-card ai-result-card-wrapper" style="display: none;">
            <div class="ai-result-header">
                <div>
                    <span class="ai-result-badge"><?= isJapanese() ? 'シェフの厳選ペアリング' : 'Chef Curated Pairing'; ?></span>
                    <h3 class="ai-result-heading"><?= e(__('ai_result_title', 'Your Dessert Matches')); ?></h3>
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

<!-- 5. Inventory-Based Product Pairing Section -->
<?php if (!empty($showcasePairingSets)): ?>
<section class="pairings-showcase-section">
    <div class="container">
        <div class="section-header">
            <span class="section-tag section-tag-natural"><?= e(__('pairings_tag', "Chef's Harmony")); ?></span>
            <h2 class="section-title"><?= e(__('pairings_title', 'Complementary Pairings')); ?></h2>
            <p class="section-subtitle"><?= e(__('pairings_subtitle', 'Elevate your tasting experience with curated combinations of desserts and beverages, complete with bundle savings.')); ?></p>
        </div>

        <div class="pairings-grid">
            <?php foreach ($showcasePairingSets as $set): 
                $d = $set['dessert'];
                $b = $set['drink'];
            ?>
                <div class="pairing-combo-card">
                    <div class="pairing-header">
                        <span class="pairing-badge"><?= e(__('pairings_combo_label', 'Pairing Set')); ?></span>
                        <?php if ($set['savings'] > 0): ?>
                            <span class="pairing-savings-badge"><?= sprintf(e(__('pairings_save_badge', 'Save %s')), formatPrice($set['savings'])); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="pairing-items-row">
                        <!-- Dessert -->
                        <div class="pairing-single-item">
                            <div class="pairing-item-thumb">
                                <img src="../uploads/products/<?= e($d['image']); ?>" alt="<?= e(getLocalized($d, 'name')); ?>" onerror="this.src='../assets/images/logo_banner.jpg'">
                            </div>
                            <div class="pairing-item-meta">
                                <span class="pairing-item-role"><?= isJapanese() ? 'デザート' : 'Dessert'; ?></span>
                                <strong class="pairing-item-name"><a href="product.php?id=<?= $d['id']; ?>"><?= e(getLocalized($d, 'name')); ?></a></strong>
                                <span class="pairing-item-price"><?= formatPrice($d['price']); ?></span>
                            </div>
                        </div>

                        <!-- Plus Sign -->
                        <div class="pairing-plus-sign">+</div>

                        <!-- Beverage -->
                        <div class="pairing-single-item">
                            <div class="pairing-item-thumb">
                                <img src="../uploads/products/<?= e($b['image']); ?>" alt="<?= e(getLocalized($b, 'name')); ?>" onerror="this.src='../assets/images/logo_banner.jpg'">
                            </div>
                            <div class="pairing-item-meta">
                                <span class="pairing-item-role"><?= isJapanese() ? 'ドリンク' : 'Beverage'; ?></span>
                                <strong class="pairing-item-name"><a href="product.php?id=<?= $b['id']; ?>"><?= e(getLocalized($b, 'name')); ?></a></strong>
                                <div>
                                    <?php if ($b['is_eligible_discount']): ?>
                                        <span class="price-original" style="font-size:0.8rem;"><?= formatPrice($b['original_price']); ?></span>
                                        <span class="pairing-item-price" style="color:var(--color-btn-primary);"><?= formatPrice($b['discounted_price']); ?></span>
                                    <?php else: ?>
                                        <span class="pairing-item-price"><?= formatPrice($b['price']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing & Quick Action -->
                    <div class="pairing-footer">
                        <div class="pairing-total-block">
                            <span class="pairing-total-label"><?= e(__('pairings_combo_price', 'Bundle Price')); ?></span>
                            <div class="pairing-total-price">
                                <?php if ($set['savings'] > 0): ?>
                                    <span class="price-original" style="font-size:0.9rem;"><?= formatPrice($set['regular_total']); ?></span>
                                <?php endif; ?>
                                <strong><?= formatPrice($set['combo_total']); ?></strong>
                            </div>
                        </div>

                        <div class="pairing-actions">
                            <a href="product.php?id=<?= $d['id']; ?>" class="btn btn-secondary btn-sm">
                                <?= e(__('pairings_view_pair', 'Explore Pairing')); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- 6. Seasonal & Limited Edition Campaign -->
<?php if (!empty($seasonalProducts)): ?>
<section class="seasonal-campaign-section">
    <div class="container">
        <div class="section-header">
            <span class="section-tag"><?= e(__('seasonal_campaign_tag', 'Seasonal Edition')); ?></span>
            <h2 class="section-title"><?= e(__('seasonal_campaign_title', 'Artisanal Seasonal Campaign')); ?></h2>
            <p class="section-subtitle"><?= e(__('seasonal_campaign_subtitle', 'Limited-edition confections celebrating the fleeting beauty of seasonal ingredients.')); ?></p>
        </div>

        <div class="product-grid seasonal-grid">
            <?php foreach ($seasonalProducts as $seasonProd): 
                $isFav = isProductFavorited($pdo, (int)$seasonProd['id']);
            ?>
                <div class="product-card seasonal-card">
                    <div class="product-img-wrapper">
                        <div class="product-badges">
                            <span class="badge badge-limited"><?= e(__('limited_stock', 'Limited Edition')); ?></span>
                        </div>
                        <button type="button" class="btn-favorite <?= $isFav ? 'active' : ''; ?>" data-product-id="<?= $seasonProd['id']; ?>" title="<?= e(__('add_to_favorites', 'Favorite')); ?>">
                            ♥
                        </button>
                        <a href="product.php?id=<?= $seasonProd['id']; ?>" class="product-img-link">
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
                            <a href="product.php?id=<?= $seasonProd['id']; ?>" class="btn btn-primary btn-sm">
                                <?= e(__('view_details', 'View Details')); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- 7. Brand Story / About Us Section -->
<section class="brand-story-section">
    <div class="container brand-story-container">
        <div class="brand-story-grid">
            <div class="brand-story-visual">
                <div class="brand-story-img-frame">
                    <img src="../uploads/banners/banner_1792401665_7f772738.png" alt="Sweet Choice Artisan Pastry Prep" class="brand-story-img" onerror="this.src='../assets/images/logo_banner.jpg'">
                </div>
                <div class="brand-story-stat-card">
                    <span class="stat-number">2021</span>
                    <span class="stat-desc"><?= isJapanese() ? '銀座アトリエ創業' : 'Established in Ginza, Tokyo'; ?></span>
                </div>
            </div>

            <div class="brand-story-text">
                <span class="section-tag section-tag-natural"><?= e(__('brand_story_tag', 'Our Artisan Heritage')); ?></span>
                <h2 class="brand-story-title"><?= e(__('brand_story_title', 'The Art of Japanese Pâtisserie')); ?></h2>
                
                <p class="brand-story-lead">
                    <?= e(__('brand_story_p1', 'Born in the heart of Ginza, Sweet Choice blends timeless French pâtisserie techniques with Japan’s deep reverence for pristine seasonal ingredients.')); ?>
                </p>
                <p class="brand-story-body">
                    <?= e(__('brand_story_p2', 'From airy sponge cakes folded with rich Hokkaido fresh dairy to ceremonial grade Kyoto Uji matcha, every creation is baked with mindful sweetness and culinary devotion.')); ?>
                </p>

                <div class="brand-pillars">
                    <div class="brand-pillar">
                        <span class="pillar-num">01</span>
                        <div>
                            <h4><?= e(__('brand_pillar_1_title', 'Hokkaido Fresh Dairy')); ?></h4>
                            <p><?= e(__('brand_pillar_1_desc', 'Pure, unadulterated fresh cream delivering an ethereal, cloud-soft melt.')); ?></p>
                        </div>
                    </div>
                    <div class="brand-pillar">
                        <span class="pillar-num">02</span>
                        <div>
                            <h4><?= e(__('brand_pillar_2_title', 'Kyoto Ceremonial Matcha')); ?></h4>
                            <p><?= e(__('brand_pillar_2_desc', 'Single-origin Uji green tea ground with traditional stone mills.')); ?></p>
                        </div>
                    </div>
                    <div class="brand-pillar">
                        <span class="pillar-num">03</span>
                        <div>
                            <h4><?= e(__('brand_pillar_3_title', 'Mindful Sweetness')); ?></h4>
                            <p><?= e(__('brand_pillar_3_desc', 'Lightly sweetened to allow natural aromatics and delicate textures to shine.')); ?></p>
                        </div>
                    </div>
                </div>

                <div class="brand-story-action">
                    <a href="category.php" class="btn btn-primary">
                        <?= e(__('hero_cta_explore', 'Explore Our Collection')); ?> &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
