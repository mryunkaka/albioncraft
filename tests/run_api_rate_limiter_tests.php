<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Support\ApiRateLimiter;
use App\Support\Env;
use App\Support\Request;
use App\Support\Session;

Env::load(dirname(__DIR__) . '/.env');
Session::start();

$failures = [];

/**
 * @param list<string> $failures
 */
function expectApiLimit(bool $condition, string $message, array &$failures): void
{
    if (! $condition) {
        $failures[] = $message;
    }
}

try {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'codex-test-agent';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/api/calculate';

    $request = new Request();
    ApiRateLimiter::clear('calculate', $request);

    $initial = ApiRateLimiter::check('calculate', $request);
    expectApiLimit($initial['allowed'] === true, 'Initial rate limit check harus allowed.', $failures);

    $maxAttempts = (int) Env::get('API_RATE_LIMIT_MAX_ATTEMPTS', '30');
    $lastStatus = ['allowed' => true, 'retry_after' => 0];
    for ($i = 0; $i < $maxAttempts; $i++) {
        $lastStatus = ApiRateLimiter::hit('calculate', $request);
    }

    expectApiLimit($lastStatus['allowed'] === false || $lastStatus['retry_after'] >= 0, 'Hit terakhir harus mencapai limit window.', $failures);

    $blocked = ApiRateLimiter::check('calculate', $request);
    expectApiLimit($blocked['allowed'] === false, 'Check setelah limit harus blocked.', $failures);
    expectApiLimit((int) $blocked['retry_after'] >= 0, 'Blocked check harus punya retry_after.', $failures);

    ApiRateLimiter::clear('calculate', $request);
    $afterClear = ApiRateLimiter::check('calculate', $request);
    expectApiLimit($afterClear['allowed'] === true, 'Check setelah clear harus allowed lagi.', $failures);
} catch (\Throwable $e) {
    $failures[] = 'Unhandled exception: ' . $e->getMessage();
}

if ($failures !== []) {
    fwrite(STDERR, "FAILED\n" . implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "PASS\n";
