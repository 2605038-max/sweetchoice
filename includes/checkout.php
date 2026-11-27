<?php
/**
 * Sweet Choice - Server-Side Checkout & Mini-Game Engine
 *
 * Centralizes:
 * - Trusted server-side cart totals and ¥3,000 mini-game eligibility (product price x quantity ONLY).
 * - Checkout session lifecycle (checkout_sessions).
 * - Mini-game attempt & ad state evaluation (1 free + up to 2 demo ads, max 3 attempts, >2,000 pts = ¥200 cap).
 * - Bilingual checkout progress indicator (Cart -> Login/Register -> Mini-Game -> Payment -> Confirmation).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/language.php';
require_once __DIR__ . '/pairings.php';

const MINIGAME_MIN_SUBTOTAL = 3000;
const MINIGAME_TARGET_SCORE = 2000; // Must be STRICTLY higher (> 2000, i.e., 2001+)
const MINIGAME_DISCOUNT_AMOUNT = 200;
const MINIGAME_MAX_ATTEMPTS = 3;
const MINIGAME_MAX_ADS = 2;
const MINIGAME_DEMO_AD_SECONDS = 5;

/**
 * Calculate all cart totals strictly from live MySQL product prices and quantities.
 *
 * Eligibility rule:
 *   eligible_product_subtotal = SUM(products.price * cart_items.quantity)
 *   Excludes: customization_price, delivery_fee, service_fee, pairing discounts.
 */
