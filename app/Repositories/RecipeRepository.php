<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RecipeRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchCraftableItems(string $keyword = '', int $limit = 30): array
    {
        $sql = 'SELECT
                    i.id,
                    i.item_code,
                    i.name,
                    i.item_value,
                    i.default_output_qty,
                    ic.id AS category_id,
                    ic.code AS category_code,
                    ic.name AS category_name
                FROM recipes r
                INNER JOIN items i ON i.id = r.item_id
                INNER JOIN item_categories ic ON ic.id = i.category_id
                WHERE r.is_active = 1';

        if (trim($keyword) !== '') {
            $sql .= ' AND (i.name LIKE :q OR i.item_code LIKE :q)';
        }

        $sql .= ' ORDER BY i.name ASC LIMIT :limit_value';

        $stmt = $this->db->prepare($sql);
        if (trim($keyword) !== '') {
            $stmt->bindValue(':q', '%' . trim($keyword) . '%');
        }
        $stmt->bindValue(':limit_value', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRecipeDetailByItemId(int $itemId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                r.id AS recipe_id,
                r.output_qty AS recipe_output_qty,
                r.notes AS recipe_notes,
                i.id AS item_id,
                i.item_code,
                i.name AS item_name,
                i.item_value,
                i.default_output_qty,
                i.tier,
                i.enchantment_level,
                ic.id AS category_id,
                ic.code AS category_code,
                ic.name AS category_name
             FROM recipes r
             INNER JOIN items i ON i.id = r.item_id
             INNER JOIN item_categories ic ON ic.id = i.category_id
             WHERE r.item_id = :item_id
               AND r.is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['item_id' => $itemId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMaterialsByRecipeId(int $recipeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                rm.id,
                rm.qty_per_recipe,
                rm.return_type,
                rm.sort_order,
                mi.id AS material_item_id,
                mi.item_code AS material_item_code,
                mi.name AS material_name,
                mi.item_value AS material_item_value
             FROM recipe_materials rm
             INNER JOIN items mi ON mi.id = rm.material_item_id
             WHERE rm.recipe_id = :recipe_id
             ORDER BY rm.sort_order ASC, rm.id ASC'
        );
        $stmt->execute(['recipe_id' => $recipeId]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCityBonus(int $cityId, int $categoryId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                cb.id,
                cb.bonus_percent,
                c.id AS city_id,
                c.code AS city_code,
                c.name AS city_name
             FROM city_bonuses cb
             INNER JOIN cities c ON c.id = cb.city_id
             WHERE cb.city_id = :city_id
               AND cb.category_id = :category_id
             LIMIT 1'
        );
        $stmt->execute([
            'city_id' => $cityId,
            'category_id' => $categoryId,
        ]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listCityBonusesByCategoryId(int $categoryId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                cb.id,
                cb.bonus_percent,
                c.id AS city_id,
                c.code AS city_code,
                c.name AS city_name
             FROM city_bonuses cb
             INNER JOIN cities c ON c.id = cb.city_id
             WHERE cb.category_id = :category_id
             ORDER BY cb.bonus_percent DESC, c.name ASC'
        );
        $stmt->execute(['category_id' => $categoryId]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
