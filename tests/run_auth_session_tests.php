<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Repositories\PlanRepository;
use App\Repositories\UserRepository;
use App\Services\AuthSessionService;
use App\Support\Database;
use App\Support\Env;
use App\Support\Session;

Env::load(dirname(__DIR__) . '/.env');
Session::start();

$failures = [];
$createdUserId = 0;

/**
 * @param list<string> $failures
 */
function assertTrue(bool $condition, string $message, array &$failures): void
{
    if (! $condition) {
        $failures[] = $message;
    }
}

function persistentCookieValue(int $userId, string $sessionToken): string
{
    $secret = trim(Env::get('AUTH_PERSISTENT_SECRET', ''));
    if ($secret === '') {
        $fallback = implode('|', [
            Env::get('APP_ENV', 'production'),
            Env::get('DB_NAME', ''),
            Env::get('DB_USER', ''),
            Env::get('DB_PASS', ''),
        ]);

        $secret = hash('sha256', $fallback !== 'production|||' ? $fallback : dirname(__DIR__) . '/app/Services/AuthSessionService.php');
    }

    $payloadJson = json_encode([
        'user_id' => $userId,
        'session_token' => $sessionToken,
    ], JSON_UNESCAPED_SLASHES);

    if (! is_string($payloadJson) || $payloadJson === '') {
        throw new RuntimeException('Gagal membangun payload cookie auth.');
    }

    $encoded = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $encoded, $secret);

    return $encoded . '.' . $signature;
}

try {
    $db = Database::connection();
    $plans = new PlanRepository($db);
    $users = new UserRepository($db);
    $authSessions = new AuthSessionService();

    $plans->ensureDefaultPlans();
    $freePlanId = $plans->findIdByCode('FREE');
    if ($freePlanId === null) {
        throw new RuntimeException('FREE plan tidak ditemukan.');
    }

    $seed = (string) time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    $createdUserId = $users->create([
        'username' => 'auth_session_' . $seed,
        'email' => 'auth_session_' . $seed . '@example.local',
        'password_hash' => password_hash('TestPass123!', PASSWORD_DEFAULT),
        'referral_code' => strtoupper(substr(bin2hex(random_bytes(8)), 0, 10)),
        'referred_by_code' => null,
        'plan_id' => $freePlanId,
        'plan_expired_at' => null,
        'status' => 'ACTIVE',
    ]);

    $tokenA = $authSessions->issue($createdUserId);
    $_SESSION = [];
    $_COOKIE['albion_persistent_auth'] = persistentCookieValue($createdUserId, $tokenA);

    $authSessions->bootstrap();
    $restored = Session::get('auth');

    assertTrue(is_array($restored), 'Session auth tidak ter-restore dari cookie persistent.', $failures);
    assertTrue((int) ($restored['user_id'] ?? 0) === $createdUserId, 'User id hasil restore tidak sesuai.', $failures);
    assertTrue((string) ($restored['session_token'] ?? '') === $tokenA, 'Session token hasil restore tidak sesuai.', $failures);

    $tokenB = $authSessions->issue($createdUserId);

    $_SESSION = [];
    unset($_SESSION['auth']);
    $_COOKIE['albion_persistent_auth'] = persistentCookieValue($createdUserId, $tokenA);

    $authSessions->bootstrap();
    $afterOtherDeviceLogin = Session::get('auth');

    assertTrue(
        ! is_array($afterOtherDeviceLogin),
        'Login device lama seharusnya invalid setelah ada login dari device lain.',
        $failures
    );
    assertTrue($_COOKIE['albion_persistent_auth'] ?? '' === '', 'Cookie persistent lama seharusnya dihapus saat token mismatch.', $failures);

    $_SESSION = [];
    $_COOKIE['albion_persistent_auth'] = persistentCookieValue($createdUserId, $tokenB);
    $authSessions->bootstrap();
    $restoredLatest = Session::get('auth');

    assertTrue(is_array($restoredLatest), 'Session auth token terbaru tidak bisa di-restore.', $failures);
    assertTrue((string) ($restoredLatest['session_token'] ?? '') === $tokenB, 'Token terbaru tidak dipakai saat restore.', $failures);
} catch (\Throwable $e) {
    $failures[] = 'Unhandled exception: ' . $e->getMessage();
}

if ($createdUserId > 0) {
    try {
        $db = Database::connection();
        $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $createdUserId]);
    } catch (\Throwable) {
    }
}

if ($failures !== []) {
    fwrite(STDERR, "FAILED\n" . implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "PASS\n";
