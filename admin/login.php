<?php
/**
 * Sweet Choice - Admin Login Portal
 * Enforces admin-only authentication
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getDB();

if (isAdmin()) {
    header("Location: index.php");
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please reload.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $res = loginUser($pdo, $email, $password, true);
        if ($res['success']) {
            setFlash('success', 'Logged in as Administrator.');
            header("Location: index.php");
            exit;
        } else {
            $error = $res['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Portal Login | Sweet Choice</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body style="background:#2D2224; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px;">

<div style="background:#FFFFFF; border-radius:var(--radius-lg); padding:40px; width:100%; max-width:440px; box-shadow:0 10px 40px rgba(0,0,0,0.3);">
    <div style="text-align:center; margin-bottom:26px;">
        <div style="font-size:2.8rem; margin-bottom:10px;">🍰</div>
        <h1 style="font-size:1.8rem; color:#2D2224;">Staff & Admin Portal</h1>
        <p style="font-size:0.88rem; color:var(--color-secondary-text);">Sweet Choice Boutique Management</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:18px;"><?= e($error); ?></div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">

        <div class="form-group">
            <label class="form-label">Staff Email Address *</label>
            <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? 'admin@sweetchoice.jp'); ?>" placeholder="admin@sweetchoice.jp">
        </div>

        <div class="form-group">
            <label class="form-label">Password *</label>
            <input type="password" name="password" class="form-control" required placeholder="••••••••">
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:20px;">
            Sign In to Dashboard &rarr;
        </button>
    </form>

    <div style="margin-top:20px; background:#FAF6F7; border-radius:var(--radius-sm); padding:12px; font-size:0.82rem; color:var(--color-secondary-text); text-align:center;">
        <strong>Default Admin Credentials:</strong><br>
        Email: <code>admin@sweetchoice.jp</code> / Password: <code>admin123</code>
    </div>

    <div style="margin-top:16px; text-align:center;">
        <a href="../public/index.php" style="font-size:0.85rem; color:var(--color-secondary-text);">&larr; Return to Customer Storefront</a>
    </div>
</div>

</body>
</html>
