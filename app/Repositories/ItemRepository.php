<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ItemRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchByName(string $keyword, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, item_code, name
             FROM items
             WHERE name LIKE :q OR item_code LIKE :q
             ORDER BY name ASC
             LIMIT :limit_value'
        );
        $stmt->bindValue(':q', '%' . trim($keyword) . '%');
        $stmt->bindValue(':limit_value', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAll(int $limit = 300): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, item_code, name
             FROM items
             ORDER BY name ASC
             LIMIT :limit_value'
        );
        $stmt->bindValue(':limit_value', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAllForLookup(): array
    {
        $stmt = $this->db->query(
            'SELECT id, item_code, name
             FROM items
             ORDER BY name ASC'
        );
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
