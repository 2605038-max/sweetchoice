<?php
/**
 * Sweet Choice - Legacy Post-Order Mini-Cake Campaign API (Disabled)
 *
 * The legacy post-order free-mini-cake reward campaign has been disabled so it
 * cannot stack with the new checkout ¥200 mini-game discount.
 * Historical rows in `game_campaign_attempts` and `orders` are preserved untouched.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/language.php';

echo json_encode([
    'success' => false,
    'legacy_disabled' => true,
    'is_eligible' => false,
    'reward_claimed' => false,
    'remaining_attempts' => 0,
    'message' => __('minigame_legacy_disabled', 'The post-order free mini-cake campaign has ended. Mini-game ¥200 discounts are now available during checkout on orders with ¥3,000+ in products.')
], JSON_UNESCAPED_UNICODE);
exit;
