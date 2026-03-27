<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin User Management</title>
  <link rel="stylesheet" href="/assets/app.css?v=20260326-10">
</head>
<?php
$header_title = 'AlbionCraft Admin';
require dirname(__DIR__) . '/partials/auth-shell-start.php';

$overview = is_array($overview ?? null) ? $overview : [];
$rows = is_array($overview['rows'] ?? null) ? $overview['rows'] : [];
$selectedUser = is_array($overview['selected_user'] ?? null) ? $overview['selected_user'] : null;
$plans = is_array($overview['plans'] ?? null) ? $overview['plans'] : [];
$page = (int) ($overview['page'] ?? 1);
$lastPage = (int) ($overview['last_page'] ?? 1);
$total = (int) ($overview['total'] ?? 0);
$perPage = (int) ($overview['per_page'] ?? 20);
$q = (string) ($overview['q'] ?? '');
$status = (string) ($overview['status'] ?? '');
$selectedUserId = (int) ($overview['user_id'] ?? 0);

$expiryInputValue = '';
if (is_array($selectedUser) && is_string($selectedUser['plan_expired_at'] ?? null) && trim((string) $selectedUser['plan_expired_at']) !== '') {
    $expiryInputValue = str_replace(' ', 'T', substr((string) $selectedUser['plan_expired_at'], 0, 16));
}
?>
      <section class="page-header">
        <h1 class="page-title">Admin User Management</h1>
        <p class="page-subtitle">Kelola email, username, status akun, password, dan plan user dari satu halaman admin.</p>
      </section>

      <?php if (!empty($flash_error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars((string) $flash_error) ?></div>
      <?php endif; ?>
      <?php if (!empty($flash_success)): ?>
        <div class="alert"><?= htmlspecialchars((string) $flash_success) ?></div>
      <?php endif; ?>

      <section class="card">
        <h2 class="card-title">Filter User</h2>
        <form class="form" method="get" action="/admin/users">
          <div class="grid">
            <label class="field">
              <span class="field-label">Search</span>
              <input class="input" type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="username / email / referral code">
            </label>
            <label class="field">
              <span class="field-label">Status</span>
              <select class="select" name="status">
                <option value="" <?= $status === '' ? 'selected' : '' ?>>Semua</option>
                <option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                <option value="INACTIVE" <?= $status === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE</option>
              </select>
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
            <a class="button button-ghost" href="/admin/users">Reset</a>
          </div>
        </form>
      </section>

      <section class="grid md:grid-cols-3">
        <article class="widget">
          <div class="widget-title">Total User</div>
          <div class="widget-value"><?= htmlspecialchars((string) $total) ?></div>
          <div class="widget-muted">Hasil sesuai filter aktif</div>
        </article>
        <article class="widget">
          <div class="widget-title">Selected User</div>
          <div class="widget-value"><?= htmlspecialchars((string) ($selectedUser['username'] ?? '-')) ?></div>
          <div class="widget-muted"><?= htmlspecialchars((string) ($selectedUser['email'] ?? '-')) ?></div>
        </article>
        <article class="widget">
          <div class="widget-title">Plan Aktif</div>
          <div class="widget-value"><?= htmlspecialchars((string) ($selectedUser['plan_code'] ?? '-')) ?></div>
          <div class="widget-muted">Expired: <?= htmlspecialchars((string) (($selectedUser['plan_expired_at'] ?? null) ?: '-')) ?></div>
        </article>
      </section>

      <section class="card">
        <h2 class="card-title">Daftar User</h2>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Status</th>
                <th>Plan</th>
                <th>Expired</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($rows === []): ?>
                <tr><td colspan="7">Belum ada user yang cocok dengan filter.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <?php
                  $selectLink = '/admin/users?user_id=' . (int) ($row['id'] ?? 0)
                    . '&q=' . rawurlencode($q)
                    . '&status=' . rawurlencode($status)
                    . '&per_page=' . rawurlencode((string) $perPage)
                    . '&page=' . rawurlencode((string) $page);
                  ?>
                  <tr>
                    <td><?= htmlspecialchars((string) ($row['id'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['username'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['email'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['status'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['plan_code'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) (($row['plan_expired_at'] ?? null) ?: '-')) ?></td>
                    <td><a class="button button-ghost" href="<?= $selectLink ?>">Manage</a></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="actions">
          <?php
          $base = '/admin/users?q=' . rawurlencode($q)
            . '&status=' . rawurlencode($status)
            . '&per_page=' . rawurlencode((string) $perPage)
            . '&user_id=' . rawurlencode((string) $selectedUserId);
          ?>
          <a class="button button-ghost <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>" href="<?= $page <= 1 ? '#' : ($base . '&page=' . ($page - 1)) ?>">Prev</a>
          <div class="text-sm text-slate-600">Page <?= htmlspecialchars((string) $page) ?> / <?= htmlspecialchars((string) $lastPage) ?></div>
          <a class="button button-ghost <?= $page >= $lastPage ? 'pointer-events-none opacity-50' : '' ?>" href="<?= $page >= $lastPage ? '#' : ($base . '&page=' . ($page + 1)) ?>">Next</a>
        </div>
      </section>

      <?php if ($selectedUser !== null): ?>
        <section class="card">
          <h2 class="card-title">Profil User #<?= htmlspecialchars((string) ($selectedUser['id'] ?? '-')) ?></h2>
          <form class="form" method="post" action="/admin/users/profile">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
            <input type="hidden" name="user_id" value="<?= htmlspecialchars((string) ($selectedUser['id'] ?? 0)) ?>">
            <div class="grid">
              <label class="field">
                <span class="field-label">Username</span>
                <input class="input" type="text" name="username" value="<?= htmlspecialchars((string) ($selectedUser['username'] ?? '')) ?>" required>
              </label>
              <label class="field">
                <span class="field-label">Email</span>
                <input class="input" type="email" name="email" value="<?= htmlspecialchars((string) ($selectedUser['email'] ?? '')) ?>" required>
              </label>
              <label class="field">
                <span class="field-label">Status</span>
                <select class="select" name="status" required>
                  <option value="ACTIVE" <?= strtoupper((string) ($selectedUser['status'] ?? 'ACTIVE')) === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                  <option value="INACTIVE" <?= strtoupper((string) ($selectedUser['status'] ?? 'ACTIVE')) === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE</option>
                </select>
              </label>
            </div>
            <div class="actions">
              <button class="button button-primary" type="submit">Simpan Profil</button>
            </div>
          </form>
        </section>

        <section class="card">
          <h2 class="card-title">Reset Password</h2>
          <form class="form" method="post" action="/admin/users/password">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
            <input type="hidden" name="user_id" value="<?= htmlspecialchars((string) ($selectedUser['id'] ?? 0)) ?>">
            <div class="grid">
              <label class="field">
                <span class="field-label">Password Baru</span>
                <input class="input" type="password" name="new_password" minlength="8" required>
              </label>
              <label class="field">
                <span class="field-label">Konfirmasi Password</span>
                <input class="input" type="password" name="new_password_confirmation" minlength="8" required>
              </label>
            </div>
            <div class="actions">
              <button class="button button-primary" type="submit">Ganti Password</button>
            </div>
          </form>
        </section>

        <section class="card">
          <h2 class="card-title">Plan & Downgrade</h2>
          <form class="form" method="post" action="/admin/users/plan">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
            <input type="hidden" name="user_id" value="<?= htmlspecialchars((string) ($selectedUser['id'] ?? 0)) ?>">
            <div class="grid">
              <label class="field">
                <span class="field-label">Plan</span>
                <select class="select" name="plan_code" id="admin-plan-code" required>
                  <?php foreach ($plans as $plan): ?>
                    <?php $planCode = strtoupper((string) ($plan['code'] ?? 'FREE')); ?>
                    <option value="<?= htmlspecialchars($planCode) ?>" <?= strtoupper((string) ($selectedUser['plan_code'] ?? 'FREE')) === $planCode ? 'selected' : '' ?>>
                      <?= htmlspecialchars((string) ($plan['name'] ?? $planCode)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="field" id="admin-expired-field">
                <span class="field-label">Expired At</span>
                <input class="input" type="datetime-local" name="expired_at" id="admin-expired-at" value="<?= htmlspecialchars($expiryInputValue) ?>">
              </label>
            </div>
            <p class="text-sm text-slate-600">Pilih FREE untuk downgrade instan. Plan FREE akan mengosongkan expiry user.</p>
            <div class="actions">
              <button class="button button-primary" type="submit">Simpan Plan</button>
            </div>
          </form>
        </section>
        <script>
          (() => {
            const planSelect = document.getElementById('admin-plan-code');
            const expiredField = document.getElementById('admin-expired-field');
            const expiredInput = document.getElementById('admin-expired-at');
            if (!planSelect || !expiredField || !expiredInput) return;

            const sync = () => {
              const planCode = String(planSelect.value || '').toUpperCase();
              const needsExpiry = planCode !== 'FREE';
              expiredField.style.display = needsExpiry ? '' : 'none';
              expiredInput.required = needsExpiry;
              if (!needsExpiry) {
                expiredInput.value = '';
              }
            };

            sync();
            planSelect.addEventListener('change', sync);
          })();
        </script>
      <?php endif; ?>
<?php require dirname(__DIR__) . '/partials/auth-shell-end.php'; ?>
