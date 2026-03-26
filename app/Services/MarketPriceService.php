<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CityRepository;
use App\Repositories\ItemRepository;
use App\Repositories\MarketPriceRepository;
use App\Support\Database;
use Throwable;

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
        $id = (int) ($input['id'] ?? 0);
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

        if ($id > 0) {
            $target = $this->prices->findByIdAndUser($id, $userId);
            if ($target === null) {
                return ['ok' => false, 'message' => 'Data harga tidak ditemukan.'];
            }

            $duplicate = $this->prices->findOneByUnique($userId, $itemId, $cityId, $priceType);
            if ($duplicate !== null && (int) $duplicate['id'] !== $id) {
                return ['ok' => false, 'message' => 'Kombinasi item/city/type sudah ada. Gunakan Edit pada data tersebut.'];
            }

            $this->prices->updateFull($id, $userId, [
                'item_id' => $itemId,
                'city_id' => $cityId,
                'price_type' => $priceType,
                'price_value' => $priceValue,
                'observed_at' => $observedAtSql,
                'notes' => $notes !== '' ? $notes : null,
            ]);
            return ['ok' => true, 'message' => 'Harga berhasil diupdate.'];
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
     * @return array{ok: bool, message: string}
     */
    public function deletePrice(int $userId, int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'ID tidak valid.'];
        }

        $target = $this->prices->findByIdAndUser($id, $userId);
        if ($target === null) {
            return ['ok' => false, 'message' => 'Data harga tidak ditemukan.'];
        }

        $this->prices->deleteByIdAndUser($id, $userId);
        return ['ok' => true, 'message' => 'Data harga berhasil dihapus.'];
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

    /**
     * @return array{
     *   ok: bool,
     *   message: string,
     *   created_count?: int,
     *   updated_count?: int,
     *   error_count?: int,
     *   errors?: array<int, string>
     * }
     */
    public function bulkUpsertPrices(int $userId, array $input): array
    {
        $raw = trim((string) ($input['bulk_rows'] ?? ''));
        if ($raw === '') {
            return [
                'ok' => false,
                'message' => 'Data bulk wajib diisi.',
                'errors' => ['Data bulk tidak boleh kosong.'],
                'error_count' => 1,
            ];
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $parsedRows = [];
        $errors = [];

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $columns = $this->parseBulkColumns($trimmed);
            if ($columns === []) {
                continue;
            }

            if ($index === 0 && $this->looksLikeBulkHeader($columns)) {
                continue;
            }

            if (count($columns) < 4) {
                $errors[] = 'Baris ' . ($index + 1) . ' minimal harus punya 4 kolom: item, city, type, price.';
                continue;
            }

            $parsedRows[] = [
                'line' => $index + 1,
                'item_ref' => trim((string) ($columns[0] ?? '')),
                'city_ref' => trim((string) ($columns[1] ?? '')),
                'price_type' => strtoupper(trim((string) ($columns[2] ?? ''))),
                'price_value' => trim((string) ($columns[3] ?? '')),
                'observed_at' => trim((string) ($columns[4] ?? '')),
                'notes' => trim((string) ($columns[5] ?? '')),
            ];
        }

        if ($parsedRows === []) {
            return [
                'ok' => false,
                'message' => 'Tidak ada row bulk yang valid untuk diproses.',
                'errors' => $errors !== [] ? $errors : ['Tidak ada row data yang bisa diproses.'],
                'error_count' => max(1, count($errors)),
            ];
        }

        $itemMap = $this->buildItemLookupMap();
        $cityMap = $this->buildCityLookupMap();
        $createdCount = 0;
        $updatedCount = 0;
        $db = Database::connection();

        try {
            $db->beginTransaction();

            foreach ($parsedRows as $row) {
                $itemRef = (string) $row['item_ref'];
                $cityRef = (string) $row['city_ref'];
                $priceType = (string) $row['price_type'];
                $priceValueRaw = str_replace(',', '.', (string) $row['price_value']);
                $observedAt = (string) $row['observed_at'];
                $notes = (string) $row['notes'];

                $itemId = $itemMap[$this->normalizeLookupKey($itemRef)] ?? 0;
                if ($itemId <= 0) {
                    $errors[] = 'Baris ' . $row['line'] . ': item tidak dikenali [' . $itemRef . '].';
                    continue;
                }

                $cityId = null;
                if ($cityRef !== '' && ! in_array(strtoupper($cityRef), ['GLOBAL', '-'], true)) {
                    $cityId = $cityMap[$this->normalizeLookupKey($cityRef)] ?? 0;
                    if ($cityId <= 0) {
                        $errors[] = 'Baris ' . $row['line'] . ': city tidak dikenali [' . $cityRef . '].';
                        continue;
                    }
                }

                if (! in_array($priceType, ['BUY', 'SELL'], true)) {
                    $errors[] = 'Baris ' . $row['line'] . ': price type harus BUY atau SELL.';
                    continue;
                }

                if ($priceValueRaw === '' || ! is_numeric($priceValueRaw) || (float) $priceValueRaw < 0) {
                    $errors[] = 'Baris ' . $row['line'] . ': price value tidak valid.';
                    continue;
                }

                $observedAtSql = null;
                if ($observedAt !== '') {
                    $observedAtSql = str_replace('T', ' ', $observedAt);
                    if (strlen($observedAtSql) === 10) {
                        $observedAtSql .= ' 00:00:00';
                    } elseif (strlen($observedAtSql) === 16) {
                        $observedAtSql .= ':00';
                    }
                }

                $existing = $this->prices->findOneByUnique($userId, $itemId, $cityId, $priceType);
                if ($existing !== null) {
                    $this->prices->update((int) $existing['id'], [
                        'price_value' => (float) $priceValueRaw,
                        'observed_at' => $observedAtSql,
                        'notes' => $notes !== '' ? $notes : null,
                    ]);
                    $updatedCount++;
                    continue;
                }

                $this->prices->insert([
                    'user_id' => $userId,
                    'item_id' => $itemId,
                    'city_id' => $cityId,
                    'price_type' => $priceType,
                    'price_value' => (float) $priceValueRaw,
                    'observed_at' => $observedAtSql,
                    'notes' => $notes !== '' ? $notes : null,
                ]);
                $createdCount++;
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            return [
                'ok' => false,
                'message' => 'Bulk import gagal diproses.',
                'errors' => [$e->getMessage()],
                'error_count' => 1,
            ];
        }

        $errorCount = count($errors);
        $successCount = $createdCount + $updatedCount;
        $ok = $successCount > 0 || $errorCount === 0;
        $message = sprintf(
            'Bulk selesai. %d created, %d updated, %d error.',
            $createdCount,
            $updatedCount,
            $errorCount
        );
        if (! $ok) {
            $message = 'Bulk gagal. Semua row bermasalah. ' . $message;
        }

        return [
            'ok' => $ok,
            'message' => $message,
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'error_count' => $errorCount,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function parseBulkColumns(string $line): array
    {
        if (str_contains($line, "\t")) {
            return array_map('trim', explode("\t", $line));
        }

        return array_map(
            static fn (string $value): string => trim($value),
            str_getcsv($line, ',', '"', '\\')
        );
    }

    /**
     * @param array<int, string> $columns
     */
    private function looksLikeBulkHeader(array $columns): bool
    {
        $first = strtoupper(trim((string) ($columns[0] ?? '')));
        $third = strtoupper(trim((string) ($columns[2] ?? '')));
        return in_array($first, ['ITEM', 'ITEM_CODE', 'ITEM_NAME'], true)
            || in_array($third, ['TYPE', 'PRICE_TYPE'], true);
    }

    /**
     * @return array<string, int>
     */
    private function buildItemLookupMap(): array
    {
        $map = [];
        foreach ($this->items->listAllForLookup() as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $itemCode = trim((string) ($row['item_code'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));

            if ($itemCode !== '') {
                $map[$this->normalizeLookupKey($itemCode)] = $id;
            }
            if ($name !== '') {
                $nameKey = $this->normalizeLookupKey($name);
                if (! isset($map[$nameKey])) {
                    $map[$nameKey] = $id;
                }
            }
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    private function buildCityLookupMap(): array
    {
        $map = [];
        foreach ($this->cities->listAllForLookup() as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $code = trim((string) ($row['code'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));

            if ($code !== '') {
                $map[$this->normalizeLookupKey($code)] = $id;
            }
            if ($name !== '') {
                $nameKey = $this->normalizeLookupKey($name);
                if (! isset($map[$nameKey])) {
                    $map[$nameKey] = $id;
                }
            }
        }

        return $map;
    }

    private function normalizeLookupKey(string $value): string
    {
        return mb_strtoupper(trim($value), 'UTF-8');
    }
}
