<?php
/**
 * Sweet Choice - Admin Products Management (CRUD)
 * Create, Read, Update, Delete desserts with ingredients, allergies, customizations, stock, and secure image upload.
 */

$adminTitle = 'Manage Desserts';
require_once __DIR__ . '/header.php';

$action = $_GET['action'] ?? 'list';
$productId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();

$errors = [];

// Handle Product Deletion
if ($action === 'delete' && $productId > 0) {
    if (verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        $del = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $del->execute([$productId]);
        setFlash('success', "Dessert #{$productId} was deleted.");
    } else {
        setFlash('danger', "Invalid CSRF token.");
    }
    header("Location: products.php");
    exit;
}

// Handle Product Create / Update Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'new' || $action === 'edit')) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Session token expired. Please try again.';
    } else {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $nameEn = trim($_POST['name_en'] ?? '');
        $nameJa = trim($_POST['name_ja'] ?? '');
        $descEn = trim($_POST['description_en'] ?? '');
        $descJa = trim($_POST['description_ja'] ?? '');
        $price = max(0, (int)($_POST['price'] ?? 0));
        $stock = max(0, (int)($_POST['stock'] ?? 0));
        $availability = in_array($_POST['availability'] ?? '', ['available', 'limited', 'sold_out'], true) ? $_POST['availability'] : 'available';
        $isPopular = !empty($_POST['is_popular']) ? 1 : 0;
        $tags = trim($_POST['tags'] ?? '');
        $slug = trim($_POST['slug'] ?? '');

        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $nameEn));
        }

        if (empty($nameEn) || empty($nameJa) || $categoryId <= 0 || $price <= 0) {
            $errors[] = 'Please fill in English Name, Japanese Name, Category, and valid JPY Price.';
        }

        // Handle Image Upload
        $imageFilename = $_POST['existing_image'] ?? 'prod_strawberry_shortcake.jpg';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (!in_array($mime, $allowedMimes, true) || !in_array($ext, $allowedExts, true)) {
                $errors[] = 'Invalid image file. Only JPG, PNG, WEBP, or GIF are allowed.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Image exceeds 5MB size limit.';
            } else {
                $imageFilename = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destPath = '/workspaces/sweetchoice/uploads/products/' . $imageFilename;
                if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                    $errors[] = 'Failed to save uploaded image.';
                }
            }
        }

        if (empty($errors)) {
            try {
                if ($action === 'new') {
                    $checkSlug = $pdo->prepare("SELECT id FROM products WHERE slug = ?");
                    $checkSlug->execute([$slug]);
                    if ($checkSlug->fetch()) {
                        $slug .= '-' . time();
                    }

                    $ins = $pdo->prepare("INSERT INTO products 
                        (category_id, name_en, name_ja, slug, description_en, description_ja, price, image, stock, availability, is_popular, tags, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $ins->execute([$categoryId, $nameEn, $nameJa, $slug, $descEn, $descJa, $price, $imageFilename, $stock, $availability, $isPopular, $tags]);
                    $productId = (int)$pdo->lastInsertId();
                    setFlash('success', "New dessert '{$nameEn}' added successfully!");
                } else {
                    $upd = $pdo->prepare("UPDATE products SET 
                        category_id = ?, name_en = ?, name_ja = ?, slug = ?, description_en = ?, description_ja = ?, 
                        price = ?, image = ?, stock = ?, availability = ?, is_popular = ?, tags = ?, updated_at = NOW() 
                        WHERE id = ?");
                    $upd->execute([$categoryId, $nameEn, $nameJa, $slug, $descEn, $descJa, $price, $imageFilename, $stock, $availability, $isPopular, $tags, $productId]);
                    setFlash('success', "Dessert '{$nameEn}' updated successfully!");
                }

            // Save Ingredients
            $pdo->prepare("DELETE FROM product_ingredients WHERE product_id = ?")->execute([$productId]);
            $rawIngs = trim($_POST['ingredients'] ?? '');
            if (!empty($rawIngs)) {
                $ingInsert = $pdo->prepare("INSERT INTO product_ingredients (product_id, ingredient_en, ingredient_ja) VALUES (?, ?, ?)");
                $lines = explode("\n", str_replace("\r", "", $rawIngs));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line) {
                        $parts = explode('|', $line, 2);
                        $ingEn = trim($parts[0]);
                        $ingJa = isset($parts[1]) ? trim($parts[1]) : $ingEn;
                        $ingInsert->execute([$productId, $ingEn, $ingJa]);
                    }
                }
            }

            // Save Allergies
            $pdo->prepare("DELETE FROM product_allergies WHERE product_id = ?")->execute([$productId]);
            $rawAllergies = trim($_POST['allergies'] ?? '');
            if (!empty($rawAllergies)) {
                $algInsert = $pdo->prepare("INSERT INTO product_allergies (product_id, allergy_en, allergy_ja) VALUES (?, ?, ?)");
                $lines = explode("\n", str_replace("\r", "", $rawAllergies));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line) {
                        $parts = explode('|', $line, 2);
                        $algEn = trim($parts[0]);
                        $algJa = isset($parts[1]) ? trim($parts[1]) : $algEn;
                        $algInsert->execute([$productId, $algEn, $algJa]);
                    }
                }
            }

            // Save Customizations
            $pdo->prepare("DELETE FROM product_customizations WHERE product_id = ?")->execute([$productId]);
            $rawCustoms = trim($_POST['customizations'] ?? '');
            if (!empty($rawCustoms)) {
                $custInsert = $pdo->prepare("INSERT INTO product_customizations (product_id, group_name_en, group_name_ja, option_name_en, option_name_ja, price_extra, is_default) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $lines = explode("\n", str_replace("\r", "", $rawCustoms));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line) {
                        // Format: GroupEn|GroupJa|OptionEn|OptionJa|PriceExtra|isDefault
                        $parts = explode('|', $line);
                        if (count($parts) >= 3) {
                            $gEn = trim($parts[0]);
                            $gJa = trim($parts[1]);
                            $oEn = trim($parts[2]);
                            $oJa = isset($parts[3]) ? trim($parts[3]) : $oEn;
                            $pEx = isset($parts[4]) ? (int)trim($parts[4]) : 0;
                            $isDef = isset($parts[5]) && trim($parts[5]) === '1' ? 1 : 0;
                            $custInsert->execute([$productId, $gEn, $gJa, $oEn, $oJa, $pEx, $isDef]);
                        }
                    }
                }
            }

            header("Location: products.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
}

