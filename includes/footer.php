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
            <div class="footer-col brand-col">
                <div class="footer-brand">
                    <span class="footer-brand-title">Sweet Choice</span>
                    <span class="footer-brand-sub">ATELIER GINZA TOKYO</span>
                </div>
                <p class="footer-about-text">
                    <?= e(__('footer_about', 'Sweet Choice is an artisanal Japanese dessert shop bringing delicate, cloud-soft pastries and seasonal confections to sweet lovers.')); ?>
                </p>
                <div class="footer-lang-switch">
                    <a href="?lang=en" class="<?= getCurrentLang() === 'en' ? 'active' : ''; ?>">English</a>
                    <span class="divider">/</span>
                    <a href="?lang=ja" class="<?= getCurrentLang() === 'ja' ? 'active' : ''; ?>">日本語</a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="footer-col">
                <h4><?= e(__('footer_links', 'Collections & Links')); ?></h4>
                <ul>
                    <li><a href="index.php"><?= e(__('nav_home', 'Home')); ?></a></li>
                    <li><a href="category.php"><?= e(__('nav_category', 'All Desserts')); ?></a></li>
                    <li><a href="cart.php"><?= e(__('nav_cart', 'Shopping Cart')); ?></a></li>
                    <li><a href="favorites.php"><?= e(__('nav_favorites', 'Saved Favorites')); ?></a></li>
                    <li><a href="orders.php"><?= e(__('nav_orders', 'Order Tracking')); ?></a></li>
                    <li><a href="account.php"><?= e(__('nav_account', 'My Account')); ?></a></li>
                </ul>
            </div>

            <!-- Boutique Hours & Fulfillment -->
            <div class="footer-col">
                <h4><?= e(__('footer_hours', 'Boutique Hours')); ?></h4>
                <p class="footer-text">
                    <strong><?= isJapanese() ? '営業時間：' : 'Store Hours:'; ?></strong><br>
                    <?= e($shopHours); ?>
                </p>
                <p class="footer-text footer-subnote">
                    <?= isJapanese() ? '銀座本店受取・距離別指定配達対応' : 'Boutique pickup & distance-based local delivery available daily.'; ?>
                </p>
                <div class="footer-badge-jpy">
                    <?= isJapanese() ? '日本円（¥ / JPY）税込表示' : 'Exclusively Japanese Yen (¥ / JPY)'; ?>
                </div>
            </div>

            <!-- Contact & Location -->
            <div class="footer-col">
                <h4><?= e(__('footer_contact', 'Boutique Atelier')); ?></h4>
                <p class="footer-text"><?= e($shopAddress); ?></p>
                <p class="footer-text">Tel: <?= e($shopPhone); ?></p>
                <p class="footer-text">Email: <?= e($shopEmail); ?></p>
                <div class="footer-legal-links">
                    <a href="#privacy"><?= isJapanese() ? 'プライバシーポリシー' : 'Privacy Policy'; ?></a>
                    <span>•</span>
                    <a href="#terms"><?= isJapanese() ? '利用規約' : 'Terms & Conditions'; ?></a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-copyright">&copy; <?= date('Y'); ?> <?= e(__('copyright', 'Sweet Choice Confectionery Co., Ltd. All rights reserved.')); ?></div>
            <div class="footer-currency-notice"><?= e(__('all_prices_jpy', 'All prices are displayed in Japanese Yen (JPY / ¥) including consumption tax.')); ?></div>
        </div>
    </div>
</footer>

<!-- Application Scripts -->
<script src="../assets/js/main.js"></script>
<script src="../assets/js/slider.js"></script>
<script src="../assets/js/recommendation.js?v=<?= file_exists(__DIR__ . '/../assets/js/recommendation.js') ? filemtime(__DIR__ . '/../assets/js/recommendation.js') : time(); ?>"></script>

</body>
</html>
