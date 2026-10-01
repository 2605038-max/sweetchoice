<?php
/**
 * Sweet Choice - Common Page Footer
 */

$shopHours = getShopSetting($pdo, 'business_hours_display', 'Mon - Sun: 10:00 AM - 8:00 PM');
$shopPhone = getShopSetting($pdo, 'shop_phone', '+81 3-5555-0199');
$shopEmail = getShopSetting($pdo, 'shop_email', 'contact@sweetchoice.jp');
$shopAddress = getShopSetting($pdo, 'shop_address', 'Ginza 4-2-11, Chuo-ku, Tokyo 104-0061, Japan');
?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <!-- Brand Column -->
            <div class="footer-col">
                <div class="brand-wrapper" style="margin-bottom:14px;">
                    <div class="brand-logo-icon">🍰</div>
                    <div class="brand-text" style="font-size:1.6rem;">Sweet <span>Choice</span></div>
                </div>
                <p style="color:var(--color-secondary-text); font-size:0.9rem; line-height:1.7; margin-bottom:16px;">
                    <?= e(__('footer_about', 'Sweet Choice is an artisanal Japanese dessert shop bringing delicate, cloud-soft pastries and seasonal confections to sweet lovers.')); ?>
                </p>
                <div class="lang-switch" style="display:inline-flex;">
                    <a href="?lang=en" class="<?= getCurrentLang() === 'en' ? 'active' : ''; ?>">English</a>
                    <span>|</span>
                    <a href="?lang=ja" class="<?= getCurrentLang() === 'ja' ? 'active' : ''; ?>">日本語</a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="footer-col">
                <h4><?= e(__('footer_links', 'Quick Links')); ?></h4>
                <ul>
                    <li><a href="index.php"><?= e(__('nav_home', 'Home')); ?></a></li>
                    <li><a href="category.php"><?= e(__('nav_category', 'Category')); ?></a></li>
                    <li><a href="cart.php"><?= e(__('nav_cart', 'Cart')); ?></a></li>
                    <li><a href="favorites.php"><?= e(__('nav_favorites', 'Favorites')); ?></a></li>
                    <li><a href="orders.php"><?= e(__('nav_orders', 'Orders')); ?></a></li>
                    <li><a href="account.php"><?= e(__('nav_account', 'My Account')); ?></a></li>
                </ul>
            </div>

            <!-- Boutique Hours -->
            <div class="footer-col">
                <h4><?= e(__('footer_hours', 'Boutique Hours')); ?></h4>
                <p style="font-size:0.9rem; color:var(--color-secondary-text); margin-bottom:12px;">
                    <strong><?= isJapanese() ? '営業時間：' : 'Store Hours:'; ?></strong><br>
                    <?= e($shopHours); ?>
                </p>
                <p style="font-size:0.85rem; color:var(--color-secondary-text);">
                    🌸 <?= isJapanese() ? '当日受取＆指定配達対応' : 'Daily boutique pickup & scheduled delivery available.'; ?>
                </p>
            </div>

            <!-- Contact & Location -->
            <div class="footer-col">
                <h4><?= e(__('footer_contact', 'Store Information')); ?></h4>
                <p style="font-size:0.88rem; color:var(--color-secondary-text); margin-bottom:8px;">
                    📍 <?= e($shopAddress); ?>
                </p>
                <p style="font-size:0.88rem; color:var(--color-secondary-text); margin-bottom:8px;">
                    📞 <?= e($shopPhone); ?>
                </p>
                <p style="font-size:0.88rem; color:var(--color-secondary-text); margin-bottom:14px;">
                    ✉️ <?= e($shopEmail); ?>
                </p>
                <span class="badge badge-available"><?= isJapanese() ? '日本円（¥ / JPY）決済' : 'Exclusively Japanese Yen (¥ / JPY)'; ?></span>
            </div>
        </div>

        <div class="footer-bottom">
            <div>&copy; <?= date('Y'); ?> <?= e(__('copyright', 'Sweet Choice Confectionery Co., Ltd. All rights reserved.')); ?></div>
            <div><?= e(__('all_prices_jpy', 'All prices are displayed in Japanese Yen (JPY / ¥) including consumption tax.')); ?></div>
        </div>
    </div>
</footer>

<!-- Application Scripts -->
<script src="../assets/js/main.js"></script>
<script src="../assets/js/slider.js"></script>
<script src="../assets/js/recommendation.js"></script>

</body>
</html>
