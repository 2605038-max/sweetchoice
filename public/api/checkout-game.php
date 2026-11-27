<?php
/**
 * Sweet Choice - Checkout Mini-Game & Demo Rewarded Ad API
 *
 * Server-authoritative endpoints for:
 *   - status: Get current checkout session eligibility, attempts, ads, and discount state.
 *   - start_attempt: Issue a single-use attempt_token (Attempt 1 free, Attempts 2 & 3 require granted demo ad).
 *   - finish_attempt: Validate attempt_token, server-clock plausibility, score > 2000 rule, and ¥200 cap.
 *   - ad_start: Start a 5-second Demo Rewarded Ad session via RewardedAdProvider.
 *   - ad_complete: Verify server-clock duration >= 5s and unlock 1 attempt (max 2 ads).
 *   - continue_to_payment: Mark game stage done and redirect to payment settings.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/language.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/checkout.php';
require_once __DIR__ . '/../../includes/ads/DemoRewardedAdProvider.php';

$pdo = getDB();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => __('auth_checkout_notice', 'Please log in to continue.')], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'status';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Require CSRF verification for all state-changing POST actions
if ($method === 'POST') {
    $csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verifyCsrfToken($csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => __('auth_csrf_expired', 'Session expired. Please refresh and try again.')], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$userId = (int)$_SESSION['user_id'];
$cartId = getOrCreateCartId($pdo);
$totals = calculateCartTotals($pdo, $cartId);

if (empty($totals['items'])) {
    echo json_encode(['success' => false, 'empty_cart' => true, 'error' => __('cart_empty', 'Your cart is empty.')], JSON_UNESCAPED_UNICODE);
    exit;
}

$checkoutSession = getOrCreateCheckoutSession($pdo, $userId, $cartId, $totals);
$adProvider = new DemoRewardedAdProvider();

// -----------------------------------------------------------------
// ACTION: status
// -----------------------------------------------------------------
if ($action === 'status') {
    $state = getCheckoutGameState($pdo, $checkoutSession, $totals);
    echo json_encode(['success' => true, 'state' => $state], JSON_UNESCAPED_UNICODE);
    exit;
}

// -----------------------------------------------------------------
// ACTION: start_attempt
// -----------------------------------------------------------------
if ($action === 'start_attempt' && $method === 'POST') {
    if (!$totals['is_minigame_eligible']) {
        echo json_encode([
            'success' => false,
            'error' => __('minigame_not_qualified_msg', 'Your order does not meet the ¥3,000 minimum purchase requirement.'),
            'state' => getCheckoutGameState($pdo, $checkoutSession, $totals),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sessionId = (int)$checkoutSession['id'];

    try {
        $pdo->beginTransaction();

        // Lock checkout session
        $lock = $pdo->prepare("SELECT id, status FROM checkout_sessions WHERE id = ? AND user_id = ? FOR UPDATE");
        $lock->execute([$sessionId, $userId]);
        $locked = $lock->fetch();
        if (!$locked || $locked['status'] !== 'active') {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => __('auth_csrf_expired', 'Session expired.')], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Fetch existing attempts for this session
        $attStmt = $pdo->prepare("SELECT id, attempt_number, status, attempt_token FROM checkout_game_attempts WHERE checkout_session_id = ? ORDER BY attempt_number ASC FOR UPDATE");
        $attStmt->execute([$sessionId]);
        $attempts = $attStmt->fetchAll();

        // If an attempt is currently started, do not spawn a duplicate attempt; return existing active token
        foreach ($attempts as $a) {
            if ($a['status'] === 'started') {
                $pdo->commit();
                $state = getCheckoutGameState($pdo, $checkoutSession, $totals);
                echo json_encode([
                    'success' => true,
                    'resumed' => true,
                    'attempt_number' => (int)$a['attempt_number'],
                    'attempt_token' => $a['attempt_token'],
                    'state' => $state,
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $attemptsUsed = count($attempts);
        if ($attemptsUsed >= MINIGAME_MAX_ATTEMPTS) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'error' => __('minigame_all_attempts_used', 'You have used all 3 attempts for this order.'),
                'state' => getCheckoutGameState($pdo, $checkoutSession, $totals),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $nextNumber = $attemptsUsed + 1; // 1, 2, or 3
        $attemptType = 'free';
        $adRewardId = null;

        if ($nextNumber > 1) {
            // Requires a granted, unused ad reward
            $adStmt = $pdo->prepare("
                SELECT ar.id
                FROM checkout_ad_rewards ar
                LEFT JOIN checkout_game_attempts ga ON ga.ad_reward_id = ar.id
                WHERE ar.checkout_session_id = ?
                  AND ar.user_id = ?
                  AND ar.status = 'granted'
                  AND ga.id IS NULL
                ORDER BY ar.sequence_no ASC
                LIMIT 1
                FOR UPDATE
            ");
            $adStmt->execute([$sessionId, $userId]);
            $adRow = $adStmt->fetch();

            if (!$adRow) {
                $pdo->rollBack();
                echo json_encode([
                    'success' => false,
                    'needs_ad' => true,
                    'error' => __('ad_watch_prompt', 'Watch an advertisement to unlock another attempt.'),
                    'state' => getCheckoutGameState($pdo, $checkoutSession, $totals),
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $attemptType = 'demo_ad';
            $adRewardId = (int)$adRow['id'];
        }

        $attemptToken = bin2hex(random_bytes(32));
        $ins = $pdo->prepare("
            INSERT INTO checkout_game_attempts
                (checkout_session_id, user_id, attempt_number, attempt_type, ad_reward_id, attempt_token, status, started_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'started', NOW(), NOW())
        ");
        $ins->execute([$sessionId, $userId, $nextNumber, $attemptType, $adRewardId, $attemptToken]);

        $pdo->commit();

        $state = getCheckoutGameState($pdo, $checkoutSession, $totals);
        echo json_encode([
            'success' => true,
            'attempt_number' => $nextNumber,
            'attempt_type' => $attemptType,
            'attempt_token' => $attemptToken,
            'state' => $state,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('checkout-game start_attempt error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => __('checkout_err_unexpected', 'An unexpected error occurred.')], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// -----------------------------------------------------------------
// ACTION: finish_attempt
// -----------------------------------------------------------------
if ($action === 'finish_attempt' && $method === 'POST') {
    $attemptToken = trim($_POST['attempt_token'] ?? '');
    $rawScore = $_POST['score'] ?? null;

    if ($attemptToken === '' || !is_numeric($rawScore)) {
        echo json_encode(['success' => false, 'error' => __('minigame_score_rejected', 'Invalid attempt submission.')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $score = (int)$rawScore;
    $sessionId = (int)$checkoutSession['id'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT id, checkout_session_id, user_id, attempt_number, attempt_type, status,
                   TIMESTAMPDIFF(SECOND, started_at, NOW()) AS elapsed_seconds
            FROM checkout_game_attempts
            WHERE attempt_token = ?
            FOR UPDATE
        ");
        $stmt->execute([$attemptToken]);
        $attempt = $stmt->fetch();

        if (!$attempt || (int)$attempt['checkout_session_id'] !== $sessionId || (int)$attempt['user_id'] !== $userId) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => __('minigame_score_rejected', 'Invalid attempt token.')], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Prevent duplicate / replayed submissions
        if ($attempt['status'] !== 'started') {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'duplicate' => true,
                'error' => __('minigame_score_rejected', 'This attempt has already been recorded.'),
                'state' => getCheckoutGameState($pdo, $checkoutSession, $totals),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $elapsed = max(0, (int)$attempt['elapsed_seconds']);

        // Plausibility checks against Sweet Run's physics (12 pts/sec base + collectibles):
        // Reject impossible negative scores, absurd scores, or instant winning scores (>2000 in < 12s or > 200 pts/sec + 500)
        $isPlausible = true;
        if ($score < 0 || $score > 50000) {
            $isPlausible = false;
        } elseif ($score > MINIGAME_TARGET_SCORE) {
            if ($elapsed < 12 || $score > ($elapsed * 200 + 500)) {
                $isPlausible = false;
            }
        }

        if (!$isPlausible) {
            $rej = $pdo->prepare("
                UPDATE checkout_game_attempts
                SET status = 'rejected', score = ?, is_winning = 0, duration_seconds = ?, finished_at = NOW()
                WHERE id = ?
            ");
            $rej->execute([$score, $elapsed, $attempt['id']]);
            $pdo->commit();

            $state = getCheckoutGameState($pdo, $checkoutSession, $totals);
            echo json_encode([
                'success' => false,
                'rejected' => true,
                'score' => $score,
                'is_winning' => false,
                'message' => __('minigame_score_rejected', 'This score could not be verified by the server.'),
                'state' => $state,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Strict winning condition: score MUST be strictly higher than 2,000 (2,001+)
        $isWinning = ($score > MINIGAME_TARGET_SCORE);

        $updAtt = $pdo->prepare("
            UPDATE checkout_game_attempts
            SET status = 'finished', score = ?, is_winning = ?, duration_seconds = ?, finished_at = NOW()
            WHERE id = ?
        ");
        $updAtt->execute([$score, $isWinning ? 1 : 0, $elapsed, $attempt['id']]);

        // Award ¥200 discount once per order (capped strictly at 200 even across multiple wins)
        if ($isWinning && $totals['is_minigame_eligible']) {
            $updSess = $pdo->prepare("
                UPDATE checkout_sessions
                SET game_discount_amount = ?,
                    game_discount_status = IF(game_discount_status = 'applied', 'applied', 'earned'),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updSess->execute([MINIGAME_DISCOUNT_AMOUNT, $sessionId]);
            $checkoutSession['game_discount_amount'] = MINIGAME_DISCOUNT_AMOUNT;
            $checkoutSession['game_discount_status'] = 'earned';
        }

        $pdo->commit();

        $state = getCheckoutGameState($pdo, $checkoutSession, $totals);
        if ($isWinning) {
            $msg = __('minigame_congrats_discount', 'Congratulations! You earned a ¥200 discount!');
        } elseif ($score === MINIGAME_TARGET_SCORE) {
            $msg = __('minigame_exact_2000_notice', 'Exactly 2,000 points does not qualify—you need strictly over 2,000 points (2,001+).');
        } else {
            $msg = sprintf(__('minigame_try_again_score', 'You scored %s points. Score over 2,000 points to earn the ¥200 discount!'), number_format($score));
        }

        echo json_encode([
            'success' => true,
            'score' => $score,
            'is_winning' => $isWinning,
            'exact_2000' => ($score === MINIGAME_TARGET_SCORE),
            'discount_amount' => $state['discount_amount'],
            'message' => $msg,
            'state' => $state,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('checkout-game finish_attempt error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => __('checkout_err_unexpected', 'An unexpected error occurred.')], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// -----------------------------------------------------------------
// ACTION: ad_start (Demo Rewarded Advertisement Mode)
// -----------------------------------------------------------------
if ($action === 'ad_start' && $method === 'POST') {
    $res = $adProvider->startAd($pdo, $checkoutSession, $userId, $totals);
    $res['state'] = getCheckoutGameState($pdo, $checkoutSession, $totals);
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

// -----------------------------------------------------------------
// ACTION: ad_complete (Demo Rewarded Advertisement Mode)
// -----------------------------------------------------------------
if ($action === 'ad_complete' && $method === 'POST') {
    $txnId = trim($_POST['reward_txn_id'] ?? '');
    $res = $adProvider->verifyAndCompleteAd($pdo, $checkoutSession, $userId, $txnId, $_POST);
    $res['state'] = getCheckoutGameState($pdo, $checkoutSession, $totals);
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

// -----------------------------------------------------------------
// ACTION: continue_to_payment
// -----------------------------------------------------------------
if ($action === 'continue_to_payment' && $method === 'POST') {
    $sessionId = (int)$checkoutSession['id'];
    // Mark any unfinished started attempt as abandoned so it cannot be replayed later
    $ab = $pdo->prepare("UPDATE checkout_game_attempts SET status = 'abandoned', finished_at = NOW() WHERE checkout_session_id = ? AND status = 'started'");
    $ab->execute([$sessionId]);

    $upd = $pdo->prepare("UPDATE checkout_sessions SET game_stage = 'done', updated_at = NOW() WHERE id = ?");
    $upd->execute([$sessionId]);

    echo json_encode(['success' => true, 'redirect' => 'checkout.php'], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
