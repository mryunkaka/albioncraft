<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ItemCategoryRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM item_categories WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => trim($code)]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM item_categories WHERE UPPER(name) = UPPER(:name) LIMIT 1');
        $stmt->execute(['name' => trim($name)]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(array $payload): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO item_categories (code, name, category_group)
             VALUES (:code, :name, :category_group)'
        );
        $stmt->execute([
            'code' => $payload['code'],
            'name' => $payload['name'],
            'category_group' => $payload['category_group'],
        ]);

        return (int) $this->db->lastInsertId();
    }
}
