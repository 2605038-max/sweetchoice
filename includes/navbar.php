<?php
/**
 * Sweet Choice - Main Navigation Bar Component
 */

$currentScript = basename($_SERVER['PHP_SELF']);
$cartCount = getCartItemCount($pdo);
$favCount = getFavoritesCount($pdo);
$currentLang = getCurrentLang();
?>



<!-- Main Sticky Navbar -->
<header class="site-navbar">
    <div class="container nav-container">
        <!-- Brand Logo -->
        <a href="index.php" class="brand-wrapper">
            <div class="brand-identity">
                <span class="brand-text">SWEET CHOICE</span>
                
            </div>
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
                        <span class="fav-counter"><?= $favCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item <?= $currentScript === 'orders.php' ? 'active' : ''; ?>">
                <a href="orders.php"><?= e(__('nav_orders', 'Orders')); ?></a>
            </li>
            <!-- Mini Game Navigation Item(new) -->
            <li class="nav-item">
                <a href="game/index.html" class="nav-game-link" title="<?= isJapanese() ? '走るケーキゲームで遊ぶ' : 'Play Running Cake Game'; ?>">
                    <span style="margin-right: 4px;">🎮</span><?= isJapanese() ? 'ミニゲーム' : 'Mini Game'; ?>
                </a>
            </li>
            <?php if (isLoggedIn()): ?>
                <li class="nav-item <?= $currentScript === 'account.php' ? 'active' : ''; ?>">
                    <a href="account.php"><?= e(__('nav_account', 'My Account')); ?></a>
                </li>
                <?php if (isAdmin()): ?>
                    <li class="nav-item">
                        <a href="../admin/index.php" class="nav-admin-link"><?= e(__('nav_admin', 'Admin Panel')); ?></a>
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
                <span class="lang-divider">/</span>
                <a href="?lang=ja" class="<?= $currentLang === 'ja' ? 'active' : ''; ?>">JA</a>
            </div>

            <!-- Cart Icon with dynamic counter -->
            <a href="cart.php" class="nav-btn-icon" title="<?= e(__('nav_cart', 'Cart')); ?>" aria-label="<?= e(__('nav_cart', 'Cart')); ?>">
                <svg class="cart-icon-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
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
