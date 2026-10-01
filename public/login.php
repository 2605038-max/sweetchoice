<?php
/**
 * Sweet Choice - Customer Login
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
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $res = loginUser($pdo, $email, $password);
        if ($res['success']) {
            setFlash('success', isJapanese() ? 'ログインしました。お帰りなさい！' : 'Welcome back! You are now logged in.');
            $dest = $_SESSION['intended_redirect'] ?? 'account.php';
            unset($_SESSION['intended_redirect']);
            header("Location: " . $dest);
            exit;
        } else {
            $error = $res['error'];
        }
    }
}

$pageTitle = __('login_title', 'Customer Login');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 60px 20px; max-width:480px;">
    <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-lg); padding:36px; box-shadow:var(--shadow-md);">
        <div style="text-align:center; margin-bottom:24px;">
            <div style="font-size:2.4rem; margin-bottom:8px;">🍰</div>
            <h1 style="font-size:1.8rem;"><?= e(__('login_title', 'Customer Login')); ?></h1>
            <p style="font-size:0.88rem; color:var(--color-secondary-text);">Access your Sweet Choice favorites, cart, and past orders.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error); ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
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
            <a href="register.php" style="font-weight:700;"><?= e(__('nav_register', 'Register here')); ?></a>
        </div>

        <div style="margin-top:16px; background:#FAF6F7; border-radius:var(--radius-sm); padding:12px; font-size:0.8rem; color:var(--color-secondary-text); text-align:center;">
            <strong>Demo Customer Account:</strong><br>
            Email: <code>customer@sweetchoice.jp</code> / Password: <code>customer123</code>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
