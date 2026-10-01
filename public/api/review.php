<?php
/**
 * Sweet Choice - Review Submission Handler
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Security validation failed.');
    header("Location: ../category.php");
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    setFlash('warning', 'Please log in to submit a review.');
    header("Location: ../login.php");
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 5);
$comment = trim($_POST['comment'] ?? '');

if ($productId <= 0 || $rating < 1 || $rating > 5 || empty($comment)) {
    setFlash('danger', 'Please provide a valid rating and review comment.');
    header("Location: ../product.php?id=" . $productId);
    exit;
}

// Verify that the user actually purchased this product in a completed/delivered order
$orderStmt = $pdo->prepare("SELECT o.id FROM orders o 
                            JOIN order_items oi ON o.id = oi.order_id 
                            WHERE o.user_id = ? AND oi.product_id = ? AND o.status IN ('Completed', 'Delivered') 
                            LIMIT 1");
$orderStmt->execute([$userId, $productId]);
$order = $orderStmt->fetch();

if (!$order) {
    setFlash('danger', 'Only customers who have ordered and received this dessert can submit a review.');
    header("Location: ../product.php?id=" . $productId);
    exit;
}

$ins = $pdo->prepare("INSERT INTO reviews (product_id, user_id, order_id, rating, comment, is_approved, created_at) 
                      VALUES (?, ?, ?, ?, ?, 1, NOW())");
$ins->execute([$productId, $userId, $order['id'], $rating, $comment]);

setFlash('success', isJapanese() ? 'レビューを投稿しました。貴重なご意見ありがとうございます！' : 'Thank you! Your review has been submitted.');
header("Location: ../product.php?id=" . $productId);
exit;
