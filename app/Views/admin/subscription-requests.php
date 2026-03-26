<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Subscription Requests</title>
  <link rel="stylesheet" href="/assets/app.css?v=20260326-10">
</head>
<body>
  <div class="app-main">
    <header class="app-header">
      <div class="header-inner">
        <div class="mobile-brand">AlbionCraft Admin</div>
        <div class="header-actions">
          <a class="button button-ghost" href="/dashboard">Dashboard</a>
          <a class="button button-ghost" href="/subscription">Subscription</a>
          <a class="button button-secondary" href="/admin/subscription-actions">Action History</a>
        </div>
      </div>
    </header>

    <main class="app-content stack">
      <section class="page-header">
        <h1 class="page-title">Admin - Pending Subscription Requests</h1>
        <p class="page-subtitle">Approve/reject request extend manual admin v1.</p>
      </section>

      <?php if (!empty($flash_error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars((string) $flash_error) ?></div>
      <?php endif; ?>
      <?php if (!empty($flash_success)): ?>
        <div class="alert"><?= htmlspecialchars((string) $flash_success) ?></div>
      <?php endif; ?>

      <section class="card">
        <div class="table-wrap">
          <table class="table">
            <thead>
            <tr>
              <th>ID</th>
              <th>User</th>
              <th>Email</th>
              <th>Plan</th>
              <th>Durasi</th>
              <th>Hari</th>
              <th>Created</th>
              <th>Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!is_array($rows) || $rows === []): ?>
              <tr><td colspan="8">Tidak ada request pending.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td><?= htmlspecialchars((string) ($row['id'] ?? '-')) ?></td>
                  <td><?= htmlspecialchars((string) ($row['username'] ?? '-')) ?></td>
                  <td><?= htmlspecialchars((string) ($row['email'] ?? '-')) ?></td>
                  <td><?= htmlspecialchars((string) (($row['plan_code'] ?? null) ?: '-')) ?></td>
                  <td><?= htmlspecialchars((string) (($row['duration_type'] ?? null) ?: '-')) ?></td>
                  <td><?= htmlspecialchars((string) (($row['duration_days'] ?? null) ?: 0)) ?></td>
                  <td><?= htmlspecialchars((string) (($row['created_at'] ?? null) ?: '-')) ?></td>
                  <td>
                    <div class="flex gap-2">
                      <form method="post" action="/admin/subscription-requests/approve">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                        <input type="hidden" name="request_action_id" value="<?= htmlspecialchars((string) ($row['id'] ?? 0)) ?>">
                        <button class="button button-primary" type="submit">Approve</button>
                      </form>
                      <form method="post" action="/admin/subscription-requests/reject">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                        <input type="hidden" name="request_action_id" value="<?= htmlspecialchars((string) ($row['id'] ?? 0)) ?>">
                        <button class="button button-danger" type="submit">Reject</button>
                      </form>
                    </div>
                  </td>
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
