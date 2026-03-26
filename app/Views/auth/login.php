<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - Albion Crafting Profit Calculator</title>
  <link rel="stylesheet" href="/assets/app.css?v=20260326-9">
</head>
<body class="auth-page">
  <main class="auth-wrap">
    <section class="page-header">
      <h1 class="page-title">Login</h1>
      <p class="page-subtitle">Masuk untuk mengakses dashboard dan fitur lanjutan.</p>
    </section>

    <section class="card auth-card">
      <?php if (!empty($flash_error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars((string) $flash_error) ?></div>
      <?php endif; ?>
      <?php if (!empty($flash_success)): ?>
        <div class="alert"><?= htmlspecialchars((string) $flash_success) ?></div>
      <?php endif; ?>
      <?php if (!empty($errors['auth'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars((string) $errors['auth']) ?></div>
      <?php endif; ?>

      <form method="post" action="/login" class="form">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
        <div class="grid auth-grid">
          <label class="field">
            <span class="field-label">Email</span>
            <input class="input" type="email" name="email" id="email" required value="<?= htmlspecialchars((string) ($old['email'] ?? '')) ?>">
            <?php if (!empty($errors['email'])): ?><small class="field-error"><?= htmlspecialchars((string) $errors['email']) ?></small><?php endif; ?>
          </label>
          <label class="field">
            <span class="field-label">Password</span>
            <input class="input" type="password" name="password" id="password" required>
            <?php if (!empty($errors['password'])): ?><small class="field-error"><?= htmlspecialchars((string) $errors['password']) ?></small><?php endif; ?>
          </label>
        </div>
        <div class="actions auth-actions">
          <a class="button button-ghost" href="/calculator">Calculator</a>
          <a class="button button-ghost" href="/register">Register</a>
          <button class="button button-primary" type="submit">Login</button>
        </div>
      </form>
    </section>
  </main>
</body>
</html>

