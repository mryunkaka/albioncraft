<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Repositories\PlanRepository;
use App\Repositories\ReferralRepository;
use App\Repositories\UserRepository;
use App\Services\ReferralService;
use App\Services\SubscriptionService;
use App\Support\Database;
use App\Support\Env;

Env::load(dirname(__DIR__) . '/.env');

$failures = [];
$createdUserIds = [];

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
    $subscriptionService = new SubscriptionService();
    $referralService = new ReferralService();

    $plans->ensureDefaultPlans();

    $freePlanId = $plans->findIdByCode('FREE');
    $proPlanId = $plans->findIdByCode('PRO');

    assertTrue($freePlanId !== null && $freePlanId > 0, 'FREE plan tidak ditemukan.', $failures);
    assertTrue($proPlanId !== null && $proPlanId > 0, 'PRO plan tidak ditemukan.', $failures);

    if ($freePlanId === null || $proPlanId === null) {
        throw new \RuntimeException('Plan minimum tidak tersedia.');
    }

    $seed = (string) time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    $referrerCode = strtoupper(substr(bin2hex(random_bytes(8)), 0, 10));
    $referredCode = strtoupper(substr(bin2hex(random_bytes(8)), 0, 10));

    $referrerId = $users->create([
        'username' => 'test_referrer_' . $seed,
        'email' => 'test_referrer_' . $seed . '@example.local',
        'password_hash' => password_hash('TestPass123!', PASSWORD_DEFAULT),
        'referral_code' => $referrerCode,
        'referred_by_code' => null,
        'plan_id' => $freePlanId,
        'plan_expired_at' => null,
        'status' => 'ACTIVE',
    ]);
    $createdUserIds[] = $referrerId;

    $referredId = $users->create([
        'username' => 'test_referred_' . $seed,
        'email' => 'test_referred_' . $seed . '@example.local',
        'password_hash' => password_hash('TestPass123!', PASSWORD_DEFAULT),
        'referral_code' => $referredCode,
        'referred_by_code' => $referrerCode,
        'plan_id' => $freePlanId,
        'plan_expired_at' => null,
        'status' => 'ACTIVE',
    ]);
    $createdUserIds[] = $referredId;

    // Test 1: referral registration reward flow
    putenv('REFERRAL_REWARD_DAYS=3');
    $_ENV['REFERRAL_REWARD_DAYS'] = '3';
    $_SERVER['REFERRAL_REWARD_DAYS'] = '3';

    $referralService->processRegistrationReferral($referredId, $referrerCode);

    $stmt = $db->prepare('SELECT * FROM referrals WHERE referred_user_id = :id LIMIT 1');
    $stmt->execute(['id' => $referredId]);
    $referralRow = $stmt->fetch();
    assertTrue(is_array($referralRow), 'Referral row tidak terbentuk.', $failures);

    if (is_array($referralRow)) {
        assertTrue((int) $referralRow['referrer_user_id'] === $referrerId, 'referrer_user_id tidak sesuai.', $failures);
    }

    $rewardRows = (new ReferralRepository($db))->listRewardsByUserId($referrerId, 10);
    assertTrue(count($rewardRows) >= 1, 'Referral reward tidak terbentuk.', $failures);

    $referrerAfterReward = $users->findWithPlanById($referrerId);
    assertTrue(
        is_array($referrerAfterReward) && ! empty($referrerAfterReward['plan_expired_at']),
        'Plan expiry referrer tidak ter-extend setelah reward.',
        $failures
    );

    // Test 2: request + approve subscription
    $requestResult = $subscriptionService->requestExtend($referredId, 'PRO', 'MONTHLY');
    assertTrue($requestResult['ok'] === true, 'Request extend gagal: ' . $requestResult['message'], $failures);

    $pending = $subscriptionService->pendingRequests(200);
    $requestActionId = 0;
    foreach ($pending as $row) {
        if ((int) ($row['user_id'] ?? 0) === $referredId && (string) ($row['action_type'] ?? '') === 'REQUEST_EXTEND') {
            $requestActionId = (int) ($row['id'] ?? 0);
            break;
        }
    }
    assertTrue($requestActionId > 0, 'Pending request extend tidak ditemukan.', $failures);

    if ($requestActionId > 0) {
        $approveResult = $subscriptionService->approveRequest($requestActionId, 'admin-test@example.local');
        assertTrue($approveResult['ok'] === true, 'Approve request gagal: ' . $approveResult['message'], $failures);
    }

    $referredAfterApprove = $users->findWithPlanById($referredId);
    assertTrue(
        is_array($referredAfterApprove) && (int) ($referredAfterApprove['plan_id'] ?? 0) === $proPlanId,
        'Plan user tidak berubah ke PRO setelah approve.',
        $failures
    );
    assertTrue(
        is_array($referredAfterApprove) && ! empty($referredAfterApprove['plan_expired_at']),
        'Plan expiry user kosong setelah approve.',
        $failures
    );

    $subsStmt = $db->prepare('SELECT COUNT(*) FROM subscriptions WHERE user_id = :uid AND source_type = :source');
    $subsStmt->execute(['uid' => $referredId, 'source' => 'MANUAL_ADMIN']);
    $manualAdminSubs = (int) $subsStmt->fetchColumn();
    assertTrue($manualAdminSubs >= 1, 'Subscription MANUAL_ADMIN tidak tercatat.', $failures);

    $logsStmt = $db->prepare('SELECT COUNT(*) FROM subscription_logs WHERE user_id = :uid');
    $logsStmt->execute(['uid' => $referredId]);
    $subscriptionLogs = (int) $logsStmt->fetchColumn();
    assertTrue($subscriptionLogs >= 1, 'Subscription log tidak tercatat.', $failures);

    // Test 3: reject request flow
    $requestReject = $subscriptionService->requestExtend($referredId, 'PRO', 'WEEKLY');
    assertTrue($requestReject['ok'] === true, 'Request reject-case gagal: ' . $requestReject['message'], $failures);

    $pending2 = $subscriptionService->pendingRequests(200);
    $rejectActionId = 0;
    foreach ($pending2 as $row) {
        if ((int) ($row['user_id'] ?? 0) === $referredId && (string) ($row['duration_type'] ?? '') === 'WEEKLY') {
            $rejectActionId = (int) ($row['id'] ?? 0);
            break;
        }
    }
    assertTrue($rejectActionId > 0, 'Pending request reject-case tidak ditemukan.', $failures);

    if ($rejectActionId > 0) {
        $rejectResult = $subscriptionService->rejectRequest($rejectActionId, 'admin-test@example.local');
        assertTrue($rejectResult['ok'] === true, 'Reject request gagal: ' . $rejectResult['message'], $failures);

        $pendingAfterReject = $subscriptionService->pendingRequests(200);
        $stillPending = false;
        foreach ($pendingAfterReject as $row) {
            if ((int) ($row['id'] ?? 0) === $rejectActionId) {
                $stillPending = true;
                break;
            }
        }
        assertTrue(! $stillPending, 'Request yang sudah direject masih tampil di pending list.', $failures);
    }

    // Test 4: expiry auto-downgrade to FREE
    $yesterday = (new \DateTimeImmutable('now'))->modify('-1 day')->format('Y-m-d H:i:s');
    $users->updatePlan($referredId, $proPlanId, $yesterday);

    $synced = $subscriptionService->syncUserPlan($referredId);
    assertTrue(is_array($synced), 'syncUserPlan mengembalikan null.', $failures);
    assertTrue(
        is_array($synced) && strtoupper((string) ($synced['plan_code'] ?? '')) === 'FREE',
        'Auto-downgrade ke FREE tidak berjalan saat expired.',
        $failures
    );

    // Cleanup test data
    if ($createdUserIds !== []) {
        $idList = implode(',', array_map(static fn (int $v): string => (string) $v, $createdUserIds));
        $db->exec("DELETE FROM referral_rewards WHERE rewarded_user_id IN ({$idList})");
        $db->exec("DELETE FROM referrals WHERE referrer_user_id IN ({$idList}) OR referred_user_id IN ({$idList})");
        $db->exec("DELETE FROM subscription_logs WHERE user_id IN ({$idList})");
        $db->exec("DELETE FROM subscriptions WHERE user_id IN ({$idList})");
        $db->exec("DELETE FROM admin_subscription_actions WHERE user_id IN ({$idList})");
        $db->exec("DELETE FROM market_prices WHERE user_id IN ({$idList})");
        $db->exec("DELETE FROM calculation_histories WHERE user_id IN ({$idList})");
        $db->exec("DELETE FROM users WHERE id IN ({$idList})");
    }
} catch (\Throwable $e) {
    $failures[] = 'Unhandled exception: ' . $e->getMessage();
}

if ($failures !== []) {
    fwrite(STDERR, "FAILED\n" . implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "PASS\n";
