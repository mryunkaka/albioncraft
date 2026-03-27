<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CalculatorRecipeLibraryRepository
{
    public function __construct(private PDO $db)
    {
        $this->ensureTable();
    }

    public function create(array $payload): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO calculator_recipe_library
             (user_id, owner_label, owner_scope, plan_code, item_id, item_name, input_snapshot)
             VALUES
             (:user_id, :owner_label, :owner_scope, :plan_code, :item_id, :item_name, :input_snapshot)'
        );
        $stmt->execute([
            'user_id' => $payload['user_id'] ?? null,
            'owner_label' => $payload['owner_label'],
            'owner_scope' => $payload['owner_scope'],
            'plan_code' => $payload['plan_code'],
            'item_id' => $payload['item_id'] ?? null,
            'item_name' => $payload['item_name'],
            'input_snapshot' => $payload['input_snapshot'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM calculator_recipe_library WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listVisible(?int $userId, string $planCode, string $keyword = '', int $limit = 100): array
    {
        $planCode = strtoupper(trim($planCode));
        $isPro = $planCode === 'PRO';

        $where = ['1=1'];
        $params = [];

        if (! $isPro) {
            if (($userId ?? 0) > 0) {
                $where[] = 'user_id = :user_id';
                $params['user_id'] = (int) $userId;
            } else {
                $where[] = 'user_id IS NULL';
            }
        }

        if (trim($keyword) !== '') {
            $where[] = '(item_name LIKE :q OR owner_label LIKE :q)';
            $params['q'] = '%' . trim($keyword) . '%';
        }

        $sql = 'SELECT *
                FROM calculator_recipe_library
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY created_at DESC, id DESC
                LIMIT :limit_value';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit_value', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private function ensureTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS calculator_recipe_library (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NULL,
                owner_label VARCHAR(120) NOT NULL,
                owner_scope VARCHAR(20) NOT NULL DEFAULT \'GUEST\',
                plan_code VARCHAR(20) NOT NULL DEFAULT \'FREE\',
                item_id BIGINT UNSIGNED NULL,
                item_name VARCHAR(190) NOT NULL,
                input_snapshot JSON NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_calculator_recipe_library_user_created (user_id, created_at),
                KEY idx_calculator_recipe_library_item_name (item_name),
                CONSTRAINT fk_calculator_recipe_library_user FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE SET NULL,
                CONSTRAINT fk_calculator_recipe_library_item FOREIGN KEY (item_id) REFERENCES items(id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
