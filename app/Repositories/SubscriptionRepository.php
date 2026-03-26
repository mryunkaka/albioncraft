<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SubscriptionRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLatestByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, p.code AS plan_code, p.name AS plan_name
             FROM subscriptions s
             JOIN plans p ON p.id = s.plan_id
             WHERE s.user_id = :user_id
             ORDER BY s.id DESC
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listLogsByUserId(int $userId, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT l.*, op.code AS old_plan_code, np.code AS new_plan_code
             FROM subscription_logs l
             LEFT JOIN plans op ON op.id = l.old_plan_id
             LEFT JOIN plans np ON np.id = l.new_plan_id
             WHERE l.user_id = :user_id
             ORDER BY l.id DESC
             LIMIT :limit_value'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit_value', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function create(array $payload): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO subscriptions
             (user_id, plan_id, duration_type, duration_days, started_at, expired_at, status, source_type, source_reference, notes)
             VALUES
             (:user_id, :plan_id, :duration_type, :duration_days, :started_at, :expired_at, :status, :source_type, :source_reference, :notes)'
        );
        $stmt->execute([
            'user_id' => $payload['user_id'],
            'plan_id' => $payload['plan_id'],
            'duration_type' => $payload['duration_type'],
            'duration_days' => $payload['duration_days'],
            'started_at' => $payload['started_at'],
            'expired_at' => $payload['expired_at'],
            'status' => $payload['status'] ?? 'ACTIVE',
            'source_type' => $payload['source_type'] ?? 'MANUAL_ADMIN',
            'source_reference' => $payload['source_reference'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function createLog(array $payload): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO subscription_logs
             (subscription_id, user_id, action_type, old_plan_id, new_plan_id, old_expired_at, new_expired_at, actor_label, notes)
             VALUES
             (:subscription_id, :user_id, :action_type, :old_plan_id, :new_plan_id, :old_expired_at, :new_expired_at, :actor_label, :notes)'
        );
        $stmt->execute([
            'subscription_id' => $payload['subscription_id'],
            'user_id' => $payload['user_id'],
            'action_type' => $payload['action_type'],
            'old_plan_id' => $payload['old_plan_id'] ?? null,
            'new_plan_id' => $payload['new_plan_id'] ?? null,
            'old_expired_at' => $payload['old_expired_at'] ?? null,
            'new_expired_at' => $payload['new_expired_at'] ?? null,
            'actor_label' => $payload['actor_label'] ?? 'SYSTEM',
            'notes' => $payload['notes'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function createAdminAction(array $payload): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO admin_subscription_actions
             (user_id, action_type, plan_id, duration_type, duration_days, actor_label, notes)
             VALUES
             (:user_id, :action_type, :plan_id, :duration_type, :duration_days, :actor_label, :notes)'
        );
        $stmt->execute([
            'user_id' => $payload['user_id'],
            'action_type' => $payload['action_type'],
            'plan_id' => $payload['plan_id'] ?? null,
            'duration_type' => $payload['duration_type'] ?? null,
            'duration_days' => $payload['duration_days'] ?? null,
            'actor_label' => $payload['actor_label'] ?? 'USER',
            'notes' => $payload['notes'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }
}

