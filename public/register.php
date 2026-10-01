<?php
/**
 * Sweet Choice - Customer Registration
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getDB();

if (isLoggedIn()) {
    header("Location: account.php");
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Session expired. Please try again.';
    } else {
        $res = registerUser($pdo, $_POST);
        if ($res['success']) {
            setFlash('success', isJapanese() ? '会員登録が完了しました！ようこそSweet Choiceへ。' : 'Account created successfully! Welcome to Sweet Choice.');
            header("Location: account.php");
            exit;
        } else {
            $error = $res['error'];
        }
    }
}

$pageTitle = __('register_title', 'Create Account');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 60px 20px; max-width:540px;">
    <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-lg); padding:36px; box-shadow:var(--shadow-md);">
        <div style="text-align:center; margin-bottom:24px;">
            <div style="font-size:2.4rem; margin-bottom:8px;">🌸</div>
            <h1 style="font-size:1.8rem;"><?= e(__('register_title', 'Create an Account')); ?></h1>
            <p style="font-size:0.88rem; color:var(--color-secondary-text);">Join our confectionery club to save favorites, earn perks, and track orders.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error); ?></div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">

            <div class="form-group">
                <label class="form-label"><?= e(__('full_name', 'Full Name')); ?> *</label>
                <input type="text" name="name" class="form-control" required placeholder="Hana Tanaka" value="<?= e($_POST['name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label"><?= e(__('email_address', 'Email Address')); ?> *</label>
                <input type="email" name="email" class="form-control" required placeholder="hana@example.com" value="<?= e($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label"><?= e(__('password', 'Password (min 6 characters)')); ?> *</label>
                <input type="password" name="password" class="form-control" required minlength="6" placeholder="••••••••">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= e(__('phone_number', 'Phone Number')); ?></label>
                    <input type="tel" name="phone" class="form-control" placeholder="090-1234-5678" value="<?= e($_POST['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e(__('postal_code', 'Postal Code')); ?></label>
                    <input type="text" name="postal_code" class="form-control" placeholder="104-0061" value="<?= e($_POST['postal_code'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><?= e(__('street_address', 'Street Address')); ?></label>
                <input type="text" name="address" class="form-control" placeholder="Tokyo, Chuo-ku, Ginza 4-2-11" value="<?= e($_POST['address'] ?? ''); ?>">
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:20px;">
                <?= e(__('nav_register', 'Register Account')); ?> &rarr;
            </button>
        </form>

        <div style="margin-top:24px; padding-top:20px; border-top:1px solid var(--color-border-light); text-align:center; font-size:0.9rem;">
            <?= e(__('already_have_account', 'Already have an account?')); ?> 
            <a href="login.php" style="font-weight:700;"><?= e(__('nav_login', 'Log in here')); ?></a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
