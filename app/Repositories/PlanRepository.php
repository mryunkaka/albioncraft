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
}

