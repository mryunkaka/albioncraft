<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Albion Crafting Profit Calculator</title>
  <link rel="stylesheet" href="/assets/app.css?v=20260326-10">
  <style>
    .calculator-inline-grid {
      display: grid;
      gap: .75rem;
      grid-template-columns: repeat(1, minmax(0, 1fr));
    }
    @media (min-width: 768px) {
      .calculator-inline-grid.cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
      .calculator-inline-grid.cols-5 { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    }
    .materials-shell {
      border: 1px solid #dbeafe;
      border-radius: .75rem;
      overflow: hidden;
      background: rgba(239, 246, 255, .35);
    }
    .materials-scroll {
      overflow-x: auto;
      overflow-y: hidden;
    }
    .materials-header,
    .calc-material-row {
      display: grid;
      grid-template-columns: minmax(220px, 2fr) minmax(90px, .7fr) minmax(110px, .9fr) minmax(120px, .9fr) minmax(160px, 1fr) 120px;
      gap: .5rem;
      min-width: 920px;
      align-items: center;
      padding: .625rem;
    }
    .materials-header {
      border-bottom: 1px solid #dbeafe;
      background: #f8fafc;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .025em;
      color: #64748b;
    }
    .calc-material-row {
      border-bottom: 1px solid #eff6ff;
      background: rgba(239, 246, 255, .4);
    }
    .calc-material-row:last-child {
      border-bottom: 0;
    }
    .material-action {
      display: flex;
      justify-content: flex-end;
    }
    .material-remove {
      white-space: nowrap;
      width: 100%;
    }
  </style>
</head>
<?php
$header_title = 'Albion Crafting Profit Calculator';
$show_guide_button = true;
$currentPlanCode = is_array($auth ?? null) ? strtoupper((string) ($auth['plan_code'] ?? 'FREE')) : 'GUEST';
$currentPlanName = is_array($auth ?? null) ? (string) ($auth['plan_name'] ?? $currentPlanCode) : 'Tanpa Login';
$currentUserLabel = is_array($auth ?? null) ? (string) (($auth['username'] ?? $auth['email'] ?? 'User')) : 'Mode publik / guest';
$canUseRecipeDatabase = in_array($currentPlanCode, ['MEDIUM', 'PRO'], true);
require dirname(__DIR__) . '/partials/auth-shell-start.php';
?>
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
    <div class="widget-title">Output</div>
    <div class="widget-value">IDR</div>
    <div class="widget-muted">Market scenario & status badge</div>
  </article>
  <article class="widget">
    <div class="widget-title">Storage</div>
    <div class="widget-value">Local</div>
    <div class="widget-muted">Input persist saat refresh</div>
  </article>
  <article class="widget">
    <div class="widget-title">Plan</div>
    <div class="widget-value"><?= htmlspecialchars($currentPlanCode) ?></div>
    <div class="widget-muted"><?= htmlspecialchars($currentPlanName . ' · ' . $currentUserLabel) ?></div>
  </article>
</section>

<section class="card animate-fade-in-up">
  <h2 class="card-title">Input Parameters</h2>

  <form id="calc-form" class="form">
    <input type="hidden" name="item_id" value="">
    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">

              <?php if ($canUseRecipeDatabase): ?>
              <div class="card-subtitle">Recipe Database & Rekomendasi Kota</div>
              <div class="grid">
                <label class="field xl:col-span-2">
          <span class="field-label">Database Recipe Item</span>
          <select class="select" id="recipe-item-select" name="recipe_item_id">
            <option value="">Pilih item recipe database</option>
          </select>
        </label>
        <label class="field">
          <span class="field-label">Kota Bonus</span>
          <select class="select" id="recipe-city-select" name="recipe_city_id">
            <option value="">Tanpa bonus kota</option>
            <?php foreach (($recipe_cities ?? []) as $city): ?>
              <option value="<?= (int) ($city['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($city['name'] ?? '')) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="field justify-end">
          <span class="field-label">Auto Fill</span>
          <button class="button button-secondary" type="button" id="recipe-autofill-btn">Load Recipe</button>
        </div>
              </div>
              <?php endif; ?>

    <div class="calculator-inline-grid">
      <label class="field">
        <span class="field-label">Rounding Mode</span>
        <select class="select" name="return_rounding_mode">
          <option value="SPREADSHEET_BULK" selected>Spreadsheet (Default)</option>
          <option value="INGAME_PER_CRAFT">In-game (Per Craft)</option>
        </select>
      </label>
    </div>

    <div class="calculator-inline-grid cols-4">
      <label class="field">
        <span class="field-label">Nama Item</span>
        <input class="input" name="item_name" type="text" value="" placeholder="Contoh: ENERGY POTION T4" autocomplete="off" required>
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

    <div class="calculator-inline-grid cols-5">
      <label class="field">
        <span class="field-label">Premium</span>
        <select class="select" name="premium_status">
          <option value="0" selected>No</option>
          <option value="1">Yes</option>
        </select>
      </label>
      <label class="field">
        <span class="field-label">Bonus Basic</span>
        <input class="input" name="bonus_basic" type="number" step="0.01" value="18">
      </label>
      <label class="field">
        <span class="field-label">Bonus Local</span>
        <input class="input" name="bonus_local" type="number" step="0.01" value="0">
      </label>
      <label class="field">
        <span class="field-label">Kota Bonus Local</span>
        <select class="select" id="bonus-local-city-select" name="bonus_local_city_id">
          <option value="">Tidak ada kota bonus local</option>
          <?php foreach (($recipe_cities ?? []) as $city): ?>
            <option value="<?= (int) ($city['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($city['name'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">
        <span class="field-label">Bonus Daily</span>
        <input class="input" name="bonus_daily" type="number" step="0.01" value="0">
      </label>
    </div>

    <div class="calculator-inline-grid cols-5">
      <label class="field">
        <span class="field-label">Craft Price</span>
        <input class="input" name="usage_fee" type="number" step="0.01" value="200">
      </label>
      <label class="field">
        <span class="field-label">Kota Craft Price</span>
        <select class="select" id="craft-fee-city-select" name="craft_fee_city_id">
          <option value="">Pilih kota craft fee</option>
          <?php foreach (($recipe_cities ?? []) as $city): ?>
            <option value="<?= (int) ($city['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($city['name'] ?? '')) ?></option>
          <?php endforeach; ?>
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
    <div id="recipe-recommendations" class="alert" hidden></div>

    <div class="calculator-inline-grid cols-5">
      <label class="field">
        <span class="field-label">Market Price</span>
        <input class="input" name="sell_price" type="number" step="0.01" value="" min="0" placeholder="Masukkan harga jual" required>
      </label>
      <label class="field">
        <span class="field-label">Kota Market Price</span>
        <select class="select" id="sell-price-city-select" name="sell_price_city_id">
          <option value="">Pilih kota market price</option>
          <?php foreach (($recipe_cities ?? []) as $city): ?>
            <option value="<?= (int) ($city['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($city['name'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <div class="card-subtitle">Materials</div>
    <div class="materials-shell">
      <div class="materials-scroll">
        <div class="materials-header">
          <div>Nama Item</div>
          <div>Qty</div>
          <div>Harga</div>
          <div>RR</div>
          <div>Kota Beli</div>
          <div>Action</div>
        </div>
        <div id="materials" class="calc-materials"></div>
      </div>
    </div>
    <div class="actions">
      <button class="button button-secondary" type="button" id="add-material">Tambah Material</button>
      <?php if ($canUseRecipeDatabase): ?>
      <button class="button button-ghost" type="button" id="save-craft-fee-btn">Simpan Craft Price ke DB</button>
      <button class="button button-ghost" type="button" id="save-sell-price-btn">Simpan Harga Jual ke DB</button>
      <button class="button button-ghost" type="button" id="save-material-prices-btn">Simpan Harga Material ke DB</button>
      <?php endif; ?>
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

<script id="calculator-cities-data" type="application/json">
  <?= json_encode(array_values($recipe_cities ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<script src="/assets/calculator.js?v=20260327-01"></script>
<?php require dirname(__DIR__) . '/partials/auth-shell-end.php'; ?>
