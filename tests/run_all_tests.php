<?php

declare(strict_types=1);

$php = PHP_BINARY;
$root = dirname(__DIR__);

$tests = [
    'Auth Session' => $root . '/tests/run_auth_session_tests.php',
    'Calculation Engine' => $root . '/tests/run_calculation_engine_tests.php',
    'Subscription Referral Admin' => $root . '/tests/run_subscription_referral_admin_tests.php',
    'Market Price Service' => $root . '/tests/run_market_price_service_tests.php',
    'Dashboard History' => $root . '/tests/run_dashboard_history_tests.php',
    'Recipe Auto Fill' => $root . '/tests/run_recipe_autofill_tests.php',
    'Recipe Auto Fill E2E' => $root . '/tests/run_recipe_autofill_e2e_tests.php',
    'API Rate Limiter' => $root . '/tests/run_api_rate_limiter_tests.php',
];

$failures = [];

foreach ($tests as $label => $script) {
    if (! is_file($script)) {
        $failures[] = $label . ': script tidak ditemukan [' . $script . ']';
        continue;
    }

    $command = escapeshellarg($php) . ' ' . escapeshellarg($script);
    $output = [];
    $exitCode = 0;
    exec($command . ' 2>&1', $output, $exitCode);

    echo '[' . $label . ']' . PHP_EOL;
    echo implode(PHP_EOL, $output) . PHP_EOL;
    echo str_repeat('-', 60) . PHP_EOL;

    if ($exitCode !== 0) {
        $failures[] = $label . ': exit code ' . $exitCode;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "FAILED\n" . implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "ALL TESTS PASS\n";