// Prepare Data for Edit Mode
$product = [
    'id' => 0, 'category_id' => 1, 'name_en' => '', 'name_ja' => '', 'slug' => '',
    'description_en' => '', 'description_ja' => '', 'price' => 600, 'stock' => 20,
    'availability' => 'available', 'is_popular' => 0, 'tags' => '', 'image' => ''
];
$ingredientsText = '';
$allergiesText = '';
$customizationsText = '';

if ($action === 'edit' && $productId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $p = $stmt->fetch();
    if ($p) {
        $product = $p;

        // Fetch ingredients as text lines
        $ingList = $pdo->prepare("SELECT * FROM product_ingredients WHERE product_id = ?");
        $ingList->execute([$productId]);
        $lines = [];
        foreach ($ingList->fetchAll() as $ing) {
            $lines[] = $ing['ingredient_en'] . '|' . $ing['ingredient_ja'];
        }
        $ingredientsText = implode("\n", $lines);

        // Fetch allergies as text lines
        $algList = $pdo->prepare("SELECT * FROM product_allergies WHERE product_id = ?");
        $algList->execute([$productId]);
        $lines = [];
        foreach ($algList->fetchAll() as $alg) {
            $lines[] = $alg['allergy_en'] . '|' . $alg['allergy_ja'];
        }
        $allergiesText = implode("\n", $lines);

        // Fetch customizations as text lines
        $cList = $pdo->prepare("SELECT * FROM product_customizations WHERE product_id = ?");
        $cList->execute([$productId]);
        $lines = [];
        foreach ($cList->fetchAll() as $c) {
            $lines[] = "{$c['group_name_en']}|{$c['group_name_ja']}|{$c['option_name_en']}|{$c['option_name_ja']}|{$c['price_extra']}|{$c['is_default']}";
        }
        $customizationsText = implode("\n", $lines);
    }
}

