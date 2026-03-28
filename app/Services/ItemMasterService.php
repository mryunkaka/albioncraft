<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CityBonusRepository;
use App\Repositories\ItemCategoryRepository;
use App\Repositories\ItemRepository;
use App\Support\Database;
use Throwable;

final class ItemMasterService
{
    private const CRAFT_CATEGORY = [
        'code' => 'USER_CRAFTING_MANUAL',
        'name' => 'User Crafting Manual',
        'category_group' => 'CRAFTING',
    ];

    private const MATERIAL_CATEGORY = [
        'code' => 'USER_MATERIAL_MANUAL',
        'name' => 'User Material Manual',
        'category_group' => 'MATERIAL',
    ];

    private ItemRepository $items;
    private ItemCategoryRepository $categories;
    private CityBonusRepository $cityBonuses;
    private MarketPriceService $prices;

    public function __construct()
    {
        $db = Database::connection();
        $this->items = new ItemRepository($db);
        $this->categories = new ItemCategoryRepository($db);
        $this->cityBonuses = new CityBonusRepository($db);
        $this->prices = new MarketPriceService();
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function persistSelectionHelper(int $userId, array $input): array
    {
        $itemName = $this->normalizeDisplayName((string) ($input['item_name'] ?? ''));
        $itemValue = $this->floatValue($input['item_value'] ?? 0);
        $craftFee = $this->nullableFloat($input['craft_fee'] ?? null);
        $craftFeeCityId = $this->nullableInt($input['craft_fee_city_id'] ?? null);
        $sellPrice = $this->nullableFloat($input['sell_price'] ?? null);
        $sellPriceCityId = $this->nullableInt($input['sell_price_city_id'] ?? null);
        $materials = is_array($input['materials'] ?? null) ? $input['materials'] : [];
        $rows = is_array($input['rows'] ?? null) ? $input['rows'] : [];

        if ($userId <= 0) {
            return ['ok' => false, 'message' => 'Unauthorized'];
        }

        if ($itemName === '') {
            return ['ok' => false, 'message' => 'Nama item craft wajib diisi.'];
        }

        $db = Database::connection();
        $createdItemCount = 0;
        $createdCategoryCount = 0;
        $savedPriceCount = 0;
        $materialResults = [];
        $materialItems = [];
        $helperObservedAt = date('Y-m-d H:i:s');

        try {
            $db->beginTransaction();

            [$craftCategoryId, $createdCraftCategory] = $this->ensureCategory(self::CRAFT_CATEGORY);
            [$materialCategoryId, $createdMaterialCategory] = $this->ensureCategory(self::MATERIAL_CATEGORY);
            if ($createdCraftCategory) {
                $createdCategoryCount++;
            }
            if ($createdMaterialCategory) {
                $createdCategoryCount++;
            }

            [$craftItem, $createdCraftItem] = $this->ensureItem($itemName, $craftCategoryId, $itemValue);
            if ($createdCraftItem) {
                $createdItemCount++;
            }

            foreach ($materials as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $materialName = $this->normalizeDisplayName((string) ($row['name'] ?? ''));
                if ($materialName === '') {
                    continue;
                }

                $buyPrice = $this->nullableFloat($row['buy_price'] ?? null);
                $cityId = $this->nullableInt($row['city_id'] ?? null);

                [$materialItem, $createdMaterialItem] = $this->ensureItem($materialName, $materialCategoryId, 0.0);
                if ($createdMaterialItem) {
                    $createdItemCount++;
                }

                if ($buyPrice !== null && $cityId !== null && $cityId > 0) {
                    $result = $this->prices->upsertPrice($userId, [
                        'item_id' => (int) ($materialItem['id'] ?? 0),
                        'city_id' => $cityId,
                        'price_type' => 'BUY',
                        'price_value' => $buyPrice,
                        'observed_at' => $helperObservedAt,
                        'notes' => 'selection helper buy',
                    ]);
                    if (! $result['ok']) {
                        throw new \RuntimeException($result['message']);
                    }
                    $savedPriceCount++;
                }

                $materialResults[] = [
                    'id' => (int) ($materialItem['id'] ?? 0),
                    'name' => (string) ($materialItem['name'] ?? $materialName),
                    'item_code' => (string) ($materialItem['item_code'] ?? ''),
                    'slug' => (string) ($materialItem['slug'] ?? ''),
                    'item_value' => (float) ($materialItem['item_value'] ?? 0),
                ];
                $materialItems[] = $materialItem;
            }

            if ($rows !== []) {
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $cityId = $this->nullableInt($row['city_id'] ?? null);
                    if ($cityId === null || $cityId <= 0) {
                        continue;
                    }

                    $rowBonus = $this->nullableFloat($row['bonus'] ?? null);
                    if ($rowBonus !== null) {
                        $this->upsertCityBonus($cityId, (int) ($craftItem['category_id'] ?? 0), $rowBonus);
                    }

                    $rowCraftFee = $this->nullableFloat($row['craft_fee'] ?? null);
                    if ($rowCraftFee !== null) {
                        $result = $this->prices->upsertPrice($userId, [
                            'item_id' => (int) ($craftItem['id'] ?? 0),
                            'city_id' => $cityId,
                            'price_type' => 'CRAFT_FEE',
                            'price_value' => $rowCraftFee,
                            'observed_at' => $helperObservedAt,
                            'notes' => 'selection helper craft fee',
                        ]);
                        if (! $result['ok']) {
                            throw new \RuntimeException($result['message']);
                        }
                        $savedPriceCount++;
                    }

                    $rowSellPrice = $this->nullableFloat($row['sell_price'] ?? null);
                    if ($rowSellPrice !== null) {
                        $result = $this->prices->upsertPrice($userId, [
                            'item_id' => (int) ($craftItem['id'] ?? 0),
                            'city_id' => $cityId,
                            'price_type' => 'SELL',
                            'price_value' => $rowSellPrice,
                            'observed_at' => $helperObservedAt,
                            'notes' => 'selection helper sell',
                        ]);
                        if (! $result['ok']) {
                            throw new \RuntimeException($result['message']);
                        }
                        $savedPriceCount++;
                    }

                    $rowMaterials = is_array($row['materials'] ?? null) ? $row['materials'] : [];
                    foreach ($materialItems as $index => $materialItem) {
                        $materialRow = is_array($rowMaterials[$index] ?? null) ? $rowMaterials[$index] : [];
                        $buyPrice = $this->nullableFloat($materialRow['buy_price'] ?? null);
                        if ($buyPrice === null) {
                            continue;
                        }

                        $result = $this->prices->upsertPrice($userId, [
                            'item_id' => (int) ($materialItem['id'] ?? 0),
                            'city_id' => $cityId,
                            'price_type' => 'BUY',
                            'price_value' => $buyPrice,
                            'observed_at' => $helperObservedAt,
                            'notes' => 'selection helper buy',
                        ]);
                        if (! $result['ok']) {
                            throw new \RuntimeException($result['message']);
                        }
                        $savedPriceCount++;
                    }
                }
            } else {
                if ($craftFee !== null && $craftFeeCityId !== null && $craftFeeCityId > 0) {
                    $result = $this->prices->upsertPrice($userId, [
                        'item_id' => (int) ($craftItem['id'] ?? 0),
                        'city_id' => $craftFeeCityId,
                        'price_type' => 'CRAFT_FEE',
                        'price_value' => $craftFee,
                        'observed_at' => $helperObservedAt,
                        'notes' => 'selection helper craft fee',
                    ]);
                    if (! $result['ok']) {
                        throw new \RuntimeException($result['message']);
                    }
                    $savedPriceCount++;
                }

                if ($sellPrice !== null && $sellPriceCityId !== null && $sellPriceCityId > 0) {
                    $result = $this->prices->upsertPrice($userId, [
                        'item_id' => (int) ($craftItem['id'] ?? 0),
                        'city_id' => $sellPriceCityId,
                        'price_type' => 'SELL',
                        'price_value' => $sellPrice,
                        'observed_at' => $helperObservedAt,
                        'notes' => 'selection helper sell',
                    ]);
                    if (! $result['ok']) {
                        throw new \RuntimeException($result['message']);
                    }
                    $savedPriceCount++;
                }
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            return [
                'ok' => false,
                'message' => 'Auto-create item master gagal: ' . $e->getMessage(),
            ];
        }

        return [
            'ok' => true,
            'message' => sprintf(
                'Helper tersimpan. %d kategori dibuat, %d item dibuat, %d harga disimpan.',
                $createdCategoryCount,
                $createdItemCount,
                $savedPriceCount
            ),
            'data' => [
                'item' => [
                    'id' => (int) ($craftItem['id'] ?? 0),
                    'name' => (string) ($craftItem['name'] ?? $itemName),
                    'item_code' => (string) ($craftItem['item_code'] ?? ''),
                    'slug' => (string) ($craftItem['slug'] ?? ''),
                    'item_value' => (float) ($craftItem['item_value'] ?? $itemValue),
                ],
                'materials' => $materialResults,
                'created_category_count' => $createdCategoryCount,
                'created_item_count' => $createdItemCount,
                'saved_price_count' => $savedPriceCount,
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function syncCalculatorInputMasterData(int $userId, array $input): array
    {
        $itemName = $this->normalizeDisplayName((string) ($input['item_name'] ?? ''));
        $itemValue = $this->floatValue($input['item_value'] ?? 0);
        $materials = is_array($input['materials'] ?? null) ? $input['materials'] : [];
        $bonusLocal = $this->nullableFloat($input['bonus_local'] ?? null);
        $bonusLocalCityId = $this->nullableInt($input['bonus_local_city_id'] ?? null);

        if ($userId <= 0 || $itemName === '') {
            return [
                'ok' => true,
                'message' => 'Skip sync master item.',
                'data' => [
                    'item' => null,
                    'materials' => [],
                ],
            ];
        }

        $db = Database::connection();
        $createdCategoryCount = 0;
        $createdItemCount = 0;
        $materialResults = [];

        try {
            $db->beginTransaction();

            [$craftCategoryId, $createdCraftCategory] = $this->ensureCategory(self::CRAFT_CATEGORY);
            [$materialCategoryId, $createdMaterialCategory] = $this->ensureCategory(self::MATERIAL_CATEGORY);
            if ($createdCraftCategory) {
                $createdCategoryCount++;
            }
            if ($createdMaterialCategory) {
                $createdCategoryCount++;
            }

            [$craftItem, $createdCraftItem] = $this->ensureItem($itemName, $craftCategoryId, $itemValue);
            if ($createdCraftItem) {
                $createdItemCount++;
            }

            foreach ($materials as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $materialName = $this->normalizeDisplayName((string) ($row['name'] ?? ''));
                if ($materialName === '') {
                    continue;
                }

                $materialValue = $this->floatValue($row['item_value'] ?? 0);
                [$materialItem, $createdMaterialItem] = $this->ensureItem($materialName, $materialCategoryId, $materialValue);
                if ($createdMaterialItem) {
                    $createdItemCount++;
                }

                $materialResults[] = [
                    'id' => (int) ($materialItem['id'] ?? 0),
                    'name' => (string) ($materialItem['name'] ?? $materialName),
                    'item_code' => (string) ($materialItem['item_code'] ?? ''),
                    'slug' => (string) ($materialItem['slug'] ?? ''),
                    'item_value' => (float) ($materialItem['item_value'] ?? $materialValue),
                ];
            }

            if ($bonusLocalCityId !== null && $bonusLocalCityId > 0 && $bonusLocal !== null) {
                $this->upsertCityBonus($bonusLocalCityId, (int) ($craftItem['category_id'] ?? 0), $bonusLocal);
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            return [
                'ok' => false,
                'message' => 'Sync master item gagal: ' . $e->getMessage(),
            ];
        }

        return [
            'ok' => true,
            'message' => sprintf(
                'Sync master selesai. %d kategori dibuat, %d item dibuat.',
                $createdCategoryCount,
                $createdItemCount
            ),
            'data' => [
                'item' => [
                    'id' => (int) ($craftItem['id'] ?? 0),
                    'name' => (string) ($craftItem['name'] ?? $itemName),
                    'item_code' => (string) ($craftItem['item_code'] ?? ''),
                    'slug' => (string) ($craftItem['slug'] ?? ''),
                    'item_value' => (float) ($craftItem['item_value'] ?? $itemValue),
                ],
                'materials' => $materialResults,
                'created_category_count' => $createdCategoryCount,
                'created_item_count' => $createdItemCount,
            ],
        ];
    }

    /**
     * @param array{code: string, name: string, category_group: string} $payload
     * @return array{0: int, 1: bool}
     */
    private function ensureCategory(array $payload): array
    {
        $existing = $this->categories->findByCode($payload['code']);
        if ($existing === null) {
            $existing = $this->categories->findByName($payload['name']);
        }

        if ($existing !== null) {
            return [(int) ($existing['id'] ?? 0), false];
        }

        $id = $this->categories->create($payload);
        return [$id, true];
    }

    /**
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function ensureItem(string $displayName, int $categoryId, float $itemValue): array
    {
        $itemCodeBase = $this->makeItemCode($displayName);
        $slugBase = $this->makeSlug($displayName);
        $existing = $this->items->findByIdentity($displayName, $itemCodeBase, $slugBase);

        if ($existing !== null) {
            $updatedValue = (float) ($existing['item_value'] ?? 0);
            $nextValue = $itemValue > 0 ? $itemValue : $updatedValue;
            $tier = (string) ($existing['tier'] ?? '');
            $enchantment = (string) ($existing['enchantment_level'] ?? '');
            $parsedTier = $this->extractTier($displayName);
            $parsedEnchantment = $this->extractEnchantment($displayName);

            $needsUpdate = false;
            if ($nextValue !== $updatedValue) {
                $needsUpdate = true;
            }
            if ($tier === '' && $parsedTier !== null) {
                $tier = $parsedTier;
                $needsUpdate = true;
            }
            if (($enchantment === '' || $enchantment === '0') && $parsedEnchantment !== null) {
                $enchantment = $parsedEnchantment;
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $this->items->updateMetadata((int) $existing['id'], [
                    'item_value' => $nextValue,
                    'default_output_qty' => (int) ($existing['default_output_qty'] ?? 1),
                    'tier' => $tier !== '' ? $tier : null,
                    'enchantment_level' => $enchantment !== '' ? $enchantment : '0',
                ]);
                $existing = $this->items->findById((int) $existing['id']) ?? $existing;
            }

            return [$existing, false];
        }

        $itemCode = $this->makeUniqueItemCode($itemCodeBase);
        $slug = $this->makeUniqueSlug($slugBase);

        $id = $this->items->create([
            'item_code' => $itemCode,
            'name' => $displayName,
            'slug' => $slug,
            'category_id' => $categoryId,
            'item_value' => $itemValue > 0 ? $itemValue : 0.0,
            'default_output_qty' => 1,
            'tier' => $this->extractTier($displayName),
            'enchantment_level' => $this->extractEnchantment($displayName) ?? '0',
            'is_database_ready' => 0,
        ]);

        $created = $this->items->findById($id);
        if ($created === null) {
            throw new \RuntimeException('Item berhasil dibuat tetapi gagal dibaca ulang.');
        }

        return [$created, true];
    }

    private function upsertCityBonus(int $cityId, int $categoryId, float $bonusPercent): void
    {
        if ($cityId <= 0 || $categoryId <= 0) {
            return;
        }

        $existing = $this->cityBonuses->findOneByUnique($cityId, $categoryId);
        if ($existing !== null) {
            if ((float) ($existing['bonus_percent'] ?? 0) === $bonusPercent) {
                return;
            }
            $this->cityBonuses->update((int) ($existing['id'] ?? 0), $bonusPercent);
            return;
        }

        $this->cityBonuses->insert([
            'city_id' => $cityId,
            'category_id' => $categoryId,
            'bonus_percent' => $bonusPercent,
        ]);
    }

    private function makeUniqueItemCode(string $baseCode): string
    {
        $candidate = $baseCode !== '' ? $baseCode : 'USER_ITEM';
        $suffix = 2;

        while ($this->items->findByItemCode($candidate) !== null) {
            $candidate = $baseCode . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function makeUniqueSlug(string $baseSlug): string
    {
        $candidate = $baseSlug !== '' ? $baseSlug : 'user-item';
        $suffix = 2;

        while ($this->items->findBySlug($candidate) !== null) {
            $candidate = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function makeItemCode(string $displayName): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9]+/', '_', strtoupper($displayName));
        $normalized = is_string($normalized) ? trim($normalized, '_') : '';
        return $normalized !== '' ? $normalized : 'USER_ITEM';
    }

    private function makeSlug(string $displayName): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $displayName);
        $normalized = is_string($normalized) ? $normalized : $displayName;
        $normalized = strtolower($normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized);
        $normalized = is_string($normalized) ? trim($normalized, '-') : '';
        return $normalized !== '' ? $normalized : 'user-item';
    }

    private function normalizeDisplayName(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if ($value === '') {
            return '';
        }

        return mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function extractTier(string $value): ?string
    {
        if (preg_match('/\bT([1-8])\b/i', $value, $matches) === 1) {
            return 'T' . $matches[1];
        }

        return null;
    }

    private function extractEnchantment(string $value): ?string
    {
        if (preg_match('/(?:\.|@)([1-4])\b/', $value, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function floatValue(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
