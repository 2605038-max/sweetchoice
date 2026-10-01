<?php
/**
 * Sweet Choice - Customer Account Management
 * Profile editing, password change, order history overview, and saved favorites.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getDB();
requireLogin('login.php');

$user = getCurrentUser($pdo);
$userId = (int)$user['id'];

$profileMsg = null;
$passwordMsg = null;
$profileErr = null;
$passwordErr = null;

// Handle Profile Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $profileErr = 'Session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            $email = trim(strtolower($_POST['email'] ?? ''));
            $phone = trim($_POST['phone'] ?? '');
            $postal = trim($_POST['postal_code'] ?? '');
            $addr = trim($_POST['address'] ?? '');
            $apt = trim($_POST['apartment'] ?? '');

            if (empty($name) || empty($email)) {
                $profileErr = 'Name and email are required.';
            } else {
                // Check if email taken by other user
                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $chk->execute([$email, $userId]);
                if ($chk->fetch()) {
                    $profileErr = 'This email is already in use by another account.';
                } else {
                    $upd = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, postal_code = ?, address = ?, apartment = ? WHERE id = ?");
                    $upd->execute([$name, $email, $phone, $postal, $addr, $apt, $userId]);
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $user = getCurrentUser($pdo);
                    $profileMsg = isJapanese() ? 'ご登録情報を更新いたしました。' : 'Profile updated successfully!';
                }
            }
        }

        if ($action === 'change_password') {
            $currentPass = $_POST['current_password'] ?? '';
            $newPass = $_POST['new_password'] ?? '';
            $confirmPass = $_POST['confirm_password'] ?? '';

            // Fetch hashed password
            $pStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $pStmt->execute([$userId]);
            $currentHashed = $pStmt->fetchColumn();

            if (!password_verify($currentPass, $currentHashed)) {
                $passwordErr = 'Current password does not match.';
            } elseif (strlen($newPass) < 6) {
                $passwordErr = 'New password must be at least 6 characters.';
            } elseif ($newPass !== $confirmPass) {
                $passwordErr = 'New passwords do not match.';
            } else {
                $newHashed = password_hash($newPass, PASSWORD_BCRYPT);
                $updPass = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updPass->execute([$newHashed, $userId]);
                $passwordMsg = isJapanese() ? 'パスワードを変更いたしました。' : 'Password changed successfully!';
            }
        }
    }
}

// Fetch user's orders
$ordersStmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$ordersStmt->execute([$userId]);
$recentOrders = $ordersStmt->fetchAll();

// Fetch favorites
$favStmt = $pdo->prepare("SELECT p.*, c.name_en AS category_name_en, c.name_ja AS category_name_ja 
                          FROM favorites f 
                          JOIN products p ON f.product_id = p.id 
                          JOIN categories c ON p.category_id = c.id 
                          WHERE f.user_id = ? 
                          LIMIT 4");
$favStmt->execute([$userId]);
$favoriteProducts = $favStmt->fetchAll();

$pageTitle = __('my_account', 'My Account');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 40px 20px;">
    <div class="section-header" style="text-align:left; margin-bottom:24px;">
        <span class="section-tag"><?= e(__('nav_account', 'My Account')); ?></span>
        <h1 class="section-title"><?= isJapanese() ? 'マイページ' : 'Hello, ' . e($user['name']); ?></h1>
        <p class="section-subtitle" style="margin:0;">
            Manage your boutique preferences, saved addresses, and dessert order history.
        </p>
    </div>

    <div style="display:grid; grid-template-columns:1.2fr 1fr; gap:36px;">
        <!-- Left: Profile & Password Editing -->
        <div>
            <!-- Profile Info Form -->
            <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:26px; box-shadow:var(--shadow-sm); margin-bottom:26px;">
                <h3 style="font-size:1.2rem; margin-bottom:18px;">👤 <?= e(__('profile_info', 'Profile Details')); ?></h3>

                <?php if ($profileMsg): ?>
                    <div class="alert alert-success"><?= e($profileMsg); ?></div>
                <?php endif; ?>
                <?php if ($profileErr): ?>
                    <div class="alert alert-danger"><?= e($profileErr); ?></div>
                <?php endif; ?>

                <form action="account.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><?= e(__('full_name', 'Full Name')); ?> *</label>
                            <input type="text" name="name" class="form-control" required value="<?= e($user['name']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= e(__('email_address', 'Email Address')); ?> *</label>
                            <input type="email" name="email" class="form-control" required value="<?= e($user['email']); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><?= e(__('phone_number', 'Phone Number')); ?></label>
                            <input type="tel" name="phone" class="form-control" value="<?= e($user['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= e(__('postal_code', 'Postal Code')); ?></label>
                            <input type="text" name="postal_code" class="form-control" value="<?= e($user['postal_code'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?= e(__('street_address', 'Street Address')); ?></label>
                        <input type="text" name="address" class="form-control" value="<?= e($user['address'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?= e(__('apartment_unit', 'Apartment / Building')); ?></label>
                        <input type="text" name="apartment" class="form-control" value="<?= e($user['apartment'] ?? ''); ?>">
                    </div>

                    <button type="submit" class="btn btn-primary"><?= e(__('update_profile', 'Save Changes')); ?></button>
                </form>
            </div>

            <!-- Change Password Form -->
            <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:26px; box-shadow:var(--shadow-sm);">
                <h3 style="font-size:1.2rem; margin-bottom:18px;">🔒 <?= e(__('change_password', 'Change Password')); ?></h3>

                <?php if ($passwordMsg): ?>
                    <div class="alert alert-success"><?= e($passwordMsg); ?></div>
                <?php endif; ?>
                <?php if ($passwordErr): ?>
                    <div class="alert alert-danger"><?= e($passwordErr); ?></div>
                <?php endif; ?>

                <form action="account.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label class="form-label"><?= e(__('current_password', 'Current Password')); ?> *</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><?= e(__('new_password', 'New Password')); ?> *</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= e(__('confirm_password', 'Confirm New Password')); ?> *</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-secondary"><?= e(__('change_password', 'Update Password')); ?></button>
                </form>
            </div>
        </div>

        <!-- Right: Recent Orders & Quick Favorites -->
        <div>
            <!-- Order History Card -->
            <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm); margin-bottom:24px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <h3 style="font-size:1.15rem;">📦 <?= e(__('my_orders', 'Recent Orders')); ?></h3>
                    <a href="orders.php" style="font-size:0.85rem; font-weight:600;"><?= e(__('view_all', 'View All')); ?> &rarr;</a>
                </div>

                <?php if (empty($recentOrders)): ?>
                    <p style="font-size:0.88rem; color:var(--color-secondary-text);">No past orders found.</p>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <?php foreach ($recentOrders as $ro): ?>
                            <div style="border-bottom:1px solid var(--color-border-light); padding-bottom:10px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.9rem;">
                                    <strong><?= e($ro['order_number']); ?></strong>
                                    <?= renderOrderStatusBadge($ro['status']); ?>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.82rem; color:var(--color-secondary-text); margin-top:4px;">
                                    <span><?= date('Y/m/d', strtotime($ro['created_at'])); ?> &bull; <?= ucfirst($ro['fulfillment_type']); ?></span>
                                    <span style="font-weight:700; color:var(--color-deep-plum);"><?= formatPrice($ro['grand_total']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Saved Favorites Card -->
            <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <h3 style="font-size:1.15rem;">♥ <?= e(__('favorites_title', 'Saved Favorites')); ?></h3>
                    <a href="favorites.php" style="font-size:0.85rem; font-weight:600;"><?= e(__('view_all', 'View All')); ?> &rarr;</a>
                </div>

                <?php if (empty($favoriteProducts)): ?>
                    <p style="font-size:0.88rem; color:var(--color-secondary-text);">No saved favorites yet.</p>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <?php foreach ($favoriteProducts as $favP): ?>
                            <div style="display:flex; align-items:center; gap:12px; font-size:0.88rem;">
                                <img src="../uploads/products/<?= e($favP['image']); ?>" alt="" style="width:44px; height:44px; border-radius:6px; object-fit:cover;">
                                <div style="flex:1;">
                                    <a href="product.php?id=<?= $favP['id']; ?>" style="font-weight:600; color:var(--color-deep-text);">
                                        <?= e(getLocalized($favP, 'name')); ?>
                                    </a>
                                    <div style="color:var(--color-deep-plum); font-weight:700; font-size:0.82rem;">
                                        <?= formatPrice($favP['price']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
