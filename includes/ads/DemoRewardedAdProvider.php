<?php
/**
 * Sweet Choice - Demo Rewarded Advertisement Mode (School Project Implementation)
 *
 * Clearly labeled simulated advertisement provider:
 * - No external advertising account required.
 * - Generates no real advertising revenue.
 * - Enforces server-side attempt/ad caps (max 2 ads per checkout session),
 *   single-use reward_txn_id, and a 5-second server-clock minimum view duration.
 *
 * NOTE: In a production environment with a real ad network, replace this provider
 * with one that verifies cryptographically signed server-to-server (S2S) reward callbacks.
 */

require_once __DIR__ . '/RewardedAdProvider.php';
require_once __DIR__ . '/../checkout.php';

class DemoRewardedAdProvider implements RewardedAdProvider {
    public function getProviderName(): string {
        return 'demo';
    }

    public function startAd(PDO $pdo, array $checkoutSession, int $userId, array $totals): array {
        if (empty($totals['is_minigame_eligible'])) {
            return ['success' => false, 'error' => __('minigame_not_qualified_msg', 'Your order does not meet the ¥3,000 minimum purchase requirement.')];
        }

        $sessionId = (int)$checkoutSession['id'];

        try {
            $pdo->beginTransaction();

            // Lock checkout session row to prevent race conditions
            $lockStmt = $pdo->prepare("SELECT id, status FROM checkout_sessions WHERE id = ? AND user_id = ? FOR UPDATE");
            $lockStmt->execute([$sessionId, $userId]);
            $locked = $lockStmt->fetch();
            if (!$locked || $locked['status'] !== 'active') {
                $pdo->rollBack();
                return ['success' => false, 'error' => __('auth_csrf_expired', 'Session expired. Please refresh and try again.')];
            }

            // Ensure no active unfinished game attempt
            $activeAtt = $pdo->prepare("SELECT id FROM checkout_game_attempts WHERE checkout_session_id = ? AND status = 'started' LIMIT 1");
            $activeAtt->execute([$sessionId]);
            if ($activeAtt->fetch()) {
                $pdo->rollBack();
                return ['success' => false, 'error' => __('ad_finish_before_ad', 'Please finish your current attempt before watching an advertisement.')];
            }

            // Count granted ads and used attempts
            $grantedStmt = $pdo->prepare("SELECT COUNT(*) FROM checkout_ad_rewards WHERE checkout_session_id = ? AND status = 'granted'");
            $grantedStmt->execute([$sessionId]);
            $adsGranted = (int)$grantedStmt->fetchColumn();

            if ($adsGranted >= MINIGAME_MAX_ADS) {
                $pdo->rollBack();
                return ['success' => false, 'error' => __('ad_limit_reached', 'Maximum of 2 advertisements per order reached.')];
            }

            $attCountStmt = $pdo->prepare("SELECT COUNT(*) FROM checkout_game_attempts WHERE checkout_session_id = ?");
            $attCountStmt->execute([$sessionId]);
            $attemptsUsed = (int)$attCountStmt->fetchColumn();

            if ($attemptsUsed >= MINIGAME_MAX_ATTEMPTS) {
                $pdo->rollBack();
                return ['success' => false, 'error' => __('minigame_all_attempts_used', 'You have used all 3 attempts for this order.')];
            }

            // Must finish currently unlocked attempts before unlocking another via ad
            $unlockedSoFar = 1 + $adsGranted;
            if ($attemptsUsed < $unlockedSoFar) {
                $pdo->rollBack();
                return ['success' => false, 'error' => __('ad_finish_before_ad', 'Please use your currently available attempt before watching an advertisement.')];
            }

            $nextSeq = $adsGranted + 1; // 1 or 2

            // Check if a pending ad record already exists for this sequence number (e.g., user cancelled earlier)
            $pendStmt = $pdo->prepare("SELECT id, reward_txn_id FROM checkout_ad_rewards WHERE checkout_session_id = ? AND sequence_no = ? FOR UPDATE");
            $pendStmt->execute([$sessionId, $nextSeq]);
            $existingPend = $pendStmt->fetch();

            $txnId = bin2hex(random_bytes(32));

            if ($existingPend) {
                // Rotate transaction ID and reset server start timestamp
                $upd = $pdo->prepare("
                    UPDATE checkout_ad_rewards
                    SET reward_txn_id = ?, status = 'pending', demo_completed = 0, started_at = NOW(), completed_at = NULL
                    WHERE id = ? AND status = 'pending'
                ");
                $upd->execute([$txnId, $existingPend['id']]);
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO checkout_ad_rewards
                        (checkout_session_id, user_id, provider, reward_txn_id, sequence_no, demo_completed, status, started_at, created_at)
                    VALUES (?, ?, 'demo', ?, ?, 0, 'pending', NOW(), NOW())
                ");
                $ins->execute([$sessionId, $userId, $txnId, $nextSeq]);
            }

            $pdo->commit();

            return [
                'success' => true,
                'provider' => $this->getProviderName(),
                'reward_txn_id' => $txnId,
                'sequence_no' => $nextSeq,
                'required_seconds' => MINIGAME_DEMO_AD_SECONDS,
            ];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('DemoRewardedAdProvider::startAd error: ' . $e->getMessage());
            return ['success' => false, 'error' => __('checkout_err_unexpected', 'An unexpected error occurred. Please try again.')];
        }
    }

    public function verifyAndCompleteAd(PDO $pdo, array $checkoutSession, int $userId, string $rewardTxnId, array $payload = []): array {
        $rewardTxnId = trim($rewardTxnId);
        if ($rewardTxnId === '') {
            return ['success' => false, 'error' => __('ad_duplicate_error', 'Invalid advertisement reward transaction.')];
        }

        $sessionId = (int)$checkoutSession['id'];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, checkout_session_id, user_id, sequence_no, status,
                       TIMESTAMPDIFF(SECOND, started_at, NOW()) AS elapsed_seconds
                FROM checkout_ad_rewards
                WHERE reward_txn_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$rewardTxnId]);
            $reward = $stmt->fetch();

