<?php
/**
 * Sweet Choice - Main Navigation Bar Component
 */

$currentScript = basename($_SERVER['PHP_SELF']);
$cartCount = getCartItemCount($pdo);
$favCount = getFavoritesCount($pdo);
$currentLang = getCurrentLang();
?>

<!-- Announcement Notice Bar -->
<div class="top-notice-bar">
    <div class="container" style="display:flex; justify-content:space-between; align-items:center; width:100%;">
        <span>🌸 <?= isJapanese() ? '銀座本店より毎日手作り・焼きたてをお届けいたします。' : 'Handmade daily at our Ginza boutique with pure Hokkaido dairy & Kyoto matcha.'; ?></span>
        <span><?= e(__('all_prices_jpy', 'All prices in Japanese Yen (¥ / JPY)')); ?></span>
    </div>
</div>

<!-- Main Sticky Navbar -->
<header class="site-navbar">
    <div class="container nav-container">
        <!-- Brand Logo -->
        <a href="index.php" class="brand-wrapper">
            <div class="brand-logo-icon">🍰</div>
            <div class="brand-text">Sweet <span>Choice</span></div>
        </a>

        <!-- Desktop Navigation Links -->
        <ul class="nav-links">
            <li class="nav-item <?= $currentScript === 'index.php' ? 'active' : ''; ?>">
                <a href="index.php"><?= e(__('nav_home', 'Home')); ?></a>
            </li>
            <li class="nav-item <?= $currentScript === 'category.php' ? 'active' : ''; ?>">
                <a href="category.php"><?= e(__('nav_category', 'Category')); ?></a>
            </li>
            <li class="nav-item <?= $currentScript === 'favorites.php' ? 'active' : ''; ?>">
                <a href="favorites.php">
                    <?= e(__('nav_favorites', 'Favorites')); ?>
                    <?php if ($favCount > 0): ?>
                        <span class="badge badge-limited fav-counter" style="margin-left:4px;"><?= $favCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item <?= $currentScript === 'orders.php' ? 'active' : ''; ?>">
                <a href="orders.php"><?= e(__('nav_orders', 'Orders')); ?></a>
            </li>
            <?php if (isLoggedIn()): ?>
                <li class="nav-item <?= $currentScript === 'account.php' ? 'active' : ''; ?>">
                    <a href="account.php"><?= e(__('nav_account', 'My Account')); ?></a>
                </li>
                <?php if (isAdmin()): ?>
                    <li class="nav-item">
                        <a href="../admin/index.php" style="color:var(--color-deep-plum); font-weight:700;">★ <?= e(__('nav_admin', 'Admin Panel')); ?></a>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a href="logout.php"><?= e(__('nav_logout', 'Logout')); ?></a>
                </li>
            <?php else: ?>
                <li class="nav-item <?= $currentScript === 'login.php' ? 'active' : ''; ?>">
                    <a href="login.php"><?= e(__('nav_login', 'Login')); ?></a>
                </li>
            <?php endif; ?>
        </ul>

        <!-- Action Icons & Language Switcher -->
        <div class="nav-actions">
            <!-- Language Switcher: EN | 日本語 -->
            <div class="lang-switch">
                <a href="?lang=en" class="<?= $currentLang === 'en' ? 'active' : ''; ?>">EN</a>
                <span>|</span>
                <a href="?lang=ja" class="<?= $currentLang === 'ja' ? 'active' : ''; ?>">日本語</a>
            </div>

            <!-- Cart Icon with dynamic counter -->
            <a href="cart.php" class="nav-btn-icon" title="<?= e(__('nav_cart', 'Cart')); ?>">
                🛒
                <?php if ($cartCount > 0): ?>
                    <span class="badge-counter"><?= $cartCount; ?></span>
                <?php endif; ?>
            </a>

            <!-- Mobile Hamburger Button -->
            <button type="button" class="mobile-menu-btn" aria-label="Toggle navigation menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </div>
</header>
