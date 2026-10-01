<?php
/**
 * Sweet Choice - Admin Categories Management (CRUD)
 */

$adminTitle = 'Manage Categories';
require_once __DIR__ . '/header.php';

$action = $_GET['action'] ?? 'list';
$catId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

// Handle Delete
if ($action === 'delete' && $catId > 0) {
    if (verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        // Check if any products are in this category
        $count = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $count->execute([$catId]);
        if ($count->fetchColumn() > 0) {
            setFlash('danger', 'Cannot delete category: existing desserts are assigned to it.');
        } else {
            $del = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $del->execute([$catId]);
            setFlash('success', "Category #{$catId} deleted.");
        }
    }
    header("Location: categories.php");
    exit;
}

// Handle Form POST (Create or Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'new' || $action === 'edit')) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Session expired.';
    } else {
        $nameEn = trim($_POST['name_en'] ?? '');
        $nameJa = trim($_POST['name_ja'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $descEn = trim($_POST['description_en'] ?? '');
        $descJa = trim($_POST['description_ja'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $nameEn));
        }

        if (empty($nameEn) || empty($nameJa)) {
            $errors[] = 'English and Japanese category names are required.';
        }

        // Image upload handling
        $imageName = $_POST['existing_image'] ?? 'cat_cakes.jpg';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $imageName = 'cat_' . time() . '.' . $ext;
                move_uploaded_file($file['tmp_name'], '/workspaces/sweetchoice/uploads/products/' . $imageName);
            }
        }

        if (empty($errors)) {
            if ($action === 'new') {
                $ins = $pdo->prepare("INSERT INTO categories (name_en, name_ja, slug, description_en, description_ja, image, sort_order, is_active, created_at)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $ins->execute([$nameEn, $nameJa, $slug, $descEn, $descJa, $imageName, $sortOrder, $isActive]);
                setFlash('success', "Category '{$nameEn}' created successfully.");
            } else {
                $upd = $pdo->prepare("UPDATE categories SET name_en = ?, name_ja = ?, slug = ?, description_en = ?, description_ja = ?, image = ?, sort_order = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $upd->execute([$nameEn, $nameJa, $slug, $descEn, $descJa, $imageName, $sortOrder, $isActive, $catId]);
                setFlash('success', "Category '{$nameEn}' updated.");
            }
            header("Location: categories.php");
            exit;
        }
    }
}

// Prepare Edit Data
$category = ['id' => 0, 'name_en' => '', 'name_ja' => '', 'slug' => '', 'description_en' => '', 'description_ja' => '', 'sort_order' => 0, 'is_active' => 1, 'image' => ''];
if ($action === 'edit' && $catId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$catId]);
    $c = $stmt->fetch();
    if ($c) $category = $c;
}

$allCategories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count 
                             FROM categories c ORDER BY c.sort_order ASC, c.id ASC")->fetchAll();
?>

<?php if ($action === 'new' || $action === 'edit'): ?>
    <div class="admin-card" style="max-width:700px; margin:0 auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="font-size:1.3rem;"><?= $action === 'new' ? '➕ Add Category' : '✏️ Edit Category #' . $category['id']; ?></h2>
            <a href="categories.php" class="btn btn-secondary btn-sm">&larr; Back to Categories</a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
        <?php endif; ?>

        <form action="categories.php?action=<?= $action; ?>&id=<?= $catId; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
            <input type="hidden" name="existing_image" value="<?= e($category['image']); ?>">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Category Name (English) *</label>
                    <input type="text" name="name_en" class="form-control" required value="<?= e($category['name_en']); ?>" placeholder="Cakes">
                </div>
                <div class="form-group">
                    <label class="form-label">Category Name (Japanese) *</label>
                    <input type="text" name="name_ja" class="form-control" required value="<?= e($category['name_ja']); ?>" placeholder="ケーキ">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">URL Slug</label>
                    <input type="text" name="slug" class="form-control" value="<?= e($category['slug']); ?>" placeholder="cakes">
                </div>
                <div class="form-group">
                    <label class="form-label">Sort Order (0-99)</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= $category['sort_order']; ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Description (English)</label>
                <textarea name="description_en" rows="2" class="form-control"><?= e($category['description_en']); ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Description (Japanese)</label>
                <textarea name="description_ja" rows="2" class="form-control"><?= e($category['description_ja']); ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Cover Image</label>
                <input type="file" name="image" class="form-control" accept="image/*">
                <?php if ($category['image']): ?>
                    <div style="margin-top:6px; font-size:0.8rem;">Current: <?= e($category['image']); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="is_active" value="1" <?= $category['is_active'] ? 'checked' : ''; ?>>
                    <strong>Active (Visible on public menu)</strong>
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-lg">💾 Save Category</button>
        </form>
    </div>

<?php else: ?>
    <div class="admin-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="font-size:1.4rem;">📁 Confection Categories (<?= count($allCategories); ?>)</h2>
                <p style="font-size:0.85rem; color:var(--color-secondary-text);">Organize your shop menu into collections.</p>
            </div>
            <a href="categories.php?action=new" class="btn btn-primary">+ Add New Category</a>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Image</th>
                    <th>Name (EN / JA)</th>
                    <th>Slug</th>
                    <th>Products Count</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allCategories as $c): ?>
                    <tr>
                        <td><strong>#<?= $c['sort_order']; ?></strong></td>
                        <td>
                            <img src="../uploads/products/<?= e($c['image']); ?>" style="width:40px; height:40px; border-radius:6px; object-fit:cover;" onerror="this.src='../assets/images/logo_banner.jpg'">
                        </td>
                        <td>
                            <strong><?= e($c['name_en']); ?></strong>
                            <div style="font-size:0.8rem; color:var(--color-secondary-text);"><?= e($c['name_ja']); ?></div>
                        </td>
                        <td><code><?= e($c['slug']); ?></code></td>
                        <td>
                            <span class="badge badge-available"><?= $c['product_count']; ?> desserts</span>
                        </td>
                        <td>
                            <span class="badge <?= $c['is_active'] ? 'badge-available' : 'badge-sold-out'; ?>">
                                <?= $c['is_active'] ? 'Active' : 'Hidden'; ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex; gap:6px;">
                                <a href="categories.php?action=edit&id=<?= $c['id']; ?>" class="btn btn-secondary btn-sm" style="padding:4px 8px; font-size:0.75rem;">Edit</a>
                                <a href="categories.php?action=delete&id=<?= $c['id']; ?>&csrf_token=<?= getCsrfToken(); ?>" 
                                   class="btn btn-secondary btn-sm" 
                                   style="padding:4px 8px; font-size:0.75rem; color:var(--color-danger);"
                                   onclick="return confirm('Delete this category?');">Delete</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
