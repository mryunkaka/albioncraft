<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class MarketPriceRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function paginateByUser(
        int $userId,
        string $keyword = '',
        string $priceType = '',
        int $cityId = 0,
        int $page = 1,
        int $perPage = 20
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;

        $where = ['mp.user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($keyword !== '') {
            $where[] = '(i.name LIKE :q OR i.item_code LIKE :q)';
            $params['q'] = '%' . $keyword . '%';
        }
        if ($priceType !== '') {
            $where[] = 'mp.price_type = :price_type';
            $params['price_type'] = $priceType;
        }
        if ($cityId > 0) {
            $where[] = 'mp.city_id = :city_id';
            $params['city_id'] = $cityId;
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM market_prices mp JOIN items i ON i.id = mp.item_id WHERE {$whereSql}";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $k => $v) {
            if (is_int($v)) {
                $countStmt->bindValue(':' . $k, $v, PDO::PARAM_INT);
            } else {
                $countStmt->bindValue(':' . $k, $v);
            }
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT
                    mp.id,
                    mp.item_id,
                    i.item_code,
                    i.name AS item_name,
                    mp.city_id,
                    c.name AS city_name,
                    mp.price_type,
                    mp.price_value,
                    mp.observed_at,
                    mp.notes,
                    mp.updated_at
                FROM market_prices mp
                JOIN items i ON i.id = mp.item_id
                LEFT JOIN cities c ON c.id = mp.city_id
                WHERE {$whereSql}
                ORDER BY mp.updated_at DESC, mp.id DESC
                LIMIT :limit_value OFFSET :offset_value";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            if (is_int($v)) {
                $stmt->bindValue(':' . $k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':' . $k, $v);
            }
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

    /**
     * @return array<string, mixed>|null
     */
    public function findOneByUnique(int $userId, int $itemId, ?int $cityId, string $priceType): ?array
    {
        if ($cityId === null) {
            $stmt = $this->db->prepare(
                'SELECT * FROM market_prices
                 WHERE user_id = :user_id
                   AND item_id = :item_id
                   AND city_id IS NULL
                   AND price_type = :price_type
                 LIMIT 1'
            );
            $stmt->execute([
                'user_id' => $userId,
                'item_id' => $itemId,
                'price_type' => $priceType,
            ]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT * FROM market_prices
                 WHERE user_id = :user_id
                   AND item_id = :item_id
                   AND city_id = :city_id
                   AND price_type = :price_type
                 LIMIT 1'
            );
            $stmt->execute([
                'user_id' => $userId,
                'item_id' => $itemId,
                'city_id' => $cityId,
                'price_type' => $priceType,
            ]);
        }

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function insert(array $payload): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO market_prices
             (user_id, item_id, city_id, price_type, price_value, observed_at, notes)
             VALUES
             (:user_id, :item_id, :city_id, :price_type, :price_value, :observed_at, :notes)'
        );
        $stmt->execute([
            'user_id' => $payload['user_id'],
            'item_id' => $payload['item_id'],
            'city_id' => $payload['city_id'],
            'price_type' => $payload['price_type'],
            'price_value' => $payload['price_value'],
            'observed_at' => $payload['observed_at'],
            'notes' => $payload['notes'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $stmt = $this->db->prepare(
            'UPDATE market_prices
             SET price_value = :price_value,
                 observed_at = :observed_at,
                 notes = :notes,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'price_value' => $payload['price_value'],
            'observed_at' => $payload['observed_at'],
            'notes' => $payload['notes'],
        ]);
    }
}

