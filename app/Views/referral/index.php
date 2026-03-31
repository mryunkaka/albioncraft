<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Referral - Albion Crafting Profit Calculator</title>
  <link rel="stylesheet" href="/assets/app.css?v=20260326-10">
</head>
<?php
$header_title = 'Albion Crafting Profit Calculator';
require dirname(__DIR__) . '/partials/auth-shell-start.php';
$fmtWib = static fn ($value, bool $withTime = true): string => \App\Support\DateFormatter::wib(is_scalar($value) ? (string) $value : null, $withTime);
?>
      <section class="page-header">
        <h1 class="page-title">Referral</h1>
        <p class="page-subtitle">Bagikan kode referral untuk mendapatkan bonus hari subscription.</p>
      </section>

      <?php if (!empty($flash_error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars((string) $flash_error) ?></div>
      <?php endif; ?>
      <?php if (!empty($flash_success)): ?>
        <div class="alert"><?= htmlspecialchars((string) $flash_success) ?></div>
      <?php endif; ?>

      <?php
      $referralCode = (string) (($overview['referral_code'] ?? null) ?: '-');
      $rewards = is_array($overview['rewards'] ?? null) ? $overview['rewards'] : [];
      ?>

      <section class="card">
        <h2 class="card-title">Kode Referral Kamu</h2>
        <div class="widgets">
          <article class="widget">
            <div class="widget-title">Referral Code</div>
            <div class="widget-value"><?= htmlspecialchars($referralCode) ?></div>
            <div class="widget-muted">Dipakai saat user baru register</div>
          </article>
        </div>
      </section>

      <section class="card">
        <h2 class="card-title">History Reward</h2>
        <div class="table-wrap">
          <table class="table">
            <thead>
            <tr>
              <th>Reward Type</th>
              <th>Hari</th>
              <th>Referred User</th>
              <th>Kode Dipakai</th>
              <th>Notes</th>
              <th>Created At</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rewards === []): ?>
              <tr><td colspan="6">Belum ada reward.</td></tr>
            <?php else: ?>
              <?php foreach ($rewards as $reward): ?>
                <tr>
                  <td><?= htmlspecialchars((string) ($reward['reward_type'] ?? '-')) ?></td>
                  <td><?= htmlspecialchars((string) ($reward['reward_days'] ?? 0)) ?></td>
                  <td><?= htmlspecialchars((string) (($reward['referred_username'] ?? null) ?: '-')) ?></td>
                  <td><?= htmlspecialchars((string) (($reward['referral_code_used'] ?? null) ?: '-')) ?></td>
                  <td><?= htmlspecialchars((string) (($reward['notes'] ?? null) ?: '-')) ?></td>
                  <td><?= htmlspecialchars($fmtWib($reward['created_at'] ?? null)) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
<?php require dirname(__DIR__) . '/partials/auth-shell-end.php'; ?>
