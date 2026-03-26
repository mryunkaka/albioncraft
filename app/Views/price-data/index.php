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
          <a class="button button-ghost" href="/subscription">Subscription</a>
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
        <p class="page-subtitle">Search, filter, pagination server-side, dan simpan/update harga item.</p>
      </section>

      <?php if (!empty($flash_error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars((string) $flash_error) ?></div>
      <?php endif; ?>
      <?php if (!empty($flash_success)): ?>
        <div class="alert"><?= htmlspecialchars((string) $flash_success) ?></div>
      <?php endif; ?>

      <?php
      $cities = is_array($cities ?? null) ? $cities : [];
      $items = is_array($item_options ?? null) ? $item_options : [];
      ?>

      <section class="card">
        <h2 class="card-title">Input Harga</h2>
        <form id="price-form" class="form" method="post" action="/price-data/save">
          <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
          <div class="grid">
            <label class="field xl:col-span-2">
              <span class="field-label">Item</span>
              <select class="select" name="item_id" id="price-item-id" required>
                <option value="">Pilih item</option>
                <?php foreach ($items as $item): ?>
                  <option value="<?= htmlspecialchars((string) ($item['id'] ?? '')) ?>">
                    <?= htmlspecialchars((string) (($item['name'] ?? '-') . ' [' . ($item['item_code'] ?? '-') . ']')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>

            <label class="field">
              <span class="field-label">City (Optional)</span>
              <select class="select" name="city_id">
                <option value="">Global / Tanpa kota</option>
                <?php foreach ($cities as $city): ?>
                  <option value="<?= htmlspecialchars((string) ($city['id'] ?? '')) ?>">
                    <?= htmlspecialchars((string) ($city['name'] ?? '-')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>

            <label class="field">
              <span class="field-label">Price Type</span>
              <select class="select" name="price_type" required>
                <option value="BUY">BUY</option>
                <option value="SELL">SELL</option>
              </select>
            </label>

            <label class="field">
              <span class="field-label">Price Value</span>
              <input class="input" name="price_value" type="number" step="0.01" min="0" required>
            </label>

            <label class="field">
              <span class="field-label">Observed At</span>
              <input class="input" name="observed_at" type="datetime-local">
            </label>

            <label class="field xl:col-span-2">
              <span class="field-label">Notes</span>
              <input class="input" name="notes" type="text" maxlength="255" placeholder="Catatan opsional">
            </label>
          </div>
          <div class="actions">
            <button class="button button-primary" type="submit" id="price-save-btn">Simpan Harga</button>
          </div>
        </form>
      </section>

      <section class="card">
        <h2 class="card-title">Filter & Table Harga</h2>
        <div class="form">
          <div class="grid">
            <label class="field">
              <span class="field-label">Search Item</span>
              <input class="input" id="filter-q" type="text" placeholder="Nama item / kode item">
            </label>
            <label class="field">
              <span class="field-label">Price Type</span>
              <select class="select" id="filter-price-type">
                <option value="">Semua</option>
                <option value="BUY">BUY</option>
                <option value="SELL">SELL</option>
              </select>
            </label>
            <label class="field">
              <span class="field-label">City</span>
              <select class="select" id="filter-city-id">
                <option value="">Semua kota</option>
                <?php foreach ($cities as $city): ?>
                  <option value="<?= htmlspecialchars((string) ($city['id'] ?? '')) ?>">
                    <?= htmlspecialchars((string) ($city['name'] ?? '-')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="field">
              <span class="field-label">Per Page</span>
              <select class="select" id="filter-per-page">
                <option value="10">10</option>
                <option value="20" selected>20</option>
                <option value="50">50</option>
              </select>
            </label>
          </div>
        </div>

        <div class="table-wrap">
          <table class="table" id="price-table">
            <thead>
            <tr>
              <th>ID</th>
              <th>Item</th>
              <th>Code</th>
              <th>City</th>
              <th>Type</th>
              <th class="right">Price</th>
              <th>Observed</th>
              <th>Updated</th>
              <th>Notes</th>
            </tr>
            </thead>
            <tbody id="price-table-body">
              <tr><td colspan="9">Loading...</td></tr>
            </tbody>
          </table>
        </div>

        <div class="actions">
          <button class="button button-ghost" type="button" id="page-prev">Prev</button>
          <div class="text-sm text-slate-600" id="page-info">Page 1 / 1</div>
          <button class="button button-ghost" type="button" id="page-next">Next</button>
        </div>
      </section>
    </main>
  </div>
  <script>
    window.__PRICE_DATA__ = {
      csrfToken: <?= json_encode((string) ($csrf_token ?? ''), JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
  <script src="/assets/price-data.js?v=20260326-1"></script>
</body>
</html>
