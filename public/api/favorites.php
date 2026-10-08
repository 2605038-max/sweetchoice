<?php
/**
 * Sweet Choice - Favorites API Endpoint
 * Handles asynchronous favorite toggling
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$productId = (int)($_REQUEST['product_id'] ?? 0);
$userId = $_SESSION['user_id'] ?? null;

if ($productId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid product ID']);
    exit;
}

$isFavorited = false;

if ($userId) {
    // Check if already in favorites table
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);
    $fav = $stmt->fetch();

    if ($fav) {
        $del = $pdo->prepare("DELETE FROM favorites WHERE id = ?");
        $del->execute([$fav['id']]);
        $isFavorited = false;
    } else {
        $ins = $pdo->prepare("INSERT INTO favorites (user_id, product_id, created_at) VALUES (?, ?, NOW())");
        $ins->execute([$userId, $productId]);
        $isFavorited = true;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $totalCount = (int)$countStmt->fetchColumn();
} else {
    // Guest session favorites
    if (!isset($_SESSION['guest_favorites'])) {
        $_SESSION['guest_favorites'] = [];
    }
    $index = array_search($productId, $_SESSION['guest_favorites']);
    if ($index !== false) {
        unset($_SESSION['guest_favorites'][$index]);
        $_SESSION['guest_favorites'] = array_values($_SESSION['guest_favorites']);
        $isFavorited = false;
    } else {
        $_SESSION['guest_favorites'][] = $productId;
        $isFavorited = true;
    }
    $totalCount = count($_SESSION['guest_favorites']);
}

echo json_encode([
    'success' => true,
    'product_id' => $productId,
    'is_favorited' => $isFavorited,
    'total_count' => $totalCount
]);
