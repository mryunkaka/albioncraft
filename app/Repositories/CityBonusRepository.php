<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CityBonusRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findOneByUnique(int $cityId, int $categoryId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM city_bonuses
             WHERE city_id = :city_id
               AND category_id = :category_id
             LIMIT 1'
        );
        $stmt->execute([
            'city_id' => $cityId,
            'category_id' => $categoryId,
        ]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function insert(array $payload): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO city_bonuses
             (city_id, category_id, bonus_percent)
             VALUES
             (:city_id, :category_id, :bonus_percent)'
        );
        $stmt->execute([
            'city_id' => $payload['city_id'],
            'category_id' => $payload['category_id'],
            'bonus_percent' => $payload['bonus_percent'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, float $bonusPercent): void
    {
        $stmt = $this->db->prepare(
            'UPDATE city_bonuses
             SET bonus_percent = :bonus_percent,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'bonus_percent' => $bonusPercent,
        ]);
    }
}
