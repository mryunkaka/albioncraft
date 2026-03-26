<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlanRepository;
use App\Repositories\UserRepository;
use App\Support\Database;
use DateTimeImmutable;
use RuntimeException;

final class SubscriptionService
{
    private UserRepository $users;
    private PlanRepository $plans;

    public function __construct()
    {
        $db = Database::connection();
        $this->users = new UserRepository($db);
        $this->plans = new PlanRepository($db);
    }

    /**
     * Sync plan user berdasarkan expiry.
     * Jika expired, user otomatis downgrade ke FREE.
     *
     * @return array<string, mixed>|null
     */
    public function syncUserPlan(int $userId): ?array
    {
        $user = $this->users->findWithPlanById($userId);
        if ($user === null) {
            return null;
        }

        $expiredAtRaw = $user['plan_expired_at'] ?? null;
        if (! is_string($expiredAtRaw) || trim($expiredAtRaw) === '') {
            return $user;
        }

        $expiredAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $expiredAtRaw)
            ?: new DateTimeImmutable($expiredAtRaw);
        $now = new DateTimeImmutable('now');

        if ($expiredAt > $now) {
            return $user;
        }

        $freePlan = $this->plans->findByCode('FREE');
        if ($freePlan === null) {
            throw new RuntimeException('Plan FREE tidak ditemukan. Pastikan seed plans sudah dijalankan.');
        }

        $freePlanId = (int) $freePlan['id'];
        if ((int) $user['plan_id'] !== $freePlanId || $expiredAtRaw !== null) {
            $this->users->updatePlan($userId, $freePlanId, null);
        }

        return $this->users->findWithPlanById($userId);
    }

    public function userCanAccessFeature(int $userId, string $featureKey): bool
    {
        $user = $this->syncUserPlan($userId);
        if ($user === null) {
            return false;
        }

        return $this->plans->hasFeature((int) $user['plan_id'], $featureKey);
    }
}

