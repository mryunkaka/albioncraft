<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Subscription Actions</title>
  <link rel="stylesheet" href="/assets/app.css?v=20260326-10">
</head>
<body>
  <div class="app-main">
    <header class="app-header">
      <div class="header-inner">
        <div class="mobile-brand">AlbionCraft Admin</div>
        <div class="header-actions">
          <a class="button button-ghost" href="/dashboard">Dashboard</a>
          <a class="button button-ghost" href="/admin/subscription-requests">Pending Requests</a>
        </div>
      </div>
    </header>

    <main class="app-content stack">
      <section class="page-header">
        <h1 class="page-title">Admin Action History</h1>
        <p class="page-subtitle">Audit trail untuk aksi subscription manual admin.</p>
      </section>

      <?php if (!empty($flash_error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars((string) $flash_error) ?></div>
      <?php endif; ?>
      <?php if (!empty($flash_success)): ?>
        <div class="alert"><?= htmlspecialchars((string) $flash_success) ?></div>
      <?php endif; ?>

      <?php
      $history = is_array($history ?? null) ? $history : [];
      $rows = is_array($history['rows'] ?? null) ? $history['rows'] : [];
      $page = (int) ($history['page'] ?? 1);
      $lastPage = (int) ($history['last_page'] ?? 1);
      $total = (int) ($history['total'] ?? 0);
      $perPage = (int) ($history['per_page'] ?? 30);
      $actionType = (string) ($history['action_type'] ?? '');
      $q = (string) ($history['q'] ?? '');
      ?>

      <section class="card">
        <h2 class="card-title">Filter</h2>
        <form class="form" method="get" action="/admin/subscription-actions">
          <div class="grid">
            <label class="field">
              <span class="field-label">Action Type</span>
              <select class="select" name="action_type">
                <option value="" <?= $actionType === '' ? 'selected' : '' ?>>Semua</option>
                <option value="REQUEST_EXTEND" <?= $actionType === 'REQUEST_EXTEND' ? 'selected' : '' ?>>REQUEST_EXTEND</option>
                <option value="APPROVE_EXTEND" <?= $actionType === 'APPROVE_EXTEND' ? 'selected' : '' ?>>APPROVE_EXTEND</option>
                <option value="REJECT_EXTEND" <?= $actionType === 'REJECT_EXTEND' ? 'selected' : '' ?>>REJECT_EXTEND</option>
              </select>
            </label>
            <label class="field">
              <span class="field-label">Search</span>
              <input class="input" type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="username/email/notes">
            </label>
            <label class="field">
              <span class="field-label">Per Page</span>
              <select class="select" name="per_page">
                <option value="20" <?= $perPage === 20 ? 'selected' : '' ?>>20</option>
                <option value="30" <?= $perPage === 30 ? 'selected' : '' ?>>30</option>
                <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50</option>
              </select>
            </label>
          </div>
          <div class="actions">
            <button class="button button-primary" type="submit">Apply Filter</button>
            <a class="button button-ghost" href="/admin/subscription-actions">Reset</a>
          </div>
        </form>
      </section>

      <section class="card">
        <h2 class="card-title">Total: <?= htmlspecialchars((string) $total) ?></h2>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Action</th>
                <th>User</th>
                <th>Email</th>
                <th>Plan</th>
                <th>Durasi</th>
                <th>Hari</th>
                <th>Actor</th>
                <th>Notes</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($rows === []): ?>
                <tr><td colspan="10">Belum ada data action.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars((string) ($row['id'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['action_type'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['username'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['email'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) (($row['plan_code'] ?? null) ?: '-')) ?></td>
                    <td><?= htmlspecialchars((string) (($row['duration_type'] ?? null) ?: '-')) ?></td>
                    <td><?= htmlspecialchars((string) (($row['duration_days'] ?? null) ?: '-')) ?></td>
                    <td><?= htmlspecialchars((string) (($row['actor_label'] ?? null) ?: '-')) ?></td>
                    <td><?= htmlspecialchars((string) (($row['notes'] ?? null) ?: '-')) ?></td>
                    <td><?= htmlspecialchars((string) (($row['created_at'] ?? null) ?: '-')) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="actions">
          <?php
          $base = '/admin/subscription-actions?action_type=' . rawurlencode($actionType)
            . '&q=' . rawurlencode($q)
            . '&per_page=' . rawurlencode((string) $perPage);
          ?>
          <a class="button button-ghost <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>" href="<?= $page <= 1 ? '#' : ($base . '&page=' . ($page - 1)) ?>">Prev</a>
          <div class="text-sm text-slate-600">Page <?= htmlspecialchars((string) $page) ?> / <?= htmlspecialchars((string) $lastPage) ?></div>
          <a class="button button-ghost <?= $page >= $lastPage ? 'pointer-events-none opacity-50' : '' ?>" href="<?= $page >= $lastPage ? '#' : ($base . '&page=' . ($page + 1)) ?>">Next</a>
        </div>
      </section>
    </main>
  </div>
</body>
</html>

