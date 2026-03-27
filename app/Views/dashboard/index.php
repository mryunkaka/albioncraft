<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard - Albion Crafting Profit Calculator</title>
  <link rel="stylesheet" href="/assets/app.css?v=20260326-10">
</head>
<?php
$auth = is_array($user ?? null) ? $user : null;
$header_title = 'Albion Crafting Profit Calculator';
require dirname(__DIR__) . '/partials/auth-shell-start.php';
?>
      <section class="page-header">
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">
          Login sebagai <strong><?= htmlspecialchars((string) ($user['username'] ?? '-')) ?></strong>
          (<?= htmlspecialchars((string) ($user['email'] ?? '-')) ?>)
        </p>
      </section>

      <?php if (!empty($flash_error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars((string) $flash_error) ?></div>
      <?php endif; ?>
      <?php if (!empty($flash_success)): ?>
        <div class="alert"><?= htmlspecialchars((string) $flash_success) ?></div>
      <?php endif; ?>

      <?php
      $summary = is_array($calculation_summary ?? null) ? $calculation_summary : [];
      $latest = is_array($summary['latest'] ?? null) ? $summary['latest'] : null;
      $recentRows = is_array($summary['recent_rows'] ?? null) ? $summary['recent_rows'] : [];
      ?>

      <section class="widgets">
        <article class="widget">
          <div class="widget-title">Status Login</div>
          <div class="widget-value">Aktif</div>
          <div class="widget-muted">Session native PHP</div>
        </article>
        <article class="widget">
          <div class="widget-title">Plan</div>
          <div class="widget-value"><?= htmlspecialchars((string) ($user['plan_code'] ?? '-')) ?></div>
          <div class="widget-muted">ID: <?= htmlspecialchars((string) ($user['plan_id'] ?? '-')) ?></div>
        </article>
        <article class="widget">
          <div class="widget-title">Subscription</div>
          <div class="widget-value"><?= htmlspecialchars((string) ($user['plan_name'] ?? 'Free')) ?></div>
          <div class="widget-muted">
            Expired at: <?= htmlspecialchars((string) (($user['plan_expired_at'] ?? null) ?: '-')) ?>
          </div>
        </article>
        <article class="widget">
          <div class="widget-title">Engine</div>
          <div class="widget-value">STRICT</div>
          <div class="widget-muted">Golden test PASS</div>
        </article>
      </section>

      <section class="widgets">
        <article class="widget">
          <div class="widget-title">Total Kalkulasi</div>
          <div class="widget-value"><?= htmlspecialchars((string) ($summary['total_count'] ?? 0)) ?></div>
          <div class="widget-muted">Histori tersimpan</div>
        </article>
        <article class="widget">
          <div class="widget-title">Profit Simulasi</div>
          <div class="widget-value">Rp <?= number_format((float) ($summary['recent_total_profit'] ?? 0), 0, ',', '.') ?></div>
          <div class="widget-muted">Akumulasi 20 histori terbaru</div>
        </article>
        <article class="widget">
          <div class="widget-title">Avg Margin</div>
          <div class="widget-value"><?= number_format((float) ($summary['recent_avg_margin'] ?? 0), 2, ',', '.') ?>%</div>
          <div class="widget-muted">Rata-rata 20 histori terbaru</div>
        </article>
        <article class="widget">
          <div class="widget-title">Win / Loss</div>
          <div class="widget-value">
            <?= htmlspecialchars((string) ($summary['recent_profit_count'] ?? 0)) ?> / <?= htmlspecialchars((string) ($summary['recent_loss_count'] ?? 0)) ?>
          </div>
          <div class="widget-muted">Profit vs rugi pada histori terbaru</div>
        </article>
      </section>

      <section class="card">
        <h2 class="card-title">Kalkulasi Terakhir</h2>
        <?php if ($latest === null): ?>
          <div class="widget-muted">Belum ada histori kalkulasi. Gunakan calculator untuk mulai menyimpan data.</div>
        <?php else: ?>
          <div class="widgets">
            <article class="widget">
              <div class="widget-title">Item</div>
              <div class="widget-value"><?= htmlspecialchars((string) ($latest['item_name'] ?? '-')) ?></div>
              <div class="widget-muted"><?= htmlspecialchars((string) ($latest['created_at'] ?? '-')) ?></div>
            </article>
            <article class="widget">
              <div class="widget-title">Output</div>
              <div class="widget-value"><?= htmlspecialchars((string) ($latest['total_output'] ?? 0)) ?></div>
              <div class="widget-muted">Total crafted item</div>
            </article>
            <article class="widget">
              <div class="widget-title">Profit</div>
              <div class="widget-value">Rp <?= number_format((float) ($latest['scenario_profit'] ?? 0), 0, ',', '.') ?></div>
              <div class="widget-muted"><?= htmlspecialchars((string) ($latest['status'] ?? '-')) ?></div>
            </article>
            <article class="widget">
              <div class="widget-title">Margin</div>
              <div class="widget-value"><?= number_format((float) ($latest['scenario_margin'] ?? 0), 2, ',', '.') ?>%</div>
              <div class="widget-muted"><?= htmlspecialchars((string) ($latest['scenario_mode'] ?? '-')) ?></div>
            </article>
          </div>
        <?php endif; ?>
      </section>

      <section class="card">
        <h2 class="card-title">Recent Calculation History</h2>
        <div class="table-wrap">
          <table class="table">
            <thead>
            <tr>
              <th>Tanggal</th>
              <th>Item</th>
              <th>Plan</th>
              <th>Mode</th>
              <th class="right">Output</th>
              <th class="right">Production Cost</th>
              <th class="right">Sell Price</th>
              <th class="right">Profit</th>
              <th class="right">Margin</th>
              <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($recentRows === []): ?>
              <tr>
                <td colspan="10">Belum ada histori kalkulasi.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($recentRows as $row): ?>
                <?php
                $profit = isset($row['scenario_profit']) ? (float) $row['scenario_profit'] : 0.0;
                $profitClass = $profit >= 0 ? 'text-emerald-700' : 'text-rose-700';
                ?>
                <tr>
                  <td><?= htmlspecialchars((string) ($row['created_at'] ?? '-')) ?></td>
                  <td><?= htmlspecialchars((string) ($row['item_name'] ?? '-')) ?></td>
                  <td><?= htmlspecialchars((string) ($row['plan_code'] ?? '-')) ?></td>
                  <td><?= htmlspecialchars((string) ($row['calculation_mode'] ?? '-')) ?></td>
                  <td class="right"><?= htmlspecialchars((string) ($row['total_output'] ?? 0)) ?></td>
                  <td class="right">Rp <?= number_format((float) ($row['production_cost'] ?? 0), 0, ',', '.') ?></td>
                  <td class="right">
                    <?= isset($row['scenario_sell_price']) && $row['scenario_sell_price'] !== null ? 'Rp ' . number_format((float) $row['scenario_sell_price'], 0, ',', '.') : '-' ?>
                  </td>
                  <td class="right <?= $profitClass ?>">Rp <?= number_format($profit, 0, ',', '.') ?></td>
                  <td class="right"><?= number_format((float) ($row['scenario_margin'] ?? 0), 2, ',', '.') ?>%</td>
                  <td><?= htmlspecialchars((string) ($row['status'] ?? '-')) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
<?php require dirname(__DIR__) . '/partials/auth-shell-end.php'; ?>
