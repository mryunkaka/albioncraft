<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CityRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAll(): array
    {
        $stmt = $this->db->query('SELECT id, code, name FROM cities ORDER BY name ASC');
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAllForLookup(): array
    {
        $stmt = $this->db->query('SELECT id, code, name FROM cities ORDER BY name ASC');
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
