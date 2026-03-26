<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Albion Crafting Profit Calculator</title>
  <link rel="stylesheet" href="/assets/app.css?v=20260326-10">
</head>
<body>
  <div class="app-shell">
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>
    <aside class="app-sidebar animate-stagger">
      <div class="flex items-start justify-between gap-2">
        <div>
          <div class="sidebar-brand">AlbionCraft</div>
          <div class="sidebar-subtitle">Profit Calculator Suite</div>
        </div>
        <button class="button button-ghost" type="button" id="close-sidebar">Close</button>
      </div>
      <nav class="sidebar-nav">
        <a class="nav-link active" href="/calculator">Calculator</a>
        <?php if (is_array($auth ?? null)): ?>
          <a class="nav-link" href="/dashboard">Dashboard</a>
          <a class="nav-link" href="/subscription">Subscription</a>
          <a class="nav-link" href="/referral">Referral</a>
          <a class="nav-link" href="/price-data">Data Harga (PRO)</a>
        <?php else: ?>
          <a class="nav-link" href="/login">Login</a>
          <a class="nav-link" href="/register">Register</a>
        <?php endif; ?>
      </nav>
      <div class="widget animate-float">
        <div class="widget-title">Mode</div>
        <div class="widget-value">STRICT</div>
        <div class="widget-muted">Sesuai docs/11 & docs/12</div>
      </div>
      <div class="widget">
        <div class="widget-title">Asset Pipeline</div>
        <div class="widget-value">Tailwind</div>
        <div class="widget-muted">Local build, no CDN</div>
      </div>
    </aside>

    <div class="app-main">
      <header class="app-header">
        <div class="header-inner">
          <div>
            <div class="mobile-brand">AlbionCraft</div>
            <div class="text-sm font-semibold text-slate-800">Albion Crafting Profit Calculator</div>
          </div>
          <div class="header-actions">
            <button class="button button-ghost" type="button" id="toggle-sidebar">Menu</button>
            <button class="button button-secondary" type="button" id="open-help-modal">Guide</button>
            <?php if (is_array($auth ?? null)): ?>
              <form method="post" action="/logout">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                <button class="button button-ghost" type="submit">Logout</button>
              </form>
            <?php else: ?>
              <a class="button button-ghost" href="/login">Login</a>
            <?php endif; ?>
          </div>
        </div>
      </header>

      <main class="app-content">
        <div class="container stack">
          <section class="page-header">
            <h1 class="page-title">Quick Calculator</h1>
            <p class="page-subtitle">UI modern, responsif, dan kalkulasi strict dengan akurasi spreadsheet Famouzak free.</p>
          </section>

          <section class="widgets">
            <article class="widget">
              <div class="widget-title">Engine</div>
              <div class="widget-value">Spreadsheet Sim</div>
              <div class="widget-muted">RRR + Iterasi + SRP net-aware</div>
            </article>
            <article class="widget">
              <div class="widget-title">Rounding</div>
              <div class="widget-value">2 Mode</div>
              <div class="widget-muted">SPREADSHEET_BULK / INGAME_PER_CRAFT</div>
            </article>
            <article class="widget">
              <div class="widget-title">Output</div>
              <div class="widget-value">IDR</div>
              <div class="widget-muted">Market scenario & status badge</div>
            </article>
            <article class="widget">
              <div class="widget-title">Storage</div>
              <div class="widget-value">Local</div>
              <div class="widget-muted">Input persist saat refresh</div>
            </article>
          </section>

          <section class="card animate-fade-in-up">
            <h2 class="card-title">Input Parameters</h2>

            <form id="calc-form" class="form">
              <div class="grid">
                <label class="field xl:col-span-2">
                  <span class="field-label">Nama Item (Optional)</span>
                  <input class="input" name="item_name" type="text" value="" placeholder="Contoh: ENERGY POTION T4" autocomplete="off">
                </label>
              </div>

              <div class="grid">
                <label class="field">
                  <span class="field-label">Bonus Basic</span>
                  <input class="input" name="bonus_basic" type="number" step="0.01" value="18">
                </label>
                <label class="field">
                  <span class="field-label">Bonus Local</span>
                  <input class="input" name="bonus_local" type="number" step="0.01" value="0">
                </label>
                <label class="field">
                  <span class="field-label">Bonus Daily</span>
                  <input class="input" name="bonus_daily" type="number" step="0.01" value="0">
                </label>
                <label class="field">
                  <span class="field-label">Premium</span>
                  <select class="select" name="premium_status">
                    <option value="1" selected>Yes</option>
                    <option value="0">No</option>
                  </select>
                </label>
              </div>

              <div class="grid">
                <label class="field">
                  <span class="field-label">Craft Price</span>
                  <input class="input" name="usage_fee" type="number" step="0.01" value="200">
                </label>
                <label class="field">
                  <span class="field-label">Item Value</span>
                  <input class="input" name="item_value" type="number" step="0.01" value="64">
                </label>
                <label class="field">
                  <span class="field-label">Output Quantity / Recipe</span>
                  <input class="input" name="output_qty" type="number" step="1" value="1" min="1">
                </label>
                <label class="field">
                  <span class="field-label">Target Jumlah Craft</span>
                  <input class="input" name="target_output_qty" type="number" step="1" value="100" min="1">
                </label>
              </div>

              <div class="grid">
                <label class="field">
                  <span class="field-label">Market Price (Optional)</span>
                  <input class="input" name="sell_price" type="number" step="0.01" value="" min="0" placeholder="Kosongkan jika hanya ingin SRP">
                </label>
                <label class="field">
                  <span class="field-label">Rounding Mode</span>
                  <select class="select" name="return_rounding_mode">
                    <option value="SPREADSHEET_BULK" selected>Spreadsheet (Default)</option>
                    <option value="INGAME_PER_CRAFT">In-game (Per Craft)</option>
                  </select>
                </label>
                <label class="field">
                  <span class="field-label">Craft With Focus</span>
                  <select class="select" name="craft_with_focus">
                    <option value="0" selected>No</option>
                    <option value="1">Yes</option>
                  </select>
                </label>
                <label class="field">
                  <span class="field-label">Focus Points</span>
                  <input class="input" name="focus_points" type="number" step="1" value="0" min="0">
                </label>
                <label class="field">
                  <span class="field-label">Focus per Craft</span>
                  <input class="input" name="focus_per_craft" type="number" step="1" value="0" min="0">
                </label>
              </div>

              <div class="card-subtitle">Materials</div>
              <div id="materials" class="calc-materials"></div>
              <div class="actions">
                <button class="button button-secondary" type="button" id="add-material">Tambah Material</button>
                <button class="button button-ghost" type="button" id="clear-local">Clear</button>
                <button class="button button-primary" type="submit" id="calc-submit">Hitung</button>
              </div>
              <div id="calc-error" class="alert alert-error" hidden></div>
            </form>
          </section>

          <section class="card animate-fade-in-up">
            <h2 class="card-title">Result</h2>
            <div class="table-wrap">
              <table class="table table-summary" id="summary-row">
                <thead>
                  <tr>
                    <th>NAMA ITEM</th>
                    <th class="right">QTY</th>
                    <th class="right">HARGA MARKET</th>
                    <th class="right">MARGIN</th>
                    <th class="right">SRP 5%</th>
                    <th class="right">SRP 10%</th>
                    <th class="right">SRP 15%</th>
                    <th>MATERIAL LIST TO BUY</th>
                    <th class="right">PRODUCTION COST</th>
                    <th class="right">PROD COST / ITEM</th>
                    <th class="right">TOTAL PROFIT</th>
                    <th class="right">PROFIT / ITEM</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>

            <details class="details">
              <summary class="details-summary">Detail Perhitungan</summary>

              <div class="card-subtitle">RESULT / VALUE (Excel Style)</div>
              <div class="table-wrap">
                <table class="table" id="excel-result">
                  <thead>
                    <tr>
                      <th>RESULT</th>
                      <th class="right">VALUE</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

              <div class="card-subtitle">Focus Summary</div>
              <div class="table-wrap">
                <table class="table" id="focus-summary">
                  <thead>
                    <tr>
                      <th>Field</th>
                      <th class="right">Value</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

              <div class="card-subtitle">Material Fields</div>
              <div class="table-wrap">
                <table class="table" id="material-fields">
                  <thead>
                    <tr>
                      <th>Field</th>
                      <th class="right">Material 1</th>
                      <th class="right">Material 2</th>
                      <th class="right">Material 3</th>
                      <th class="right">Material 4</th>
                      <th class="right">Material 5</th>
                      <th class="right">Material 6</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

              <div class="card-subtitle">Iterasi</div>
              <div class="table-wrap">
                <table class="table" id="iteration-table">
                  <thead>
                    <tr>
                      <th>Iterasi</th>
                      <th class="right">Material 1</th>
                      <th class="right">Material 2</th>
                      <th class="right">Material 3</th>
                      <th class="right">Material 4</th>
                      <th class="right">Material 5</th>
                      <th class="right">Material 6</th>
                      <th class="right">Craftable Item</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

              <div class="card-subtitle">Material To Buy / Sisa Material</div>
              <div class="table-wrap">
                <table class="table" id="material-summary">
                  <thead>
                    <tr>
                      <th>Material</th>
                      <th class="right">To Buy</th>
                      <th class="right">Sisa</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </details>
          </section>
        </div>
      </main>

      <footer class="app-footer">
        <div class="mx-auto w-full max-w-[1280px]">
          Albion Crafting Profit Calculator | Tailwind local build | No CDN
        </div>
      </footer>
    </div>
  </div>

  <div class="modal" id="help-modal" aria-hidden="true">
    <div class="modal-backdrop" id="close-help-backdrop"></div>
    <div class="modal-panel">
      <div class="modal-header">
        <div class="modal-title">Guide Singkat</div>
        <button class="button button-ghost" type="button" id="close-help-modal">Tutup</button>
      </div>
      <div class="modal-content">
        <p>Isi parameter craft, tambah material, lalu tekan <strong>Hitung</strong>.</p>
        <p>Untuk akurasi spreadsheet, gunakan mode <strong>SPREADSHEET_BULK</strong>.</p>
        <p>Jika ingin simulasi rounding lebih granular, pakai <strong>INGAME_PER_CRAFT</strong>.</p>
      </div>
      <div class="modal-footer">
        <button class="button button-primary" type="button" id="close-help-footer">Mengerti</button>
      </div>
    </div>
  </div>

  <script src="/assets/calculator.js?v=20260326-10"></script>
</body>
</html>
