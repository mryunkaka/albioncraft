<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CalculationHistoryRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function create(array $payload): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO calculation_histories
             (user_id, item_id, plan_code, calculation_mode, input_snapshot, output_snapshot)
             VALUES
             (:user_id, :item_id, :plan_code, :calculation_mode, :input_snapshot, :output_snapshot)'
        );

        $stmt->execute([
            'user_id' => $payload['user_id'],
            'item_id' => $payload['item_id'] ?? null,
            'plan_code' => $payload['plan_code'],
            'calculation_mode' => $payload['calculation_mode'],
            'input_snapshot' => $payload['input_snapshot'],
            'output_snapshot' => $payload['output_snapshot'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findExactDuplicate(
        int $userId,
        ?int $itemId,
        string $planCode,
        string $calculationMode,
        string $inputSnapshot,
        string $outputSnapshot
    ): ?array {
        $params = [
            'user_id' => $userId,
            'plan_code' => $planCode,
            'calculation_mode' => $calculationMode,
            'input_snapshot' => $inputSnapshot,
            'output_snapshot' => $outputSnapshot,
        ];

        if ($itemId === null) {
            $sql = 'SELECT *
                    FROM calculation_histories
                    WHERE user_id = :user_id
                      AND item_id IS NULL
                      AND plan_code = :plan_code
                      AND calculation_mode = :calculation_mode
                      AND input_snapshot = :input_snapshot
                      AND output_snapshot = :output_snapshot
                    ORDER BY id DESC
                    LIMIT 1';
        } else {
            $sql = 'SELECT *
                    FROM calculation_histories
                    WHERE user_id = :user_id
                      AND item_id = :item_id
                      AND plan_code = :plan_code
                      AND calculation_mode = :calculation_mode
                      AND input_snapshot = :input_snapshot
                      AND output_snapshot = :output_snapshot
                    ORDER BY id DESC
                    LIMIT 1';
            $params['item_id'] = $itemId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRecentByUser(int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT ch.*, i.name AS item_name_db
             FROM calculation_histories ch
             LEFT JOIN items i ON i.id = ch.item_id
             WHERE ch.user_id = :user_id
             ORDER BY ch.id DESC
             LIMIT :limit_value'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit_value', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function countByUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM calculation_histories WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }
}
