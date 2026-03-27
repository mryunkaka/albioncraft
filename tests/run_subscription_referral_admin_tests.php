<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Repositories\PlanRepository;
use App\Repositories\ReferralRepository;
use App\Repositories\UserRepository;
use App\Services\AdminUserManagementService;
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
    $adminUserService = new AdminUserManagementService();
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

    // Test 4: request FREE tanpa durasi dan approve harus clear expiry
    $requestFree = $subscriptionService->requestExtend($referredId, 'FREE', '');
    assertTrue($requestFree['ok'] === true, 'Request FREE gagal: ' . $requestFree['message'], $failures);

    $pendingFree = $subscriptionService->pendingRequests(200);
    $freeActionId = 0;
    foreach ($pendingFree as $row) {
        if ((int) ($row['user_id'] ?? 0) === $referredId && (string) (($row['plan_code'] ?? '')) === 'FREE') {
            $freeActionId = (int) ($row['id'] ?? 0);
            break;
        }
    }
    assertTrue($freeActionId > 0, 'Pending request FREE tidak ditemukan.', $failures);

    if ($freeActionId > 0) {
        $approveFree = $subscriptionService->approveRequest($freeActionId, 'admin-test@example.local');
        assertTrue($approveFree['ok'] === true, 'Approve FREE request gagal: ' . $approveFree['message'], $failures);
    }

    $referredAfterFree = $users->findWithPlanById($referredId);
    assertTrue(
        is_array($referredAfterFree) && strtoupper((string) ($referredAfterFree['plan_code'] ?? '')) === 'FREE',
        'Plan user tidak berubah ke FREE setelah approve FREE request.',
        $failures
    );
    assertTrue(
        is_array($referredAfterFree) && empty($referredAfterFree['plan_expired_at']),
        'Plan expiry user harus kosong setelah approve FREE request.',
        $failures
    );

    // Test 5: expiry auto-downgrade to FREE
    $yesterday = (new \DateTimeImmutable('now'))->modify('-1 day')->format('Y-m-d H:i:s');
    $users->updatePlan($referredId, $proPlanId, $yesterday);

    $synced = $subscriptionService->syncUserPlan($referredId);
    assertTrue(is_array($synced), 'syncUserPlan mengembalikan null.', $failures);
    assertTrue(
        is_array($synced) && strtoupper((string) ($synced['plan_code'] ?? '')) === 'FREE',
        'Auto-downgrade ke FREE tidak berjalan saat expired.',
        $failures
    );

    // Test 6: admin user management profile update
    $profileUpdate = $adminUserService->updateProfile(
        $referredId,
        'managed_' . $seed,
        'managed_' . $seed . '@example.local',
        'INACTIVE',
        'admin-test@example.local'
    );
    assertTrue($profileUpdate['ok'] === true, 'Update profile admin gagal: ' . $profileUpdate['message'], $failures);

    $managedAfterProfile = $users->findWithPlanById($referredId);
    assertTrue(
        is_array($managedAfterProfile) && (string) ($managedAfterProfile['email'] ?? '') === 'managed_' . $seed . '@example.local',
        'Email user tidak berubah lewat admin user management.',
        $failures
    );
    assertTrue(
        is_array($managedAfterProfile) && (string) ($managedAfterProfile['status'] ?? '') === 'INACTIVE',
        'Status user tidak berubah lewat admin user management.',
        $failures
    );

    // Test 7: admin reset password
    $passwordUpdate = $adminUserService->resetPassword(
        $referredId,
        'AdminReset123!',
        'AdminReset123!',
        'admin-test@example.local'
    );
    assertTrue($passwordUpdate['ok'] === true, 'Reset password admin gagal: ' . $passwordUpdate['message'], $failures);

    $managedAfterPassword = $users->findById($referredId);
    assertTrue(
        is_array($managedAfterPassword) && password_verify('AdminReset123!', (string) ($managedAfterPassword['password_hash'] ?? '')),
        'Password hash user tidak berubah lewat admin user management.',
        $failures
    );

    // Test 8: admin downgrade / upgrade plan direct
    $planUpdateFree = $adminUserService->updatePlan(
        $referredId,
        'FREE',
        '',
        'admin-test@example.local'
    );
    assertTrue($planUpdateFree['ok'] === true, 'Downgrade FREE admin gagal: ' . $planUpdateFree['message'], $failures);

    $managedAfterFree = $users->findWithPlanById($referredId);
    assertTrue(
        is_array($managedAfterFree) && strtoupper((string) ($managedAfterFree['plan_code'] ?? '')) === 'FREE',
        'Plan user tidak downgrade ke FREE lewat admin user management.',
        $failures
    );
    assertTrue(
        is_array($managedAfterFree) && empty($managedAfterFree['plan_expired_at']),
        'Expiry harus kosong setelah downgrade FREE lewat admin user management.',
        $failures
    );

    $futureExpiry = (new \DateTimeImmutable('now'))->modify('+14 days')->format('Y-m-d H:i:s');
    $planUpdatePro = $adminUserService->updatePlan(
        $referredId,
        'PRO',
        $futureExpiry,
        'admin-test@example.local'
    );
    assertTrue($planUpdatePro['ok'] === true, 'Upgrade PRO admin gagal: ' . $planUpdatePro['message'], $failures);

    $managedAfterPro = $users->findWithPlanById($referredId);
    assertTrue(
        is_array($managedAfterPro) && strtoupper((string) ($managedAfterPro['plan_code'] ?? '')) === 'PRO',
        'Plan user tidak berubah ke PRO lewat admin user management.',
        $failures
    );
    assertTrue(
        is_array($managedAfterPro) && (string) ($managedAfterPro['plan_expired_at'] ?? '') === $futureExpiry,
        'Expiry PRO admin user management tidak sesuai.',
        $failures
    );

    $adminActionsStmt = $db->prepare(
        "SELECT COUNT(*)
         FROM admin_subscription_actions
         WHERE user_id = :uid
           AND action_type IN ('MANAGE_USER_PROFILE', 'MANAGE_USER_PASSWORD', 'MANAGE_USER_PLAN')"
    );
    $adminActionsStmt->execute(['uid' => $referredId]);
    $managedActionCount = (int) $adminActionsStmt->fetchColumn();
    assertTrue($managedActionCount >= 3, 'Audit admin user management tidak tercatat lengkap.', $failures);

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
