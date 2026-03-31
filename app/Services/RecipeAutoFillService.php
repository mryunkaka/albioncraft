<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CalculatorRecipeLibraryRepository;
use App\Repositories\CityRepository;
use App\Repositories\MarketPriceRepository;
use App\Repositories\RecipeRepository;
use App\Support\Database;

final class RecipeAutoFillService
{
    private RecipeRepository $recipes;
    private CityRepository $cities;
    private MarketPriceRepository $prices;
    private CalculatorRecipeLibraryRepository $savedRecipes;

    public function __construct()
    {
        $db = Database::connection();
        $this->recipes = new RecipeRepository($db);
        $this->cities = new CityRepository($db);
        $this->prices = new MarketPriceRepository($db);
        $this->savedRecipes = new CalculatorRecipeLibraryRepository($db);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function itemOptions(string $keyword = '', ?array $auth = null): array
    {
        $limit = trim($keyword) === '' ? 100 : 30;
        $userId = is_array($auth) ? (int) ($auth['user_id'] ?? 0) : 0;
        $planCode = is_array($auth) ? (string) ($auth['plan_code'] ?? 'FREE') : 'GUEST';
        $savedRows = $this->savedRecipes->listVisible($userId > 0 ? $userId : null, $planCode, $keyword, $limit);
        $options = [];

        if (strtoupper($planCode) === 'PRO') {
            foreach ($savedRows as $row) {
                $options[] = $this->savedRowToOption($row, true);
            }
        } else {
            $latestByName = [];
            foreach ($savedRows as $row) {
                $normalizedName = strtolower(trim((string) ($row['item_name'] ?? '')));
                if ($normalizedName === '' || isset($latestByName[$normalizedName])) {
                    continue;
                }

                $latestByName[$normalizedName] = true;
                $options[] = $this->savedRowToOption($row, false);
            }
        }

        foreach ($this->recipes->searchCraftableItems($keyword, $limit) as $row) {
            $options[] = [
                'id' => 'system:' . (int) ($row['id'] ?? 0),
                'source' => 'system',
                'name' => (string) ($row['name'] ?? ''),
                'item_code' => (string) ($row['item_code'] ?? ''),
                'label' => sprintf(
                    '%s (%s) [Master]',
                    (string) ($row['name'] ?? ''),
                    (string) ($row['item_code'] ?? '')
                ),
                'created_at' => null,
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'owner_label' => 'Master Recipe',
            ];
        }

        return $options;
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
    public function recipeDetail(string $entryReference, ?int $cityId = null, ?array $auth = null): array
    {
        $entryReference = trim($entryReference);
        if ($entryReference === '') {
            return ['ok' => false, 'message' => 'Item recipe tidak valid.'];
        }

        if (str_starts_with($entryReference, 'saved:')) {
            return $this->savedRecipeDetail((int) substr($entryReference, 6), $auth);
        }

        $itemId = (int) preg_replace('/[^0-9]/', '', str_starts_with($entryReference, 'system:') ? substr($entryReference, 7) : $entryReference);
        if ($itemId <= 0) {
            return ['ok' => false, 'message' => 'Item recipe tidak valid.'];
        }

        $userId = is_array($auth) ? (int) ($auth['user_id'] ?? 0) : 0;

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
        $selectedCityCraftFee = null;
        $selectedSellCityId = null;
        $selectedCraftFeeCityId = null;
        $recommendations = [
            'best_sell_city' => null,
            'best_craft_fee_city' => null,
            'recommended_craft_city' => null,
            'local_bonus_cities' => [],
            'cheapest_material_cities' => [],
        ];
        $bestBuyByMaterialId = [];

        if ($userId > 0) {
            $materialItemIds = array_map(
                static fn (array $row): int => (int) ($row['material_item_id'] ?? 0),
                $materialRows
            );
            $materialPriceMap = $this->prices->resolvePriceMapByItems(
                $userId,
                $materialItemIds,
                $cityId,
                'BUY'
            );

            $sellPriceMap = $this->prices->resolvePriceMapByItems(
                $userId,
                [(int) ($recipe['item_id'] ?? 0)],
                $cityId,
                'SELL'
            );
            $recipeItemId = (int) ($recipe['item_id'] ?? 0);
            if ($recipeItemId > 0 && array_key_exists($recipeItemId, $sellPriceMap)) {
                $sellPrice = (float) $sellPriceMap[$recipeItemId];
            }

            $craftFeeRows = $this->prices->listByUserItemAndType($userId, $recipeItemId, 'CRAFT_FEE');
            if ($craftFeeRows !== []) {
                $bestCraftFee = $craftFeeRows[0];
                $recommendations['best_craft_fee_city'] = $this->priceRowToRecommendation($bestCraftFee);
                $selectedCraftFeeCityId = isset($bestCraftFee['city_id']) ? (int) $bestCraftFee['city_id'] : null;
                $selectedCityCraftFee = (float) ($bestCraftFee['price_value'] ?? 0);
                if (($cityId ?? 0) > 0) {
                    foreach ($craftFeeRows as $craftFeeRow) {
                        if ((int) ($craftFeeRow['city_id'] ?? 0) === (int) $cityId) {
                            $selectedCityCraftFee = (float) ($craftFeeRow['price_value'] ?? 0);
                            $selectedCraftFeeCityId = (int) ($craftFeeRow['city_id'] ?? 0);
                            break;
                        }
                    }
                }
            }

            $sellRows = $this->prices->listByUserItemAndType($userId, $recipeItemId, 'SELL');
            if ($sellRows !== []) {
                usort($sellRows, static fn (array $a, array $b): int => ((float) ($b['price_value'] ?? 0)) <=> ((float) ($a['price_value'] ?? 0)));
                $recommendations['best_sell_city'] = $this->priceRowToRecommendation($sellRows[0]);
                $selectedSellCityId = isset($sellRows[0]['city_id']) ? (int) $sellRows[0]['city_id'] : null;
                if (($cityId ?? 0) > 0) {
                    foreach ($sellRows as $sellRow) {
                        if ((int) ($sellRow['city_id'] ?? 0) === (int) $cityId) {
                            $selectedSellCityId = (int) ($sellRow['city_id'] ?? 0);
                            $sellPrice = (float) ($sellRow['price_value'] ?? 0);
                            break;
                        }
                    }
                }
            }

            $groupedBuyRows = $this->prices->listByUserItemsAndType($userId, $materialItemIds, 'BUY');
            foreach ($materialRows as $materialRow) {
                $materialItemId = (int) ($materialRow['material_item_id'] ?? 0);
                $rows = $groupedBuyRows[$materialItemId] ?? [];
                if ($rows === []) {
                    continue;
                }

                $bestBuy = $rows[0];
                $bestBuyByMaterialId[$materialItemId] = $bestBuy;
                $recommendations['cheapest_material_cities'][] = [
                    'item_id' => $materialItemId,
                    'item_code' => (string) ($materialRow['material_item_code'] ?? ''),
                    'name' => (string) ($materialRow['material_name'] ?? ''),
                    'city_id' => isset($bestBuy['city_id']) ? (int) $bestBuy['city_id'] : null,
                    'city_code' => (string) ($bestBuy['city_code'] ?? ''),
                    'city_name' => (string) ($bestBuy['city_name'] ?? ''),
                    'price_value' => (float) ($bestBuy['price_value'] ?? 0),
                    'price_type' => 'BUY',
                ];
            }
        }

        $materials = [];
        foreach ($materialRows as $row) {
            $materialItemId = (int) ($row['material_item_id'] ?? 0);
            $materials[] = [
                'item_id' => $materialItemId,
                'item_code' => (string) ($row['material_item_code'] ?? ''),
                'name' => (string) ($row['material_name'] ?? ''),
                'item_value' => (float) ($row['material_item_value'] ?? 0),
                'qty_per_recipe' => (float) ($row['qty_per_recipe'] ?? 0),
                'buy_price' => (float) ($materialPriceMap[$materialItemId] ?? 0),
                'return_type' => (string) ($row['return_type'] ?? 'RETURN'),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }

        foreach ($this->recipes->listCityBonusesByCategoryId((int) ($recipe['category_id'] ?? 0)) as $bonusRow) {
            $recommendations['local_bonus_cities'][] = [
                'city_id' => (int) ($bonusRow['city_id'] ?? 0),
                'city_code' => (string) ($bonusRow['city_code'] ?? ''),
                'city_name' => (string) ($bonusRow['city_name'] ?? ''),
                'bonus_percent' => (float) ($bonusRow['bonus_percent'] ?? 0),
            ];
        }

        if ($userId > 0 && $bestBuyByMaterialId !== []) {
            $recommendations['recommended_craft_city'] = $this->recommendCraftCity(
                $materialRows,
                $bestBuyByMaterialId,
                $recipe,
                $recommendations['local_bonus_cities'],
                $craftFeeRows ?? []
            );
        }

        $selectedBonusCityId = ($cityId ?? 0) > 0 ? (int) $cityId : 0;
        if ($selectedBonusCityId <= 0) {
            $recommendedCraftCityId = (int) (($recommendations['recommended_craft_city']['city_id'] ?? 0));
            if ($recommendedCraftCityId > 0) {
                $selectedBonusCityId = $recommendedCraftCityId;
            } else {
                $selectedBonusCityId = $this->bestLocalBonusCityId($recommendations['local_bonus_cities']);
            }
        }

        $bonus = null;
        $bonusPercent = 0.0;
        if ($selectedBonusCityId > 0) {
            $bonus = $this->recipes->findCityBonus($selectedBonusCityId, (int) ($recipe['category_id'] ?? 0));
            if ($bonus !== null) {
                $bonusPercent = (float) ($bonus['bonus_percent'] ?? 0);
            }
        }

        if ($selectedCraftFeeCityId === null && $recommendations['recommended_craft_city'] !== null) {
            $selectedCraftFeeCityId = (int) (($recommendations['recommended_craft_city']['city_id'] ?? 0) ?: 0);
        }

        if ($selectedSellCityId === null && $recommendations['best_sell_city'] !== null) {
            $selectedSellCityId = (int) (($recommendations['best_sell_city']['city_id'] ?? 0) ?: 0);
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
                    'sell_price_city_id' => $selectedSellCityId,
                    'craft_fee' => $selectedCityCraftFee,
                    'craft_fee_city_id' => $selectedCraftFeeCityId,
                    'tier' => (string) ($recipe['tier'] ?? ''),
                    'enchantment_level' => (string) ($recipe['enchantment_level'] ?? ''),
                ],
                'category' => [
                    'id' => (int) ($recipe['category_id'] ?? 0),
                    'code' => (string) ($recipe['category_code'] ?? ''),
                    'name' => (string) ($recipe['category_name'] ?? ''),
                ],
                'city_bonus' => [
                    'city_id' => $bonus !== null ? (int) ($bonus['city_id'] ?? 0) : $selectedBonusCityId,
                    'city_code' => $bonus !== null ? (string) ($bonus['city_code'] ?? '') : '',
                    'city_name' => $bonus !== null ? (string) ($bonus['city_name'] ?? '') : '',
                    'bonus_percent' => $bonusPercent,
                ],
                'materials' => $materials,
                'recommendations' => $recommendations,
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $auth
     */
    public function storeCalculatedRecipe(?array $auth, array $input): ?int
    {
        $itemName = strtoupper(trim((string) ($input['item_name'] ?? '')));
        if ($itemName === '') {
            return null;
        }

        if (is_array($input['materials'] ?? null)) {
            $normalizedMaterials = [];
            foreach ((array) $input['materials'] as $material) {
                if (! is_array($material)) {
                    continue;
                }

                $material['name'] = strtoupper(trim((string) ($material['name'] ?? '')));
                $normalizedMaterials[] = $material;
            }
            $input['materials'] = $normalizedMaterials;
        }

        $input['item_name'] = $itemName;

        $snapshot = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($snapshot === false) {
            return null;
        }

        $userId = is_array($auth) ? (int) ($auth['user_id'] ?? 0) : 0;
        $ownerLabel = $userId > 0
            ? (string) (($auth['username'] ?? $auth['email'] ?? 'User'))
            : 'Tanpa Login';
        $planCode = $userId > 0 ? (string) ($auth['plan_code'] ?? 'FREE') : 'GUEST';
        $ownerScope = $userId > 0 ? 'USER' : 'GUEST';

        $duplicate = $this->savedRecipes->findExactDuplicate(
            $userId > 0 ? $userId : null,
            $ownerScope,
            isset($input['item_id']) ? (int) $input['item_id'] : null,
            $itemName,
            $snapshot
        );
        if ($duplicate !== null) {
            return (int) ($duplicate['id'] ?? 0);
        }

        return $this->savedRecipes->create([
            'user_id' => $userId > 0 ? $userId : null,
            'owner_label' => $ownerLabel,
            'owner_scope' => $ownerScope,
            'plan_code' => $planCode,
            'item_id' => isset($input['item_id']) ? (int) $input['item_id'] : null,
            'item_name' => $itemName,
            'input_snapshot' => $snapshot,
        ]);
    }

    /**
     * @param array<string, mixed>|null $auth
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    private function savedRecipeDetail(int $savedId, ?array $auth): array
    {
        if ($savedId <= 0) {
            return ['ok' => false, 'message' => 'Recipe tersimpan tidak valid.'];
        }

        $row = $this->savedRecipes->findById($savedId);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Recipe tersimpan tidak ditemukan.'];
        }

        $userId = is_array($auth) ? (int) ($auth['user_id'] ?? 0) : 0;
        $planCode = is_array($auth) ? strtoupper((string) ($auth['plan_code'] ?? 'FREE')) : 'GUEST';
        $rowUserId = isset($row['user_id']) ? (int) $row['user_id'] : 0;

        if ($planCode !== 'PRO') {
            if ($userId > 0) {
                if ($rowUserId !== $userId) {
                    return ['ok' => false, 'message' => 'Recipe tersimpan ini bukan milik user aktif.'];
                }
            } elseif ($rowUserId > 0) {
                return ['ok' => false, 'message' => 'Recipe tersimpan ini membutuhkan login pemilik.'];
            }
        }

        $snapshot = json_decode((string) ($row['input_snapshot'] ?? '{}'), true);
        if (! is_array($snapshot)) {
            $snapshot = [];
        }

        $materials = [];
        foreach ((array) ($snapshot['materials'] ?? []) as $index => $material) {
            if (! is_array($material)) {
                continue;
            }

            $materials[] = [
                'item_id' => isset($material['item_id']) ? (int) $material['item_id'] : 0,
                'item_code' => '',
                'name' => (string) ($material['name'] ?? ''),
                'item_value' => (float) ($material['item_value'] ?? 0),
                'qty_per_recipe' => (float) ($material['qty_per_recipe'] ?? 0),
                'buy_price' => (float) ($material['buy_price'] ?? 0),
                'return_type' => (string) ($material['return_type'] ?? 'RETURN'),
                'city_id' => isset($material['city_id']) ? (int) $material['city_id'] : 0,
                'sort_order' => $index + 1,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Recipe tersimpan ditemukan.',
            'data' => [
                'item' => [
                    'id' => isset($snapshot['item_id']) ? (int) $snapshot['item_id'] : 0,
                    'item_code' => '',
                    'name' => (string) ($snapshot['item_name'] ?? $row['item_name'] ?? ''),
                    'item_value' => (float) ($snapshot['item_value'] ?? 0),
                    'output_qty' => (int) ($snapshot['output_qty'] ?? 1),
                    'sell_price' => array_key_exists('sell_price', $snapshot) ? (float) ($snapshot['sell_price'] ?? 0) : null,
                    'sell_price_city_id' => isset($snapshot['sell_price_city_id']) ? (int) $snapshot['sell_price_city_id'] : null,
                    'craft_fee' => (float) ($snapshot['usage_fee'] ?? 0),
                    'craft_fee_city_id' => isset($snapshot['craft_fee_city_id']) ? (int) $snapshot['craft_fee_city_id'] : null,
                    'tier' => '',
                    'enchantment_level' => '',
                ],
                'category' => [
                    'id' => 0,
                    'code' => 'SAVED',
                    'name' => 'Saved Recipe',
                ],
                'city_bonus' => [
                    'city_id' => isset($snapshot['bonus_local_city_id']) ? (int) $snapshot['bonus_local_city_id'] : 0,
                    'city_code' => '',
                    'city_name' => '',
                    'bonus_percent' => (float) ($snapshot['bonus_local'] ?? 0),
                ],
                'materials' => $materials,
                'recommendations' => [],
                'saved_recipe' => [
                    'id' => (int) ($row['id'] ?? 0),
                    'owner_label' => (string) ($row['owner_label'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function savedRowToOption(array $row, bool $withTimestamp): array
    {
        $createdAt = (string) ($row['created_at'] ?? '');
        $label = (string) ($row['item_name'] ?? 'Untitled Recipe') . ' [Saved: ' . (string) ($row['owner_label'] ?? 'Unknown');
        if ($withTimestamp && $createdAt !== '') {
            $label .= ' - ' . $createdAt;
        }
        $label .= ']';

        return [
            'id' => 'saved:' . (int) ($row['id'] ?? 0),
            'source' => 'saved',
            'name' => (string) ($row['item_name'] ?? ''),
            'item_code' => '',
            'label' => $label,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'owner_label' => (string) ($row['owner_label'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function priceRowToRecommendation(array $row): array
    {
        return [
            'city_id' => isset($row['city_id']) ? (int) $row['city_id'] : null,
            'city_code' => (string) ($row['city_code'] ?? ''),
            'city_name' => (string) ($row['city_name'] ?? ''),
            'price_value' => (float) ($row['price_value'] ?? 0),
            'price_type' => (string) ($row['price_type'] ?? ''),
            'observed_at' => (string) ($row['observed_at'] ?? ''),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $localBonusCities
     */
    private function bestLocalBonusCityId(array $localBonusCities): int
    {
        $bestCityId = 0;
        $bestBonus = null;

        foreach ($localBonusCities as $city) {
            $cityId = (int) ($city['city_id'] ?? 0);
            if ($cityId <= 0) {
                continue;
            }

            $bonusPercent = (float) ($city['bonus_percent'] ?? 0);
            if ($bestBonus === null || $bonusPercent > $bestBonus) {
                $bestBonus = $bonusPercent;
                $bestCityId = $cityId;
            }
        }

        return $bestCityId;
    }

    /**
     * @param array<int, array<string, mixed>> $materialRows
     * @param array<int, array<string, mixed>> $bestBuyByMaterialId
     * @param array<string, mixed> $recipe
     * @param array<int, array<string, mixed>> $localBonusCities
     * @param array<int, array<string, mixed>> $craftFeeRows
     * @return array<string, mixed>|null
     */
    private function recommendCraftCity(
        array $materialRows,
        array $bestBuyByMaterialId,
        array $recipe,
        array $localBonusCities,
        array $craftFeeRows
    ): ?array {
        $craftFeeByCityId = [];
        $globalCraftFee = null;

        foreach ($craftFeeRows as $row) {
            $rowCityId = isset($row['city_id']) ? (int) $row['city_id'] : 0;
            if ($rowCityId > 0) {
                $craftFeeByCityId[$rowCityId] = (float) ($row['price_value'] ?? 0);
                continue;
            }

            $globalCraftFee = (float) ($row['price_value'] ?? 0);
        }

        $candidates = [];
        foreach ($localBonusCities as $city) {
            $cityId = (int) ($city['city_id'] ?? 0);
            if ($cityId <= 0) {
                continue;
            }

            $candidates[$cityId] = [
                'city_id' => $cityId,
                'city_code' => (string) ($city['city_code'] ?? ''),
                'city_name' => (string) ($city['city_name'] ?? ''),
                'bonus_percent' => (float) ($city['bonus_percent'] ?? 0),
                'craft_fee' => $craftFeeByCityId[$cityId] ?? $globalCraftFee,
            ];
        }

        foreach ($craftFeeRows as $row) {
            $cityId = isset($row['city_id']) ? (int) $row['city_id'] : 0;
            if ($cityId <= 0 || isset($candidates[$cityId])) {
                continue;
            }

            $candidates[$cityId] = [
                'city_id' => $cityId,
                'city_code' => (string) ($row['city_code'] ?? ''),
                'city_name' => (string) ($row['city_name'] ?? ''),
                'bonus_percent' => 0.0,
                'craft_fee' => (float) ($row['price_value'] ?? 0),
            ];
        }

        if ($candidates === []) {
            return null;
        }

        $itemValue = (float) ($recipe['item_value'] ?? 0);
        $outputQty = max(1, (int) ($recipe['recipe_output_qty'] ?? $recipe['default_output_qty'] ?? 1));
        $best = null;

        foreach ($candidates as $candidate) {
            $rrr = 1 - (1 / (1 + ((18.0 + (float) $candidate['bonus_percent']) / 100)));
            $estimatedMaterialCost = 0.0;

            foreach ($materialRows as $materialRow) {
                $materialItemId = (int) ($materialRow['material_item_id'] ?? 0);
                $bestBuy = $bestBuyByMaterialId[$materialItemId] ?? null;
                if (! is_array($bestBuy)) {
                    continue;
                }

                $qtyPerRecipe = (float) ($materialRow['qty_per_recipe'] ?? 0);
                $buyPrice = (float) ($bestBuy['price_value'] ?? 0);
                $multiplier = (string) ($materialRow['return_type'] ?? 'RETURN') === 'RETURN' ? (1 - $rrr) : 1.0;
                $estimatedMaterialCost += $qtyPerRecipe * $multiplier * $buyPrice;
            }

            $estimatedCraftFeePerRecipe = 0.0;
            if ($candidate['craft_fee'] !== null && $itemValue > 0) {
                $estimatedCraftFeePerRecipe = (((float) $candidate['craft_fee']) * $itemValue * $outputQty) / 20 / (400 / 9);
            }

            $candidate['estimated_cost_per_item'] = round(($estimatedMaterialCost + $estimatedCraftFeePerRecipe) / $outputQty, 2);

            if (
                $best === null
                || (float) $candidate['estimated_cost_per_item'] < (float) $best['estimated_cost_per_item']
                || (
                    (float) $candidate['estimated_cost_per_item'] === (float) $best['estimated_cost_per_item']
                    && (float) $candidate['bonus_percent'] > (float) $best['bonus_percent']
                )
            ) {
                $best = $candidate;
            }
        }

        return $best;
    }
}
