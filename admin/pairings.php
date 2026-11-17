<?php
/**
 * Sweet Choice - Product Pairing & Inventory Discounts Administration
 * Allows administrators to manage bidirectional dessert <-> drink pairings,
 * global discount percentage, inventory thresholds, and view live stock eligibility.
 */

$adminTitle = 'Product Pairing & Inventory Discounts';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../includes/pairings.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Security token expired. Please refresh and try again.');
        header("Location: pairings.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. Update Global Settings
    if ($action === 'update_global_settings') {
        $threshold = max(0, (int)($_POST['pairing_inventory_threshold'] ?? 20));
        $percent = max(1, min(100, (int)($_POST['pairing_discount_percent'] ?? 10)));
        $enabled = !empty($_POST['pairing_discount_enabled']) ? '1' : '0';

        $upd = $pdo->prepare("INSERT INTO shop_settings (setting_key, setting_value, description) 
                              VALUES (?, ?, ?) 
                              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        $upd->execute(['pairing_inventory_threshold', (string)$threshold, 'Minimum stock threshold for recommended product to qualify for inventory discount']);
        $upd->execute(['pairing_discount_percent', (string)$percent, 'Default discount percentage for recommended product pairing (inventory-based)']);
        $upd->execute(['pairing_discount_enabled', (string)$enabled, 'Global master switch for pairing discounts (1 = enabled, 0 = disabled)']);

        setFlash('success', 'Global inventory discount settings saved successfully.');
        header("Location: pairings.php");
        exit;
    }

    // 2. Add / Pair Products
    if ($action === 'add_pairings') {
        $mainProductId = (int)($_POST['product_id'] ?? 0);
        $pairedIds = $_POST['paired_product_ids'] ?? [];
        $customPctRaw = trim($_POST['custom_discount_percent'] ?? '');
        $customPct = ($customPctRaw !== '' && is_numeric($customPctRaw)) ? max(0, min(100, (int)$customPctRaw)) : null;
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        if ($mainProductId <= 0 || empty($pairedIds) || !is_array($pairedIds)) {
            setFlash('danger', 'Please select a main product and at least one recommended product.');
            header("Location: pairings.php");
            exit;
        }

        $ins = $pdo->prepare("INSERT INTO product_pairings (product_id, paired_product_id, custom_discount_percent, is_active, sort_order)
                              VALUES (?, ?, ?, ?, ?)
                              ON DUPLICATE KEY UPDATE custom_discount_percent = VALUES(custom_discount_percent), is_active = VALUES(is_active)");

        $count = 0;
        $order = 1;
        foreach ($pairedIds as $pId) {
            $pId = (int)$pId;
            if ($pId > 0 && $pId !== $mainProductId) {
                $ins->execute([$mainProductId, $pId, $customPct, $isActive, $order++]);
                $count++;
            }
        }

        setFlash('success', "Successfully saved {$count} pairing(s) for the selected product.");
        header("Location: pairings.php?filter_product=" . $mainProductId);
        exit;
    }

    // 3. Edit Single Pairing
    if ($action === 'edit_pairing') {
        $pairingId = (int)($_POST['pairing_id'] ?? 0);
        $customPctRaw = trim($_POST['custom_discount_percent'] ?? '');
        $customPct = ($customPctRaw !== '' && is_numeric($customPctRaw)) ? max(0, min(100, (int)$customPctRaw)) : null;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        $upd = $pdo->prepare("UPDATE product_pairings SET custom_discount_percent = ?, sort_order = ?, is_active = ? WHERE id = ?");
        $upd->execute([$customPct, $sortOrder, $isActive, $pairingId]);

        setFlash('success', 'Pairing updated successfully.');
        header("Location: pairings.php" . (!empty($_POST['return_filter']) ? '?filter_product=' . (int)$_POST['return_filter'] : ''));
        exit;
    }

    // 4. Toggle Active State
    if ($action === 'toggle_active') {
        $pairingId = (int)($_POST['pairing_id'] ?? 0);
        $upd = $pdo->prepare("UPDATE product_pairings SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?");
        $upd->execute([$pairingId]);
        setFlash('info', 'Pairing status toggled.');
        header("Location: pairings.php" . (!empty($_POST['return_filter']) ? '?filter_product=' . (int)$_POST['return_filter'] : ''));
        exit;
    }

    // 5. Delete Pairing
    if ($action === 'delete_pairing') {
        $pairingId = (int)($_POST['pairing_id'] ?? 0);
        $del = $pdo->prepare("DELETE FROM product_pairings WHERE id = ?");
        $del->execute([$pairingId]);
        setFlash('info', 'Pairing removed successfully.');
        header("Location: pairings.php" . (!empty($_POST['return_filter']) ? '?filter_product=' . (int)$_POST['return_filter'] : ''));
        exit;
    }
}

// Fetch global settings
$globalSettings = getPairingSettings($pdo);

// Fetch all products categorized (for dropdowns and dynamic selection)
$allProductsStmt = $pdo->query("
    SELECT p.id, p.category_id, p.name_en, p.name_ja, p.price, p.stock, p.availability, p.image,
           c.slug AS category_slug, c.name_en AS category_name_en, c.name_ja AS category_name_ja
    FROM products p
    JOIN categories c ON p.category_id = c.id
    ORDER BY c.sort_order ASC, p.id ASC
");
$allProducts = $allProductsStmt->fetchAll(PDO::FETCH_ASSOC);

$dessertProducts = [];
$drinkProducts = [];
$productMap = [];

foreach ($allProducts as $p) {
    $productMap[$p['id']] = $p;
    if ($p['category_slug'] === 'drinks') {
        $drinkProducts[] = $p;
    } else {
        $dessertProducts[] = $p;
    }
}

// Filter parameter
$filterProduct = isset($_GET['filter_product']) && is_numeric($_GET['filter_product']) ? (int)$_GET['filter_product'] : 0;

// Fetch pairings list with live stock and status
$sql = "
    SELECT pp.*,
           p1.name_en AS main_name_en, p1.name_ja AS main_name_ja, p1.image AS main_image, p1.price AS main_price,
           c1.slug AS main_cat_slug, c1.name_en AS main_cat_name,
           p2.name_en AS paired_name_en, p2.name_ja AS paired_name_ja, p2.image AS paired_image, 
           p2.price AS paired_price, p2.stock AS paired_stock, p2.availability AS paired_avail,
           c2.slug AS paired_cat_slug, c2.name_en AS paired_cat_name
    FROM product_pairings pp
    JOIN products p1 ON pp.product_id = p1.id
    JOIN categories c1 ON p1.category_id = c1.id
    JOIN products p2 ON pp.paired_product_id = p2.id
    JOIN categories c2 ON p2.category_id = c2.id
    WHERE 1=1
";
$params = [];
if ($filterProduct > 0) {
    $sql .= " AND (pp.product_id = ? OR pp.paired_product_id = ?)";
    $params[] = $filterProduct;
    $params[] = $filterProduct;
}
$sql .= " ORDER BY p1.id ASC, pp.sort_order ASC, pp.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pairingsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Dashboard Stats
$totalPairings = count($pairingsList);
$activePairings = 0;
$eligibleDiscountsCount = 0;

foreach ($pairingsList as $pr) {
    if ((int)$pr['is_active'] === 1) {
        $activePairings++;
        if ($globalSettings['enabled'] && (int)$pr['paired_stock'] > $globalSettings['threshold'] && $pr['paired_avail'] !== 'sold_out') {
            $eligibleDiscountsCount++;
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; margin-bottom:24px;">
    <div>
        <h1 style="font-size:1.85rem; color:var(--color-deep-text); margin:0 0 6px 0;">
            🌸 Product Pairing & Inventory Discounts
        </h1>
        <p style="color:var(--color-secondary-text); margin:0; font-size:0.95rem;">
            Define bidirectional dessert &amp; drink pairings. Items with high inventory automatically qualify for promotional discounts.
        </p>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="#new-pairing-form" class="btn btn-primary">+ Create New Pairing</a>
        <a href="../public/category.php" target="_blank" class="btn btn-secondary">Preview Storefront &rarr;</a>
    </div>
</div>

<!-- Stat Cards -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom:26px;">
    <div class="stat-card">
        <div class="stat-label">Global Discount Switch</div>
        <div class="stat-val" style="font-size:1.6rem; color:<?= $globalSettings['enabled'] ? 'var(--color-success)' : 'var(--color-secondary-text)'; ?>;">
            <?= $globalSettings['enabled'] ? '✓ Active (' . $globalSettings['default_percent'] . '% OFF)' : '✕ Disabled'; ?>
        </div>
        <div style="font-size:0.8rem; color:var(--color-secondary-text);">Inventory-based promotional rule</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Min. Stock Threshold</div>
        <div class="stat-val" style="font-size:1.6rem;"><?= $globalSettings['threshold']; ?> units</div>
        <div style="font-size:0.8rem; color:var(--color-secondary-text);">Stock &gt; threshold qualifies for discount</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Active Pairing Rules</div>
        <div class="stat-val" style="font-size:1.6rem; color:var(--color-deep-plum);"><?= $activePairings; ?> / <?= $totalPairings; ?></div>
        <div style="font-size:0.8rem; color:var(--color-secondary-text);">Across desserts &amp; drinks</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Currently Qualifying (Live)</div>
        <div class="stat-val" style="font-size:1.6rem; color:#2E7D32;"><?= $eligibleDiscountsCount; ?> pairings</div>
        <div style="font-size:0.8rem; color:var(--color-success);">Current inventory exceeds threshold</div>
    </div>
</div>

<!-- Top Section: Global Settings Configuration -->
<div class="admin-card" style="margin-bottom:26px; border-left:4px solid var(--color-dusty-rose);">
    <h3 style="font-size:1.2rem; margin-bottom:14px; display:flex; align-items:center; gap:8px;">
        ⚙️ Global Inventory Discount Settings
    </h3>
    <form action="pairings.php" method="POST" style="display:flex; flex-wrap:wrap; gap:20px; align-items:flex-end;">
        <input type="hidden" name="action" value="update_global_settings">
        <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">

        <div class="form-group" style="margin-bottom:0; min-width:200px;">
            <label class="form-label" style="font-weight:600;">Minimum Inventory Threshold (Units)</label>
            <input type="number" name="pairing_inventory_threshold" class="form-control" min="0" max="1000" value="<?= $globalSettings['threshold']; ?>" required>
            <div style="font-size:0.75rem; color:var(--color-secondary-text); margin-top:4px;">Products with stock strictly greater than this receive discount.</div>
        </div>

        <div class="form-group" style="margin-bottom:0; min-width:200px;">
            <label class="form-label" style="font-weight:600;">Default Discount Percentage (%)</label>
            <div style="position:relative;">
                <input type="number" name="pairing_discount_percent" class="form-control" min="1" max="90" value="<?= $globalSettings['default_percent']; ?>" required style="padding-right:30px;">
                <span style="position:absolute; right:10px; top:50%; transform:translateY(-50%); color:var(--color-secondary-text); font-weight:bold;">%</span>
            </div>
            <div style="font-size:0.75rem; color:var(--color-secondary-text); margin-top:4px;">Applied to paired product when stock meets threshold.</div>
        </div>

        <div class="form-group" style="margin-bottom:0; display:flex; align-items:center; gap:8px; padding-bottom:8px;">
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:600;">
                <input type="checkbox" name="pairing_discount_enabled" value="1" <?= $globalSettings['enabled'] ? 'checked' : ''; ?> style="width:18px; height:18px;">
                Enable Inventory-Based Pairing Discounts
            </label>
        </div>

        <div>
            <button type="submit" class="btn btn-primary" style="padding:10px 22px;">Save Global Settings</button>
        </div>
    </form>
</div>

<!-- Add New Pairing Section -->
<div id="new-pairing-form" class="admin-card" style="margin-bottom:30px; background:#FAF7F6;">
    <h3 style="font-size:1.25rem; margin-bottom:6px;">✨ Create Product Pairing (At least 3 Opposites)</h3>
    <p style="font-size:0.88rem; color:var(--color-secondary-text); margin-bottom:18px;">
        Select a main product (dessert or drink). The system will automatically present opposite category options so you can pair at least 3 complementary items.
    </p>

    <form action="pairings.php" method="POST" id="pairForm">
        <input type="hidden" name="action" value="add_pairings">
        <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">

        <div style="display:grid; grid-template-columns:1fr 2fr; gap:24px; margin-bottom:20px;">
            <!-- Step 1: Select Main Product -->
            <div>
                <label class="form-label" style="font-weight:700;">Step 1: Select Main Product *</label>
                <select name="product_id" id="mainProductSelect" class="form-control" required style="font-size:0.92rem; padding:10px;" onchange="updateOppositeCategoryList();">
                    <option value="">-- Choose a Product --</option>
                    <optgroup label="Desserts (Cakes, Macarons, Cookies, etc.)">
                        <?php foreach ($dessertProducts as $dp): ?>
                            <option value="<?= $dp['id']; ?>" data-type="dessert" <?= $filterProduct === (int)$dp['id'] ? 'selected' : ''; ?>>
                                <?= e($dp['name_en']); ?> (<?= e($dp['category_name_en']); ?> - Stock: <?= $dp['stock']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Drinks (Cold Brew, Matcha Latte, etc.)">
                        <?php foreach ($drinkProducts as $dr): ?>
                            <option value="<?= $dr['id']; ?>" data-type="drink" <?= $filterProduct === (int)$dr['id'] ? 'selected' : ''; ?>>
                                <?= e($dr['name_en']); ?> (Drinks - Stock: <?= $dr['stock']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>

                <div style="margin-top:16px;">
                    <label class="form-label" style="font-weight:600;">Custom Discount % (Optional)</label>
                    <input type="number" name="custom_discount_percent" class="form-control" placeholder="Leave blank to use global <?= $globalSettings['default_percent']; ?>%" min="0" max="90">
                    <span style="font-size:0.75rem; color:var(--color-secondary-text);">Optional override for these pairings.</span>
                </div>

                <div style="margin-top:14px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" checked style="width:16px; height:16px;">
                        <span>Activate pairing immediately</span>
                    </label>
                </div>
            </div>

            <!-- Step 2: Select Opposite Products (At least 3 recommended) -->
            <div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <label class="form-label" style="font-weight:700; margin:0;" id="oppositeListLabel">
                        Step 2: Choose Recommended Complements (Select at least 3) *
                    </label>
                    <span id="selectedCountBadge" class="badge" style="background:#FAF0F2; color:var(--color-deep-plum);">0 Selected</span>
                </div>

                <div id="oppositeOptionsContainer" style="max-height:280px; overflow-y:auto; border:1px solid var(--color-border); border-radius:var(--radius-sm); padding:12px; background:#FFFFFF;">
                    <p style="color:var(--color-secondary-text); font-size:0.9rem; text-align:center; padding:30px 0;">
                        ← Please select a main product on the left first.
                    </p>
                </div>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:12px;">
            <button type="submit" class="btn btn-primary" id="savePairingBtn" style="padding:10px 24px;">
                Save Product Pairings
            </button>
        </div>
    </form>
</div>

<!-- Pairings Management Table -->
<div class="admin-card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom:18px;">
        <h3 style="font-size:1.25rem;">📋 Configured Pairings &amp; Real-Time Inventory Status</h3>

        <!-- Filter by Product -->
        <form method="GET" action="pairings.php" style="display:flex; gap:10px; align-items:center;">
            <select name="filter_product" class="form-control" style="width:auto; font-size:0.85rem;" onchange="this.form.submit();">
                <option value="0">Show All Products (<?= count($allProducts); ?>)</option>
                <?php foreach ($allProducts as $p): ?>
                    <option value="<?= $p['id']; ?>" <?= $filterProduct === (int)$p['id'] ? 'selected' : ''; ?>>
                        <?= e($p['name_en']); ?> (<?= e($p['category_name_en']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($filterProduct > 0): ?>
                <a href="pairings.php" class="btn btn-secondary btn-sm">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Main Product</th>
                    <th>Recommended Pairing</th>
                    <th style="text-align:center;">Current Stock</th>
                    <th>Discount Eligibility (Real-Time)</th>
                    <th style="text-align:center;">Discount Rule</th>
                    <th style="text-align:center;">Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pairingsList)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding:40px; color:var(--color-secondary-text);">
                            No pairings found for this selection. Create pairings using the form above!
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pairingsList as $pr): 
                        $stock = (int)$pr['paired_stock'];
                        $isSoldOut = ($pr['paired_avail'] === 'sold_out' || $stock <= 0);
                        $isEligible = ($globalSettings['enabled'] && (int)$pr['is_active'] === 1 && $stock > $globalSettings['threshold'] && !$isSoldOut);
                        $discPct = $pr['custom_discount_percent'] !== null ? (int)$pr['custom_discount_percent'] : $globalSettings['default_percent'];
                        $discPrice = max(0, (int)$pr['paired_price'] - (int)round((int)$pr['paired_price'] * ($discPct / 100)));
                    ?>
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <img src="../uploads/products/<?= e($pr['main_image']); ?>" onerror="this.src='../assets/images/logo_banner.jpg'" style="width:42px; height:42px; object-fit:cover; border-radius:6px; border:1px solid #EAE0E0;">
                                    <div>
                                        <div style="font-weight:600; font-size:0.9rem;"><?= e($pr['main_name_en']); ?></div>
                                        <span class="badge <?= $pr['main_cat_slug'] === 'drinks' ? 'badge-limited' : 'badge-available'; ?>" style="font-size:0.72rem; padding:1px 6px;">
                                            <?= e($pr['main_cat_name']); ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <img src="../uploads/products/<?= e($pr['paired_image']); ?>" onerror="this.src='../assets/images/logo_banner.jpg'" style="width:42px; height:42px; object-fit:cover; border-radius:6px; border:1px solid #EAE0E0;">
                                    <div>
                                        <div style="font-weight:600; font-size:0.9rem;"><?= e($pr['paired_name_en']); ?></div>
                                        <span class="badge <?= $pr['paired_cat_slug'] === 'drinks' ? 'badge-limited' : 'badge-available'; ?>" style="font-size:0.72rem; padding:1px 6px;">
                                            <?= e($pr['paired_cat_name']); ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align:center;">
                                <strong style="font-size:1.05rem; color:<?= $stock <= 5 ? '#C53030' : ($stock <= 20 ? '#B7791F' : '#22543D'); ?>;">
                                    <?= $stock; ?>
                                </strong>
                                <div style="font-size:0.72rem; color:var(--color-secondary-text);">units</div>
                            </td>
                            <td>
                                <?php if ($isSoldOut): ?>
                                    <span class="badge" style="background:#FDE8E8; color:#9B1C1C; font-size:0.75rem;">
                                        ✕ Out of Stock (Omitted)
                                    </span>
                                <?php elseif ($isEligible): ?>
                                    <span class="badge" style="background:#EBF5EA; color:#2E7D32; font-weight:700; font-size:0.78rem;">
                                        ✓ <?= $discPct; ?>% OFF (Stock: <?= $stock; ?> &gt; <?= $globalSettings['threshold']; ?>)
                                    </span>
                                    <div style="font-size:0.78rem; margin-top:3px;">
                                        <del style="color:#888;"><?= formatPrice($pr['paired_price']); ?></del>
                                        <strong style="color:#D6336C;"><?= formatPrice($discPrice); ?></strong>
                                    </div>
                                <?php else: ?>
                                    <span class="badge" style="background:#FFF8F0; color:#B7791F; font-size:0.75rem;">
                                        Regular Price (Stock <?= $stock; ?> &le; <?= $globalSettings['threshold']; ?>)
                                    </span>
                                    <div style="font-size:0.78rem; color:var(--color-secondary-text); margin-top:3px;">
                                        Price: <?= formatPrice($pr['paired_price']); ?> (No discount)
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($pr['custom_discount_percent'] !== null): ?>
                                    <span class="badge" style="background:#EDE9FE; color:#5B21B6; font-size:0.75rem;">
                                        Custom (<?= (int)$pr['custom_discount_percent']; ?>%)
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background:#F3F4F6; color:#4B5563; font-size:0.75rem;">
                                        Default (<?= $globalSettings['default_percent']; ?>%)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <form action="pairings.php" method="POST" style="margin:0; display:inline;">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
                                    <input type="hidden" name="pairing_id" value="<?= $pr['id']; ?>">
                                    <input type="hidden" name="return_filter" value="<?= $filterProduct; ?>">
                                    <button type="submit" class="badge <?= (int)$pr['is_active'] === 1 ? 'badge-available' : 'badge-sold-out'; ?>" style="cursor:pointer; border:none; padding:4px 8px;" title="Click to toggle active state">
                                        <?= (int)$pr['is_active'] === 1 ? 'Active' : 'Disabled'; ?>
                                    </button>
                                </form>
                            </td>
                            <td style="text-align:right;">
                                <button type="button" class="btn btn-secondary btn-sm" style="padding:4px 8px; font-size:0.78rem;" 
                                        onclick="openEditPairingModal(<?= $pr['id']; ?>, '<?= e(addslashes($pr['main_name_en'])); ?>', '<?= e(addslashes($pr['paired_name_en'])); ?>', '<?= $pr['custom_discount_percent'] ?? ''; ?>', <?= (int)$pr['sort_order']; ?>, <?= (int)$pr['is_active']; ?>);">
                                    Edit
                                </button>
                                <form action="pairings.php" method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Delete pairing between <?= e(addslashes($pr['main_name_en'])); ?> and <?= e(addslashes($pr['paired_name_en'])); ?>?');">
                                    <input type="hidden" name="action" value="delete_pairing">
                                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
                                    <input type="hidden" name="pairing_id" value="<?= $pr['id']; ?>">
                                    <input type="hidden" name="return_filter" value="<?= $filterProduct; ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm" style="padding:4px 8px; font-size:0.78rem; color:#C53030;" title="Delete">✕</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Pairing Modal -->
<div id="editPairingModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#FFF; border-radius:var(--radius-md); max-width:480px; width:90%; padding:24px; box-shadow:var(--shadow-hover); position:relative;">
        <h3 style="font-size:1.2rem; margin-bottom:14px;">Edit Product Pairing</h3>
        <p id="editPairingDescription" style="font-size:0.9rem; color:var(--color-secondary-text); margin-bottom:16px;"></p>
        
        <form action="pairings.php" method="POST">
            <input type="hidden" name="action" value="edit_pairing">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken(); ?>">
            <input type="hidden" name="pairing_id" id="editPairingId" value="0">
            <input type="hidden" name="return_filter" value="<?= $filterProduct; ?>">

            <div class="form-group">
                <label class="form-label">Custom Discount % (leave blank to inherit global <?= $globalSettings['default_percent']; ?>%)</label>
                <input type="number" name="custom_discount_percent" id="editCustomDiscount" class="form-control" min="0" max="90" placeholder="e.g. 15">
            </div>

            <div class="form-group">
                <label class="form-label">Display Sort Order</label>
                <input type="number" name="sort_order" id="editSortOrder" class="form-control" min="0" max="100">
            </div>

            <div class="form-group">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="is_active" id="editIsActive" value="1" style="width:16px; height:16px;">
                    <span>Active pairing rule</span>
                </label>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeEditPairingModal();">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// JSON catalogs of desserts and drinks
const dessertsCatalog = <?= json_encode($dessertProducts); ?>;
const drinksCatalog = <?= json_encode($drinkProducts); ?>;
const globalThreshold = <?= $globalSettings['threshold']; ?>;
const globalDefaultPct = <?= $globalSettings['default_percent']; ?>;

function updateOppositeCategoryList() {
    const select = document.getElementById('mainProductSelect');
    const container = document.getElementById('oppositeOptionsContainer');
    const label = document.getElementById('oppositeListLabel');
    const badge = document.getElementById('selectedCountBadge');

    const selectedOption = select.options[select.selectedIndex];
    if (!selectedOption || !selectedOption.value) {
        container.innerHTML = '<p style="color:var(--color-secondary-text); font-size:0.9rem; text-align:center; padding:30px 0;">← Please select a main product on the left first.</p>';
        badge.textContent = '0 Selected';
        return;
    }

    const type = selectedOption.getAttribute('data-type');
    const targetCatalog = (type === 'dessert') ? drinksCatalog : dessertsCatalog;
    const targetCategoryTitle = (type === 'dessert') ? 'Recommended Drinks' : 'Recommended Desserts';

    label.textContent = `Step 2: Choose ${targetCategoryTitle} (Select at least 3) *`;

    let html = '<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">';
    targetCatalog.forEach(p => {
        const isEligible = (parseInt(p.stock) > globalThreshold && p.availability !== 'sold_out');
        const badgeHtml = isEligible 
            ? `<span style="background:#EBF5EA; color:#2E7D32; font-size:0.7rem; font-weight:700; padding:1px 5px; border-radius:3px;">${globalDefaultPct}% OFF (Stock: ${p.stock})</span>`
            : `<span style="background:#FFF8F0; color:#B7791F; font-size:0.7rem; padding:1px 5px; border-radius:3px;">Regular (Stock: ${p.stock})</span>`;

        html += `
            <label style="display:flex; align-items:center; gap:10px; padding:8px 10px; border:1px solid #F0E6E6; border-radius:6px; cursor:pointer; background:#FFFDFD; transition:all 0.15s;" onmouseover="this.style.borderColor='var(--color-dusty-rose)'" onmouseout="this.style.borderColor='#F0E6E6'">
                <input type="checkbox" name="paired_product_ids[]" value="${p.id}" class="pair-checkbox" onchange="updateSelectedCount();" style="width:16px; height:16px;">
                <img src="../uploads/products/${p.image}" onerror="this.src='../assets/images/logo_banner.jpg'" style="width:36px; height:36px; object-fit:cover; border-radius:4px;">
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:600; font-size:0.85rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${p.name_en}</div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:2px;">
                        <span style="font-size:0.8rem; font-weight:700; color:var(--color-deep-plum);">¥${Number(p.price).toLocaleString()}</span>
                        ${badgeHtml}
                    </div>
                </div>
            </label>
        `;
    });
    html += '</div>';

    container.innerHTML = html;
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.pair-checkbox:checked').length;
    const badge = document.getElementById('selectedCountBadge');
    badge.textContent = checked + ' Selected';
    if (checked >= 3) {
        badge.style.background = '#EBF5EA';
        badge.style.color = '#2E7D32';
    } else {
        badge.style.background = '#FAF0F2';
        badge.style.color = '#C44569';
    }
}

function openEditPairingModal(id, mainName, pairedName, customPct, sortOrder, isActive) {
    document.getElementById('editPairingId').value = id;
    document.getElementById('editPairingDescription').textContent = `${mainName} ➔ ${pairedName}`;
    document.getElementById('editCustomDiscount').value = customPct;
    document.getElementById('editSortOrder').value = sortOrder;
    document.getElementById('editIsActive').checked = (isActive === 1);
    document.getElementById('editPairingModal').style.display = 'flex';
}

function closeEditPairingModal() {
    document.getElementById('editPairingModal').style.display = 'none';
}

// Initial populate if filter was set
window.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('mainProductSelect');
    if (select.value) {
        updateOppositeCategoryList();
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
