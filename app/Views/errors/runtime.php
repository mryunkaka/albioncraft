<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars((string) ($title ?? 'Error')) ?></title>
  <link rel="stylesheet" href="/assets/app.css?v=20260326-9">
</head>
<body class="page">
  <main class="container">
    <section class="card auth-card">
      <h1 class="card-title"><?= htmlspecialchars((string) ($title ?? 'Error')) ?></h1>
      <div class="alert alert-error">
        <?= htmlspecialchars((string) ($message ?? 'Terjadi kesalahan.')) ?>
      </div>
      <div class="actions auth-actions">
        <a class="button button-secondary" href="/calculator">Ke Calculator</a>
        <a class="button button-ghost" href="/login">Ke Login</a>
      </div>
    </section>
  </main>
</body>
</html>