function calculateCartTotals(PDO $pdo, int $cartId): array {
    // Revalidate pairing discounts against live inventory first
    revalidateCartPairingDiscounts($pdo, $cartId);

    $stmt = $pdo->prepare("
        SELECT ci.*, p.name_en, p.name_ja, p.price, p.image, p.stock, p.availability,
               c.name_en AS category_name_en, c.name_ja AS category_name_ja,
               parent_p.name_en AS parent_name_en, parent_p.name_ja AS parent_name_ja
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        JOIN categories c ON p.category_id = c.id
        LEFT JOIN products parent_p ON ci.pairing_product_id = parent_p.id
        WHERE ci.cart_id = ?
        ORDER BY ci.id DESC
    ");
    $stmt->execute([$cartId]);
    $items = $stmt->fetchAll();

    $eligibleProductSubtotal = 0;
    $pairingDiscountTotal = 0;
    $customizationTotal = 0;
    $hasStockIssue = false;

    foreach ($items as $item) {
        $qty = max(1, (int)$item['quantity']);
        $unitPrice = (int)$item['price'];
        $unitDisc = (int)($item['discount_amount'] ?? 0);
        $unitCustom = (int)($item['customization_price'] ?? 0);

        $eligibleProductSubtotal += ($unitPrice * $qty);
        $pairingDiscountTotal += ($unitDisc * $qty);
        $customizationTotal += ($unitCustom * $qty);

        if ($item['availability'] === 'sold_out' || (int)$item['stock'] < $qty || (int)$item['stock'] <= 0) {
            $hasStockIssue = true;
        }
    }

    $effectiveProductSubtotal = max(0, $eligibleProductSubtotal - $pairingDiscountTotal);
    $serviceFee = 0;
    $isEligible = ($eligibleProductSubtotal >= MINIGAME_MIN_SUBTOTAL);
    $amountNeeded = max(0, MINIGAME_MIN_SUBTOTAL - $eligibleProductSubtotal);

    return [
        'items' => $items,
        'eligible_product_subtotal' => $eligibleProductSubtotal,
        'pairing_discount_total' => $pairingDiscountTotal,
        'effective_product_subtotal' => $effectiveProductSubtotal,
        'customization_total' => $customizationTotal,
        'service_fee' => $serviceFee,
        'is_minigame_eligible' => $isEligible,
        'amount_needed_for_minigame' => $amountNeeded,
        'has_stock_issue' => $hasStockIssue,
    ];
}

/**
 * Retrieve or create an active checkout session for the logged-in user and cart,
 * and synchronize it with the current server-calculated eligible product subtotal.
 */
function getOrCreateCheckoutSession(PDO $pdo, int $userId, int $cartId, array $totals): array {
    $stmt = $pdo->prepare("
        SELECT * FROM checkout_sessions
        WHERE user_id = ? AND cart_id = ? AND status = 'active'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $cartId]);
    $session = $stmt->fetch();

    $eligibleSubtotal = (int)$totals['eligible_product_subtotal'];
    $isEligible = (bool)$totals['is_minigame_eligible'];
    $discountJustVoided = false;

    if (!$session) {
        $publicToken = bin2hex(random_bytes(32));
        $submitToken = bin2hex(random_bytes(32));
        $gameStage = $isEligible ? 'pending' : 'skipped';

        $ins = $pdo->prepare("
            INSERT INTO checkout_sessions
                (public_token, user_id, cart_id, status, eligible_subtotal, game_stage, game_discount_amount, game_discount_status, submit_token, created_at, updated_at)
            VALUES (?, ?, ?, 'active', ?, ?, 0, 'none', ?, NOW(), NOW())
        ");
        $ins->execute([$publicToken, $userId, $cartId, $eligibleSubtotal, $gameStage, $submitToken]);
        $sessionId = (int)$pdo->lastInsertId();

        $fetch = $pdo->prepare("SELECT * FROM checkout_sessions WHERE id = ?");
        $fetch->execute([$sessionId]);
        $session = $fetch->fetch();
        $session['discount_just_voided'] = false;
        return $session;
    }

    // Sync existing active session with live cart eligibility
    $newGameStage = $session['game_stage'];
    $newDiscountAmount = (int)$session['game_discount_amount'];
    $newDiscountStatus = $session['game_discount_status'];
    $newSubmitToken = $session['submit_token'] ?: bin2hex(random_bytes(32));

    if (!$isEligible) {
        $newGameStage = 'skipped';
        if ($newDiscountStatus === 'earned' || $newDiscountAmount > 0) {
            $newDiscountStatus = 'voided';
            $newDiscountAmount = 0;
            $discountJustVoided = true;
        }
    } else {
        if ($newGameStage === 'skipped') {
            $newGameStage = 'pending';
        }
        // If customer previously earned a win in this session, restore eligibility if cart went back >= 3000
        if ($newDiscountStatus === 'voided') {
            $winChk = $pdo->prepare("SELECT COUNT(*) FROM checkout_game_attempts WHERE checkout_session_id = ? AND is_winning = 1 AND status = 'finished'");
            $winChk->execute([$session['id']]);
            if ((int)$winChk->fetchColumn() > 0) {
                $newDiscountStatus = 'earned';
                $newDiscountAmount = MINIGAME_DISCOUNT_AMOUNT;
            }
        }
    }

    if (
        (int)$session['eligible_subtotal'] !== $eligibleSubtotal ||
        $session['game_stage'] !== $newGameStage ||
        (int)$session['game_discount_amount'] !== $newDiscountAmount ||
        $session['game_discount_status'] !== $newDiscountStatus ||
        empty($session['submit_token'])
    ) {
        $upd = $pdo->prepare("
            UPDATE checkout_sessions
            SET eligible_subtotal = ?,
                game_stage = ?,
                game_discount_amount = ?,
                game_discount_status = ?,
                submit_token = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $upd->execute([$eligibleSubtotal, $newGameStage, $newDiscountAmount, $newDiscountStatus, $newSubmitToken, $session['id']]);

        $session['eligible_subtotal'] = $eligibleSubtotal;
        $session['game_stage'] = $newGameStage;
        $session['game_discount_amount'] = $newDiscountAmount;
        $session['game_discount_status'] = $newDiscountStatus;
        $session['submit_token'] = $newSubmitToken;
    }

    $session['discount_just_voided'] = $discountJustVoided;
    return $session;
}

/**
 * Evaluate full mini-game attempt, demo ad, and discount status for a checkout session.
 */
function getCheckoutGameState(PDO $pdo, array $session, array $totals): array {
    $sessionId = (int)$session['id'];

    $attStmt = $pdo->prepare("
        SELECT id, attempt_number, attempt_type, ad_reward_id, attempt_token, status, score, is_winning, duration_seconds, started_at, finished_at
        FROM checkout_game_attempts
        WHERE checkout_session_id = ?
        ORDER BY attempt_number ASC
    ");
    $attStmt->execute([$sessionId]);
    $attempts = $attStmt->fetchAll();

    $adStmt = $pdo->prepare("
        SELECT id, sequence_no, reward_txn_id, demo_completed, status, started_at, completed_at
        FROM checkout_ad_rewards
        WHERE checkout_session_id = ?
        ORDER BY sequence_no ASC
    ");
    $adStmt->execute([$sessionId]);
    $ads = $adStmt->fetchAll();

    $attemptsUsed = count($attempts);
    $activeAttempt = null;
    $bestScore = 0;
    $hasWon = false;

    foreach ($attempts as $att) {
        if ($att['status'] === 'started') {
            $activeAttempt = $att;
        }
        if ($att['status'] === 'finished' && (int)$att['score'] > $bestScore) {
            $bestScore = (int)$att['score'];
        }
        if ($att['status'] === 'finished' && (int)$att['is_winning'] === 1) {
            $hasWon = true;
        }
    }

    $adsGranted = 0;
    $pendingAd = null;
    foreach ($ads as $ad) {
        if ($ad['status'] === 'granted') {
            $adsGranted++;
        } elseif ($ad['status'] === 'pending') {
            $pendingAd = $ad;
        }
    }

    $unlockedAttempts = min(MINIGAME_MAX_ATTEMPTS, 1 + $adsGranted);
    $isEligible = (bool)$totals['is_minigame_eligible'];

    $canStartAttempt = $isEligible && ($activeAttempt === null) && ($attemptsUsed < $unlockedAttempts) && ($attemptsUsed < MINIGAME_MAX_ATTEMPTS);
    $canWatchAd = $isEligible && ($activeAttempt === null) && ($attemptsUsed >= $unlockedAttempts) && ($adsGranted < MINIGAME_MAX_ADS) && ($attemptsUsed < MINIGAME_MAX_ATTEMPTS);

    $discountAmount = ($isEligible && $session['game_discount_status'] === 'earned')
        ? min(MINIGAME_DISCOUNT_AMOUNT, (int)$session['game_discount_amount'])
        : 0;

    return [
        'session_token' => $session['public_token'],
        'is_eligible' => $isEligible,
        'eligible_subtotal' => (int)$totals['eligible_product_subtotal'],
        'min_subtotal' => MINIGAME_MIN_SUBTOTAL,
        'target_score' => MINIGAME_TARGET_SCORE,
        'max_attempts' => MINIGAME_MAX_ATTEMPTS,
        'max_ads' => MINIGAME_MAX_ADS,
        'demo_ad_seconds' => MINIGAME_DEMO_AD_SECONDS,
        'attempts_used' => $attemptsUsed,
        'unlocked_attempts' => $unlockedAttempts,
        'remaining_total_attempts' => max(0, MINIGAME_MAX_ATTEMPTS - $attemptsUsed),
        'ads_granted' => $adsGranted,
        'ads_remaining' => max(0, MINIGAME_MAX_ADS - $adsGranted),
        'active_attempt' => $activeAttempt ? [
            'attempt_number' => (int)$activeAttempt['attempt_number'],
            'attempt_type' => $activeAttempt['attempt_type'],
            'attempt_token' => $activeAttempt['attempt_token'],
            'started_at' => $activeAttempt['started_at'],
        ] : null,
        'pending_ad' => $pendingAd ? [
            'sequence_no' => (int)$pendingAd['sequence_no'],
            'reward_txn_id' => $pendingAd['reward_txn_id'],
            'started_at' => $pendingAd['started_at'],
        ] : null,
        'can_start_attempt' => $canStartAttempt,
        'can_watch_ad' => $canWatchAd,
        'best_score' => $bestScore,
        'has_won' => $hasWon,
        'discount_amount' => $discountAmount,
        'discount_status' => $session['game_discount_status'],
        'attempts' => array_map(static function (array $a): array {
            return [
                'attempt_number' => (int)$a['attempt_number'],
                'attempt_type' => $a['attempt_type'],
                'status' => $a['status'],
                'score' => $a['score'] !== null ? (int)$a['score'] : null,
                'is_winning' => (bool)$a['is_winning'],
                'started_at' => $a['started_at'],
                'finished_at' => $a['finished_at'],
            ];
        }, $attempts),
    ];
}

/**
 * Calculate final payable total on the server (never negative).
 */
function calculateFinalPayableTotal(array $totals, int $deliveryFee, int $gameDiscountAmount): int {
    $safeGameDiscount = max(0, min(MINIGAME_DISCOUNT_AMOUNT, $gameDiscountAmount));
    $raw = (int)$totals['effective_product_subtotal']
         + (int)$totals['customization_total']
         + (int)$totals['service_fee']
         + max(0, $deliveryFee)
         - $safeGameDiscount;
    return max(0, $raw);
}

/**
 * Render the bilingual 5-step horizontal checkout progress indicator:
 * Cart -> Login/Register -> Mini-Game -> Payment -> Confirmation
 */
function renderCheckoutStepper(string $activeStep, bool $minigameSkipped = false): string {
    $steps = [
        ['key' => 'cart',         'num' => 1, 'label' => __('step_cart', 'Cart')],
        ['key' => 'auth',         'num' => 2, 'label' => __('step_auth', 'Login / Register')],
        ['key' => 'minigame',     'num' => 3, 'label' => __('step_minigame', 'Mini-Game')],
        ['key' => 'payment',      'num' => 4, 'label' => __('step_payment', 'Payment')],
        ['key' => 'confirmation', 'num' => 5, 'label' => __('step_confirmation', 'Confirmation')],
    ];

    $orderMap = ['cart' => 1, 'auth' => 2, 'minigame' => 3, 'payment' => 4, 'confirmation' => 5];
    $currentPos = $orderMap[$activeStep] ?? 1;

    $html = '<nav class="checkout-stepper" aria-label="Checkout Progress"><div class="checkout-stepper-track">';
    foreach ($steps as $idx => $step) {
        $pos = $step['num'];
        $isSkipped = ($step['key'] === 'minigame' && $minigameSkipped);
        $isActive = ($step['key'] === $activeStep);
        $isDone = ($pos < $currentPos && !$isSkipped);

        $cls = 'checkout-step';
        if ($isActive) {
            $cls .= ' is-active';
        } elseif ($isSkipped) {
            $cls .= ' is-skipped';
        } elseif ($isDone) {
            $cls .= ' is-done';
        } else {
            $cls .= ' is-upcoming';
        }

        $badgeText = $isDone ? '✓' : ($isSkipped ? '–' : (string)$pos);

        $html .= '<div class="' . $cls . '">';
        $html .= '<span class="checkout-step-circle">' . $badgeText . '</span>';
        $html .= '<span class="checkout-step-label">' . e($step['label']);
        if ($isSkipped) {
            $html .= '<small class="checkout-step-skip-tag">' . e(__('step_skipped', 'Skipped')) . '</small>';
        }
        $html .= '</span>';
        $html .= '</div>';

        if ($idx < count($steps) - 1) {
            $connDone = ($pos < $currentPos) ? ' is-done' : '';
            $html .= '<div class="checkout-step-connector' . $connDone . '" aria-hidden="true"></div>';
        }
    }
    $html .= '</div></nav>';

    return $html;
}
