<?php
/**
 * Sweet Choice - Admin Banners Management (CRUD)
 */

$adminTitle = 'Manage Hero Banners';
require_once __DIR__ . '/header.php';

$action = $_GET['action'] ?? 'list';
$bannerId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

// Handle Delete
if ($action === 'delete' && $bannerId > 0) {
    if (verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        $del = $pdo->prepare("DELETE FROM banners WHERE id = ?");
        $del->execute([$bannerId]);
        setFlash('success', "Banner #{$bannerId} was removed.");
    }
    header("Location: banners.php");
    exit;
}

// Handle Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'new' || $action === 'edit')) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Session expired.';
    } else {
        $titleEn = trim($_POST['title_en'] ?? '');
        $titleJa = trim($_POST['title_ja'] ?? '');
        $subtitleEn = trim($_POST['subtitle_en'] ?? '');
        $subtitleJa = trim($_POST['subtitle_ja'] ?? '');
        $btnEn = trim($_POST['button_text_en'] ?? 'Explore Menu');
        $btnJa = trim($_POST['button_text_ja'] ?? 'メニューを見る');
        $link = trim($_POST['link'] ?? 'category.php');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        if (empty($titleEn) || empty($titleJa)) {
            $errors[] = 'Banner titles in English and Japanese are required.';
        }

        // Image upload handling
        $imageName = $_POST['existing_image'] ?? 'banner1.jpg';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $imageName = 'banner_' . time() . '.' . $ext;
                move_uploaded_file($file['tmp_name'], '/workspaces/sweetchoice/uploads/banners/' . $imageName);
            }
        }

        if (empty($errors)) {
            if ($action === 'new') {
                $ins = $pdo->prepare("INSERT INTO banners (title_en, title_ja, subtitle_en, subtitle_ja, button_text_en, button_text_ja, link, image, sort_order, is_active, created_at)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $ins->execute([$titleEn, $titleJa, $subtitleEn, $subtitleJa, $btnEn, $btnJa, $link, $imageName, $sortOrder, $isActive]);
                setFlash('success', 'New banner slide added.');
            } else {
                $upd = $pdo->prepare("UPDATE banners SET title_en = ?, title_ja = ?, subtitle_en = ?, subtitle_ja = ?, button_text_en = ?, button_text_ja = ?, link = ?, image = ?, sort_order = ?, is_active = ? WHERE id = ?");
                $upd->execute([$titleEn, $titleJa, $subtitleEn, $subtitleJa, $btnEn, $btnJa, $link, $imageName, $sortOrder, $isActive, $bannerId]);
                setFlash('success', 'Banner slide updated.');
            }
            header("Location: banners.php");
            exit;
        }
    }
}

// Edit Form Data
$banner = ['id' => 0, 'title_en' => '', 'title_ja' => '', 'subtitle_en' => '', 'subtitle_ja' => '', 'button_text_en' => 'Explore Menu', 'button_text_ja' => 'メニューを見る', 'link' => 'category.php', 'sort_order' => 0, 'is_active' => 1, 'image' => ''];
if ($action === 'edit' && $bannerId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM banners WHERE id = ?");
    $stmt->execute([$bannerId]);
    $b = $stmt->fetch();
    if ($b) $banner = $b;
}

$allBanners = $pdo->query("SELECT * FROM banners ORDER BY sort_order ASC, id ASC")->fetchAll();
?>

<?php if ($action === 'new' || $action === 'edit'): ?>
    <div class="admin-card" style="max-width:800px; margin:0 auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="font-size:1.3rem;"><?= $action === 'new' ? '➕ Add Banner Slide' : '✏️ Edit Banner Slide #' . $banner['id']; ?></h2>
            <a href="banners.php" class="btn btn-secondary btn-sm">&larr; Back to Banners</a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
        <?php endif; ?>

        <form action="banners.php?action=<?= $action; ?>&id=<?= $bannerId; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
            <input type="hidden" name="existing_image" value="<?= e($banner['image']); ?>">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Title (English) *</label>
                    <input type="text" name="title_en" class="form-control" required value="<?= e($banner['title_en']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Title (Japanese) *</label>
                    <input type="text" name="title_ja" class="form-control" required value="<?= e($banner['title_ja']); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Subtitle (English)</label>
                    <input type="text" name="subtitle_en" class="form-control" value="<?= e($banner['subtitle_en']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Subtitle (Japanese)</label>
                    <input type="text" name="subtitle_ja" class="form-control" value="<?= e($banner['subtitle_ja']); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Button Text (English)</label>
                    <input type="text" name="button_text_en" class="form-control" value="<?= e($banner['button_text_en']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Button Text (Japanese)</label>
                    <input type="text" name="button_text_ja" class="form-control" value="<?= e($banner['button_text_ja']); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Button Link URL</label>
                    <input type="text" name="link" class="form-control" value="<?= e($banner['link']); ?>" placeholder="category.php?category=1">
                </div>
                <div class="form-group">
                    <label class="form-label">Slide Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= $banner['sort_order']; ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Hero Banner Image (stored in /uploads/banners/)</label>
                <input type="file" name="image" class="form-control" accept="image/*">
                <?php if ($banner['image']): ?>
                    <div style="margin-top:6px; font-size:0.8rem;">Current: <?= e($banner['image']); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="is_active" value="1" <?= $banner['is_active'] ? 'checked' : ''; ?>>
                    <strong>Active (Shown in Hero Carousel)</strong>
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-lg">💾 Save Banner</button>
        </form>
    </div>

<?php else: ?>
    <div class="admin-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="font-size:1.4rem;">🖼️ Hero Banner Carousel (<?= count($allBanners); ?>)</h2>
                <p style="font-size:0.85rem; color:var(--color-secondary-text);">Manage sliding hero images on the storefront homepage.</p>
            </div>
            <a href="banners.php?action=new" class="btn btn-primary">+ Add New Banner</a>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Image</th>
                    <th>Title (EN / JA)</th>
                    <th>Link</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allBanners as $b): ?>
                    <tr>
                        <td><strong>#<?= $b['sort_order']; ?></strong></td>
                        <td>
                            <img src="../uploads/banners/<?= e($b['image']); ?>" style="width:120px; height:50px; border-radius:6px; object-fit:cover;" onerror="this.src='../assets/images/logo_banner.jpg'">
                        </td>
                        <td>
                            <strong><?= e($b['title_en']); ?></strong>
                            <div style="font-size:0.8rem; color:var(--color-secondary-text);"><?= e($b['title_ja']); ?></div>
                        </td>
                        <td><code><?= e($b['link']); ?></code></td>
                        <td>
                            <span class="badge <?= $b['is_active'] ? 'badge-available' : 'badge-sold-out'; ?>">
                                <?= $b['is_active'] ? 'Active' : 'Disabled'; ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex; gap:6px;">
                                <a href="banners.php?action=edit&id=<?= $b['id']; ?>" class="btn btn-secondary btn-sm" style="padding:4px 8px; font-size:0.75rem;">Edit</a>
                                <a href="banners.php?action=delete&id=<?= $b['id']; ?>&csrf_token=<?= getCsrfToken(); ?>" 
                                   class="btn btn-secondary btn-sm" 
                                   style="padding:4px 8px; font-size:0.75rem; color:var(--color-danger);"
                                   onclick="return confirm('Delete this banner?');">Delete</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
