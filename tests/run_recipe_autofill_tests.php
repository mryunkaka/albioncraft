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
    if ($freePlanId === null || $freePlanId <= 0) {
        throw new \RuntimeException('FREE plan tidak ditemukan.');
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

    $items = $service->itemOptions('');
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
        $detail = $service->recipeDetail((int) $leatherT3['id'], null, $userId);
        expectOk($detail['ok'] === true, 'Recipe detail LEATHER_T3 harus sukses.', $failures);
        $data = $detail['data'] ?? [];
        $materials = is_array($data['materials'] ?? null) ? $data['materials'] : [];
        expectOk((int) (($data['item']['output_qty'] ?? 0)) === 1, 'Output qty LEATHER_T3 harus 1.', $failures);
        expectOk(count($materials) === 2, 'Material LEATHER_T3 harus 2.', $failures);
        expectOk((float) (($data['city_bonus']['bonus_percent'] ?? 0)) === 0.0, 'Tanpa city, bonus local harus 0.', $failures);
    }

    $findItemIdByCode = static function (array $rows, string $itemCode): int {
        foreach ($rows as $row) {
            if (($row['item_code'] ?? '') === $itemCode) {
                return (int) ($row['id'] ?? 0);
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
        $detail = $service->recipeDetail((int) $potion['id'], 1, $userId);
        expectOk($detail['ok'] === true, 'Recipe detail potion harus sukses.', $failures);
        $data = $detail['data'] ?? [];
        $materials = is_array($data['materials'] ?? null) ? $data['materials'] : [];
        expectOk((int) (($data['item']['output_qty'] ?? 0)) === 10, 'Output qty potion sample harus 10.', $failures);
        expectOk((float) (($data['city_bonus']['bonus_percent'] ?? -1)) === 15.0, 'Bonus city potion Brecilien harus 15.', $failures);
        expectOk((float) (($data['item']['sell_price'] ?? 0)) === 1888.0, 'Sell price potion harus auto-fill dari market price city.', $failures);
        expectOk(isset($materials[0]['buy_price']) && (float) $materials[0]['buy_price'] === 444.0, 'Material pertama potion harus fallback ke BUY global.', $failures);
        expectOk(isset($materials[1]['buy_price']) && (float) $materials[1]['buy_price'] === 555.0, 'Material kedua potion harus pakai BUY city.', $failures);
    }

    if (is_array($helmet)) {
        $detail = $service->recipeDetail((int) $helmet['id'], 5, $userId);
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

    if ($created['market_prices'] !== []) {
        $idCsv = implode(',', array_map(static fn (int $id): string => (string) $id, $created['market_prices']));
        $db->exec("DELETE FROM market_prices WHERE id IN ({$idCsv})");
    }

    if ($created['users'] !== []) {
        $idCsv = implode(',', array_map(static fn (int $id): string => (string) $id, $created['users']));
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
