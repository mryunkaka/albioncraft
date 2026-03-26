<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard - Albion Crafting Profit Calculator</title>
  <link rel="stylesheet" href="/assets/app.css?v=20260326-10">
</head>
<body>
  <div class="app-main">
    <header class="app-header">
      <div class="header-inner">
        <div class="mobile-brand">AlbionCraft</div>
        <div class="header-actions">
          <?php if (!empty($is_admin)): ?>
            <a class="button button-danger" href="/admin/subscription-requests">Admin Requests</a>
          <?php endif; ?>
          <a class="button button-ghost" href="/subscription">Subscription</a>
          <a class="button button-ghost" href="/referral">Referral</a>
          <a class="button button-ghost" href="/price-data">Data Harga</a>
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
    </main>

    <footer class="app-footer">Albion Crafting Profit Calculator | Tailwind local build | No CDN</footer>
  </div>
</body>
</html>
