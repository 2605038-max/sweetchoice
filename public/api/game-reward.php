<?php
/**
 * Sweet Choice - Mini Game Campaign & Reward API
 * 
 * Rules:
 * 1. Purchase First: Customer must have completed an order >= 3,000 JPY.
 * 2. 3-Attempt Limit: Eligible customers get maximum 3 tries to reach 2,000 points.
 *    Attempt count is persisted in database table `game_campaign_attempts` tied to Order ID.
 * 3. Target Score: 2,000 points.
 * 4. Automatic Reward: When score >= 2,000 within 3 tries, automatically add
 *    "パティシエ特製ミニケーキ (¥0)" directly into their existing order in `order_items`.
 * 5. Challenge Stops: When 3 attempts are exhausted (0/3 remaining) without 2,000 points,
 *    the campaign challenge to win the free mini cake stops permanently for that order.
 * 6. Multi-Order Support: Customers can switch or verify different order numbers anytime.
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/language.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? 'check_status';

// Helper: Ensure campaign attempts table exists
function ensureCampaignTable(PDO $pdo): void {
    $sql = "
    CREATE TABLE IF NOT EXISTS game_campaign_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        order_number VARCHAR(50) NOT NULL,
        attempts_used INT NOT NULL DEFAULT 0,
        reward_claimed TINYINT(1) NOT NULL DEFAULT 0,
        high_score INT NOT NULL DEFAULT 0,
        claimed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_order_id (order_id),
        INDEX idx_order_number (order_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($sql);
}

// Helper: Fetch qualifying order and its persistent campaign attempt record
function getCustomerCampaignStatus(PDO $pdo, ?string $explicitOrderNum = null): array {
    ensureCampaignTable($pdo);

    $userId = $_SESSION['user_id'] ?? null;
    $explicitOrder = !empty($explicitOrderNum) ? trim($explicitOrderNum) : null;
    $targetOrderNum = $explicitOrder ?? ($_SESSION['verified_order_number'] ?? null);
    $order = null;

    // 1. If explicit order or verified order in session, check that specific order first!
    if ($targetOrderNum) {
        $stmt = $pdo->prepare("
            SELECT id, order_number, grand_total, customer_name, status, notes, created_at 
            FROM orders 
            WHERE order_number = ? AND grand_total >= 3000 AND status != 'Cancelled'
            LIMIT 1
        ");
        $stmt->execute([$targetOrderNum]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        // Update active verified order in session
        if ($order) {
            $_SESSION['verified_order_number'] = $order['order_number'];
        }
    }

    // 2. If no valid verified order, and user is logged in, find their MOST RECENT order >= 3000
    if (!$order && $userId) {
        $stmt = $pdo->prepare("
            SELECT id, order_number, grand_total, customer_name, status, notes, created_at 
            FROM orders 
            WHERE user_id = ? AND grand_total >= 3000 AND status != 'Cancelled'
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            $_SESSION['verified_order_number'] = $order['order_number'];
        }
    }

    // If no qualifying order found
    if (!$order) {
        return [
            'is_eligible' => false,
            'is_logged_in' => !empty($userId),
            'order_id' => null,
            'order_number' => null,
            'grand_total' => 0,
            'customer_name' => $_SESSION['user_name'] ?? null,
            'target_score' => 2000,
            'max_attempts' => 3,
            'attempts_used' => 0,
            'remaining_attempts' => 0,
            'reward_claimed' => false,
            'claimed_at' => null
        ];
    }

    // Find or create attempt record in DB
    $attStmt = $pdo->prepare("SELECT * FROM game_campaign_attempts WHERE order_id = ?");
    $attStmt->execute([$order['id']]);
    $attempt = $attStmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
        $ins = $pdo->prepare("
            INSERT INTO game_campaign_attempts (order_id, order_number, attempts_used, reward_claimed, created_at)
            VALUES (?, ?, 0, 0, NOW())
        ");
        $ins->execute([$order['id'], $order['order_number']]);
        $attempt = [
            'order_id' => $order['id'],
            'order_number' => $order['order_number'],
            'attempts_used' => 0,
            'reward_claimed' => 0,
            'high_score' => 0,
            'claimed_at' => null
        ];
    }

    $attemptsUsed = (int)$attempt['attempts_used'];
    $maxAttempts = 3;
    $remainingAttempts = max(0, $maxAttempts - $attemptsUsed);
    $rewardClaimed = (bool)$attempt['reward_claimed'];

    return [
        'is_eligible' => true,
        'is_logged_in' => !empty($userId),
        'order_id' => (int)$order['id'],
        'order_number' => $order['order_number'],
        'grand_total' => (int)$order['grand_total'],
        'customer_name' => $order['customer_name'] ?? ($_SESSION['user_name'] ?? 'Customer'),
        'target_score' => 2000,
        'max_attempts' => $maxAttempts,
        'attempts_used' => $attemptsUsed,
        'remaining_attempts' => $remainingAttempts,
        'reward_claimed' => $rewardClaimed,
        'claimed_at' => $attempt['claimed_at'],
        'present_name' => isJapanese() ? 'パティシエ特製ミニケーキ' : 'Mini Artisan Cake Present'
    ];
}

// -------------------------------------------------------------
// ACTION: check_status (supports ?order=SWC-XXXXX query parameter)
// -------------------------------------------------------------
if ($action === 'check_status') {
    $orderParam = trim($_GET['order'] ?? $_POST['order'] ?? $_POST['order_number'] ?? '');
    $status = getCustomerCampaignStatus($pdo, $orderParam ?: null);
    echo json_encode(array_merge(['success' => true], $status), JSON_UNESCAPED_UNICODE);
    exit;
}

// -------------------------------------------------------------
// ACTION: verify_order (Customer enters / switches order number)
// -------------------------------------------------------------
if ($action === 'verify_order' || $action === 'switch_order') {
    $inputOrder = trim($_POST['order_number'] ?? $_GET['order_number'] ?? '');

    if (empty($inputOrder)) {
        echo json_encode([
            'success' => false, 
            'message' => isJapanese() ? '注文番号を入力してください。' : 'Please enter an order number.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, order_number, grand_total, customer_name, status 
        FROM orders 
        WHERE order_number = ?
        LIMIT 1
    ");
    $stmt->execute([$inputOrder]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode([
            'success' => false, 
            'message' => isJapanese() ? '注文番号が見つかりませんでした。正しい注文番号を入力してください。' : 'Order number not found. Please verify the code.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($order['status'] === 'Cancelled') {
        echo json_encode([
            'success' => false, 
            'message' => isJapanese() ? '該当のご注文はキャンセルされています。' : 'This order has been cancelled.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $total = (int)$order['grand_total'];
    if ($total < 3000) {
        echo json_encode([
            'success' => false, 
            'message' => isJapanese() 
                ? "注文番号を確認しました（合計: ¥" . number_format($total) . "）。本特典は¥3,000以上ご購入のご注文が対象となります。"
                : "Order found (Total: ¥" . number_format($total) . "). This reward requires a purchase of ¥3,000 or above."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Set verified order in session and reset attempt session
    $_SESSION['verified_order_number'] = $order['order_number'];
    unset($_SESSION['active_challenge_attempt']);

    $status = getCustomerCampaignStatus($pdo, $order['order_number']);
    echo json_encode([
        'success' => true,
        'message' => isJapanese() 
            ? "認証完了！¥" . number_format($total) . "のご注文（#{$order['order_number']}）に切り替えました。残りチャレンジ回数: {$status['remaining_attempts']}/3回です！"
            : "Verified! Switched to order #{$order['order_number']} (¥" . number_format($total) . "). Remaining tries: {$status['remaining_attempts']}/3.",
        'status' => $status
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// -------------------------------------------------------------
// ACTION: start_attempt (Called when a game round begins)
// -------------------------------------------------------------
if ($action === 'start_attempt') {
    $status = getCustomerCampaignStatus($pdo);

    if (!$status['is_eligible']) {
        echo json_encode([
            'success' => true, 
            'mode' => 'normal_play', 
            'message' => 'Playing in free practice mode.'
        ]);
        exit;
    }

    if ($status['reward_claimed']) {
        echo json_encode([
            'success' => true, 
            'mode' => 'already_claimed', 
            'message' => 'Reward already claimed on this order.'
        ]);
        exit;
    }

    if ($status['remaining_attempts'] <= 0) {
        echo json_encode([
            'success' => true, 
            'mode' => 'attempts_exhausted', 
            'message' => isJapanese() ? '3回のチャレンジ回数をすべて使用しました。特典チャレンジは終了です。' : 'All 3 challenge attempts used.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Atomically increment attempts_used in database
    $upd = $pdo->prepare("
        UPDATE game_campaign_attempts 
        SET attempts_used = attempts_used + 1, updated_at = NOW() 
        WHERE order_id = ? AND attempts_used < 3
    ");
    $upd->execute([$status['order_id']]);

    // Refresh status
    $updatedStatus = getCustomerCampaignStatus($pdo);

    // Track active attempt in session
    $_SESSION['active_challenge_attempt'] = [
        'order_id' => $status['order_id'],
        'attempt_number' => $updatedStatus['attempts_used'],
        'started_at' => time()
    ];

    echo json_encode([
        'success' => true,
        'mode' => 'challenge_attempt',
        'attempts_used' => $updatedStatus['attempts_used'],
        'remaining_attempts' => $updatedStatus['remaining_attempts'],
        'message' => isJapanese() 
            ? "チャレンジ第 {$updatedStatus['attempts_used']} 回目スタート！（残り: {$updatedStatus['remaining_attempts']}回）" 
            : "Attempt {$updatedStatus['attempts_used']}/3 started!"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// -------------------------------------------------------------
// ACTION: record_attempt_finish (Called when game round ends < 2000)
// -------------------------------------------------------------
if ($action === 'record_attempt_finish') {
    $score = (int)($_POST['score'] ?? 0);
    $status = getCustomerCampaignStatus($pdo);

    if ($status['is_eligible']) {
        $upd = $pdo->prepare("
            UPDATE game_campaign_attempts 
            SET high_score = GREATEST(high_score, ?), updated_at = NOW() 
            WHERE order_id = ?
        ");
        $upd->execute([$score, $status['order_id']]);
    }

    unset($_SESSION['active_challenge_attempt']);

    echo json_encode([
        'success' => true,
        'score' => $score,
        'remaining_attempts' => $status['remaining_attempts']
    ]);
    exit;
}

// -------------------------------------------------------------
// ACTION: claim_reward (Player reaches >= 2,000 points within 3 attempts)
// -------------------------------------------------------------
if ($action === 'claim_reward') {
    $score = (int)($_POST['score'] ?? 0);
    $status = getCustomerCampaignStatus($pdo);

    if ($score < 2000) {
        echo json_encode([
            'success' => false, 
            'message' => isJapanese() ? 'スコアが目標の2,000点に達していません。' : 'Score has not reached 2,000 points.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$status['is_eligible']) {
        echo json_encode([
            'success' => false, 
            'needs_purchase' => true,
            'message' => isJapanese() ? '¥3,000以上のご注文番号の認証が必要です。' : 'Purchase of ¥3,000 or above required.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($status['reward_claimed']) {
        echo json_encode([
            'success' => false, 
            'already_claimed' => true,
            'order_number' => $status['order_number'],
            'message' => isJapanese() ? 'このご注文にはすでに特製ミニケーキ特典が追加されています。' : 'Reward already claimed on this order.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Must be within 3 attempts
    if ($status['attempts_used'] > 3) {
        echo json_encode([
            'success' => false, 
            'message' => isJapanese() ? '特典チャレンジ可能回数（3回）を超えています。' : 'Challenge attempts limit (3) exceeded.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $orderId = $status['order_id'];
    $orderNumber = $status['order_number'];

    // 1. Check if the free mini cake is already present in order_items
    $chkItem = $pdo->prepare("SELECT id FROM order_items WHERE order_id = ? AND product_name = 'パティシエ特製ミニケーキ'");
    $chkItem->execute([$orderId]);
    $alreadyInItems = $chkItem->fetch();

    if (!$alreadyInItems) {
        // Choose product_id: default to 1
        $prodId = 1;

        $giftName = 'パティシエ特製ミニケーキ';
        $summary = isJapanese() 
            ? '🎁 ミニゲーム特典（2,000点達成記念・無料プレゼント）' 
            : '🎁 Mini Game Reward (2,000 Pts Achievement - Free Present)';

        $insItem = $pdo->prepare("
            INSERT INTO order_items 
            (order_id, product_id, product_name, price, original_price, discount_amount, pairing_product_id, quantity, customization_summary, customization_price, subtotal)
            VALUES (?, ?, ?, 0, 500, 500, NULL, 1, ?, 0, 0)
        ");
        $insItem->execute([$orderId, $prodId, $giftName, $summary]);

        // 2. Append confirmation note to order
        $noteAddition = "\n[ミニゲーム特典] スコア2,000点達成記念 パティシエ特製ミニケーキ(¥0)を自動追加しました。";
        $updNote = $pdo->prepare("
            UPDATE orders 
            SET notes = CONCAT(COALESCE(notes, ''), ?), updated_at = NOW() 
            WHERE id = ?
        ");
        $updNote->execute([$noteAddition, $orderId]);
    }

    // 3. Mark reward as claimed in game_campaign_attempts
    $updCampaign = $pdo->prepare("
        UPDATE game_campaign_attempts 
        SET reward_claimed = 1, high_score = GREATEST(high_score, ?), claimed_at = NOW() 
        WHERE order_id = ?
    ");
    $updCampaign->execute([$score, $orderId]);

    // Clear active challenge attempt
    unset($_SESSION['active_challenge_attempt']);

    echo json_encode([
        'success' => true,
        'score' => $score,
        'order_id' => $orderId,
        'order_number' => $orderNumber,
        'customer_name' => $status['customer_name'],
        'gift_name' => 'パティシエ特製ミニケーキ',
        'order_url' => "../order-confirmation.php?order=" . urlencode($orderNumber),
        'message' => isJapanese() 
            ? "おめでとうございます！2,000点達成！ご注文（注文番号: #{$orderNumber}）に「パティシエ特製ミニケーキ（無料 ¥0）」を自動追加いたしました！"
            : "Congratulations! 2,000 points reached! Free Mini Artisan Cake (¥0) was automatically added to your order (#{$orderNumber})!"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
