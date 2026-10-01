<?php
/**
 * Sweet Choice - AI Recommendation API Endpoint
 * Returns JSON recommended desserts based on mood and taste query parameters.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/language.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/recommendation.php';

$pdo = getDB();

$mood = trim($_GET['mood'] ?? 'happy');
$taste = trim($_GET['taste'] ?? 'cake');

try {
    $recommendations = getAIDessertRecommendations($pdo, $mood, $taste, 3);
    $output = [];
    $isJa = isJapanese();

    foreach ($recommendations as $item) {
        $output[] = [
            'id' => (int)$item['id'],
            'name' => getLocalized($item, 'name'),
            'category_name' => getLocalized($item, 'category_name'),
            'description' => getLocalized($item, 'description'),
            'price' => (int)$item['price'],
            'image' => $item['image'],
            'availability' => $item['availability'],
            'stock' => (int)$item['stock'],
            'recommendation_reason' => $isJa ? $item['recommendation_reason_ja'] : $item['recommendation_reason_en']
        ];
    }

    echo json_encode([
        'success' => true,
        'mood' => $mood,
        'taste' => $taste,
        'recommendations' => $output
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Recommendation engine encountered an issue.'
    ]);
}
