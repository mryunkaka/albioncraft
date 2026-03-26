<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CityRepository;
use App\Repositories\ItemRepository;
use App\Repositories\MarketPriceRepository;
use App\Support\Database;

final class MarketPriceService
{
    private ItemRepository $items;
    private CityRepository $cities;
    private MarketPriceRepository $prices;

    public function __construct()
    {
        $db = Database::connection();
        $this->items = new ItemRepository($db);
        $this->cities = new CityRepository($db);
        $this->prices = new MarketPriceRepository($db);
    }

    /**
     * @return array{
     *   rows: array<int, array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   per_page: int,
     *   last_page: int
     * }
     */
    public function listByUser(int $userId, array $query): array
    {
        $keyword = trim((string) ($query['q'] ?? ''));
        $priceType = strtoupper(trim((string) ($query['price_type'] ?? '')));
        if (! in_array($priceType, ['BUY', 'SELL'], true)) {
            $priceType = '';
        }
        $cityId = (int) ($query['city_id'] ?? 0);
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }

        $result = $this->prices->paginateByUser($userId, $keyword, $priceType, $cityId, $page, $perPage);
        $total = (int) $result['total'];
        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'rows' => $result['rows'],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function upsertPrice(int $userId, array $input): array
    {
        $itemId = (int) ($input['item_id'] ?? 0);
        $cityIdRaw = trim((string) ($input['city_id'] ?? ''));
        $cityId = $cityIdRaw === '' ? null : (int) $cityIdRaw;
        $priceType = strtoupper(trim((string) ($input['price_type'] ?? '')));
        $priceValue = (float) ($input['price_value'] ?? 0);
        $observedAt = trim((string) ($input['observed_at'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));

        if ($itemId <= 0) {
            return ['ok' => false, 'message' => 'Item wajib dipilih.'];
        }
        if (! in_array($priceType, ['BUY', 'SELL'], true)) {
            return ['ok' => false, 'message' => 'Price type harus BUY atau SELL.'];
        }
        if ($priceValue < 0) {
            return ['ok' => false, 'message' => 'Price value tidak boleh negatif.'];
        }

        $observedAtSql = null;
        if ($observedAt !== '') {
            $observedAtSql = str_replace('T', ' ', $observedAt);
            if (strlen($observedAtSql) === 16) {
                $observedAtSql .= ':00';
            }
        }

        $existing = $this->prices->findOneByUnique($userId, $itemId, $cityId, $priceType);
        if ($existing !== null) {
            $this->prices->update((int) $existing['id'], [
                'price_value' => $priceValue,
                'observed_at' => $observedAtSql,
                'notes' => $notes !== '' ? $notes : null,
            ]);
            return ['ok' => true, 'message' => 'Harga berhasil diupdate.'];
        }

        $this->prices->insert([
            'user_id' => $userId,
            'item_id' => $itemId,
            'city_id' => $cityId,
            'price_type' => $priceType,
            'price_value' => $priceValue,
            'observed_at' => $observedAtSql,
            'notes' => $notes !== '' ? $notes : null,
        ]);
        return ['ok' => true, 'message' => 'Harga berhasil disimpan.'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function itemOptions(string $keyword = ''): array
    {
        if (trim($keyword) === '') {
            return $this->items->listAll(200);
        }
        return $this->items->searchByName($keyword, 30);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cityOptions(): array
    {
        return $this->cities->listAll();
    }
}

