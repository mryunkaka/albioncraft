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
        $stmt = $this->db->prepare('SELECT * FROM plans WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => strtoupper($code)]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
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
}
