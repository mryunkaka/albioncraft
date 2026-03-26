<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register - Albion Crafting Profit Calculator</title>
  <link rel="stylesheet" href="/assets/app.css?v=20260326-7">
</head>
<body class="page">
  <main class="container">
    <header class="page-header">
      <h1 class="page-title">Register</h1>
      <p class="page-subtitle">Buat akun baru untuk akses fitur subscription dan referral.</p>
    </header>

    <section class="card auth-card">
      <?php if (!empty($flash_error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars((string) $flash_error) ?></div>
      <?php endif; ?>
      <?php if (!empty($flash_success)): ?>
        <div class="alert"><?= htmlspecialchars((string) $flash_success) ?></div>
      <?php endif; ?>

      <form method="post" action="/register" class="form">
        <div class="grid auth-grid">
          <label class="field">
            <span class="field-label">Username</span>
            <input class="input" type="text" name="username" id="username" required value="<?= htmlspecialchars((string) ($old['username'] ?? '')) ?>">
            <?php if (!empty($errors['username'])): ?><small class="field-error"><?= htmlspecialchars((string) $errors['username']) ?></small><?php endif; ?>
          </label>

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

          <label class="field">
            <span class="field-label">Konfirmasi Password</span>
            <input class="input" type="password" name="password_confirmation" id="password_confirmation" required>
            <?php if (!empty($errors['password_confirmation'])): ?><small class="field-error"><?= htmlspecialchars((string) $errors['password_confirmation']) ?></small><?php endif; ?>
          </label>

          <label class="field">
            <span class="field-label">Referral Code (Optional)</span>
            <input class="input" type="text" name="referral_code" id="referral_code" value="<?= htmlspecialchars((string) ($old['referral_code'] ?? '')) ?>">
            <?php if (!empty($errors['referral_code'])): ?><small class="field-error"><?= htmlspecialchars((string) $errors['referral_code']) ?></small><?php endif; ?>
          </label>
        </div>

        <div class="actions auth-actions">
          <a class="button button-ghost" href="/login">Sudah punya akun? Login</a>
          <button class="button button-primary" type="submit">Register</button>
        </div>
      </form>
    </section>
  </main>
</body>
</html>

