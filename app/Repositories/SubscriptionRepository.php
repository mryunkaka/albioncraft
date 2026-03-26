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

    /**
     * @return array<string, mixed>|null
     */
    public function findAdminActionById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM admin_subscription_actions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function hasApprovedRequest(int $requestActionId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM admin_subscription_actions
             WHERE action_type = :action_type
               AND notes = :notes
             LIMIT 1'
        );
        $stmt->execute([
            'action_type' => 'APPROVE_EXTEND',
            'notes' => 'request_action_id=' . $requestActionId,
        ]);

        $value = $stmt->fetchColumn();
        return $value !== false;
    }

    public function hasRejectedRequest(int $requestActionId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM admin_subscription_actions
             WHERE action_type = :action_type
               AND notes = :notes
             LIMIT 1'
        );
        $stmt->execute([
            'action_type' => 'REJECT_EXTEND',
            'notes' => 'request_action_id=' . $requestActionId,
        ]);

        $value = $stmt->fetchColumn();
        return $value !== false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPendingExtendRequests(int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, u.username, u.email, p.code AS plan_code, p.name AS plan_name
             FROM admin_subscription_actions a
             JOIN users u ON u.id = a.user_id
             LEFT JOIN plans p ON p.id = a.plan_id
             WHERE a.action_type = :action_type
             ORDER BY a.id DESC
             LIMIT :limit_value'
        );
        $stmt->bindValue(':action_type', 'REQUEST_EXTEND');
        $stmt->bindValue(':limit_value', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (! is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0 && ($this->hasApprovedRequest($id) || $this->hasRejectedRequest($id))) {
                continue;
            }
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function paginateAdminActions(string $actionType = '', string $keyword = '', int $page = 1, int $perPage = 30): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];

        if ($actionType !== '') {
            $where[] = 'a.action_type = :action_type';
            $params['action_type'] = $actionType;
        }
        if ($keyword !== '') {
            $where[] = '(u.username LIKE :q OR u.email LIKE :q OR a.notes LIKE :q)';
            $params['q'] = '%' . $keyword . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
                     FROM admin_subscription_actions a
                     JOIN users u ON u.id = a.user_id
                     WHERE {$whereSql}";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue(':' . $k, $v);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT
                    a.*,
                    u.username,
                    u.email,
                    p.code AS plan_code,
                    p.name AS plan_name
                FROM admin_subscription_actions a
                JOIN users u ON u.id = a.user_id
                LEFT JOIN plans p ON p.id = a.plan_id
                WHERE {$whereSql}
                ORDER BY a.id DESC
                LIMIT :limit_value OFFSET :offset_value";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit_value', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset_value', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return [
            'rows' => is_array($rows) ? $rows : [],
            'total' => $total,
        ];
    }
}
