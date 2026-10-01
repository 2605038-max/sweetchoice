<?php
/**
 * Sweet Choice - Admin Users Management
 * Manage customer accounts, staff permissions, and roles.
 */

$adminTitle = 'Manage Users';
require_once __DIR__ . '/header.php';

$action = $_GET['action'] ?? 'list';
$userId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

// Handle Delete
if ($action === 'delete' && $userId > 0) {
    if (verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        if ($userId === (int)$_SESSION['user_id']) {
            setFlash('danger', 'You cannot delete your own active administrator account.');
        } else {
            $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $del->execute([$userId]);
            setFlash('success', "User #{$userId} deleted.");
        }
    }
    header("Location: users.php");
    exit;
}

// Handle Form POST (Add or Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'new' || $action === 'edit')) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Session expired.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $role = in_array($_POST['role'] ?? '', ['customer', 'admin'], true) ? $_POST['role'] : 'customer';
        $phone = trim($_POST['phone'] ?? '');
        $postal = trim($_POST['postal_code'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $apartment = trim($_POST['apartment'] ?? '');
        $newPass = $_POST['password'] ?? '';

        if (empty($name) || empty($email)) {
            $errors[] = 'Name and Email are required.';
        }

        // Email uniqueness check
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->execute([$email, $userId]);
        if ($chk->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }

        if (empty($errors)) {
            if ($action === 'new') {
                if (empty($newPass) || strlen($newPass) < 6) {
                    $errors[] = 'Password must be at least 6 characters.';
                } else {
                    $hashed = password_hash($newPass, PASSWORD_BCRYPT);
                    $ins = $pdo->prepare("INSERT INTO users (name, email, password, role, phone, postal_code, address, apartment, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $ins->execute([$name, $email, $hashed, $role, $phone, $postal, $address, $apartment]);
                    setFlash('success', "User '{$name}' created.");
                    header("Location: users.php");
                    exit;
                }
            } else {
                // Update
                if (!empty($newPass)) {
                    $hashed = password_hash($newPass, PASSWORD_BCRYPT);
                    $upd = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ?, phone = ?, postal_code = ?, address = ?, apartment = ? WHERE id = ?");
                    $upd->execute([$name, $email, $hashed, $role, $phone, $postal, $address, $apartment, $userId]);
                } else {
                    $upd = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, phone = ?, postal_code = ?, address = ?, apartment = ? WHERE id = ?");
                    $upd->execute([$name, $email, $role, $phone, $postal, $address, $apartment, $userId]);
                }
                setFlash('success', "User '{$name}' updated.");
                header("Location: users.php");
                exit;
            }
        }
    }
}

// Edit User Data
$user = ['id' => 0, 'name' => '', 'email' => '', 'role' => 'customer', 'phone' => '', 'postal_code' => '', 'address' => '', 'apartment' => ''];
if ($action === 'edit' && $userId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    if ($u) $user = $u;
}

$allUsers = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count FROM users u ORDER BY u.id DESC")->fetchAll();
?>

<?php if ($action === 'new' || $action === 'edit'): ?>
    <div class="admin-card" style="max-width:700px; margin:0 auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="font-size:1.3rem;"><?= $action === 'new' ? '➕ Add User' : '✏️ Edit User #' . $user['id']; ?></h2>
            <a href="users.php" class="btn btn-secondary btn-sm">&larr; Back to Users</a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
        <?php endif; ?>

        <form action="users.php?action=<?= $action; ?>&id=<?= $userId; ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-control" required value="<?= e($user['name']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-control" required value="<?= e($user['email']); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select name="role" class="form-control">
                        <option value="customer" <?= $user['role'] === 'customer' ? 'selected' : ''; ?>>Customer</option>
                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Password <?= $action === 'edit' ? '(Leave blank to keep unchanged)' : '*'; ?></label>
                    <input type="password" name="password" class="form-control" <?= $action === 'new' ? 'required minlength="6"' : ''; ?>>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-control" value="<?= e($user['phone']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Postal Code</label>
                    <input type="text" name="postal_code" class="form-control" value="<?= e($user['postal_code']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Street Address</label>
                <input type="text" name="address" class="form-control" value="<?= e($user['address']); ?>">
            </div>

            <button type="submit" class="btn btn-primary btn-lg">💾 Save User</button>
        </form>
    </div>

<?php else: ?>
    <div class="admin-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="font-size:1.4rem;">👥 User Directory (<?= count($allUsers); ?>)</h2>
                <p style="font-size:0.85rem; color:var(--color-secondary-text);">Manage registered customers and staff portal access.</p>
            </div>
            <a href="users.php?action=new" class="btn btn-primary">+ Add New User</a>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Phone</th>
                    <th>Orders</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allUsers as $u): ?>
                    <tr>
                        <td>#<?= $u['id']; ?></td>
                        <td><strong><?= e($u['name']); ?></strong></td>
                        <td><?= e($u['email']); ?></td>
                        <td>
                            <span class="badge <?= $u['role'] === 'admin' ? 'badge-limited' : 'badge-available'; ?>">
                                <?= strtoupper($u['role']); ?>
                            </span>
                        </td>
                        <td><?= e($u['phone'] ?: '-'); ?></td>
                        <td><strong><?= $u['order_count']; ?></strong> orders</td>
                        <td><?= date('Y/m/d', strtotime($u['created_at'])); ?></td>
                        <td>
                            <div style="display:flex; gap:6px;">
                                <a href="users.php?action=edit&id=<?= $u['id']; ?>" class="btn btn-secondary btn-sm" style="padding:4px 8px; font-size:0.75rem;">Edit</a>
                                <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                    <a href="users.php?action=delete&id=<?= $u['id']; ?>&csrf_token=<?= getCsrfToken(); ?>" 
                                       class="btn btn-secondary btn-sm" 
                                       style="padding:4px 8px; font-size:0.75rem; color:var(--color-danger);"
                                       onclick="return confirm('Delete this user account?');">Delete</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