            if (!$reward || (int)$reward['checkout_session_id'] !== $sessionId || (int)$reward['user_id'] !== $userId) {
                $pdo->rollBack();
                return ['success' => false, 'error' => __('ad_duplicate_error', 'Invalid advertisement reward transaction.')];
            }

            // Prevent duplicate reward claims
            if ($reward['status'] !== 'pending') {
                $pdo->rollBack();
                return ['success' => false, 'error' => __('ad_duplicate_error', 'This advertisement reward has already been processed.')];
            }

            // Server-clock minimum view check (prevents instant button-click unlock)
            $elapsed = (int)$reward['elapsed_seconds'];
            if ($elapsed < MINIGAME_DEMO_AD_SECONDS) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'error' => __('ad_too_early', 'Please finish watching the 5-second demo advertisement before claiming your attempt.'),
                    'remaining_seconds' => MINIGAME_DEMO_AD_SECONDS - $elapsed,
                ];
            }

            // Double-check hard cap of 2 granted ads per session
            $grantedStmt = $pdo->prepare("SELECT COUNT(*) FROM checkout_ad_rewards WHERE checkout_session_id = ? AND status = 'granted'");
            $grantedStmt->execute([$sessionId]);
            if ((int)$grantedStmt->fetchColumn() >= MINIGAME_MAX_ADS) {
                $pdo->rollBack();
                return ['success' => false, 'error' => __('ad_limit_reached', 'Maximum of 2 advertisements per order reached.')];
            }

            $upd = $pdo->prepare("
                UPDATE checkout_ad_rewards
                SET demo_completed = 1, status = 'granted', completed_at = NOW()
                WHERE id = ? AND status = 'pending'
            ");
            $upd->execute([$reward['id']]);

            $pdo->commit();

            return [
                'success' => true,
                'ad_reward_id' => (int)$reward['id'],
                'sequence_no' => (int)$reward['sequence_no'],
                'message' => __('ad_unlocked_success', 'Advertisement complete! 1 additional attempt has been unlocked.'),
            ];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('DemoRewardedAdProvider::verifyAndCompleteAd error: ' . $e->getMessage());
            return ['success' => false, 'error' => __('checkout_err_unexpected', 'An unexpected error occurred. Please try again.')];
        }
    }
}
