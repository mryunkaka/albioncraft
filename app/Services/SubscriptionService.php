<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlanRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\UserRepository;
use App\Support\Database;
use DateTimeImmutable;
use RuntimeException;

final class SubscriptionService
{
    private UserRepository $users;
    private PlanRepository $plans;
    private SubscriptionRepository $subscriptions;

    /**
     * @var array<string, int>
     */
    private const DURATIONS = [
        'DAILY' => 1,
        'WEEKLY' => 7,
        'MONTHLY' => 30,
        'YEARLY' => 365,
    ];

    public function __construct()
    {
        $db = Database::connection();
        $this->users = new UserRepository($db);
        $this->plans = new PlanRepository($db);
        $this->subscriptions = new SubscriptionRepository($db);
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

    /**
     * @return array{
     *   user: array<string, mixed>|null,
     *   current_subscription: array<string, mixed>|null,
     *   subscription_logs: array<int, array<string, mixed>>,
     *   plans: array<int, array<string, mixed>>,
     *   durations: array<int, array{code: string, days: int}>
     * }
     */
    public function overview(int $userId): array
    {
        $user = $this->syncUserPlan($userId);
        $subscription = $this->subscriptions->findLatestByUserId($userId);
        $logs = $this->subscriptions->listLogsByUserId($userId, 20);
        $plans = $this->plans->listAll();

        $durations = [];
        foreach (self::DURATIONS as $code => $days) {
            $durations[] = ['code' => $code, 'days' => $days];
        }

        return [
            'user' => $user,
            'current_subscription' => $subscription,
            'subscription_logs' => $logs,
            'plans' => $plans,
            'durations' => $durations,
        ];
    }

    /**
     * V1 manual admin: user hanya membuat request extend, bukan auto aktivasi.
     *
     * @return array{ok: bool, message: string}
     */
    public function requestExtend(int $userId, string $planCode, string $durationType): array
    {
        $plan = $this->plans->findByCode($planCode);
        if ($plan === null) {
            return ['ok' => false, 'message' => 'Plan tidak valid.'];
        }

        $durationType = strtoupper(trim($durationType));
        $days = self::DURATIONS[$durationType] ?? null;
        if ($days === null) {
            return ['ok' => false, 'message' => 'Durasi tidak valid.'];
        }

        $this->subscriptions->createAdminAction([
            'user_id' => $userId,
            'action_type' => 'REQUEST_EXTEND',
            'plan_id' => (int) $plan['id'],
            'duration_type' => $durationType,
            'duration_days' => $days,
            'actor_label' => 'USER',
            'notes' => 'Request extend dari halaman Subscription (manual admin v1).',
        ]);

        return [
            'ok' => true,
            'message' => 'Request extend tersimpan. Menunggu aktivasi admin.',
        ];
    }

    /**
     * Helper internal untuk reward referral/aksi sistem lain.
     * Extend dari expired_at bila masih aktif, atau dari sekarang jika sudah lewat.
     */
    public function extendUserPlanByDays(
        int $userId,
        int $days,
        string $sourceType = 'REFERRAL_REWARD',
        ?string $sourceReference = null,
        ?string $notes = null
    ): void {
        if ($days <= 0) {
            return;
        }

        $user = $this->users->findWithPlanById($userId);
        if ($user === null) {
            return;
        }

        $now = new DateTimeImmutable('now');
        $currentExpiryRaw = $user['plan_expired_at'] ?? null;
        $baseStart = $now;
        if (is_string($currentExpiryRaw) && trim($currentExpiryRaw) !== '') {
            $currentExpiry = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $currentExpiryRaw)
                ?: new DateTimeImmutable($currentExpiryRaw);
            if ($currentExpiry > $now) {
                $baseStart = $currentExpiry;
            }
        }

        $newExpiry = $baseStart->modify('+' . $days . ' days');
        $newExpirySql = $newExpiry->format('Y-m-d H:i:s');
        $oldExpirySql = is_string($currentExpiryRaw) && trim($currentExpiryRaw) !== '' ? $currentExpiryRaw : null;

        $this->users->updatePlan(
            (int) $user['id'],
            (int) $user['plan_id'],
            $newExpirySql
        );

        $subscriptionId = $this->subscriptions->create([
            'user_id' => (int) $user['id'],
            'plan_id' => (int) $user['plan_id'],
            'duration_type' => 'DAYS',
            'duration_days' => $days,
            'started_at' => $now->format('Y-m-d H:i:s'),
            'expired_at' => $newExpirySql,
            'status' => 'ACTIVE',
            'source_type' => $sourceType,
            'source_reference' => $sourceReference,
            'notes' => $notes,
        ]);

        $this->subscriptions->createLog([
            'subscription_id' => $subscriptionId,
            'user_id' => (int) $user['id'],
            'action_type' => 'EXTEND',
            'old_plan_id' => (int) $user['plan_id'],
            'new_plan_id' => (int) $user['plan_id'],
            'old_expired_at' => $oldExpirySql,
            'new_expired_at' => $newExpirySql,
            'actor_label' => 'SYSTEM',
            'notes' => $notes ?? ('Auto extend +' . $days . ' days'),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pendingRequests(int $limit = 100): array
    {
        return $this->subscriptions->listPendingExtendRequests($limit);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function approveRequest(int $requestActionId, string $actorLabel): array
    {
        $request = $this->subscriptions->findAdminActionById($requestActionId);
        if ($request === null || (string) ($request['action_type'] ?? '') !== 'REQUEST_EXTEND') {
            return ['ok' => false, 'message' => 'Request tidak ditemukan.'];
        }
        if ($this->subscriptions->hasApprovedRequest($requestActionId)) {
            return ['ok' => false, 'message' => 'Request sudah pernah di-approve.'];
        }

        $userId = (int) ($request['user_id'] ?? 0);
        $planId = (int) ($request['plan_id'] ?? 0);
        $durationDays = (int) ($request['duration_days'] ?? 0);
        $durationType = (string) (($request['duration_type'] ?? 'DAYS'));
        if ($userId <= 0 || $planId <= 0 || $durationDays <= 0) {
            return ['ok' => false, 'message' => 'Data request tidak valid.'];
        }

        $user = $this->users->findWithPlanById($userId);
        if ($user === null) {
            return ['ok' => false, 'message' => 'User tidak ditemukan.'];
        }

        $oldPlanId = (int) $user['plan_id'];
        $oldExpiredAt = is_string($user['plan_expired_at'] ?? null) ? (string) $user['plan_expired_at'] : null;
        $now = new DateTimeImmutable('now');

        $baseStart = $now;
        if ($oldExpiredAt !== null && trim($oldExpiredAt) !== '') {
            $currentExpiry = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $oldExpiredAt)
                ?: new DateTimeImmutable($oldExpiredAt);
            if ($currentExpiry > $now) {
                $baseStart = $currentExpiry;
            }
        }

        $newExpiry = $baseStart->modify('+' . $durationDays . ' days');
        $newExpirySql = $newExpiry->format('Y-m-d H:i:s');

        $this->users->updatePlan($userId, $planId, $newExpirySql);

        $subscriptionId = $this->subscriptions->create([
            'user_id' => $userId,
            'plan_id' => $planId,
            'duration_type' => $durationType,
            'duration_days' => $durationDays,
            'started_at' => $now->format('Y-m-d H:i:s'),
            'expired_at' => $newExpirySql,
            'status' => 'ACTIVE',
            'source_type' => 'MANUAL_ADMIN',
            'source_reference' => (string) $requestActionId,
            'notes' => 'Approved from request #' . $requestActionId,
        ]);

        $this->subscriptions->createLog([
            'subscription_id' => $subscriptionId,
            'user_id' => $userId,
            'action_type' => $oldPlanId === $planId ? 'EXTEND' : 'CHANGE_PLAN',
            'old_plan_id' => $oldPlanId,
            'new_plan_id' => $planId,
            'old_expired_at' => $oldExpiredAt,
            'new_expired_at' => $newExpirySql,
            'actor_label' => $actorLabel,
            'notes' => 'Approval request #' . $requestActionId,
        ]);

        $this->subscriptions->createAdminAction([
            'user_id' => $userId,
            'action_type' => 'APPROVE_EXTEND',
            'plan_id' => $planId,
            'duration_type' => $durationType,
            'duration_days' => $durationDays,
            'actor_label' => $actorLabel,
            'notes' => 'request_action_id=' . $requestActionId,
        ]);

        return ['ok' => true, 'message' => 'Request berhasil di-approve.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function rejectRequest(int $requestActionId, string $actorLabel): array
    {
        $request = $this->subscriptions->findAdminActionById($requestActionId);
        if ($request === null || (string) ($request['action_type'] ?? '') !== 'REQUEST_EXTEND') {
            return ['ok' => false, 'message' => 'Request tidak ditemukan.'];
        }
        if ($this->subscriptions->hasApprovedRequest($requestActionId)) {
            return ['ok' => false, 'message' => 'Request sudah diproses (approved).'];
        }

        $this->subscriptions->createAdminAction([
            'user_id' => (int) ($request['user_id'] ?? 0),
            'action_type' => 'REJECT_EXTEND',
            'plan_id' => (int) ($request['plan_id'] ?? 0),
            'duration_type' => (string) ($request['duration_type'] ?? 'DAYS'),
            'duration_days' => (int) ($request['duration_days'] ?? 0),
            'actor_label' => $actorLabel,
            'notes' => 'request_action_id=' . $requestActionId,
        ]);

        return ['ok' => true, 'message' => 'Request ditolak.'];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{
     *   rows: array<int, array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   per_page: int,
     *   last_page: int,
     *   action_type: string,
     *   q: string
     * }
     */
    public function adminActionHistory(array $query): array
    {
        $actionType = strtoupper(trim((string) ($query['action_type'] ?? '')));
        $allowed = [
            'REQUEST_EXTEND',
            'APPROVE_EXTEND',
            'REJECT_EXTEND',
        ];
        if (! in_array($actionType, $allowed, true)) {
            $actionType = '';
        }

        $keyword = trim((string) ($query['q'] ?? ''));
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 30);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 30;
        }

        $result = $this->subscriptions->paginateAdminActions($actionType, $keyword, $page, $perPage);
        $total = (int) $result['total'];
        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'rows' => $result['rows'],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
            'action_type' => $actionType,
            'q' => $keyword,
        ];
    }
}
