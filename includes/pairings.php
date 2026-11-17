<?php
/**
 * Sweet Choice - Product Pairing & Inventory-Based Discount System
 * Handles bidirectional dessert <-> drink pairings, dynamic inventory threshold discounts,
 * and server-side cart & checkout verification.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Retrieve pairing discount configuration settings
 */
function getPairingSettings(PDO $pdo): array {
    $enabled = getShopSetting($pdo, 'pairing_discount_enabled', '1');
    $threshold = (int)getShopSetting($pdo, 'pairing_inventory_threshold', '20');
    $defaultPercent = (int)getShopSetting($pdo, 'pairing_discount_percent', '10');

    return [
        'enabled' => ($enabled === '1' || $enabled === 'true' || $enabled === 1),
        'threshold' => max(0, $threshold),
        'default_percent' => max(1, min(100, $defaultPercent))
    ];
}

/**
 * Check if a product is a drink based on category slug
 */
function isDrinkProduct(PDO $pdo, int $productId): bool {
    static $cache = [];
    if (isset($cache[$productId])) {
        return $cache[$productId];
    }
    $stmt = $pdo->prepare("SELECT c.slug FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
    $stmt->execute([$productId]);
    $slug = $stmt->fetchColumn();
    $isDrink = ($slug === 'drinks');
    $cache[$productId] = $isDrink;
    return $isDrink;
}

/**
 * Calculate dynamic inventory discount for a given product
 */
function calculateInventoryDiscount(int $originalPrice, int $stock, array $settings, ?int $customDiscount = null): array {
    $isEligible = ($settings['enabled'] && $stock > $settings['threshold']);
    
    if ($isEligible) {
        $percent = ($customDiscount !== null && $customDiscount >= 0) ? $customDiscount : $settings['default_percent'];
    } else {
        $percent = 0;
    }

    $discountAmount = (int)round($originalPrice * ($percent / 100));
    $finalPrice = max(0, $originalPrice - $discountAmount);

    return [
        'is_eligible' => ($isEligible && $percent > 0),
        'discount_percent' => $percent,
        'discount_amount' => $discountAmount,
        'original_price' => $originalPrice,
        'final_price' => $finalPrice
    ];
}

/**
 * Fetch top recommendations for category-level browsing.
 * If browsing drinks -> recommend top desserts.
 * If browsing desserts -> recommend top drinks.
 */
function getCategoryPairings(PDO $pdo, bool $isDrinkCategory, int $limit = 4): array {
    $settings = getPairingSettings($pdo);

    if ($isDrinkCategory) {
        // Recommend desserts
        $sql = "
            SELECT p.*, c.slug AS category_slug, c.name_en AS category_name_en, c.name_ja AS category_name_ja
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE c.slug != 'drinks'
            AND p.availability != 'sold_out'
            AND p.stock > 0
            ORDER BY p.is_popular DESC, p.stock DESC, p.id ASC
            LIMIT ?
        ";
    } else {
        // Recommend drinks
        $sql = "
            SELECT p.*, c.slug AS category_slug, c.name_en AS category_name_en, c.name_ja AS category_name_ja
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE c.slug = 'drinks'
            AND p.availability != 'sold_out'
            AND p.stock > 0
            ORDER BY p.is_popular DESC, p.stock DESC, p.id ASC
            LIMIT ?
        ";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $disc = calculateInventoryDiscount((int)$item['price'], (int)$item['stock'], $settings);
        $item['is_eligible_discount'] = $disc['is_eligible'];
        $item['discount_percent'] = $disc['discount_percent'];
        $item['discount_amount'] = $disc['discount_amount'];
        $item['original_price'] = $disc['original_price'];
        $item['discounted_price'] = $disc['final_price'];
    }
    unset($item);

    return $items;
}

/**
 * Returns at least 3 recommended products from the opposite category.
 * If dessert -> returns recommended drinks.
 * If drink -> returns recommended desserts.
 */
function getProductPairings(PDO $pdo, int $productId, int $limit = 4): array {
    $settings = getPairingSettings($pdo);
    $isDrink = isDrinkProduct($pdo, $productId);

    // Fetch administrator-defined pairings first
    $stmt = $pdo->prepare("
        SELECT pp.id AS pairing_id, pp.custom_discount_percent, pp.sort_order,
            p.id, p.category_id, p.name_en, p.name_ja, p.description_en, p.description_ja,
            p.price, p.image, p.stock, p.availability, p.is_popular,
            c.slug AS category_slug, c.name_en AS category_name_en, c.name_ja AS category_name_ja
        FROM product_pairings pp
        JOIN products p ON pp.paired_product_id = p.id
        JOIN categories c ON p.category_id = c.id
        WHERE pp.product_id = ? 
        AND pp.is_active = 1 
        AND p.availability != 'sold_out' 
        AND p.stock > 0
        ORDER BY pp.sort_order ASC, p.is_popular DESC, p.stock DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $productId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $pairings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fallback: If less than 3 pairings, find top products from opposite category
    if (count($pairings) < 3) {
        $existingIds = array_column($pairings, 'id');
        $existingIds[] = $productId; // Avoid recommending itself
        $placeholders = implode(',', array_fill(0, count($existingIds), '?'));
        $needed = 3 - count($pairings);

        if ($isDrink) {
            // Main is drink -> find desserts (category_id != 8)
            $fallbackSql = "
                SELECT NULL AS pairing_id, NULL AS custom_discount_percent, 99 AS sort_order,
                    p.id, p.category_id, p.name_en, p.name_ja, p.description_en, p.description_ja,
                    p.price, p.image, p.stock, p.availability, p.is_popular,
                    c.slug AS category_slug, c.name_en AS category_name_en, c.name_ja AS category_name_ja
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE c.slug != 'drinks'
                AND p.id NOT IN ($placeholders)
                AND p.availability != 'sold_out'
                AND p.stock > 0
                ORDER BY p.is_popular DESC, p.stock DESC, p.id ASC
                LIMIT $needed
            ";
        } else {
            // Main is dessert -> find drinks (category_id = 8)
            $fallbackSql = "
                SELECT NULL AS pairing_id, NULL AS custom_discount_percent, 99 AS sort_order,
                    p.id, p.category_id, p.name_en, p.name_ja, p.description_en, p.description_ja,
                    p.price, p.image, p.stock, p.availability, p.is_popular,
                    c.slug AS category_slug, c.name_en AS category_name_en, c.name_ja AS category_name_ja
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE c.slug = 'drinks'
                AND p.id NOT IN ($placeholders)
                AND p.availability != 'sold_out'
                AND p.stock > 0
                ORDER BY p.is_popular DESC, p.stock DESC, p.id ASC
                LIMIT $needed
            ";
        }

        $fallbackStmt = $pdo->prepare($fallbackSql);
        $fallbackStmt->execute($existingIds);
        $fallbackItems = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);

        $pairings = array_merge($pairings, $fallbackItems);
    }

    // Compute live inventory discount for each recommended product
    foreach ($pairings as &$item) {
        $customPct = isset($item['custom_discount_percent']) && $item['custom_discount_percent'] !== null 
            ? (int)$item['custom_discount_percent'] 
            : null;
        $disc = calculateInventoryDiscount((int)$item['price'], (int)$item['stock'], $settings, $customPct);

        $item['is_eligible_discount'] = $disc['is_eligible'];
        $item['discount_percent'] = $disc['discount_percent'];
        $item['discount_amount'] = $disc['discount_amount'];
        $item['original_price'] = $disc['original_price'];
        $item['discounted_price'] = $disc['final_price'];
    }
    unset($item);

    return $pairings;
}

/**
 * Server-side validation of pairing and discount eligibility when adding to cart
 */
function validatePairingEligibility(PDO $pdo, int $mainProductId, int $pairedProductId): array {
    $settings = getPairingSettings($pdo);

    // Verify paired product
    $stmt = $pdo->prepare("SELECT p.*, c.slug as category_slug FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
    $stmt->execute([$pairedProductId]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prod || $prod['availability'] === 'sold_out' || (int)$prod['stock'] <= 0) {
        return [
            'valid' => false,
            'reason' => 'Product unavailable or out of stock'
        ];
    }

    // Verify pairing relationship
    $pairStmt = $pdo->prepare("SELECT custom_discount_percent, is_active FROM product_pairings WHERE product_id = ? AND paired_product_id = ?");
    $pairStmt->execute([$mainProductId, $pairedProductId]);
    $pairing = $pairStmt->fetch(PDO::FETCH_ASSOC);

    // If no explicit pairing, check reverse pairing or opposite category eligibility
    $customPct = null;
    $isPaired = false;
    if ($pairing && (int)$pairing['is_active'] === 1) {
        $isPaired = true;
        if ($pairing['custom_discount_percent'] !== null) {
            $customPct = (int)$pairing['custom_discount_percent'];
        }
    } else {
        // Check reverse
        $revStmt = $pdo->prepare("SELECT custom_discount_percent, is_active FROM product_pairings WHERE product_id = ? AND paired_product_id = ?");
        $revStmt->execute([$pairedProductId, $mainProductId]);
        $revPair = $revStmt->fetch(PDO::FETCH_ASSOC);
        if ($revPair && (int)$revPair['is_active'] === 1) {
            $isPaired = true;
            if ($revPair['custom_discount_percent'] !== null) {
                $customPct = (int)$revPair['custom_discount_percent'];
            }
        }
    }

    // If pairing is valid or opposite category, check discount eligibility
    $disc = calculateInventoryDiscount((int)$prod['price'], (int)$prod['stock'], $settings, $customPct);

    return [
        'valid' => true,
        'product' => $prod,
        'is_paired' => $isPaired,
        'is_eligible' => $disc['is_eligible'],
        'discount_percent' => $disc['discount_percent'],
        'discount_amount' => $disc['discount_amount'],
        'original_price' => $disc['original_price'],
        'final_price' => $disc['final_price']
    ];
}

/**
 * Revalidate all paired discounts in active cart against real-time database inventory
 */
function revalidateCartPairingDiscounts(PDO $pdo, int $cartId): void {
    $settings = getPairingSettings($pdo);

    $stmt = $pdo->prepare("
        SELECT ci.id, ci.product_id, ci.pairing_product_id, ci.discount_percent, ci.discount_amount,
            p.price, p.stock, p.availability
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        WHERE ci.cart_id = ? AND ci.pairing_product_id IS NOT NULL
    ");
    $stmt->execute([$cartId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updateStmt = $pdo->prepare("UPDATE cart_items SET discount_percent = ?, discount_amount = ? WHERE id = ?");

    foreach ($items as $item) {
        $stock = (int)$item['stock'];
        $price = (int)$item['price'];
        $isAvail = ($item['availability'] !== 'sold_out' && $stock > 0);

        if (!$isAvail || !$settings['enabled'] || $stock <= $settings['threshold']) {
            // Stock dropped below threshold or disabled -> revert to regular price
            if ((int)$item['discount_percent'] !== 0 || (int)$item['discount_amount'] !== 0) {
                $updateStmt->execute([0, 0, $item['id']]);
            }
        } else {
            // Eligible: recalculate discount amount to ensure 100% accuracy
            $pct = (int)$item['discount_percent'] > 0 ? (int)$item['discount_percent'] : $settings['default_percent'];
            $amt = (int)round($price * ($pct / 100));
            if ($pct !== (int)$item['discount_percent'] || $amt !== (int)$item['discount_amount']) {
                $updateStmt->execute([$pct, $amt, $item['id']]);
            }
        }
    }
}
