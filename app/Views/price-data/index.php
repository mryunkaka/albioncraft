<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Data Harga - Albion Crafting Profit Calculator</title>
  <link rel="stylesheet" href="/assets/app.css?v=20260326-10">
</head>
<body>
  <div class="app-main">
    <header class="app-header">
      <div class="header-inner">
        <div class="mobile-brand">AlbionCraft</div>
        <div class="header-actions">
          <a class="button button-ghost" href="/dashboard">Dashboard</a>
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
        <h1 class="page-title">Data Harga (PRO)</h1>
        <p class="page-subtitle">Halaman ini sudah digate oleh PlanFeatureMiddleware: `price_bulk_input`.</p>
      </section>

      <?php if (!empty($flash_error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars((string) $flash_error) ?></div>
      <?php endif; ?>
      <?php if (!empty($flash_success)): ?>
        <div class="alert"><?= htmlspecialchars((string) $flash_success) ?></div>
      <?php endif; ?>

      <section class="card">
        <h2 class="card-title">Status</h2>
        <p class="page-subtitle">
          User: <strong><?= htmlspecialchars((string) ($user['username'] ?? '-')) ?></strong> |
          Plan: <strong><?= htmlspecialchars((string) ($user['plan_code'] ?? '-')) ?></strong>
        </p>
        <p class="text-sm text-slate-600">Implementasi CRUD harga massal ada di fase berikutnya.</p>
      </section>
    </main>
  </div>
</body>
</html>

