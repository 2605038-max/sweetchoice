<?php
/**
 * Sweet Choice - Checkout Mini-Game Stage (Sweet Run)
 *
 * Embeds the existing 3D Sweet Run game inside the checkout flow.
 * - Requires authenticated user + active cart with eligible product subtotal >= ¥3,000.
 * - Automatically skips to checkout.php if subtotal < ¥3,000.
 * - Enforces 1 Free Attempt + up to 2 Demo Rewarded Ad Attempts (max 3 attempts total).
 * - Score strictly > 2,000 awards a ¥200 discount (max ¥200 per order).
 * - Fully bilingual (JA / EN) and preserves selected language without resetting state.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/checkout.php';

$pdo = getDB();

if (!isLoggedIn()) {
    $_SESSION['intended_redirect'] = 'checkout-start.php';
    header('Location: login.php?from=checkout');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$cartId = getOrCreateCartId($pdo);
$totals = calculateCartTotals($pdo, $cartId);

if (empty($totals['items'])) {
    header('Location: cart.php');
    exit;
}

$checkoutSession = getOrCreateCheckoutSession($pdo, $userId, $cartId, $totals);

// Server-side guard: if no longer eligible (< ¥3,000 product subtotal), auto-skip to payment settings
if (!$totals['is_minigame_eligible']) {
    if (!empty($checkoutSession['discount_just_voided'])) {
        setFlash('info', __('checkout_discount_voided', 'Your cart subtotal dropped below ¥3,000, so the mini-game discount was removed.'));
    } else {
        setFlash('info', __('minigame_not_qualified_msg', 'Your order does not meet the ¥3,000 minimum purchase requirement. Proceeding directly to payment settings.'));
    }
    header('Location: checkout.php');
    exit;
}

$gameState = getCheckoutGameState($pdo, $checkoutSession, $totals);
$currentLang = getCurrentLang();
$csrfToken = getCsrfToken();
$gameIframeUrl = 'game/index.html?mode=checkout&lang=' . rawurlencode($currentLang);
$showStageInitially = !empty($gameState['active_attempt']);

// Localized JS strings passed safely via data attribute
$jsI18n = [
    'start_free' => __('minigame_start_free', 'Start Free Attempt (1/3)'),
    'start_unlocked' => __('minigame_start_unlocked', 'Play Unlocked Attempt (%d/3)'),
    'watch_ad_btn' => __('ad_watch_btn', 'Watch an advertisement to play again'),
    'continue_payment' => __('minigame_continue_payment', 'Continue to Payment Settings'),
    'round_in_progress' => __('minigame_round_in_progress', 'Attempt %d in progress — Good luck!'),
    'all_attempts_used' => __('minigame_all_attempts_used', 'You have used all 3 attempts for this order.'),
    'already_won_continue' => __('minigame_already_won_continue', 'Your ¥200 discount is locked in!'),
    'discount_earned_badge' => __('minigame_discount_earned_badge', '¥200 Discount Earned!'),
    'discount_not_earned' => __('minigame_discount_not_earned', 'Not earned yet'),
    'ad_countdown_label' => __('ad_countdown_label', 'Reward unlocks in %d seconds...'),
    'ad_unlocking' => __('ad_unlocking', 'Verifying demo ad completion with server...'),
    'ad_unlocked_success' => __('ad_unlocked_success', 'Advertisement complete! 1 additional attempt has been unlocked.'),
    'attempt_free' => __('minigame_attempt_free', 'Free Attempt'),
    'attempt_ad' => __('minigame_attempt_ad', 'Ad-Unlocked Attempt'),
    'won_tag' => __('minigame_won_tag', 'WIN (+¥200 OFF)'),
    'no_attempts_yet' => __('minigame_no_attempts_yet', 'No attempts played yet. Start your free attempt!'),
    'score_rejected' => __('minigame_score_rejected', 'This score could not be verified by the server.'),
    'unexpected_err' => __('checkout_err_unexpected', 'An unexpected error occurred.'),
];

$pageTitle = __('minigame_title', 'Sweet Run Mini-Game Challenge');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container checkout-page-wrap">
    <div id="checkout-game-root"
         data-csrf="<?= e($csrfToken); ?>"
         data-state="<?= e(json_encode($gameState, JSON_UNESCAPED_UNICODE)); ?>"
         data-i18n="<?= e(json_encode($jsI18n, JSON_UNESCAPED_UNICODE)); ?>">

        <!-- 1. Promotional Invitation & Status Panel -->
        <section class="mg-invite-card">
            <div class="mg-invite-top">
                <div class="mg-invite-heading">
                    <span class="section-tag section-tag-natural">🎮 <?= e(__('minigame_badge', 'CHECKOUT BONUS GAME')); ?></span>
                    <h1 class="section-title checkout-page-title"><?= e(__('minigame_title', 'Sweet Run Mini-Game Challenge')); ?></h1>
                    <p class="mg-invite-subtitle">
                        🎉 <strong><?= e(__('minigame_qualified_msg', 'Your order qualifies for the mini-game!')); ?></strong>
                        <span class="mg-subtotal-pill">
                            <?= e(__('checkout_eligible_subtotal', 'Eligible Product Subtotal')); ?>:
                            <strong><?= formatPrice($totals['eligible_product_subtotal']); ?></strong>
                        </span>
                    </p>
                </div>
                <div class="checkout-header-actions">
                    <a href="cart.php" class="btn btn-secondary btn-sm">&larr; <?= e(__('checkout_back_to_cart', 'Back to Cart')); ?></a>
                    <button type="button" id="btn-skip-to-payment" class="btn btn-secondary btn-sm">
                        <?= e(__('minigame_skip_to_payment', 'Skip Mini-Game & Go to Payment')); ?> &rarr;
                    </button>
                </div>
            </div>

            <!-- 3 Organized Metric Cards -->
            <div class="mg-metrics-row">
                <div id="mg-discount-box" class="mg-metric-card mg-discount-card <?= $gameState['discount_amount'] > 0 ? 'is-earned' : ''; ?>">
                    <span class="mg-metric-label">🏆 <?= e(__('minigame_discount_status', 'Mini-Game Discount')); ?></span>
                    <strong class="mg-metric-value" id="mg-discount-value">
                        <?= $gameState['discount_amount'] > 0 ? '-' . formatPrice($gameState['discount_amount']) : formatPrice(0); ?>
                    </strong>
                    <span class="mg-metric-sub" id="mg-discount-label">
                        <?= $gameState['discount_amount'] > 0
                            ? e(__('minigame_discount_earned_badge', '¥200 Discount Earned!'))
                            : e(__('minigame_discount_not_earned', 'Not earned yet')); ?>
                    </span>
                </div>

                <div class="mg-metric-card">
                    <span class="mg-metric-label">🎯 <?= e(__('minigame_attempts_used', 'Attempts Used')); ?></span>
                    <strong class="mg-metric-value" id="mg-stat-attempts"><?= (int)$gameState['attempts_used']; ?> / <?= MINIGAME_MAX_ATTEMPTS; ?></strong>
                    <span class="mg-metric-sub">
                        <?= e(__('minigame_ads_used', 'Demo Ads Watched')); ?>:
                        <strong id="mg-stat-ads"><?= (int)$gameState['ads_granted']; ?> / <?= MINIGAME_MAX_ADS; ?></strong>
                    </span>
                </div>

                <div class="mg-metric-card">
                    <span class="mg-metric-label">⭐ <?= e(__('minigame_best_score', 'Best Score')); ?></span>
                    <strong class="mg-metric-value" id="mg-stat-best"><?= number_format((int)$gameState['best_score']); ?></strong>
                    <span class="mg-metric-sub">
                        <?= isJapanese() ? '目標スコア：2,001点以上（2,000点超）' : 'Target Score: Over 2,000 pts (2,001+)'; ?>
                    </span>
                </div>
            </div>

            <!-- Live Feedback Banner -->
            <div id="mg-feedback-banner" class="mg-feedback-banner" style="display:none;"></div>

            <!-- Primary Option Buttons -->
            <div class="mg-action-buttons mg-invite-actions">
                <button type="button" id="btn-start-attempt" class="btn btn-primary btn-lg">
                    🎮 <?= e(__('minigame_start_free', 'Start Free Attempt (1/3)')); ?>
                </button>

                <button type="button" id="btn-watch-ad" class="btn btn-secondary btn-lg" style="display:none;">
                    🎬 <?= e(__('ad_watch_btn', 'Watch an advertisement to play again')); ?>
                </button>

                <button type="button" id="btn-continue-payment" class="btn btn-primary btn-lg" style="display:none;">
                    💳 <?= e(__('minigame_continue_payment', 'Continue to Payment Settings')); ?> &rarr;
                </button>
            </div>

            <!-- Rules & Attempt History Two-Column Band -->
            <div class="mg-info-grid">
                <div class="mg-info-panel">
                    <h3 class="mg-side-title">📋 <?= e(__('minigame_rules_title', 'How to Win a ¥200 Discount')); ?></h3>
                    <ul class="mg-rules-list">
                        <li>🎯 <?= e(__('minigame_rule_target', 'Score strictly higher than 2,000 points (2,001+ pts) in Sweet Run.')); ?></li>
                        <li>🎁 <?= e(__('minigame_rule_reward', 'Earn a ¥200 discount applied directly to this order.')); ?></li>
                        <li>🎬 <?= e(__('minigame_rule_attempts', 'Attempt 1 is free. Watch a short Demo Ad to unlock Attempt 2 and Attempt 3 (max 3 attempts / 2 ads per order).')); ?></li>
                        <li>🔒 <?= e(__('minigame_rule_cap', 'Maximum mini-game discount is ¥200 per order, even if you win multiple times.')); ?></li>
                    </ul>
                </div>

                <div class="mg-info-panel">
                    <h3 class="mg-side-title">📜 <?= e(__('minigame_attempt_history', 'Attempt History')); ?></h3>
                    <div id="mg-attempt-history" class="mg-attempt-history">
                        <p class="mg-empty-history">
                            <?= e(__('minigame_no_attempts_yet', 'No attempts played yet. Start your free attempt!')); ?>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. Full-Screen Embedded 3D Sweet Run Game Overlay (100vw x 100vh when playing) -->
        <section id="minigame-stage-section" class="minigame-fullscreen-overlay<?= $showStageInitially ? ' is-open' : ''; ?>" aria-hidden="<?= $showStageInitially ? 'false' : 'true'; ?>">
            <!-- Floating Top-Left Exit Pill (sits below Sweet Run SCORE/TREATS HUD, matching standalone style) -->
            <div class="mg-fs-top-bar">
                <button type="button" id="btn-exit-fullscreen" class="mg-fs-pill-btn">
                    &larr; <?= isJapanese() ? 'チェックアウト画面へ戻る' : 'Back to Checkout'; ?>
                </button>
                <span class="mg-fs-goal-pill">
                    🎯 <?= isJapanese() ? '目標: 2,001点以上で¥200割引' : 'Goal: 2,001+ pts for ¥200 OFF'; ?>
                </span>
            </div>

            <div class="minigame-iframe-wrap">
                <iframe
                    id="sweet-run-iframe"
                    src="<?= $showStageInitially ? e($gameIframeUrl) : 'about:blank'; ?>"
                    data-src="<?= e($gameIframeUrl); ?>"
                    title="Sweet Run 3D Mini-Game"
                    allow="autoplay; fullscreen"
                    allowfullscreen
                ></iframe>
            </div>

            <!-- Floating Post-Round / Game Over Retry & Payment Bar (shown after round ends) -->
            <div id="mg-stage-footer" class="minigame-stage-footer" style="display:none;">
                <div id="mg-stage-feedback" class="mg-feedback-banner" style="display:none; margin-bottom:12px;"></div>
                <div class="mg-action-buttons" style="justify-content:center;">
                    <button type="button" id="btn-stage-start" class="btn btn-primary btn-lg" style="display:none;">
                        🎮 <?= e(__('minigame_start_free', 'Start Free Attempt (1/3)')); ?>
                    </button>
                    <button type="button" id="btn-stage-watch-ad" class="btn btn-secondary btn-lg" style="display:none;">
                        🎬 <?= e(__('ad_watch_btn', 'Watch an advertisement to play again')); ?>
                    </button>
                    <button type="button" id="btn-stage-continue" class="btn btn-primary btn-lg" style="display:none;">
                        💳 <?= e(__('minigame_continue_payment', 'Continue to Payment Settings')); ?> &rarr;
                    </button>
                </div>
            </div>
        </section>

    </div>
</div>

<!-- DEMO REWARDED ADVERTISEMENT MODAL (School Project Mode) -->
<div id="demo-ad-modal" class="demo-ad-overlay" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="demo-ad-card">
        <div class="demo-ad-top-badge">🎬 <?= e(__('ad_demo_badge', 'DEMO REWARDED ADVERTISEMENT MODE')); ?></div>
        <h2 class="demo-ad-headline"><?= e(__('ad_demo_headline', 'Discover your favorite desserts at Sweet Choice!')); ?></h2>
        <div class="demo-ad-visual">
            <img src="../assets/images/logo_banner.jpg" alt="Sweet Choice Promotion" onerror="this.style.display='none'">
        </div>
        <p class="demo-ad-subtext"><?= e(__('ad_demo_subtext', 'Handcrafted in Ginza with Hokkaido pure dairy & Kyoto Uji matcha.')); ?></p>

        <div class="demo-ad-progress-wrap">
            <div class="demo-ad-progress-bar" id="demo-ad-progress-bar"></div>
        </div>
        <div class="demo-ad-countdown" id="demo-ad-countdown">
            <?= e(sprintf(__('ad_countdown_label', 'Reward unlocks in %d seconds...'), MINIGAME_DEMO_AD_SECONDS)); ?>
        </div>

        <p class="demo-ad-disclaimer">
            ℹ️ <?= e(__('ad_demo_disclaimer', 'School Project Demo Mode: Simulated advertisement for educational demonstration. No external ad network or real revenue.')); ?>
        </p>

        <div style="margin-top:14px;">
            <button type="button" id="btn-cancel-demo-ad" class="btn btn-secondary btn-sm">
                <?= e(__('ad_cancel_btn', 'Cancel')); ?>
            </button>
        </div>
    </div>
</div>

<script src="../assets/js/checkout-game.js?v=<?= file_exists(__DIR__ . '/../assets/js/checkout-game.js') ? filemtime(__DIR__ . '/../assets/js/checkout-game.js') : time(); ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
