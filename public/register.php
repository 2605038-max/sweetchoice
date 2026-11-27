<?php
/**
 * Sweet Choice - Customer Registration
 * Fully bilingual (JA/EN), validates all required fields + password confirmation, preserves & merges guest cart.
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
        $res = registerUser($pdo, $_POST);
        if ($res['success']) {
            setFlash('success', isJapanese() ? '会員登録が完了しました！ようこそSweet Choiceへ。' : 'Account created successfully! Welcome to Sweet Choice.');
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
            // Guest cart remains completely untouched on failed registration
            $error = $res['error'];
        }
    }
}

$pageTitle = __('register_title', 'Create Account');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container checkout-page-wrap">
    <div class="auth-card-wrap auth-card-wrap-wide">
        <div class="checkout-card auth-card">
            <div style="text-align:center; margin-bottom:24px;">
                <div style="font-size:2.2rem; margin-bottom:6px;">🌸</div>
                <h1 style="font-size:1.8rem;"><?= e(__('register_title', 'Create an Account')); ?></h1>
                <p style="font-size:0.88rem; color:var(--color-secondary-text); margin-top:6px;">
                    <?= e(__('auth_register_subtitle', 'Create your account to complete checkout, save favorites, and track orders.')); ?>
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

            <form action="register.php<?= $fromCheckout ? '?from=checkout' : ''; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">

                <div class="form-group">
                    <label class="form-label"><?= e(__('full_name', 'Full Name')); ?> *</label>
                    <input type="text" name="name" class="form-control" required placeholder="<?= isJapanese() ? '山田 花子' : 'Hana Tanaka'; ?>" value="<?= e($_POST['name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label"><?= e(__('email_address', 'Email Address')); ?> *</label>
                    <input type="email" name="email" class="form-control" required placeholder="hana@example.com" value="<?= e($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= e(__('phone_number', 'Phone Number')); ?> *</label>
                        <input type="tel" name="phone" class="form-control" required placeholder="090-1234-5678" value="<?= e($_POST['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e(__('postal_code', 'Postal Code')); ?> *</label>
                        <input type="text" name="postal_code" class="form-control" required placeholder="104-0061" value="<?= e($_POST['postal_code'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><?= e(__('street_address', 'Street Address')); ?> *</label>
                    <input type="text" name="address" class="form-control" required placeholder="<?= isJapanese() ? '東京都中央区銀座4-2-11' : 'Tokyo, Chuo-ku, Ginza 4-2-11'; ?>" value="<?= e($_POST['address'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label"><?= e(__('apartment_unit', 'Apartment / Building')); ?> (<?= e(__('optional_label', 'Optional')); ?>)</label>
                    <input type="text" name="apartment" class="form-control" placeholder="<?= isJapanese() ? 'メゾン銀座 302号室' : 'Maison Ginza #302'; ?>" value="<?= e($_POST['apartment'] ?? ''); ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= e(__('password_min_hint', 'Password (min 6 characters)')); ?> *</label>
                        <input type="password" name="password" class="form-control" required minlength="6" placeholder="••••••••">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e(__('password_confirm', 'Confirm Password')); ?> *</label>
                        <input type="password" name="password_confirm" class="form-control" required minlength="6" placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:20px;">
                    <?= e(__('nav_register', 'Register')); ?> &rarr;
                </button>
            </form>

            <div style="margin-top:24px; padding-top:20px; border-top:1px solid var(--color-border-light); text-align:center; font-size:0.9rem;">
                <?= e(__('already_have_account', 'Already have an account?')); ?>
                <a href="login.php<?= $fromCheckout ? '?from=checkout' : ''; ?>" style="font-weight:700;"><?= e(__('nav_login', 'Login')); ?></a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
