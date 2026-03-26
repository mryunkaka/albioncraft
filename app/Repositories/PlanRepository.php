<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PlanRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM plans WHERE TRIM(UPPER(code)) = :code LIMIT 1');
        $stmt->execute(['code' => strtoupper(trim($code))]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findIdByCode(string $code): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM plans WHERE TRIM(UPPER(code)) = :code LIMIT 1');
        $stmt->execute(['code' => strtoupper(trim($code))]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return null;
        }
        $id = (int) $value;
        return $id > 0 ? $id : null;
    }

    public function findFirstPlanId(): ?int
    {
        $stmt = $this->db->query('SELECT id FROM plans ORDER BY sort_order ASC, id ASC LIMIT 1');
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return null;
        }
        $id = (int) $value;
        return $id > 0 ? $id : null;
    }

    public function ensureDefaultPlans(): void
    {
        $sql = "INSERT INTO plans (code, name, sort_order) VALUES
                ('FREE', 'Free', 1),
                ('LITE', 'Lite', 2),
                ('MEDIUM', 'Medium', 3),
                ('PRO', 'Pro', 4)
                ON DUPLICATE KEY UPDATE
                  name = VALUES(name),
                  sort_order = VALUES(sort_order)";
        $this->db->exec($sql);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM plans WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function hasFeature(int $planId, string $featureKey): bool
    {
        $stmt = $this->db->prepare(
            'SELECT is_enabled FROM plan_features WHERE plan_id = :plan_id AND feature_key = :feature_key LIMIT 1'
        );
        $stmt->execute([
            'plan_id' => $planId,
            'feature_key' => trim($featureKey),
        ]);

        $value = $stmt->fetchColumn();
        return (int) $value === 1;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM plans ORDER BY sort_order ASC, id ASC');
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
