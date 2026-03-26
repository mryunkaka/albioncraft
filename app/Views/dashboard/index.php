<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard - Albion Crafting Profit Calculator</title>
  <link rel="stylesheet" href="/assets/app.css?v=20260326-7">
</head>
<body class="page">
  <main class="container">
    <header class="page-header">
      <h1 class="page-title">Dashboard</h1>
      <p class="page-subtitle">
        Login sebagai <strong><?= htmlspecialchars((string) ($user['username'] ?? '-')) ?></strong>
        (<?= htmlspecialchars((string) ($user['email'] ?? '-')) ?>)
      </p>
    </header>

    <?php if (!empty($flash_error)): ?>
      <div class="alert alert-error"><?= htmlspecialchars((string) $flash_error) ?></div>
    <?php endif; ?>
    <?php if (!empty($flash_success)): ?>
      <div class="alert"><?= htmlspecialchars((string) $flash_success) ?></div>
    <?php endif; ?>

    <section class="card">
      <h2 class="card-title">Quick Actions</h2>
      <div class="actions dashboard-actions">
        <a class="button button-secondary" href="/calculator">Buka Calculator</a>
        <form action="/logout" method="post">
          <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
          <button class="button button-primary" type="submit">Logout</button>
        </form>
      </div>
    </section>
  </main>
</body>
</html>
