<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Repositories\PlanRepository;
use App\Repositories\UserRepository;
use App\Services\CalculationHistoryService;
use App\Services\RecipeAutoFillService;
use App\Services\CalculationEngineService;
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
function assertTrue(bool $condition, string $message, array &$failures): void
{
    if (! $condition) {
        $failures[] = $message;
    }
}

try {
    $db = Database::connection();
    $plans = new PlanRepository($db);
    $users = new UserRepository($db);
    $autoFill = new RecipeAutoFillService();
    $engine = new CalculationEngineService();
    $histories = new CalculationHistoryService();

    $plans->ensureDefaultPlans();
    $mediumPlanId = $plans->findIdByCode('MEDIUM');
    if ($mediumPlanId === null || $mediumPlanId <= 0) {
        throw new \RuntimeException('MEDIUM plan tidak ditemukan.');
    }

    $seed = (string) time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    $userId = $users->create([
        'username' => 'e2e_user_' . $seed,
        'email' => 'e2e_user_' . $seed . '@example.local',
        'password_hash' => password_hash('TestPass123!', PASSWORD_DEFAULT),
        'referral_code' => strtoupper(substr(bin2hex(random_bytes(8)), 0, 10)),
        'referred_by_code' => null,
        'plan_id' => $mediumPlanId,
        'plan_expired_at' => null,
        'status' => 'ACTIVE',
    ]);
    $created['users'][] = $userId;

    $auth = [
        'user_id' => $userId,
        'plan_code' => 'MEDIUM',
    ];

    $items = $autoFill->itemOptions('');
    $potion = null;
    foreach ($items as $item) {
        if (($item['item_code'] ?? '') === 'T4_POTION_SAMPLE') {
            $potion = $item;
            break;
        }
    }

    assertTrue(is_array($potion), 'Sample T4_POTION_SAMPLE tidak ditemukan di recipe item options.', $failures);
    if (! is_array($potion)) {
        throw new \RuntimeException('Sample recipe potion tidak tersedia.');
    }

    $findItemIdStmt = $db->prepare('SELECT id FROM items WHERE item_code = :item_code LIMIT 1');
    $findItemIdStmt->execute(['item_code' => 'TEASEL']);
    $teaselId = (int) ($findItemIdStmt->fetchColumn() ?: 0);
    $findItemIdStmt->execute(['item_code' => 'GOOSE_EGG']);
    $eggId = (int) ($findItemIdStmt->fetchColumn() ?: 0);

    $insertPrice = $db->prepare(
        'INSERT INTO market_prices (user_id, item_id, city_id, price_type, price_value, observed_at, notes)
         VALUES (:user_id, :item_id, :city_id, :price_type, :price_value, :observed_at, :notes)'
    );

    $insertPrice->execute([
        'user_id' => $userId,
        'item_id' => (int) $potion['id'],
        'city_id' => 1,
        'price_type' => 'SELL',
        'price_value' => 1888,
        'observed_at' => '2026-03-27 11:00:00',
        'notes' => 'e2e sell potion city',
    ]);
    $created['market_prices'][] = (int) $db->lastInsertId();

    if ($teaselId > 0) {
        $insertPrice->execute([
            'user_id' => $userId,
            'item_id' => $teaselId,
            'city_id' => null,
            'price_type' => 'BUY',
            'price_value' => 444,
            'observed_at' => '2026-03-27 11:05:00',
            'notes' => 'e2e buy teasel global',
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
            'observed_at' => '2026-03-27 11:10:00',
            'notes' => 'e2e buy egg city',
        ]);
        $created['market_prices'][] = (int) $db->lastInsertId();
    }

    $detail = $autoFill->recipeDetail((int) $potion['id'], 1, $userId);
    assertTrue($detail['ok'] === true, 'Recipe auto-fill end-to-end harus berhasil.', $failures);

    $data = is_array($detail['data'] ?? null) ? $detail['data'] : [];
    $materials = is_array($data['materials'] ?? null) ? $data['materials'] : [];
    $item = is_array($data['item'] ?? null) ? $data['item'] : [];
    $cityBonus = is_array($data['city_bonus'] ?? null) ? $data['city_bonus'] : [];

    assertTrue(count($materials) === 2, 'Recipe potion sample harus memiliki 2 material.', $failures);
    assertTrue((float) ($item['sell_price'] ?? 0) === 1888.0, 'Sell price item hasil craft harus terisi dari market price.', $failures);
    assertTrue((float) ($cityBonus['bonus_percent'] ?? 0) === 15.0, 'Bonus city potion harus 15.', $failures);

    $payload = [
        'item_id' => (int) ($item['id'] ?? 0),
        'item_name' => (string) ($item['name'] ?? ''),
        'bonus_basic' => 18,
        'bonus_local' => (float) ($cityBonus['bonus_percent'] ?? 0),
        'bonus_daily' => 0,
        'craft_with_focus' => false,
        'focus_points' => 0,
        'focus_per_craft' => 0,
        'usage_fee' => 200,
        'item_value' => (float) ($item['item_value'] ?? 0),
        'output_qty' => (int) ($item['output_qty'] ?? 1),
        'target_output_qty' => 100,
        'sell_price' => (float) ($item['sell_price'] ?? 0),
        'premium_status' => true,
        'return_rounding_mode' => 'SPREADSHEET_BULK',
        'materials' => array_map(
            static fn (array $row): array => [
                'name' => (string) ($row['name'] ?? ''),
                'qty_per_recipe' => (float) ($row['qty_per_recipe'] ?? 0),
                'buy_price' => (float) ($row['buy_price'] ?? 0),
                'return_type' => (string) ($row['return_type'] ?? 'RETURN'),
            ],
            $materials
        ),
    ];

    $result = $engine->calculate($payload);
    assertTrue((string) ($result['calculation_mode'] ?? '') === 'SPREADSHEET_SIM', 'Calculation mode harus SPREADSHEET_SIM.', $failures);
    assertTrue((string) ($result['scenario']['mode'] ?? '') === 'MARKET', 'Scenario mode harus MARKET setelah sell_price auto-fill.', $failures);
    assertTrue((float) ($result['scenario']['sell_price'] ?? 0) === 1888.0, 'Scenario sell price harus sama dengan auto-fill sell price.', $failures);
    assertTrue((int) ($result['total_output'] ?? 0) > 0, 'Total output hasil kalkulasi harus > 0.', $failures);

    $historyId = $histories->store($auth, $payload, $result);
    assertTrue(($historyId ?? 0) > 0, 'History end-to-end gagal disimpan.', $failures);

    $summary = $histories->dashboardSummary($userId, 20);
    $latest = is_array($summary['latest'] ?? null) ? $summary['latest'] : null;

    assertTrue((int) ($summary['total_count'] ?? 0) >= 1, 'Dashboard summary total_count harus >= 1.', $failures);
    assertTrue($latest !== null, 'Latest history harus tersedia setelah save.', $failures);

    if (is_array($latest)) {
        assertTrue((string) ($latest['item_name'] ?? '') === 'Potion Sample T4', 'Latest history item_name harus cocok dengan hasil auto-fill.', $failures);
        assertTrue((string) ($latest['plan_code'] ?? '') === 'MEDIUM', 'Plan code history harus MEDIUM.', $failures);
        assertTrue((string) ($latest['scenario_mode'] ?? '') === 'MARKET', 'Scenario mode history harus MARKET.', $failures);
        assertTrue((float) ($latest['scenario_sell_price'] ?? 0) === 1888.0, 'Scenario sell price history harus 1888.', $failures);
    }

    $rowStmt = $db->prepare(
        'SELECT item_id, plan_code, calculation_mode, input_snapshot, output_snapshot
         FROM calculation_histories
         WHERE id = :id
         LIMIT 1'
    );
    $rowStmt->execute(['id' => $historyId]);
    $row = $rowStmt->fetch();
    assertTrue(is_array($row), 'Row calculation_histories yang baru disimpan harus ditemukan.', $failures);

    if (is_array($row)) {
        $inputSnapshot = json_decode((string) ($row['input_snapshot'] ?? '{}'), true);
        $outputSnapshot = json_decode((string) ($row['output_snapshot'] ?? '{}'), true);
        if (! is_array($inputSnapshot)) {
            $inputSnapshot = [];
        }
        if (! is_array($outputSnapshot)) {
            $outputSnapshot = [];
        }

        assertTrue((int) ($row['item_id'] ?? 0) === (int) $potion['id'], 'item_id history harus mengarah ke item recipe terpilih.', $failures);
        assertTrue((string) ($row['plan_code'] ?? '') === 'MEDIUM', 'plan_code row history harus MEDIUM.', $failures);
        assertTrue((string) ($row['calculation_mode'] ?? '') === 'SPREADSHEET_SIM', 'calculation_mode row history harus SPREADSHEET_SIM.', $failures);
        assertTrue((float) ($inputSnapshot['sell_price'] ?? 0) === 1888.0, 'input_snapshot sell_price harus tersimpan.', $failures);
        assertTrue((string) ($outputSnapshot['scenario']['mode'] ?? '') === 'MARKET', 'output_snapshot scenario.mode harus MARKET.', $failures);
    }

    if ($created['users'] !== []) {
        $idCsv = implode(',', array_map(static fn (int $id): string => (string) $id, $created['users']));
        $db->exec("DELETE FROM calculation_histories WHERE user_id IN ({$idCsv})");
        $db->exec("DELETE FROM market_prices WHERE user_id IN ({$idCsv})");
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
