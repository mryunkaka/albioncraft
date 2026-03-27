<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Repositories\PlanRepository;
use App\Repositories\UserRepository;
use App\Services\MarketPriceService;
use App\Support\Database;
use App\Support\Env;

Env::load(dirname(__DIR__) . '/.env');

$failures = [];
$created = [
    'users' => [],
    'items' => [],
    'cities' => [],
    'categories' => [],
];

/**
 * @param list<string> $failures
 */
function expectTrue(bool $condition, string $message, array &$failures): void
{
    if (! $condition) {
        $failures[] = $message;
    }
}

try {
    $db = Database::connection();
    $plans = new PlanRepository($db);
    $users = new UserRepository($db);
    $service = new MarketPriceService();

    $plans->ensureDefaultPlans();
    $freePlanId = $plans->findIdByCode('FREE');
    if ($freePlanId === null || $freePlanId <= 0) {
        throw new \RuntimeException('FREE plan tidak ditemukan.');
    }

    $seed = (string) time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8);

    $userAId = $users->create([
        'username' => 'mp_user_a_' . $seed,
        'email' => 'mp_user_a_' . $seed . '@example.local',
        'password_hash' => password_hash('TestPass123!', PASSWORD_DEFAULT),
        'referral_code' => strtoupper(substr(bin2hex(random_bytes(8)), 0, 10)),
        'referred_by_code' => null,
        'plan_id' => $freePlanId,
        'plan_expired_at' => null,
        'status' => 'ACTIVE',
    ]);
    $created['users'][] = $userAId;

    $userBId = $users->create([
        'username' => 'mp_user_b_' . $seed,
        'email' => 'mp_user_b_' . $seed . '@example.local',
        'password_hash' => password_hash('TestPass123!', PASSWORD_DEFAULT),
        'referral_code' => strtoupper(substr(bin2hex(random_bytes(8)), 0, 10)),
        'referred_by_code' => null,
        'plan_id' => $freePlanId,
        'plan_expired_at' => null,
        'status' => 'ACTIVE',
    ]);
    $created['users'][] = $userBId;

    $categoryCode = 'TEST_CAT_' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $stmt = $db->prepare('INSERT INTO item_categories (code, name, category_group) VALUES (:code, :name, :group_name)');
    $stmt->execute([
        'code' => $categoryCode,
        'name' => 'Test Category ' . $seed,
        'group_name' => 'TEST',
    ]);
    $categoryId = (int) $db->lastInsertId();
    $created['categories'][] = $categoryId;

    $itemCode1 = 'TEST_ITEM_A_' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $itemCode2 = 'TEST_ITEM_B_' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $slug1 = strtolower($itemCode1) . '-' . $seed;
    $slug2 = strtolower($itemCode2) . '-' . $seed;

    $itemSql = 'INSERT INTO items (item_code, name, slug, category_id, item_value, default_output_qty, tier, enchantment_level, is_database_ready)
                VALUES (:item_code, :name, :slug, :category_id, 0, 1, :tier, :ench, 1)';
    $itemStmt = $db->prepare($itemSql);

    $itemStmt->execute([
        'item_code' => $itemCode1,
        'name' => 'Test Item A ' . $seed,
        'slug' => $slug1,
        'category_id' => $categoryId,
        'tier' => 'T4',
        'ench' => '0',
    ]);
    $item1Id = (int) $db->lastInsertId();
    $created['items'][] = $item1Id;

    $itemStmt->execute([
        'item_code' => $itemCode2,
        'name' => 'Test Item B ' . $seed,
        'slug' => $slug2,
        'category_id' => $categoryId,
        'tier' => 'T4',
        'ench' => '0',
    ]);
    $item2Id = (int) $db->lastInsertId();
    $created['items'][] = $item2Id;

    $cityCode = 'TEST_CITY_' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $cityStmt = $db->prepare('INSERT INTO cities (code, name, city_type) VALUES (:code, :name, :city_type)');
    $cityStmt->execute([
        'code' => $cityCode,
        'name' => 'Test City ' . $seed,
        'city_type' => 'ROYAL',
    ]);
    $cityId = (int) $db->lastInsertId();
    $created['cities'][] = $cityId;

    // Case 1: invalid item
    $invalid = $service->upsertPrice($userAId, [
        'item_id' => 0,
        'price_type' => 'BUY',
        'price_value' => 100,
    ]);
    expectTrue($invalid['ok'] === false, 'upsert invalid item harus gagal.', $failures);

    // Case 2: insert global BUY
    $insert1 = $service->upsertPrice($userAId, [
        'item_id' => $item1Id,
        'city_id' => '',
        'price_type' => 'BUY',
        'price_value' => 1000,
        'observed_at' => '2026-03-26T10:10',
        'notes' => 'first',
    ]);
    expectTrue($insert1['ok'] === true, 'insert global BUY harus sukses.', $failures);

    // Case 3: duplicate unique should update, not insert duplicate
    $insert1b = $service->upsertPrice($userAId, [
        'item_id' => $item1Id,
        'city_id' => '',
        'price_type' => 'BUY',
        'price_value' => 1100,
        'observed_at' => '2026-03-26T11:10',
        'notes' => 'update same unique',
    ]);
    expectTrue($insert1b['ok'] === true, 'upsert duplicate unique harus sukses (update).', $failures);

    $countStmt = $db->prepare('SELECT COUNT(*) FROM market_prices WHERE user_id = :uid AND item_id = :iid AND city_id IS NULL AND price_type = :pt');
    $countStmt->execute(['uid' => $userAId, 'iid' => $item1Id, 'pt' => 'BUY']);
    $globalBuyCount = (int) $countStmt->fetchColumn();
    expectTrue($globalBuyCount === 1, 'duplicate unique menciptakan row ganda.', $failures);

    // Case 4: insert city SELL
    $insert2 = $service->upsertPrice($userAId, [
        'item_id' => $item2Id,
        'city_id' => (string) $cityId,
        'price_type' => 'SELL',
        'price_value' => 2200,
        'observed_at' => '2026-03-26T12:30',
        'notes' => 'city sell',
    ]);
    expectTrue($insert2['ok'] === true, 'insert city SELL harus sukses.', $failures);

    // Case 5: list + filter + pagination
    $listAll = $service->listByUser($userAId, ['page' => 1, 'per_page' => 10]);
    expectTrue((int) $listAll['total'] >= 2, 'list total untuk user A harus >= 2.', $failures);

    $listSell = $service->listByUser($userAId, ['price_type' => 'SELL', 'page' => 1, 'per_page' => 10]);
    expectTrue((int) $listSell['total'] >= 1, 'filter SELL harus menemukan data.', $failures);

    $listByCity = $service->listByUser($userAId, ['city_id' => $cityId, 'page' => 1, 'per_page' => 10]);
    expectTrue((int) $listByCity['total'] >= 1, 'filter city harus menemukan data.', $failures);

    $listByQuery = $service->listByUser($userAId, ['q' => 'Test Item A', 'page' => 1, 'per_page' => 10]);
    expectTrue((int) $listByQuery['total'] >= 1, 'search query item harus menemukan data.', $failures);

    $page1 = $service->listByUser($userAId, ['page' => 1, 'per_page' => 1]);
    $page2 = $service->listByUser($userAId, ['page' => 2, 'per_page' => 1]);
    expectTrue(count($page1['rows']) === 1, 'pagination page1 per_page=1 harus 1 row.', $failures);
    expectTrue(count($page2['rows']) === 1, 'pagination page2 per_page=1 harus 1 row.', $failures);

    // Get target row id (item1 + BUY + global) for edit/delete tests
    $rowId = 0;
    $rowStmt = $db->prepare(
        'SELECT id
         FROM market_prices
         WHERE user_id = :uid
           AND item_id = :iid
           AND city_id IS NULL
           AND price_type = :pt
         LIMIT 1'
    );
    $rowStmt->execute([
        'uid' => $userAId,
        'iid' => $item1Id,
        'pt' => 'BUY',
    ]);
    $rowValue = $rowStmt->fetchColumn();
    if ($rowValue !== false) {
        $rowId = (int) $rowValue;
    }
    expectTrue($rowId > 0, 'row id target edit/delete tidak ditemukan.', $failures);

    // Case 6: explicit update by id path
    if ($rowId > 0) {
        $updateById = $service->upsertPrice($userAId, [
            'id' => $rowId,
            'item_id' => $item1Id,
            'city_id' => '',
            'price_type' => 'BUY',
            'price_value' => 1300,
            'observed_at' => '2026-03-26T13:00',
            'notes' => 'edited by id',
        ]);
        expectTrue($updateById['ok'] === true, 'update by id harus sukses.', $failures);

        $checkStmt = $db->prepare('SELECT price_value, notes FROM market_prices WHERE id = :id LIMIT 1');
        $checkStmt->execute(['id' => $rowId]);
        $edited = $checkStmt->fetch();
        expectTrue(is_array($edited), 'row edit tidak ditemukan.', $failures);
        if (is_array($edited)) {
            expectTrue((float) $edited['price_value'] === 1300.0, 'price_value tidak terupdate.', $failures);
            expectTrue((string) $edited['notes'] === 'edited by id', 'notes tidak terupdate.', $failures);
        }
    }

    // Case 7: ownership check on delete
    if ($rowId > 0) {
        $deleteByOtherUser = $service->deletePrice($userBId, $rowId);
        expectTrue($deleteByOtherUser['ok'] === false, 'delete oleh user lain harus ditolak.', $failures);

        $deleteByOwner = $service->deletePrice($userAId, $rowId);
        expectTrue($deleteByOwner['ok'] === true, 'delete oleh owner harus sukses.', $failures);
    }

    // Case 8: item options + city options
    $itemOptions = $service->itemOptions('Test Item');
    expectTrue(count($itemOptions) >= 1, 'itemOptions search harus mengembalikan data.', $failures);
    $cityOptions = $service->cityOptions();
    expectTrue(count($cityOptions) >= 1, 'cityOptions harus mengembalikan data.', $failures);

    // Case 9: bulk import/update
    $bulkRows = implode("\n", [
        'item_code,city_code,price_type,price_value,observed_at,notes',
        $itemCode1 . ',,BUY,1400,2026-03-27 09:00:00,bulk global update',
        $itemCode2 . ',' . $cityCode . ',SELL,2500,2026-03-27 09:10:00,bulk city update',
    ]);
    $bulk = $service->bulkUpsertPrices($userAId, [
        'bulk_rows' => $bulkRows,
    ]);
    expectTrue($bulk['ok'] === true, 'bulk import harus sukses.', $failures);
    expectTrue((int) ($bulk['updated_count'] ?? 0) >= 1, 'bulk import harus mengupdate minimal 1 row existing.', $failures);

    $bulkCheckStmt = $db->prepare(
        'SELECT price_value, notes
         FROM market_prices
         WHERE user_id = :uid
           AND item_id = :iid
           AND city_id IS NULL
           AND price_type = :pt
         LIMIT 1'
    );
    $bulkCheckStmt->execute([
        'uid' => $userAId,
        'iid' => $item1Id,
        'pt' => 'BUY',
    ]);
    $bulkRow = $bulkCheckStmt->fetch();
    expectTrue(is_array($bulkRow), 'bulk update row global BUY tidak ditemukan.', $failures);
    if (is_array($bulkRow)) {
        expectTrue((float) $bulkRow['price_value'] === 1400.0, 'bulk update global BUY gagal mengubah price_value.', $failures);
    }

    // Case 10: craft fee type allowed
    $craftFeeInsert = $service->upsertPrice($userAId, [
        'item_id' => $item2Id,
        'city_id' => (string) $cityId,
        'price_type' => 'CRAFT_FEE',
        'price_value' => 275,
        'observed_at' => '2026-03-27T14:00',
        'notes' => 'craft fee city',
    ]);
    expectTrue($craftFeeInsert['ok'] === true, 'insert CRAFT_FEE harus sukses.', $failures);

    $bulkInvalid = $service->bulkUpsertPrices($userAId, [
        'bulk_rows' => "item_code,city_code,price_type,price_value\nUNKNOWN_ITEM,,BUY,10",
    ]);
    expectTrue($bulkInvalid['ok'] === false, 'bulk import yang seluruh row-nya invalid harus gagal.', $failures);
    expectTrue((int) ($bulkInvalid['error_count'] ?? 0) >= 1, 'bulk invalid harus mengembalikan minimal 1 error.', $failures);

    // Cleanup
    if ($created['users'] !== []) {
        $uidCsv = implode(',', array_map(static fn (int $v): string => (string) $v, $created['users']));
        $db->exec("DELETE FROM market_prices WHERE user_id IN ({$uidCsv})");
        $db->exec("DELETE FROM calculation_histories WHERE user_id IN ({$uidCsv})");
        $db->exec("DELETE FROM admin_subscription_actions WHERE user_id IN ({$uidCsv})");
        $db->exec("DELETE FROM subscription_logs WHERE user_id IN ({$uidCsv})");
        $db->exec("DELETE FROM subscriptions WHERE user_id IN ({$uidCsv})");
        $db->exec("DELETE FROM referral_rewards WHERE rewarded_user_id IN ({$uidCsv})");
        $db->exec("DELETE FROM referrals WHERE referrer_user_id IN ({$uidCsv}) OR referred_user_id IN ({$uidCsv})");
        $db->exec("DELETE FROM users WHERE id IN ({$uidCsv})");
    }

    if ($created['items'] !== []) {
        $itemCsv = implode(',', array_map(static fn (int $v): string => (string) $v, $created['items']));
        $db->exec("DELETE FROM recipe_materials WHERE material_item_id IN ({$itemCsv})");
        $db->exec("DELETE FROM recipes WHERE item_id IN ({$itemCsv})");
        $db->exec("DELETE FROM market_prices WHERE item_id IN ({$itemCsv})");
        $db->exec("DELETE FROM calculation_histories WHERE item_id IN ({$itemCsv})");
        $db->exec("DELETE FROM items WHERE id IN ({$itemCsv})");
    }

    if ($created['categories'] !== []) {
        $catCsv = implode(',', array_map(static fn (int $v): string => (string) $v, $created['categories']));
        $db->exec("DELETE FROM city_bonuses WHERE category_id IN ({$catCsv})");
        $db->exec("DELETE FROM item_categories WHERE id IN ({$catCsv})");
    }

    if ($created['cities'] !== []) {
        $cityCsv = implode(',', array_map(static fn (int $v): string => (string) $v, $created['cities']));
        $db->exec("DELETE FROM market_prices WHERE city_id IN ({$cityCsv})");
        $db->exec("DELETE FROM city_bonuses WHERE city_id IN ({$cityCsv})");
        $db->exec("DELETE FROM cities WHERE id IN ({$cityCsv})");
    }
} catch (\Throwable $e) {
    $failures[] = 'Unhandled exception: ' . $e->getMessage();
}

if ($failures !== []) {
    fwrite(STDERR, "FAILED\n" . implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "PASS\n";
