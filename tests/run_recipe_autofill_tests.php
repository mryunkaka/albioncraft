<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Repositories\PlanRepository;
use App\Repositories\UserRepository;
use App\Services\RecipeAutoFillService;
use App\Support\Database;
use App\Support\Env;

Env::load(dirname(__DIR__) . '/.env');

$failures = [];
$created = [
    'users' => [],
    'market_prices' => [],
    'saved_recipes' => [],
];

/**
 * @param list<string> $failures
 */
function expectOk(bool $condition, string $message, array &$failures): void
{
    if (! $condition) {
        $failures[] = $message;
    }
}

try {
    $db = Database::connection();
    $plans = new PlanRepository($db);
    $users = new UserRepository($db);
    $service = new RecipeAutoFillService();

    $plans->ensureDefaultPlans();
    $freePlanId = $plans->findIdByCode('FREE');
    $proPlanId = $plans->findIdByCode('PRO');
    if ($freePlanId === null || $freePlanId <= 0) {
        throw new \RuntimeException('FREE plan tidak ditemukan.');
    }
    if ($proPlanId === null || $proPlanId <= 0) {
        throw new \RuntimeException('PRO plan tidak ditemukan.');
    }

    $seed = (string) time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    $userId = $users->create([
        'username' => 'raf_user_' . $seed,
        'email' => 'raf_user_' . $seed . '@example.local',
        'password_hash' => password_hash('TestPass123!', PASSWORD_DEFAULT),
        'referral_code' => strtoupper(substr(bin2hex(random_bytes(8)), 0, 10)),
        'referred_by_code' => null,
        'plan_id' => $freePlanId,
        'plan_expired_at' => null,
        'status' => 'ACTIVE',
    ]);
    $created['users'][] = $userId;
    $proUserId = $users->create([
        'username' => 'raf_pro_' . $seed,
        'email' => 'raf_pro_' . $seed . '@example.local',
        'password_hash' => password_hash('TestPass123!', PASSWORD_DEFAULT),
        'referral_code' => strtoupper(substr(bin2hex(random_bytes(8)), 0, 10)),
        'referred_by_code' => null,
        'plan_id' => $proPlanId,
        'plan_expired_at' => null,
        'status' => 'ACTIVE',
    ]);
    $created['users'][] = $proUserId;

    $freeAuth = [
        'user_id' => $userId,
        'username' => 'raf_user_' . $seed,
        'email' => 'raf_user_' . $seed . '@example.local',
        'plan_id' => $freePlanId,
        'plan_code' => 'FREE',
        'plan_name' => 'Free',
        'plan_expired_at' => null,
    ];
    $proAuth = [
        'user_id' => $proUserId,
        'username' => 'raf_pro_' . $seed,
        'email' => 'raf_pro_' . $seed . '@example.local',
        'plan_id' => $proPlanId,
        'plan_code' => 'PRO',
        'plan_name' => 'Pro',
        'plan_expired_at' => null,
    ];

    $items = $service->itemOptions('', $freeAuth);
    expectOk(count($items) >= 3, 'Item options recipe harus mengembalikan minimal 3 sample seed.', $failures);

    $leatherT3 = null;
    $potion = null;
    $helmet = null;
    foreach ($items as $item) {
        if (($item['item_code'] ?? '') === 'LEATHER_T3') {
            $leatherT3 = $item;
        }
        if (($item['item_code'] ?? '') === 'T4_POTION_SAMPLE') {
            $potion = $item;
        }
        if (($item['item_code'] ?? '') === 'LEATHER_HELMET_T4') {
            $helmet = $item;
        }
    }

    expectOk(is_array($leatherT3), 'Sample LEATHER_T3 tidak ditemukan di recipe item options.', $failures);
    expectOk(is_array($potion), 'Sample T4_POTION_SAMPLE tidak ditemukan di recipe item options.', $failures);
    expectOk(is_array($helmet), 'Sample LEATHER_HELMET_T4 tidak ditemukan di recipe item options.', $failures);

    if (is_array($leatherT3)) {
        $detail = $service->recipeDetail((string) $leatherT3['id'], null, $freeAuth);
        expectOk($detail['ok'] === true, 'Recipe detail LEATHER_T3 harus sukses.', $failures);
        $data = $detail['data'] ?? [];
        $materials = is_array($data['materials'] ?? null) ? $data['materials'] : [];
        expectOk((int) (($data['item']['output_qty'] ?? 0)) === 1, 'Output qty LEATHER_T3 harus 1.', $failures);
        expectOk(count($materials) === 2, 'Material LEATHER_T3 harus 2.', $failures);
        expectOk((float) (($data['city_bonus']['bonus_percent'] ?? 0)) === 0.0, 'Tanpa city, bonus local harus 0.', $failures);
    }

    $extractSystemId = static function ($value): int {
        $raw = (string) $value;
        if (str_starts_with($raw, 'system:')) {
            return (int) substr($raw, 7);
        }
        return (int) $raw;
    };

    $findItemIdByCode = static function (array $rows, string $itemCode) use ($extractSystemId): int {
        foreach ($rows as $row) {
            if (($row['item_code'] ?? '') === $itemCode) {
                return $extractSystemId($row['id'] ?? 0);
            }
        }
        return 0;
    };

    $hideT3Id = $findItemIdByCode($items, 'LEATHER_T3');
    $potionId = $findItemIdByCode($items, 'T4_POTION_SAMPLE');
    $teaselStmt = $db->prepare('SELECT id FROM items WHERE item_code = :item_code LIMIT 1');
    $teaselStmt->execute(['item_code' => 'TEASEL']);
    $teaselId = (int) ($teaselStmt->fetchColumn() ?: 0);
    $eggStmt = $db->prepare('SELECT id FROM items WHERE item_code = :item_code LIMIT 1');
    $eggStmt->execute(['item_code' => 'GOOSE_EGG']);
    $eggId = (int) ($eggStmt->fetchColumn() ?: 0);

    $insertPrice = $db->prepare(
        'INSERT INTO market_prices (user_id, item_id, city_id, price_type, price_value, observed_at, notes)
         VALUES (:user_id, :item_id, :city_id, :price_type, :price_value, :observed_at, :notes)'
    );

    if ($hideT3Id > 0) {
        $insertPrice->execute([
            'user_id' => $userId,
            'item_id' => $hideT3Id,
            'city_id' => null,
            'price_type' => 'SELL',
            'price_value' => 333,
            'observed_at' => '2026-03-27 10:00:00',
            'notes' => 'test global sell leather',
        ]);
        $created['market_prices'][] = (int) $db->lastInsertId();
    }

    if ($potionId > 0) {
        $insertPrice->execute([
            'user_id' => $userId,
            'item_id' => $potionId,
            'city_id' => 1,
            'price_type' => 'SELL',
            'price_value' => 1888,
            'observed_at' => '2026-03-27 10:05:00',
            'notes' => 'test city sell potion',
        ]);
        $created['market_prices'][] = (int) $db->lastInsertId();

        $insertPrice->execute([
            'user_id' => $userId,
            'item_id' => $potionId,
            'city_id' => 1,
            'price_type' => 'CRAFT_FEE',
            'price_value' => 275,
            'observed_at' => '2026-03-27 10:06:00',
            'notes' => 'test city craft fee potion',
        ]);
        $created['market_prices'][] = (int) $db->lastInsertId();
    }

    if ($teaselId > 0) {
        $insertPrice->execute([
            'user_id' => $userId,
            'item_id' => $teaselId,
            'city_id' => null,
            'price_type' => 'BUY',
            'price_value' => 444,
            'observed_at' => '2026-03-27 10:10:00',
            'notes' => 'test global buy teasel',
        ]);
        $created['market_prices'][] = (int) $db->lastInsertId();
    }

    if ($eggId > 0) {
        $insertPrice->execute([
            'user_id' => $userId,
            'item_id' => $eggId,
            'city_id' => 1,
            'price_type' => 'BUY',
            'price_value' => 555,
            'observed_at' => '2026-03-27 10:15:00',
            'notes' => 'test city buy egg',
        ]);
        $created['market_prices'][] = (int) $db->lastInsertId();
    }

    if (is_array($potion)) {
        $detail = $service->recipeDetail((string) $potion['id'], 1, $freeAuth);
        expectOk($detail['ok'] === true, 'Recipe detail potion harus sukses.', $failures);
        $data = $detail['data'] ?? [];
        $materials = is_array($data['materials'] ?? null) ? $data['materials'] : [];
        expectOk((int) (($data['item']['output_qty'] ?? 0)) === 10, 'Output qty potion sample harus 10.', $failures);
        expectOk((float) (($data['city_bonus']['bonus_percent'] ?? -1)) === 15.0, 'Bonus city potion Brecilien harus 15.', $failures);
        expectOk((float) (($data['item']['sell_price'] ?? 0)) === 1888.0, 'Sell price potion harus auto-fill dari market price city.', $failures);
        expectOk((float) (($data['item']['craft_fee'] ?? 0)) === 275.0, 'Craft fee potion harus auto-fill dari data kota terpilih.', $failures);
        expectOk(isset($materials[0]['buy_price']) && (float) $materials[0]['buy_price'] === 444.0, 'Material pertama potion harus fallback ke BUY global.', $failures);
        expectOk(isset($materials[1]['buy_price']) && (float) $materials[1]['buy_price'] === 555.0, 'Material kedua potion harus pakai BUY city.', $failures);
        $recommendations = is_array($data['recommendations'] ?? null) ? $data['recommendations'] : [];
        expectOk(
            isset($recommendations['best_sell_city']['price_value']) && (float) $recommendations['best_sell_city']['price_value'] === 1888.0,
            'Recommendation best sell city harus menemukan harga jual tertinggi.',
            $failures
        );
        expectOk(
            isset($recommendations['best_craft_fee_city']['price_value']) && (float) $recommendations['best_craft_fee_city']['price_value'] === 275.0,
            'Recommendation best craft fee city harus menemukan craft fee termurah.',
            $failures
        );
        expectOk(
            isset($recommendations['recommended_craft_city']['city_id']) && (int) $recommendations['recommended_craft_city']['city_id'] === 1,
            'Recommendation recommended craft city harus terisi.',
            $failures
        );
        expectOk(
            isset($recommendations['local_bonus_cities'][0]['bonus_percent']) && (float) $recommendations['local_bonus_cities'][0]['bonus_percent'] >= 15.0,
            'Recommendation local bonus cities harus terisi.',
            $failures
        );
    }

    if (is_array($helmet)) {
        $detail = $service->recipeDetail((string) $helmet['id'], 5, $freeAuth);
        expectOk($detail['ok'] === true, 'Recipe detail helmet harus sukses.', $failures);
        $data = $detail['data'] ?? [];
        $materials = is_array($data['materials'] ?? null) ? $data['materials'] : [];
        expectOk((float) (($data['city_bonus']['bonus_percent'] ?? -1)) === 15.0, 'Bonus city helmet Lymhurst harus 15.', $failures);
        expectOk(
            isset($materials[1]['return_type']) && (string) $materials[1]['return_type'] === 'NON_RETURN',
            'Material kedua helmet harus NON_RETURN.',
            $failures
        );
    }

    $guestRecipeId = $service->storeCalculatedRecipe(null, [
        'item_name' => 'Guest Shared T4',
        'item_value' => 180,
        'output_qty' => 10,
        'usage_fee' => 220,
        'bonus_local' => 15,
        'materials' => [
            ['name' => 'Teasel', 'qty_per_recipe' => 4, 'buy_price' => 444, 'return_type' => 'RETURN'],
        ],
    ]);
    if ($guestRecipeId !== null) {
        $created['saved_recipes'][] = $guestRecipeId;
    }

    $userRecipeId = $service->storeCalculatedRecipe($freeAuth, [
        'item_name' => 'My Free Recipe',
        'item_value' => 240,
        'output_qty' => 1,
        'usage_fee' => 150,
        'sell_price' => 999,
        'materials' => [
            ['name' => 'Leather T3', 'qty_per_recipe' => 8, 'buy_price' => 500, 'return_type' => 'RETURN'],
        ],
    ]);
    if ($userRecipeId !== null) {
        $created['saved_recipes'][] = $userRecipeId;
    }

    usleep(1000000);
    $userRecipeV2Id = $service->storeCalculatedRecipe($freeAuth, [
        'item_name' => 'My Free Recipe',
        'item_value' => 250,
        'output_qty' => 1,
        'usage_fee' => 175,
        'sell_price' => 1111,
        'materials' => [
            ['name' => 'Leather T3', 'qty_per_recipe' => 8, 'buy_price' => 550, 'return_type' => 'RETURN'],
        ],
    ]);
    if ($userRecipeV2Id !== null) {
        $created['saved_recipes'][] = $userRecipeV2Id;
    }

    $guestOptions = $service->itemOptions('Guest Shared', null);
    expectOk(
        count(array_filter($guestOptions, static fn (array $row): bool => (string) ($row['source'] ?? '') === 'saved')) === 1,
        'Guest hanya boleh melihat saved recipe bucket tanpa login miliknya.',
        $failures
    );

    $freeOptions = $service->itemOptions('My Free Recipe', $freeAuth);
    $freeSavedRows = array_values(array_filter($freeOptions, static fn (array $row): bool => (string) ($row['source'] ?? '') === 'saved'));
    expectOk(count($freeSavedRows) === 1, 'FREE hanya boleh melihat latest saved recipe miliknya sendiri.', $failures);
    expectOk(
        count(array_filter($freeOptions, static fn (array $row): bool => str_contains((string) ($row['label'] ?? ''), 'Tanpa Login'))) === 0,
        'FREE tidak boleh melihat saved recipe dari Tanpa Login.',
        $failures
    );

    if ($userRecipeV2Id !== null) {
        $savedDetail = $service->recipeDetail('saved:' . $userRecipeV2Id, null, $freeAuth);
        expectOk($savedDetail['ok'] === true, 'Detail saved recipe milik FREE harus bisa diambil.', $failures);
        expectOk(
            isset($savedDetail['data']['item']['sell_price']) && (float) $savedDetail['data']['item']['sell_price'] === 1111.0,
            'Detail saved recipe harus memakai snapshot terbaru.',
            $failures
        );
    }

    $proOptions = $service->itemOptions('', $proAuth);
    $proSavedRows = array_values(array_filter($proOptions, static fn (array $row): bool => (string) ($row['source'] ?? '') === 'saved'));
    expectOk(count($proSavedRows) >= 3, 'PRO harus bisa melihat semua saved recipe lintas user/guest.', $failures);
    expectOk(
        count(array_filter($proSavedRows, static fn (array $row): bool => str_contains((string) ($row['label'] ?? ''), 'Tanpa Login'))) >= 1,
        'PRO harus bisa melihat saved recipe dari Tanpa Login.',
        $failures
    );

    if ($created['saved_recipes'] !== []) {
        $savedCsv = implode(',', array_map(static fn (int $id): string => (string) $id, $created['saved_recipes']));
        $db->exec("DELETE FROM calculator_recipe_library WHERE id IN ({$savedCsv})");
    }

    if ($created['market_prices'] !== []) {
        $idCsv = implode(',', array_map(static fn (int $id): string => (string) $id, $created['market_prices']));
        $db->exec("DELETE FROM market_prices WHERE id IN ({$idCsv})");
    }

    if ($created['users'] !== []) {
        $idCsv = implode(',', array_map(static fn (int $id): string => (string) $id, $created['users']));
        $db->exec("DELETE FROM calculator_recipe_library WHERE user_id IN ({$idCsv})");
        $db->exec("DELETE FROM market_prices WHERE user_id IN ({$idCsv})");
        $db->exec("DELETE FROM calculation_histories WHERE user_id IN ({$idCsv})");
        $db->exec("DELETE FROM admin_subscription_actions WHERE user_id IN ({$idCsv})");
        $db->exec("DELETE FROM subscription_logs WHERE user_id IN ({$idCsv})");
        $db->exec("DELETE FROM subscriptions WHERE user_id IN ({$idCsv})");
        $db->exec("DELETE FROM referral_rewards WHERE rewarded_user_id IN ({$idCsv})");
        $db->exec("DELETE FROM referrals WHERE referrer_user_id IN ({$idCsv}) OR referred_user_id IN ({$idCsv})");
        $db->exec("DELETE FROM users WHERE id IN ({$idCsv})");
    }
} catch (\Throwable $e) {
    $failures[] = 'Unhandled exception: ' . $e->getMessage();
}

if ($failures !== []) {
    fwrite(STDERR, "FAILED\n" . implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "PASS\n";
