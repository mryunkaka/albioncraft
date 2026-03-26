<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Repositories\PlanRepository;
use App\Repositories\UserRepository;
use App\Services\CalculationEngineService;
use App\Services\CalculationHistoryService;
use App\Services\DashboardService;
use App\Support\Database;
use App\Support\Env;

Env::load(dirname(__DIR__) . '/.env');

$failures = [];
$createdUserIds = [];

/**
 * @param list<string> $failures
 */
function ensureTrue(bool $condition, string $message, array &$failures): void
{
    if (! $condition) {
        $failures[] = $message;
    }
}

try {
    $db = Database::connection();
    $plans = new PlanRepository($db);
    $users = new UserRepository($db);
    $engine = new CalculationEngineService();
    $historyService = new CalculationHistoryService();
    $dashboardService = new DashboardService();

    $plans->ensureDefaultPlans();
    $freePlanId = $plans->findIdByCode('FREE');
    if ($freePlanId === null || $freePlanId <= 0) {
        throw new \RuntimeException('FREE plan tidak ditemukan.');
    }

    $seed = (string) time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    $userId = $users->create([
        'username' => 'dash_user_' . $seed,
        'email' => 'dash_user_' . $seed . '@example.local',
        'password_hash' => password_hash('TestPass123!', PASSWORD_DEFAULT),
        'referral_code' => strtoupper(substr(bin2hex(random_bytes(8)), 0, 10)),
        'referred_by_code' => null,
        'plan_id' => $freePlanId,
        'plan_expired_at' => null,
        'status' => 'ACTIVE',
    ]);
    $createdUserIds[] = $userId;

    $auth = [
        'user_id' => $userId,
        'plan_code' => 'FREE',
    ];

    $inputProfit = [
        'item_name' => 'Dashboard Profit Test',
        'bonus_basic' => 18,
        'bonus_local' => 40,
        'bonus_daily' => 0,
        'craft_with_focus' => false,
        'usage_fee' => 200,
        'item_value' => 64,
        'output_qty' => 1,
        'target_output_qty' => 100,
        'sell_price' => 300,
        'premium_status' => true,
        'materials' => [
            ['name' => 'Hide T3', 'qty_per_recipe' => 2, 'buy_price' => 100, 'return_type' => 'RETURN'],
            ['name' => 'Leather T2', 'qty_per_recipe' => 1, 'buy_price' => 80, 'return_type' => 'RETURN'],
        ],
    ];
    $outputProfit = $engine->calculate($inputProfit);
    $historyId1 = $historyService->store($auth, $inputProfit, $outputProfit);
    ensureTrue(($historyId1 ?? 0) > 0, 'Histori profit gagal disimpan.', $failures);

    $inputLoss = [
        'item_name' => 'Dashboard Loss Test',
        'bonus_basic' => 18,
        'bonus_local' => 0,
        'bonus_daily' => 0,
        'craft_with_focus' => false,
        'usage_fee' => 300,
        'item_value' => 240,
        'output_qty' => 1,
        'target_output_qty' => 20,
        'sell_price' => 100,
        'premium_status' => true,
        'materials' => [
            ['name' => 'Leather T3', 'qty_per_recipe' => 8, 'buy_price' => 200, 'return_type' => 'RETURN'],
            ['name' => 'Artifact', 'qty_per_recipe' => 1, 'buy_price' => 2000, 'return_type' => 'NON_RETURN'],
        ],
    ];
    $outputLoss = $engine->calculate($inputLoss);
    $historyId2 = $historyService->store($auth, $inputLoss, $outputLoss);
    ensureTrue(($historyId2 ?? 0) > 0, 'Histori loss gagal disimpan.', $failures);

    $summary = $historyService->dashboardSummary($userId, 20);
    ensureTrue((int) ($summary['total_count'] ?? 0) >= 2, 'Total count histori harus >= 2.', $failures);
    ensureTrue((int) ($summary['recent_count'] ?? 0) >= 2, 'Recent count histori harus >= 2.', $failures);
    ensureTrue((int) ($summary['recent_profit_count'] ?? 0) >= 1, 'Recent profit count harus >= 1.', $failures);
    ensureTrue((int) ($summary['recent_loss_count'] ?? 0) >= 1, 'Recent loss count harus >= 1.', $failures);
    ensureTrue(is_array($summary['latest'] ?? null), 'Latest history summary harus terisi.', $failures);
    ensureTrue(count((array) ($summary['recent_rows'] ?? [])) >= 2, 'Recent rows harus >= 2.', $failures);

    $overview = $dashboardService->overview($userId);
    ensureTrue(is_array($overview['user'] ?? null), 'Dashboard overview user harus terisi.', $failures);
    ensureTrue(is_array($overview['calculation_summary'] ?? null), 'Dashboard overview summary harus terisi.', $failures);
    ensureTrue(
        (int) (($overview['calculation_summary']['total_count'] ?? 0)) >= 2,
        'Dashboard overview total_count harus >= 2.',
        $failures
    );

    if ($createdUserIds !== []) {
        $idCsv = implode(',', array_map(static fn (int $v): string => (string) $v, $createdUserIds));
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
