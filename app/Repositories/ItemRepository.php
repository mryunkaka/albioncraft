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

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdentity(string $name, string $itemCode, string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM items
             WHERE UPPER(name) = UPPER(:name)
                OR item_code = :item_code
                OR slug = :slug
             LIMIT 1'
        );
        $stmt->execute([
            'name' => trim($name),
            'item_code' => trim($itemCode),
            'slug' => trim($slug),
        ]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByItemCode(string $itemCode): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM items WHERE item_code = :item_code LIMIT 1');
        $stmt->execute(['item_code' => trim($itemCode)]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM items WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => trim($slug)]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(array $payload): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO items
             (item_code, name, slug, category_id, item_value, default_output_qty, tier, enchantment_level, is_database_ready)
             VALUES
             (:item_code, :name, :slug, :category_id, :item_value, :default_output_qty, :tier, :enchantment_level, :is_database_ready)'
        );
        $stmt->execute([
            'item_code' => $payload['item_code'],
            'name' => $payload['name'],
            'slug' => $payload['slug'],
            'category_id' => $payload['category_id'],
            'item_value' => $payload['item_value'],
            'default_output_qty' => $payload['default_output_qty'],
            'tier' => $payload['tier'],
            'enchantment_level' => $payload['enchantment_level'],
            'is_database_ready' => $payload['is_database_ready'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateMetadata(int $id, array $payload): void
    {
        $stmt = $this->db->prepare(
            'UPDATE items
             SET item_value = :item_value,
                 default_output_qty = :default_output_qty,
                 tier = :tier,
                 enchantment_level = :enchantment_level,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'item_value' => $payload['item_value'],
            'default_output_qty' => $payload['default_output_qty'],
            'tier' => $payload['tier'],
            'enchantment_level' => $payload['enchantment_level'],
        ]);
    }
}
