<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlanRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\UserRepository;
use App\Support\Database;
use App\Support\Session;
use DateTimeImmutable;

final class AdminUserManagementService
{
    private UserRepository $users;
    private PlanRepository $plans;
    private SubscriptionRepository $subscriptions;

    public function __construct()
    {
        $db = Database::connection();
        $this->users = new UserRepository($db);
        $this->plans = new PlanRepository($db);
        $this->subscriptions = new SubscriptionRepository($db);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{
     *   rows: array<int, array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   per_page: int,
     *   last_page: int,
     *   q: string,
     *   status: string,
     *   user_id: int,
     *   selected_user: array<string, mixed>|null,
     *   plans: array<int, array<string, mixed>>
     * }
     */
    public function overview(array $query): array
    {
        $keyword = trim((string) ($query['q'] ?? ''));
        $status = strtoupper(trim((string) ($query['status'] ?? '')));
        if (! in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            $status = '';
        }

        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }

        $result = $this->users->paginateWithPlan($keyword, $status, $page, $perPage);
        $total = (int) $result['total'];
        $lastPage = max(1, (int) ceil($total / $perPage));
        $rows = is_array($result['rows'] ?? null) ? $result['rows'] : [];

        $selectedUserId = max(0, (int) ($query['user_id'] ?? 0));
        if ($selectedUserId <= 0 && $rows !== []) {
            $selectedUserId = (int) ($rows[0]['id'] ?? 0);
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
            'q' => $keyword,
            'status' => $status,
            'user_id' => $selectedUserId,
            'selected_user' => $selectedUserId > 0 ? $this->users->findWithPlanById($selectedUserId) : null,
            'plans' => $this->plans->listAll(),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function updateProfile(int $userId, string $username, string $email, string $status, string $actorLabel): array
    {
        $user = $this->users->findWithPlanById($userId);
        if ($user === null) {
            return ['ok' => false, 'message' => 'User tidak ditemukan.'];
        }

        $username = trim($username);
        $email = strtolower(trim($email));
        $status = strtoupper(trim($status));

        if ($username === '' || strlen($username) < 3) {
            return ['ok' => false, 'message' => 'Username minimal 3 karakter.'];
        }
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Email tidak valid.'];
        }
        if (! in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            return ['ok' => false, 'message' => 'Status user tidak valid.'];
        }
        if ($this->users->findByUsernameExcludingId($username, $userId) !== null) {
            return ['ok' => false, 'message' => 'Username sudah dipakai user lain.'];
        }
        if ($this->users->findByEmailExcludingId($email, $userId) !== null) {
            return ['ok' => false, 'message' => 'Email sudah dipakai user lain.'];
        }

        $this->users->updateProfile($userId, $username, $email, $status);
        $this->subscriptions->createAdminAction([
            'user_id' => $userId,
            'action_type' => 'MANAGE_USER_PROFILE',
            'plan_id' => (int) ($user['plan_id'] ?? 0),
            'actor_label' => $actorLabel,
            'notes' => sprintf(
                'profile update username:%s->%s email:%s->%s status:%s->%s',
                (string) ($user['username'] ?? '-'),
                $username,
                (string) ($user['email'] ?? '-'),
                $email,
                (string) ($user['status'] ?? '-'),
                $status
            ),
        ]);

        $this->refreshAuthSessionIfNeeded($userId);

        return ['ok' => true, 'message' => 'Profil user berhasil diperbarui.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function resetPassword(int $userId, string $password, string $passwordConfirmation, string $actorLabel): array
    {
        $user = $this->users->findWithPlanById($userId);
        if ($user === null) {
            return ['ok' => false, 'message' => 'User tidak ditemukan.'];
        }

        if ($password === '' || strlen($password) < 8) {
            return ['ok' => false, 'message' => 'Password minimal 8 karakter.'];
        }
        if ($password !== $passwordConfirmation) {
            return ['ok' => false, 'message' => 'Konfirmasi password tidak sama.'];
        }

        $this->users->updatePasswordHash($userId, password_hash($password, PASSWORD_DEFAULT));
        $this->subscriptions->createAdminAction([
            'user_id' => $userId,
            'action_type' => 'MANAGE_USER_PASSWORD',
            'plan_id' => (int) ($user['plan_id'] ?? 0),
            'actor_label' => $actorLabel,
            'notes' => 'password reset by admin',
        ]);

        return ['ok' => true, 'message' => 'Password user berhasil diganti.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function updatePlan(int $userId, string $planCode, string $expiredAtInput, string $actorLabel): array
    {
        $user = $this->users->findWithPlanById($userId);
        if ($user === null) {
            return ['ok' => false, 'message' => 'User tidak ditemukan.'];
        }

        $targetPlan = $this->plans->findByCode($planCode);
        if ($targetPlan === null) {
            return ['ok' => false, 'message' => 'Plan tujuan tidak valid.'];
        }

        $targetCode = strtoupper((string) ($targetPlan['code'] ?? 'FREE'));
        $newExpiredAt = null;
        if ($targetCode !== 'FREE') {
            $normalized = trim($expiredAtInput);
            if ($normalized === '') {
                return ['ok' => false, 'message' => 'Expired at wajib diisi untuk plan berbayar.'];
            }

            $normalized = str_replace('T', ' ', $normalized);
            if (strlen($normalized) === 16) {
                $normalized .= ':00';
            }

            $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $normalized);
            if (! $date instanceof DateTimeImmutable) {
                try {
                    $date = new DateTimeImmutable($normalized);
                } catch (\Throwable) {
                    return ['ok' => false, 'message' => 'Format expired at tidak valid.'];
                }
            }
            $newExpiredAt = $date->format('Y-m-d H:i:s');
        }

        $oldPlanId = (int) ($user['plan_id'] ?? 0);
        $oldExpiredAt = is_string($user['plan_expired_at'] ?? null) ? (string) $user['plan_expired_at'] : null;
        $now = new DateTimeImmutable('now');

        $this->users->updatePlan($userId, (int) $targetPlan['id'], $newExpiredAt);

        $subscriptionId = $this->subscriptions->create([
            'user_id' => $userId,
            'plan_id' => (int) $targetPlan['id'],
            'duration_type' => $targetCode === 'FREE' ? 'FREE' : 'ADMIN_SET',
            'duration_days' => 0,
            'started_at' => $now->format('Y-m-d H:i:s'),
            'expired_at' => $targetCode === 'FREE' ? $now->format('Y-m-d H:i:s') : $newExpiredAt,
            'status' => 'ACTIVE',
            'source_type' => 'MANUAL_ADMIN',
            'source_reference' => 'admin-user-management',
            'notes' => 'Admin user management direct plan change',
        ]);

        $this->subscriptions->createLog([
            'subscription_id' => $subscriptionId,
            'user_id' => $userId,
            'action_type' => $oldPlanId === (int) $targetPlan['id'] ? 'EXTEND' : 'CHANGE_PLAN',
            'old_plan_id' => $oldPlanId,
            'new_plan_id' => (int) $targetPlan['id'],
            'old_expired_at' => $oldExpiredAt,
            'new_expired_at' => $newExpiredAt,
            'actor_label' => $actorLabel,
            'notes' => 'Direct plan management from admin users page',
        ]);

        $this->subscriptions->createAdminAction([
            'user_id' => $userId,
            'action_type' => 'MANAGE_USER_PLAN',
            'plan_id' => (int) $targetPlan['id'],
            'duration_type' => $targetCode === 'FREE' ? null : 'ADMIN_SET',
            'duration_days' => null,
            'actor_label' => $actorLabel,
            'notes' => $targetCode === 'FREE'
                ? 'downgrade to FREE via admin users page'
                : 'set plan to ' . $targetCode . ' until ' . $newExpiredAt,
        ]);

        $this->refreshAuthSessionIfNeeded($userId);

        return [
            'ok' => true,
            'message' => $targetCode === 'FREE'
                ? 'Plan user berhasil diturunkan ke FREE.'
                : 'Plan user berhasil diperbarui.',
        ];
    }

    /**
     * @return array{ok: bool, message: string, deleted_self?: bool}
     */
    public function deletePermanent(int $userId): array
    {
        $user = $this->users->findWithPlanById($userId);
        if ($user === null) {
            return ['ok' => false, 'message' => 'User tidak ditemukan.'];
        }

        $db = Database::connection();

        try {
            $db->beginTransaction();

            $this->users->deleteCalculatorRecipeLibraryByUserId($userId);
            $this->users->deleteMarketPricesByUserId($userId);
            $this->users->deleteCalculationHistoriesByUserId($userId);
            $this->users->deleteAdminSubscriptionActionsByUserId($userId);
            $this->users->deleteReferralRewardsByUserIdOrReferralRelation($userId);
            $this->users->deleteReferralsByUserId($userId);
            $this->users->deleteSubscriptionLogsByUserId($userId);
            $this->users->deleteSubscriptionsByUserId($userId);
            $this->users->deleteById($userId);

            $db->commit();
        } catch (\Throwable) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            return ['ok' => false, 'message' => 'Hapus akun permanen gagal diproses.'];
        }

        $auth = Session::get('auth');
        $deletedSelf = is_array($auth) && (int) ($auth['user_id'] ?? 0) === $userId;

        return [
            'ok' => true,
            'message' => 'Akun user berhasil dihapus permanen.',
            'deleted_self' => $deletedSelf,
        ];
    }

    private function refreshAuthSessionIfNeeded(int $userId): void
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || (int) ($auth['user_id'] ?? 0) !== $userId) {
            return;
        }

        $fresh = $this->users->findWithPlanById($userId);
        if ($fresh === null) {
            return;
        }

        Session::put('auth', [
            'user_id' => (int) $fresh['id'],
            'username' => (string) ($fresh['username'] ?? ''),
            'email' => (string) ($fresh['email'] ?? ''),
            'plan_id' => (int) ($fresh['plan_id'] ?? 0),
            'plan_code' => (string) ($fresh['plan_code'] ?? 'FREE'),
            'plan_name' => (string) ($fresh['plan_name'] ?? 'Free'),
            'plan_expired_at' => $fresh['plan_expired_at'] ?? null,
            'session_token' => (string) ($auth['session_token'] ?? ''),
        ]);
    }
}
