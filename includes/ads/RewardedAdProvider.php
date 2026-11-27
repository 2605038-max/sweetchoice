<?php
/**
 * Sweet Choice - Modular Rewarded Advertisement Provider Interface
 *
 * Allows DemoRewardedAdProvider (school project mode) to be replaced in the future
 * with a genuine rewarded-ad provider using verified server-to-server reward callbacks.
 */

interface RewardedAdProvider {
    /**
     * Provider identifier (e.g., 'demo', 'google_ima', etc.)
     */
    public function getProviderName(): string;

    /**
     * Start a rewarded advertisement session on the server and issue a unique transaction ID.
     *
     * @return array{success:bool, error?:string, reward_txn_id?:string, sequence_no?:int, required_seconds?:int, provider?:string}
     */
    public function startAd(PDO $pdo, array $checkoutSession, int $userId, array $totals): array;

    /**
     * Verify advertisement completion server-side and grant exactly one additional game attempt.
     *
     * @return array{success:bool, error?:string, ad_reward_id?:int, sequence_no?:int, message?:string}
     */
    public function verifyAndCompleteAd(PDO $pdo, array $checkoutSession, int $userId, string $rewardTxnId, array $payload = []): array;
}
