<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => trim($username)]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmailExcludingId(string $email, int $excludeUserId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email AND id <> :id LIMIT 1');
        $stmt->execute([
            'email' => strtolower(trim($email)),
            'id' => $excludeUserId,
        ]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUsernameExcludingId(string $username, int $excludeUserId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :username AND id <> :id LIMIT 1');
        $stmt->execute([
            'username' => trim($username),
            'id' => $excludeUserId,
        ]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByReferralCode(string $referralCode): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE referral_code = :code LIMIT 1');
        $stmt->execute(['code' => strtoupper(trim($referralCode))]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findIdByReferralCode(string $referralCode): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE referral_code = :code LIMIT 1');
        $stmt->execute(['code' => strtoupper(trim($referralCode))]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return null;
        }

        $id = (int) $value;
        return $id > 0 ? $id : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithPlanById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.*, p.code AS plan_code, p.name AS plan_name
             FROM users u
             JOIN plans p ON p.id = u.plan_id
             WHERE u.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, email, password_hash, referral_code, referred_by_code, plan_id, plan_expired_at, status)
             VALUES (:username, :email, :password_hash, :referral_code, :referred_by_code, :plan_id, :plan_expired_at, :status)'
        );

        $stmt->execute([
            'username' => $payload['username'],
            'email' => $payload['email'],
            'password_hash' => $payload['password_hash'],
            'referral_code' => $payload['referral_code'],
            'referred_by_code' => $payload['referred_by_code'],
            'plan_id' => $payload['plan_id'],
            'plan_expired_at' => $payload['plan_expired_at'],
            'status' => $payload['status'] ?? 'ACTIVE',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updatePlan(int $userId, int $planId, ?string $expiredAt): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET plan_id = :plan_id, plan_expired_at = :plan_expired_at, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $userId,
            'plan_id' => $planId,
            'plan_expired_at' => $expiredAt,
        ]);
    }

    public function updateProfile(int $userId, string $username, string $email, string $status): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET username = :username,
                 email = :email,
                 status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $userId,
            'username' => trim($username),
            'email' => strtolower(trim($email)),
            'status' => strtoupper(trim($status)),
        ]);
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $userId,
            'password_hash' => $passwordHash,
        ]);
    }

    public function deleteCalculatorRecipeLibraryByUserId(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM calculator_recipe_library WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    public function deleteMarketPricesByUserId(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM market_prices WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    public function deleteCalculationHistoriesByUserId(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM calculation_histories WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    public function deleteAdminSubscriptionActionsByUserId(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM admin_subscription_actions WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    public function deleteReferralRewardsByUserIdOrReferralRelation(int $userId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM referral_rewards
             WHERE rewarded_user_id = :user_id
                OR referral_id IN (
                    SELECT id
                    FROM referrals
                    WHERE referrer_user_id = :user_id_referrer
                       OR referred_user_id = :user_id_referred
                )'
        );
        $stmt->execute([
            'user_id' => $userId,
            'user_id_referrer' => $userId,
            'user_id_referred' => $userId,
        ]);
    }

    public function deleteReferralsByUserId(int $userId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM referrals
             WHERE referrer_user_id = :user_id_referrer
                OR referred_user_id = :user_id_referred'
        );
        $stmt->execute([
            'user_id_referrer' => $userId,
            'user_id_referred' => $userId,
        ]);
    }

    public function deleteSubscriptionLogsByUserId(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM subscription_logs WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    public function deleteSubscriptionsByUserId(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM subscriptions WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    public function deleteById(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function paginateWithPlan(string $keyword = '', string $status = '', int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];

        if ($keyword !== '') {
            $where[] = '(u.username LIKE :q OR u.email LIKE :q OR u.referral_code LIKE :q)';
            $params['q'] = '%' . $keyword . '%';
        }

        if ($status !== '') {
            $where[] = 'u.status = :status';
            $params['status'] = strtoupper(trim($status));
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM users u
             JOIN plans p ON p.id = u.plan_id
             WHERE {$whereSql}"
        );
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT
                u.*,
                p.code AS plan_code,
                p.name AS plan_name
             FROM users u
             JOIN plans p ON p.id = u.plan_id
             WHERE {$whereSql}
             ORDER BY u.id DESC
             LIMIT :limit_value OFFSET :offset_value"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit_value', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset_value', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return [
            'rows' => is_array($rows) ? $rows : [],
            'total' => $total,
        ];
    }
}
