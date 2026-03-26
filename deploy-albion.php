<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Manual Deploy PHP Native - cPanel Shared Hosting
|--------------------------------------------------------------------------
| Simpan file ini sebagai:
| /home/hark8423/public_html/deploy-albion.php
|
| Cara pakai:
| 1. Repo sudah ada langsung di /home/hark8423/public_html/albioncraft
| 2. Buka file ini dari browser saat ingin deploy manual
| 3. Script akan langsung git pull branch main
|
| Cron (jika ingin otomatis, format seperti deploy yang sudah Anda pakai):
| * * * * * /usr/bin/php /home/hark8423/public_html/deploy-albion.php
|
| Catatan:
| - Jika hosting mematikan `shell_exec` atau akses `git`, deploy akan gagal.
*/

$deployPath = '/home/hark8423/public_html/albioncraft';
$branch = 'main';
$remote = 'origin';
$logFile = '/home/hark8423/git-deploy-albion.log';

header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(120);

echo "Menjalankan deploy AlbionCraft...\n\n";

if (!is_dir($deployPath) || !is_dir($deployPath . '/.git')) {
    $message = "Deploy gagal: folder aplikasi atau repository git tidak ditemukan di {$deployPath}";
    http_response_code(500);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - {$message}\n", FILE_APPEND);
    echo $message . "\n";
    exit;
}

chdir($deployPath);

$run = static function (string $command): string {
    $result = shell_exec($command . ' 2>&1');
    return trim((string) $result);
};

$oldCommit = $run('git rev-parse HEAD');

echo "Path aktif : {$deployPath}\n";
echo "Commit lama: " . ($oldCommit !== '' ? $oldCommit : '[gagal membaca commit]') . "\n\n";

// Bersihkan perubahan lokal, tapi jangan hapus file runtime.
$cleanOutput = $run('git reset --hard HEAD && git clean -fd -e .env -e public/uploads -e storage');

echo "Output clean:\n" . ($cleanOutput !== '' ? $cleanOutput : '[kosong]') . "\n\n";

// Ambil update terbaru dari GitHub.
$pullOutput = $run(sprintf('git pull %s %s', escapeshellarg($remote), escapeshellarg($branch)));
$newCommit = $run('git rev-parse HEAD');

echo "Output git pull:\n" . ($pullOutput !== '' ? $pullOutput : '[kosong]') . "\n\n";
echo "Commit baru: " . ($newCommit !== '' ? $newCommit : '[gagal membaca commit]') . "\n\n";

// Pastikan folder runtime tetap ada.
$runtimeDirs = [
    $deployPath . '/public/uploads',
    $deployPath . '/storage',
];

foreach ($runtimeDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

$date = date('Y-m-d H:i:s');
$changed = $oldCommit !== $newCommit;

// Log format dibuat simpel seperti referensi user:
// - Selalu tulis 1 baris RUN
// - Jika ada perubahan, tulis 1 baris per commit
$pullCompact = preg_replace('/\s+/', ' ', trim((string) $pullOutput));
$runLine = $date
    . ' - RUN: '
    . ($oldCommit !== '' ? $oldCommit : '[unknown]')
    . ' -> '
    . ($newCommit !== '' ? $newCommit : '[unknown]')
    . ' | pull='
    . ($pullCompact !== '' ? $pullCompact : '[empty]')
    . "\n";
file_put_contents($logFile, $runLine, FILE_APPEND);

if ($changed && $oldCommit !== '' && $newCommit !== '') {
    $commits = $run(sprintf(
        'git log %s..%s --pretty=format:"%%h | %%an | %%s"',
        escapeshellarg($oldCommit),
        escapeshellarg($newCommit)
    ));
    $lines = array_filter(array_map('trim', explode("\n", (string) $commits)));
    foreach ($lines as $line) {
        file_put_contents($logFile, $date . ' - Deploy ' . $line . "\n", FILE_APPEND);
    }
}

if ($oldCommit === '' || $newCommit === '') {
    echo "Peringatan: commit tidak terbaca. Kemungkinan `shell_exec` atau akses `git` dibatasi hosting.\n";
}

echo $changed ? "Status: Deploy berhasil.\n" : "Status: Tidak ada perubahan.\n";
echo "Log disimpan di: {$logFile}\n";
