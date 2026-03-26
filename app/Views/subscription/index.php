<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Subscription - Albion Crafting Profit Calculator</title>
  <link rel="stylesheet" href="/assets/app.css?v=20260326-10">
</head>
<body>
  <div class="app-main">
    <header class="app-header">
      <div class="header-inner">
        <div class="mobile-brand">AlbionCraft</div>
        <div class="header-actions">
          <a class="button button-ghost" href="/dashboard">Dashboard</a>
          <a class="button button-ghost" href="/referral">Referral</a>
          <a class="button button-secondary" href="/calculator">Calculator</a>
          <form action="/logout" method="post">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
            <button class="button button-ghost" type="submit">Logout</button>
          </form>
        </div>
      </div>
    </header>

    <main class="app-content stack">
      <section class="page-header">
        <h1 class="page-title">Subscription</h1>
        <p class="page-subtitle">Mode v1: aktivasi berbayar tetap manual admin.</p>
      </section>

      <?php if (!empty($flash_error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars((string) $flash_error) ?></div>
      <?php endif; ?>
      <?php if (!empty($flash_success)): ?>
        <div class="alert"><?= htmlspecialchars((string) $flash_success) ?></div>
      <?php endif; ?>

      <?php
      $currentUser = is_array($overview['user'] ?? null) ? $overview['user'] : [];
      $currentSub = is_array($overview['current_subscription'] ?? null) ? $overview['current_subscription'] : [];
      $plans = is_array($overview['plans'] ?? null) ? $overview['plans'] : [];
      $durations = is_array($overview['durations'] ?? null) ? $overview['durations'] : [];
      $logs = is_array($overview['subscription_logs'] ?? null) ? $overview['subscription_logs'] : [];
      ?>

      <section class="widgets">
        <article class="widget">
          <div class="widget-title">Plan Aktif</div>
          <div class="widget-value"><?= htmlspecialchars((string) ($currentUser['plan_code'] ?? '-')) ?></div>
          <div class="widget-muted"><?= htmlspecialchars((string) ($currentUser['plan_name'] ?? '-')) ?></div>
        </article>
        <article class="widget">
          <div class="widget-title">Expired At</div>
          <div class="widget-value"><?= htmlspecialchars((string) (($currentUser['plan_expired_at'] ?? null) ?: '-')) ?></div>
          <div class="widget-muted">Auto downgrade ke FREE jika lewat tanggal</div>
        </article>
        <article class="widget">
          <div class="widget-title">Subscription Terakhir</div>
          <div class="widget-value"><?= htmlspecialchars((string) ($currentSub['plan_code'] ?? '-')) ?></div>
          <div class="widget-muted"><?= htmlspecialchars((string) ($currentSub['duration_type'] ?? '-')) ?></div>
        </article>
      </section>

      <section class="card">
        <h2 class="card-title">Request Extend (Manual Admin)</h2>
        <form method="post" action="/subscription/request" class="form">
          <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
          <div class="grid">
            <label class="field">
              <span class="field-label">Plan</span>
              <select class="select" name="plan_code" required>
                <?php foreach ($plans as $plan): ?>
                  <option value="<?= htmlspecialchars((string) ($plan['code'] ?? 'FREE')) ?>">
                    <?= htmlspecialchars((string) ($plan['name'] ?? '-')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>

            <label class="field">
              <span class="field-label">Durasi</span>
              <select class="select" name="duration_type" required>
                <?php foreach ($durations as $duration): ?>
                  <option value="<?= htmlspecialchars((string) ($duration['code'] ?? 'MONTHLY')) ?>">
                    <?= htmlspecialchars((string) ($duration['code'] ?? '-')) ?> (<?= htmlspecialchars((string) ($duration['days'] ?? 0)) ?> hari)
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div class="actions">
            <button type="submit" class="button button-primary">Kirim Request</button>
          </div>
        </form>
      </section>

      <section class="card">
        <h2 class="card-title">Riwayat Subscription Log</h2>
        <div class="table-wrap">
          <table class="table">
            <thead>
            <tr>
              <th>Action</th>
              <th>Old Plan</th>
              <th>New Plan</th>
              <th>Old Expired</th>
              <th>New Expired</th>
              <th>Actor</th>
              <th>Created At</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($logs === []): ?>
              <tr><td colspan="7">Belum ada log.</td></tr>
            <?php else: ?>
              <?php foreach ($logs as $log): ?>
                <tr>
                  <td><?= htmlspecialchars((string) ($log['action_type'] ?? '-')) ?></td>
                  <td><?= htmlspecialchars((string) (($log['old_plan_code'] ?? null) ?: '-')) ?></td>
                  <td><?= htmlspecialchars((string) (($log['new_plan_code'] ?? null) ?: '-')) ?></td>
                  <td><?= htmlspecialchars((string) (($log['old_expired_at'] ?? null) ?: '-')) ?></td>
                  <td><?= htmlspecialchars((string) (($log['new_expired_at'] ?? null) ?: '-')) ?></td>
                  <td><?= htmlspecialchars((string) ($log['actor_label'] ?? '-')) ?></td>
                  <td><?= htmlspecialchars((string) ($log['created_at'] ?? '-')) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</body>
</html>

