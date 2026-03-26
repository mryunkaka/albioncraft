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

    /**
     * @param array<int, int> $itemIds
     * @return array<int, float>
     */
    public function resolvePriceMapByItems(int $userId, array $itemIds, ?int $cityId, string $priceType): array
    {
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds), static fn (int $id): bool => $id > 0)));
        if ($itemIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [
            'user_id' => $userId,
            'price_type' => $priceType,
        ];

        foreach ($itemIds as $index => $itemId) {
            $key = 'item_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $itemId;
        }

        $inSql = implode(', ', $placeholders);
        $rows = [];

        if ($cityId !== null && $cityId > 0) {
            $citySql = "SELECT item_id, price_value
                        FROM market_prices
                        WHERE user_id = :user_id
                          AND price_type = :price_type
                          AND city_id = :city_id
                          AND item_id IN ({$inSql})";
            $cityStmt = $this->db->prepare($citySql);
            foreach ($params as $key => $value) {
                $cityStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $cityStmt->bindValue(':city_id', $cityId, PDO::PARAM_INT);
            $cityStmt->execute();
            $cityRows = $cityStmt->fetchAll();
            if (is_array($cityRows)) {
                foreach ($cityRows as $row) {
                    $itemId = (int) ($row['item_id'] ?? 0);
                    if ($itemId > 0) {
                        $rows[$itemId] = (float) ($row['price_value'] ?? 0);
                    }
                }
            }
        }

        $missingItemIds = array_values(array_filter(
            $itemIds,
            static fn (int $itemId): bool => ! array_key_exists($itemId, $rows)
        ));

        if ($missingItemIds === []) {
            return $rows;
        }

        $globalPlaceholders = [];
        $globalParams = [
            'user_id' => $userId,
            'price_type' => $priceType,
        ];

        foreach ($missingItemIds as $index => $itemId) {
            $key = 'global_item_' . $index;
            $globalPlaceholders[] = ':' . $key;
            $globalParams[$key] = $itemId;
        }

        $globalSql = 'SELECT item_id, price_value
                      FROM market_prices
                      WHERE user_id = :user_id
                        AND price_type = :price_type
                        AND city_id IS NULL
                        AND item_id IN (' . implode(', ', $globalPlaceholders) . ')';
        $globalStmt = $this->db->prepare($globalSql);
        foreach ($globalParams as $key => $value) {
            $globalStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $globalStmt->execute();
        $globalRows = $globalStmt->fetchAll();
        if (is_array($globalRows)) {
            foreach ($globalRows as $row) {
                $itemId = (int) ($row['item_id'] ?? 0);
                if ($itemId > 0 && ! array_key_exists($itemId, $rows)) {
                    $rows[$itemId] = (float) ($row['price_value'] ?? 0);
                }
            }
        }

        return $rows;
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

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdAndUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM market_prices WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
        ]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function updateFull(int $id, int $userId, array $payload): void
    {
        $stmt = $this->db->prepare(
            'UPDATE market_prices
             SET item_id = :item_id,
                 city_id = :city_id,
                 price_type = :price_type,
                 price_value = :price_value,
                 observed_at = :observed_at,
                 notes = :notes,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'item_id' => $payload['item_id'],
            'city_id' => $payload['city_id'],
            'price_type' => $payload['price_type'],
            'price_value' => $payload['price_value'],
            'observed_at' => $payload['observed_at'],
            'notes' => $payload['notes'],
        ]);
    }

    public function deleteByIdAndUser(int $id, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM market_prices WHERE id = :id AND user_id = :user_id');
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
        ]);
    }
}
