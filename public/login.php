<?php
/**
 * Sweet Choice - Customer Login
 * Fully bilingual (JA/EN) with checkout flow integration and guest cart preservation.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getDB();

$fromCheckout = (isset($_GET['from']) && $_GET['from'] === 'checkout')
    || (!empty($_SESSION['intended_redirect']) && str_contains($_SESSION['intended_redirect'], 'checkout'));

if ($fromCheckout && empty($_SESSION['intended_redirect'])) {
    $_SESSION['intended_redirect'] = 'checkout-start.php';
}

if (isLoggedIn()) {
    $dest = $_SESSION['intended_redirect'] ?? 'account.php';
    unset($_SESSION['intended_redirect']);
    header("Location: " . $dest);
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = __('auth_csrf_expired', 'Session expired. Please refresh and try again.');
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $res = loginUser($pdo, $email, $password);
        if ($res['success']) {
            setFlash('success', isJapanese() ? 'ログインしました。お帰りなさい！' : 'Welcome back! You are now logged in.');
            if (!empty($res['cart_merge']['merged'])) {
                setFlash('info', __('cart_merge_success', 'Your guest cart items have been safely merged into your account.'));
            }
            if (!empty($res['cart_merge']['adjusted'])) {
                setFlash('info', __('cart_merge_adjusted', 'Some cart quantities were adjusted based on current stock availability.'));
            }
            $dest = $_SESSION['intended_redirect'] ?? 'account.php';
            unset($_SESSION['intended_redirect']);
            header("Location: " . $dest);
            exit;
        } else {
            // Guest cart remains completely untouched on failed login
            $error = $res['error'];
        }
    }
}

$pageTitle = __('login_title', 'Customer Login');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container checkout-page-wrap">
    <div class="auth-card-wrap">
        <div class="checkout-card auth-card">
            <div style="text-align:center; margin-bottom:24px;">
                <h1 style="font-size:1.8rem;"><?= e(__('login_title', 'Customer Login')); ?></h1>
                <p style="font-size:0.88rem; color:var(--color-secondary-text); margin-top:6px;">
                    <?= e(__('auth_login_subtitle', 'Access your Sweet Choice favorites, cart, and past orders.')); ?>
                </p>
            </div>

            <?php if ($fromCheckout): ?>
                <div class="alert alert-info" style="font-size:0.86rem; margin-bottom:18px;">
                    🛒 <?= e(__('auth_checkout_notice', 'Please log in or create an account to continue to checkout. Your cart items are safely saved.')); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error); ?></div>
            <?php endif; ?>

            <form action="login.php<?= $fromCheckout ? '?from=checkout' : ''; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">

                <div class="form-group">
                    <label class="form-label"><?= e(__('email_address', 'Email Address')); ?> *</label>
                    <input type="email" name="email" class="form-control" required placeholder="you@example.com" value="<?= e($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label"><?= e(__('password', 'Password')); ?> *</label>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••">
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:20px;">
                    <?= e(__('nav_login', 'Login')); ?> &rarr;
                </button>
            </form>

            <div style="margin-top:24px; padding-top:20px; border-top:1px solid var(--color-border-light); text-align:center; font-size:0.9rem;">
                <?= e(__('dont_have_account', 'Don’t have an account?')); ?>
                <a href="register.php<?= $fromCheckout ? '?from=checkout' : ''; ?>" style="font-weight:700;"><?= e(__('nav_register', 'Register')); ?></a>
            </div>

            <div style="margin-top:16px; background:#FAF6F7; border-radius:var(--radius-sm); padding:12px; font-size:0.8rem; color:var(--color-secondary-text); text-align:center;">
                <strong><?= e(__('auth_demo_account', 'Demo Customer Account:')); ?></strong><br>
                <?= e(__('email_address', 'Email')); ?>: <code>customer@sweetchoice.jp</code> / <?= e(__('password', 'Password')); ?>: <code>customer123</code>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