// Fetch list of all products for 'list' view
$allProducts = $pdo->query("SELECT p.*, c.name_en AS category_name 
                           FROM products p 
                           JOIN categories c ON p.category_id = c.id 
                           ORDER BY p.id DESC")->fetchAll();
?>

<?php if ($action === 'new' || $action === 'edit'): ?>
    <!-- Form View -->
    <div class="admin-card" style="max-width:960px; margin:0 auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:12px; border-bottom:1px solid var(--color-border);">
            <h2 style="font-size:1.4rem;"><?= $action === 'new' ? '➕ Add New Dessert' : '✏️ Edit Dessert #' . $product['id']; ?></h2>
            <a href="products.php" class="btn btn-secondary btn-sm">&larr; Back to Products</a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul style="margin-left:20px;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="products.php?action=<?= $action; ?>&id=<?= $productId; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
            <input type="hidden" name="existing_image" value="<?= e($product['image']); ?>">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Category *</label>
                    <select name="category_id" class="form-control" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id']; ?>" <?= (int)$product['category_id'] === (int)$cat['id'] ? 'selected' : ''; ?>>
                                <?= e($cat['name_en']); ?> (<?= e($cat['name_ja']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Price (Japanese Yen ¥ / JPY) *</label>
                    <input type="number" name="price" class="form-control" required min="0" step="10" value="<?= $product['price']; ?>" placeholder="680">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Product Name (English) *</label>
                    <input type="text" name="name_en" class="form-control" required value="<?= e($product['name_en']); ?>" placeholder="Strawberry Shortcake">
                </div>
                <div class="form-group">
                    <label class="form-label">Product Name (Japanese) *</label>
                    <input type="text" name="name_ja" class="form-control" required value="<?= e($product['name_ja']); ?>" placeholder="極上いちごのショートケーキ">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Stock Quantity *</label>
                    <input type="number" name="stock" class="form-control" required min="0" value="<?= $product['stock']; ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Availability Status *</label>
                    <select name="availability" class="form-control">
                        <option value="available" <?= $product['availability'] === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="limited" <?= $product['availability'] === 'limited' ? 'selected' : ''; ?>>Limited Stock</option>
                        <option value="sold_out" <?= $product['availability'] === 'sold_out' ? 'selected' : ''; ?>>Sold Out</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Description (English)</label>
                    <textarea name="description_en" rows="3" class="form-control"><?= e($product['description_en']); ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Description (Japanese)</label>
                    <textarea name="description_ja" rows="3" class="form-control"><?= e($product['description_ja']); ?></textarea>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">AI Sommelier Recommendation Tags (Comma-separated)</label>
                <input type="text" name="tags" class="form-control" value="<?= e($product['tags']); ?>" placeholder="chocolate, fruit, creamy, sweet, happy, stressed, celebration">
                <div style="font-size:0.78rem; color:var(--color-secondary-text); margin-top:4px;">
                    Tags used to pair mood and taste in the AI Sommelier engine: <code>chocolate, fruit, creamy, sweet, light, comfort, celebration, romantic, energy, relaxed, cake, cookie, pudding</code>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Dessert Image (Upload File)</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <?php if (!empty($product['image'])): ?>
                        <div style="margin-top:8px; display:flex; align-items:center; gap:10px;">
                            <img src="../uploads/products/<?= e($product['image']); ?>" style="width:60px; height:60px; border-radius:6px; object-fit:cover;">
                            <span style="font-size:0.8rem; color:var(--color-secondary-text);">Current: <?= e($product['image']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:10px; margin-top:28px;">
                    <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" name="is_popular" value="1" <?= !empty($product['is_popular']) ? 'checked' : ''; ?>>
                        <strong>Feature in Popular Items Section on Home Page</strong>
                    </label>
                </div>
            </div>

            <hr style="margin:24px 0; border:none; border-top:1px solid var(--color-border);">

            <!-- Associated Ingredients -->
            <div class="form-group">
                <label class="form-label">🌿 Ingredients (Format: <code>English|Japanese</code> per line)</label>
                <textarea name="ingredients" rows="3" class="form-control" placeholder="Hokkaido Fresh Cream|北海道産生クリーム&#10;Organic Wheat Flour|国産小麦粉"><?= e($ingredientsText); ?></textarea>
            </div>

            <!-- Associated Allergies -->
            <div class="form-group">
                <label class="form-label">⚠️ Allergies (Format: <code>English|Japanese</code> per line)</label>
                <textarea name="allergies" rows="2" class="form-control" placeholder="Milk|乳&#10;Eggs|卵&#10;Wheat|小麦"><?= e($allergiesText); ?></textarea>
            </div>

            <!-- Associated Customizations -->
            <div class="form-group">
                <label class="form-label">✨ Customization Options (Format: <code>GroupEn|GroupJa|OptionEn|OptionJa|PriceExtraJPY|isDefault(1/0)</code> per line)</label>
                <textarea name="customizations" rows="4" class="form-control" placeholder="Size|サイズ|Petite (4-inch)|プチ (4号)|0|1&#10;Size|サイズ|Classic Medium (6-inch)|ミディアム (6号)|800|0&#10;Message Plaque|メッセージプレート|Happy Birthday|Happy Birthday|150|0"><?= e($customizationsText); ?></textarea>
            </div>

            <div style="margin-top:24px; display:flex; gap:14px;">
                <button type="submit" class="btn btn-primary btn-lg">💾 Save Dessert</button>
                <a href="products.php" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- Products List View -->
    <div class="admin-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="font-size:1.4rem;">🍰 Desserts Inventory (<?= count($allProducts); ?>)</h2>
                <p style="font-size:0.85rem; color:var(--color-secondary-text);">Manage artisan confections, stock levels, JPY pricing, and AI recommendation tags.</p>
            </div>
            <a href="products.php?action=new" class="btn btn-primary">+ Add New Dessert</a>
        </div>

        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Dessert</th>
                        <th>Category</th>
                        <th>Price (JPY)</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Popular</th>
                        <th>AI Tags</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allProducts as $p): ?>
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <img src="../uploads/products/<?= e($p['image']); ?>" style="width:48px; height:48px; border-radius:6px; object-fit:cover;" onerror="this.src='../assets/images/logo_banner.jpg'">
                                    <div>
                                        <strong><?= e($p['name_en']); ?></strong>
                                        <div style="font-size:0.8rem; color:var(--color-secondary-text);"><?= e($p['name_ja']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($p['category_name']); ?></td>
                            <td style="font-weight:700; color:var(--color-deep-plum);"><?= formatPrice($p['price']); ?></td>
                            <td>
                                <span class="badge <?= $p['stock'] <= 5 ? 'badge-limited' : 'badge-available'; ?>">
                                    <?= $p['stock']; ?> units
                                </span>
                            </td>
                            <td><?= renderAvailabilityBadge($p['availability']); ?></td>
                            <td><?= $p['is_popular'] ? '⭐ Yes' : '<span style="color:#aaa;">No</span>'; ?></td>
                            <td>
                                <span style="font-size:0.75rem; color:var(--color-secondary-text); max-width:140px; display:inline-block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= e($p['tags']); ?>">
                                    <?= e($p['tags']); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <a href="products.php?action=edit&id=<?= $p['id']; ?>" class="btn btn-secondary btn-sm" style="padding:4px 8px; font-size:0.75rem;">Edit</a>
                                    <a href="products.php?action=delete&id=<?= $p['id']; ?>&csrf_token=<?= getCsrfToken(); ?>" 
                                       class="btn btn-secondary btn-sm" 
                                       style="padding:4px 8px; font-size:0.75rem; color:var(--color-danger);"
                                       onclick="return confirm('Are you sure you want to delete this dessert?');">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
