<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CityRepository;
use App\Repositories\MarketPriceRepository;
use App\Repositories\RecipeRepository;
use App\Support\Database;

final class RecipeAutoFillService
{
    private RecipeRepository $recipes;
    private CityRepository $cities;
    private MarketPriceRepository $prices;

    public function __construct()
    {
        $db = Database::connection();
        $this->recipes = new RecipeRepository($db);
        $this->cities = new CityRepository($db);
        $this->prices = new MarketPriceRepository($db);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function itemOptions(string $keyword = ''): array
    {
        return $this->recipes->searchCraftableItems($keyword, trim($keyword) === '' ? 100 : 30);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cityOptions(): array
    {
        return $this->cities->listAll();
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function recipeDetail(int $itemId, ?int $cityId = null, ?int $userId = null): array
    {
        if ($itemId <= 0) {
            return ['ok' => false, 'message' => 'Item recipe tidak valid.'];
        }

        $recipe = $this->recipes->findRecipeDetailByItemId($itemId);
        if ($recipe === null) {
            return ['ok' => false, 'message' => 'Recipe item tidak ditemukan.'];
        }

        $recipeId = (int) ($recipe['recipe_id'] ?? 0);
        if ($recipeId <= 0) {
            return ['ok' => false, 'message' => 'Recipe item tidak valid.'];
        }

        $materialRows = $this->recipes->listMaterialsByRecipeId($recipeId);
        $materialPriceMap = [];
        $sellPrice = null;

        if (($userId ?? 0) > 0) {
            $materialItemIds = array_map(
                static fn (array $row): int => (int) ($row['material_item_id'] ?? 0),
                $materialRows
            );
            $materialPriceMap = $this->prices->resolvePriceMapByItems(
                (int) $userId,
                $materialItemIds,
                $cityId,
                'BUY'
            );

            $sellPriceMap = $this->prices->resolvePriceMapByItems(
                (int) $userId,
                [(int) ($recipe['item_id'] ?? 0)],
                $cityId,
                'SELL'
            );
            $recipeItemId = (int) ($recipe['item_id'] ?? 0);
            if ($recipeItemId > 0 && array_key_exists($recipeItemId, $sellPriceMap)) {
                $sellPrice = (float) $sellPriceMap[$recipeItemId];
            }
        }

        $materials = [];
        foreach ($materialRows as $row) {
            $materialItemId = (int) ($row['material_item_id'] ?? 0);
            $materials[] = [
                'item_id' => $materialItemId,
                'item_code' => (string) ($row['material_item_code'] ?? ''),
                'name' => (string) ($row['material_name'] ?? ''),
                'qty_per_recipe' => (float) ($row['qty_per_recipe'] ?? 0),
                'buy_price' => (float) ($materialPriceMap[$materialItemId] ?? 0),
                'return_type' => (string) ($row['return_type'] ?? 'RETURN'),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }

        $bonus = null;
        $bonusPercent = 0.0;
        if (($cityId ?? 0) > 0) {
            $bonus = $this->recipes->findCityBonus((int) $cityId, (int) ($recipe['category_id'] ?? 0));
            if ($bonus !== null) {
                $bonusPercent = (float) ($bonus['bonus_percent'] ?? 0);
            }
        }

        return [
            'ok' => true,
            'message' => 'Recipe ditemukan.',
            'data' => [
                'item' => [
                    'id' => (int) ($recipe['item_id'] ?? 0),
                    'item_code' => (string) ($recipe['item_code'] ?? ''),
                    'name' => (string) ($recipe['item_name'] ?? ''),
                    'item_value' => (float) ($recipe['item_value'] ?? 0),
                    'output_qty' => (int) ($recipe['recipe_output_qty'] ?? $recipe['default_output_qty'] ?? 1),
                    'sell_price' => $sellPrice,
                    'tier' => (string) ($recipe['tier'] ?? ''),
                    'enchantment_level' => (string) ($recipe['enchantment_level'] ?? ''),
                ],
                'category' => [
                    'id' => (int) ($recipe['category_id'] ?? 0),
                    'code' => (string) ($recipe['category_code'] ?? ''),
                    'name' => (string) ($recipe['category_name'] ?? ''),
                ],
                'city_bonus' => [
                    'city_id' => $bonus !== null ? (int) ($bonus['city_id'] ?? 0) : ($cityId ?? 0),
                    'city_code' => $bonus !== null ? (string) ($bonus['city_code'] ?? '') : '',
                    'city_name' => $bonus !== null ? (string) ($bonus['city_name'] ?? '') : '',
                    'bonus_percent' => $bonusPercent,
                ],
                'materials' => $materials,
            ],
        ];
    }
}
