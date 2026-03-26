<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Simple Cron Deploy (cPanel Shared Hosting)
|--------------------------------------------------------------------------
| Simpan file ini sebagai:
|   /home/hark8423/public_html/deploy-cron.php
|
| Cron command (contoh tiap 5 menit):
|   (every 5 minutes) /usr/local/bin/php -q /home/hark8423/public_html/deploy-cron.php
|
| Catatan keamanan:
| - Script ini hanya boleh jalan via CLI (cron). Jika diakses via browser, akan 403.
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Forbidden\n";
    exit;
}

$repo = '/home/hark8423/public_html/albioncraft';
$log = '/home/hark8423/git-deploy.log';
$remote = 'origin';
$branch = 'main';

$now = date('Y-m-d H:i:s');

$fail = static function (string $message) use ($now, $log): void {
    @file_put_contents($log, "{$now} - ERROR: {$message}\n", FILE_APPEND);
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

if (! function_exists('shell_exec')) {
    $fail('shell_exec tidak tersedia (disable_functions).');
}

if (! is_dir($repo) || ! is_dir($repo . '/.git')) {
    $fail("Folder repo tidak ditemukan: {$repo}");
}

chdir($repo);

$run = static function (string $cmd): string {
    $out = shell_exec($cmd . ' 2>&1');
    return trim((string) $out);
};

$old = $run('git rev-parse HEAD');
$pull = $run(sprintf('git pull %s %s', escapeshellarg($remote), escapeshellarg($branch)));
$new = $run('git rev-parse HEAD');

// Selalu tulis satu baris status supaya gampang cek cron jalan atau tidak.
@file_put_contents(
    $log,
    "{$now} - RUN: {$old} -> {$new} | pull=" . preg_replace('/\\s+/', ' ', $pull) . "\n",
    FILE_APPEND
);

if ($old !== '' && $new !== '' && $old !== $new) {
    $commits = $run(sprintf('git log %s..%s --pretty=format:"%%h | %%an | %%s"', escapeshellarg($old), escapeshellarg($new)));
    $lines = array_filter(array_map('trim', explode("\n", $commits)));

    foreach ($lines as $line) {
        @file_put_contents($log, "{$now} - Deploy {$line}\n", FILE_APPEND);
    }
}
