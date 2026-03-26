<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ReferralRepository;
use App\Repositories\UserRepository;
use App\Support\Database;

final class ReferralService
{
    private UserRepository $users;
    private ReferralRepository $referrals;
    private SubscriptionService $subscriptions;

    private const DEFAULT_REWARD_DAYS = 3;

    public function __construct()
    {
        $db = Database::connection();
        $this->users = new UserRepository($db);
        $this->referrals = new ReferralRepository($db);
        $this->subscriptions = new SubscriptionService();
    }

    /**
     * Trigger setelah register user baru.
     */
    public function processRegistrationReferral(int $newUserId, ?string $referredByCode): void
    {
        $code = strtoupper(trim((string) $referredByCode));
        if ($code === '') {
            return;
        }

        if ($newUserId <= 0) {
            return;
        }

        $existing = $this->referrals->findByReferredUserId($newUserId);
        if ($existing !== null) {
            return;
        }

        $referrerId = $this->users->findIdByReferralCode($code);
        if ($referrerId === null || $referrerId <= 0 || $referrerId === $newUserId) {
            return;
        }

        $referrer = $this->users->findById($referrerId);
        $referred = $this->users->findById($newUserId);
        if ($referrer === null || $referred === null) {
            return;
        }

        $referralId = $this->referrals->create([
            'referrer_user_id' => $referrerId,
            'referred_user_id' => $newUserId,
            'referral_code_used' => $code,
            'status' => 'VALID',
        ]);

        if ($referralId <= 0) {
            return;
        }

        $days = $this->rewardDays();
        $this->referrals->createReward([
            'referral_id' => $referralId,
            'rewarded_user_id' => (int) $referrer['id'],
            'reward_type' => 'SUBSCRIPTION_DAYS',
            'reward_days' => $days,
            'notes' => 'Referral reward dari user baru #' . $newUserId,
        ]);

        $this->subscriptions->extendUserPlanByDays(
            (int) $referrer['id'],
            $days,
            'REFERRAL_REWARD',
            (string) $referralId,
            'Auto reward referral +' . $days . ' hari'
        );
    }

    /**
     * @return array{referral_code: string, rewards: array<int, array<string, mixed>>}
     */
    public function overview(int $userId): array
    {
        $user = $this->users->findById($userId);
        return [
            'referral_code' => (string) ($user['referral_code'] ?? '-'),
            'rewards' => $this->referrals->listRewardsByUserId($userId, 30),
        ];
    }

    private function rewardDays(): int
    {
        $value = getenv('REFERRAL_REWARD_DAYS');
        if ($value === false) {
            return self::DEFAULT_REWARD_DAYS;
        }

        $days = (int) $value;
        return $days > 0 ? $days : self::DEFAULT_REWARD_DAYS;
    }
}
