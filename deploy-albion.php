<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Manual/Cron Deploy - cPanel Shared Hosting (PHP Native)
|--------------------------------------------------------------------------
| Simpan file ini sebagai:
|   /home/hark8423/public_html/albioncraft/deploy-albion.php
|
| Repo aplikasi:
|   /home/hark8423/public_html/albioncraft
|
| Cron example:
|   (every 5 minutes) /usr/bin/php /home/hark8423/public_html/albioncraft/deploy-albion.php
|
| Manual via browser (opsional, WAJIB token):
|   https://albion.harikenangan.my.id/deploy-albion.php?token=XXXX
| Token diambil dari file:
|   /home/hark8423/public_html/albioncraft/.deploy-token
|
| Catatan:
| - Jika hosting mematikan `shell_exec` atau tidak ada `git`, deploy akan gagal.
| - Disarankan interval cron 5-15 menit, bukan tiap menit.
*/

$deployPath = '/home/hark8423/public_html/albioncraft';
$remote = 'origin';
$branch = 'main';
$logFile = '/home/hark8423/git-deploy-albion.log';
$lockFile = '/home/hark8423/git-deploy-albion.lock';
// Token file disimpan di folder repo agar mudah dikelola, dan harus dikecualikan dari `git clean`.
$tokenFile = $deployPath . '/.deploy-token';

header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(180);

$isCli = (PHP_SAPI === 'cli');
$now = date('Y-m-d H:i:s');

$writeLog = static function (string $message) use ($logFile): void {
    @file_put_contents($logFile, $message . "\n", FILE_APPEND);
};

$fail = static function (int $code, string $message) use ($isCli, $now, $writeLog): void {
    if (! $isCli) {
        http_response_code($code);
    }
    echo $message . "\n";
    $writeLog($now . ' - ' . $message);
    exit;
};

// Web access protection: require token.
if (! $isCli) {
    $token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
    $expected = '';
    if (is_file($tokenFile)) {
        $expected = trim((string) @file_get_contents($tokenFile));
    }

    if ($expected === '') {
        $fail(403, 'Forbidden: token file not set. Create /home/hark8423/public_html/.deploy-token first.');
    }
    if (! hash_equals($expected, $token)) {
        $fail(403, 'Forbidden: invalid token.');
    }
}

// Basic lock to avoid overlapping deploys.
$lockHandle = @fopen($lockFile, 'c+');
if (! is_resource($lockHandle)) {
    $fail(500, "Deploy gagal: tidak bisa membuat lock file di {$lockFile}");
}
if (! @flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "Deploy skip: masih ada proses deploy lain yang berjalan.\n";
    exit;
}

echo "Deploy AlbionCraft\n";
echo "Waktu: {$now}\n\n";

if (! function_exists('shell_exec')) {
    $fail(500, 'Deploy gagal: shell_exec tidak tersedia (kemungkinan disable_functions).');
}

if (! is_dir($deployPath) || ! is_dir($deployPath . '/.git')) {
    $fail(500, "Deploy gagal: folder aplikasi atau repository git tidak ditemukan di {$deployPath}");
}

chdir($deployPath);

$run = static function (string $command): string {
    $result = shell_exec($command . ' 2>&1');
    return trim((string) $result);
};

$gitVersion = $run('git --version');
if ($gitVersion === '') {
    $fail(500, 'Deploy gagal: `git` tidak tersedia atau tidak bisa dijalankan oleh PHP.');
}

$oldCommit = $run('git rev-parse HEAD');
echo "Path aktif : {$deployPath}\n";
echo "Git        : {$gitVersion}\n";
echo "Commit lama: " . ($oldCommit !== '' ? $oldCommit : '[gagal membaca commit]') . "\n\n";

// Bersihkan perubahan lokal, tapi jangan hapus file runtime.
$cleanCmd = 'git reset --hard HEAD'
    . ' && git clean -fd -e .env -e .deploy-token -e storage -e public/uploads';
$cleanOutput = $run($cleanCmd);

echo "Output clean:\n" . ($cleanOutput !== '' ? $cleanOutput : '[kosong]') . "\n\n";

// Pull update terbaru dari remote.
$pullCmd = sprintf('git pull %s %s', escapeshellarg($remote), escapeshellarg($branch));
$pullOutput = $run($pullCmd);
$newCommit = $run('git rev-parse HEAD');

echo "Output git pull:\n" . ($pullOutput !== '' ? $pullOutput : '[kosong]') . "\n\n";
echo "Commit baru: " . ($newCommit !== '' ? $newCommit : '[gagal membaca commit]') . "\n\n";

// Pastikan folder runtime tetap ada.
$runtimeDirs = [
    $deployPath . '/storage',
    $deployPath . '/public/uploads',
];
foreach ($runtimeDirs as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

$changed = ($oldCommit !== '' && $newCommit !== '' && $oldCommit !== $newCommit);

$logMessage = $now
    . ($changed ? " - Deploy berhasil" : " - Tidak ada perubahan (atau commit tidak terbaca)")
    . "\nPath      : {$deployPath}"
    . "\nCommit    : {$oldCommit} -> {$newCommit}"
    . "\nClean     : {$cleanOutput}"
    . "\nPull      : {$pullOutput}"
    . "\n" . str_repeat('-', 60);

$writeLog($logMessage);

if ($oldCommit === '' || $newCommit === '') {
    echo "Peringatan: commit tidak terbaca. Kemungkinan akses `git` dibatasi hosting.\n";
}

echo $changed ? "Status: Deploy berhasil.\n" : "Status: Tidak ada perubahan.\n";
echo "Log: {$logFile}\n";
