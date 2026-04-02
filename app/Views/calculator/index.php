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
      .calculator-inline-grid.cols-4 {
        grid-template-columns: repeat(4, minmax(0, 1fr));
      }

      .calculator-inline-grid.cols-4-item-main {
        grid-template-columns: minmax(0, 1.9fr) minmax(110px, .7fr) minmax(140px, .9fr) minmax(140px, .9fr);
      }

      .calculator-inline-grid.cols-5 {
        grid-template-columns: repeat(5, minmax(0, 1fr));
      }
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
      grid-template-columns: minmax(240px, 2.2fr) minmax(120px, .9fr) minmax(140px, .9fr) minmax(120px, .9fr) minmax(170px, 1fr) minmax(180px, 1.1fr) minmax(140px, .9fr);
      gap: .75rem;
      min-width: 1180px;
      align-items: center;
      padding: .625rem;
    }

    .materials-header > div,
    .calc-material-row > * {
      min-width: 0;
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

    .calc-material-row .input,
    .calc-material-row .select,
    .calc-material-row .button {
      width: 100%;
      min-width: 0;
    }

    .field-label-wrap {
      display: inline-flex;
      align-items: center;
      gap: .375rem;
      flex-wrap: wrap;
    }

    .field-help-trigger {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 1.2rem;
      height: 1.2rem;
      border: 1px solid #bfdbfe;
      border-radius: 999px;
      background: #eff6ff;
      color: #1d4ed8;
      font-size: .72rem;
      font-weight: 700;
      line-height: 1;
      cursor: pointer;
      transition: background-color .2s, border-color .2s, transform .2s;
    }

    .field-help-trigger:hover,
    .field-help-trigger:focus-visible {
      background: #dbeafe;
      border-color: #93c5fd;
      transform: translateY(-1px);
      outline: none;
    }

    .tooltip-popover {
      position: fixed;
      z-index: 70;
      width: min(360px, calc(100vw - 1.5rem));
      display: none;
      border: 1px solid #bfdbfe;
      border-radius: 1rem;
      background: rgba(255, 255, 255, .98);
      box-shadow: 0 20px 40px rgba(15, 23, 42, .16);
      padding: .9rem;
      backdrop-filter: blur(10px);
    }

    .tooltip-popover.is-open {
      display: block;
    }

    .tooltip-popover-title {
      font-size: .875rem;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: .35rem;
    }

    .tooltip-popover-body {
      font-size: .8rem;
      line-height: 1.45;
      color: #475569;
      white-space: pre-line;
    }

    .tooltip-popover-actions {
      display: flex;
      justify-content: space-between;
      gap: .5rem;
      margin-top: .75rem;
    }

    .tooltip-preview-button {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      font-size: .75rem;
      font-weight: 600;
      color: #1d4ed8;
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: .75rem;
      padding: .45rem .65rem;
    }

    .tooltip-preview-button[hidden] {
      display: none !important;
    }

    .tooltip-note {
      font-size: .72rem;
      color: #64748b;
    }

    .image-modal-panel {
      position: relative;
      z-index: 10;
      width: min(1100px, calc(100vw - 1rem));
      height: min(82vh, 900px);
      display: flex;
      flex-direction: column;
      border-radius: 1rem;
      border: 1px solid #bfdbfe;
      background: rgba(255, 255, 255, .96);
      box-shadow: 0 20px 40px rgba(15, 23, 42, .18);
      overflow: hidden;
    }

    .image-modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: .75rem;
      padding: .75rem 1rem;
      border-bottom: 1px solid #dbeafe;
    }

    .image-modal-meta {
      display: flex;
      flex-direction: column;
      gap: .2rem;
      min-width: 0;
    }

    .image-modal-title {
      font-size: .95rem;
      font-weight: 700;
      color: #0f172a;
    }

    .image-modal-hint {
      font-size: .75rem;
      color: #64748b;
    }

    .image-modal-stage {
      position: relative;
      flex: 1 1 auto;
      overflow: hidden;
      background:
        radial-gradient(circle at top, rgba(191, 219, 254, .25), transparent 45%),
        linear-gradient(180deg, #f8fbff, #eff6ff);
      touch-action: none;
      cursor: grab;
    }

    .image-modal-stage.is-dragging {
      cursor: grabbing;
    }

    .image-modal-figure {
      position: absolute;
      left: 50%;
      top: 50%;
      transform-origin: center center;
      will-change: transform;
      user-select: none;
    }

    .image-modal-figure img {
      max-width: min(92vw, 980px);
      max-height: 72vh;
      border-radius: .9rem;
      box-shadow: 0 18px 36px rgba(15, 23, 42, .16);
      pointer-events: none;
      user-select: none;
      -webkit-user-drag: none;
    }

    .image-modal-toolbar {
      display: flex;
      justify-content: flex-end;
      gap: .5rem;
      padding: .75rem 1rem;
      border-top: 1px solid #dbeafe;
      background: rgba(255, 255, 255, .92);
    }

    .analysis-recommendation {
      margin-bottom: 1rem;
      padding: 1rem 1.125rem;
      border: 1px solid #bbf7d0;
      border-radius: 1rem;
      background:
        linear-gradient(180deg, rgba(240, 253, 244, .98), rgba(236, 253, 245, .95));
    }

    .analysis-recommendation[hidden] {
      display: none !important;
    }

    .analysis-recommendation-title {
      margin: 0 0 .75rem;
      font-size: .95rem;
      font-weight: 700;
      color: #166534;
    }

    .analysis-recommendation-list {
      display: grid;
      gap: .65rem;
    }

    .analysis-recommendation-item {
      display: grid;
      gap: .15rem;
      padding: .7rem .8rem;
      border: 1px solid #d1fae5;
      border-radius: .85rem;
      background: rgba(255, 255, 255, .72);
    }

    .analysis-recommendation-label {
      font-weight: 700;
      color: #14532d;
    }

    .analysis-recommendation-value {
      color: #166534;
      white-space: pre-wrap;
    }

    .summary-action-cell {
      text-align: center;
      white-space: nowrap;
    }

    .summary-copy-button {
      min-width: 3rem;
      padding-inline: .7rem;
    }

    .card-title-row {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: .75rem;
      flex-wrap: wrap;
      margin-bottom: .75rem;
    }

    .card-title-row .card-title {
      margin-bottom: 0;
    }

    .selection-helper-note {
      font-size: .875rem;
      line-height: 1.5;
      color: #475569;
      max-width: 64rem;
    }

    .selection-helper-meta {
      display: grid;
      gap: .75rem;
      grid-template-columns: repeat(1, minmax(0, 1fr));
      margin-bottom: 1rem;
    }

    @media (min-width: 1440px) {
      .selection-helper-meta {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    .selection-helper-name-label {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .5rem;
      min-height: 2.75rem;
      flex-wrap: wrap;
    }

    .selection-helper-name-text {
      display: inline-flex;
      align-items: center;
      min-height: 2.75rem;
    }

    .selection-helper-name-action-slot {
      display: inline-flex;
      align-items: center;
      justify-content: flex-end;
      min-width: 150px;
      min-height: 2.75rem;
      flex-shrink: 0;
    }

    .selection-helper-table td {
      vertical-align: top;
    }

    .table-wrap {
      overflow-x: auto;
      overflow-y: visible;
    }

    .selection-helper-table {
      min-width: max-content;
    }

    .selection-helper-table th,
    .selection-helper-table td {
      white-space: nowrap;
    }

    .selection-helper-header-cell {
      position: relative;
      display: inline-block;
      width: max-content;
      min-width: 100%;
    }

    .selection-helper-header-label {
      display: inline-block;
      flex: 0 0 auto;
      white-space: nowrap;
    }

    .selection-helper-header-copy-btn {
      display: inline-flex;
      align-items: center;
      justify-content: flex-end;
      gap: .45rem;
      width: auto;
      min-width: 2.35rem;
      height: 2.35rem;
      padding: 0 .55rem 0 .75rem;
      border: 1px solid #cbd5e1;
      border-radius: .6rem;
      background: #fff;
      color: #0f172a;
      cursor: pointer;
      flex: 0 0 auto;
      position: relative;
      z-index: 2;
      touch-action: manipulation;
      -webkit-tap-highlight-color: transparent;
      user-select: none;
      transition: border-color .15s ease, background-color .15s ease, opacity .15s ease;
      white-space: nowrap;
    }

    .selection-helper-header-copy-btn:hover:not(:disabled) {
      border-color: #2563eb;
      background: #eff6ff;
    }

    .selection-helper-header-copy-btn:disabled {
      opacity: .45;
      cursor: not-allowed;
    }

    .selection-helper-header-copy-btn svg {
      width: 1rem;
      height: 1rem;
      fill: currentColor;
      flex: 0 0 auto;
      pointer-events: none;
    }

    .selection-helper-header-copy-feedback {
      position: absolute;
      top: calc(100% + .25rem);
      right: 0;
      font-size: .75rem;
      line-height: 1rem;
      color: #15803d;
      white-space: nowrap;
      padding: .2rem .45rem;
      border: 1px solid #bbf7d0;
      border-radius: .45rem;
      background: #f0fdf4;
      box-shadow: 0 8px 18px rgba(15, 23, 42, .08);
      z-index: 3;
    }

    .selection-helper-table .input,
    .selection-helper-table .select {
      min-width: 110px;
    }

    .selection-helper-table .helper-city-select {
      min-width: 150px;
    }

    .selection-helper-table .selection-helper-city-input {
      min-width: 135px;
      width: 135px;
    }

    .selection-helper-table .selection-helper-compact-input {
      min-width: 84px;
      width: 84px;
    }

    .selection-helper-table .selection-helper-price-input {
      min-width: 108px;
      width: 108px;
    }

    .selection-helper-item-value-input,
    .selection-helper-material-value-input {
      max-width: 130px;
    }

    .selection-helper-dual-input {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(110px, 130px);
      gap: .5rem;
      align-items: start;
    }

    .selection-helper-actions {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: .75rem;
      margin-top: 1rem;
      padding-top: 1rem;
      border-top: 1px solid #dbeafe;
    }

    .selection-helper-inline-actions {
      display: flex;
      flex-wrap: wrap;
      gap: .5rem;
    }

    .selection-helper-summary {
      margin-top: .75rem;
      margin-bottom: 0;
      white-space: pre-line;
      display: none !important;
    }

    .selection-helper-meta .field {
      min-width: 0;
    }

    .selection-helper-config {
      display: flex;
      flex-wrap: wrap;
      gap: .75rem;
      margin-bottom: 1rem;
      padding: .9rem 1rem;
      border: 1px solid #dbeafe;
      border-radius: 1rem;
      background: rgba(248, 250, 252, .88);
    }

    .selection-helper-mount-fields {
      display: flex;
      flex-wrap: wrap;
      gap: .75rem;
      align-items: end;
    }

    .selection-helper-mount-fields[hidden] {
      display: none !important;
    }

    .selection-helper-mount-fields .field {
      min-width: min(100%, 240px);
      margin-bottom: 0;
    }

    .selection-helper-item-grid {
      display: flex;
      flex-wrap: wrap;
      gap: .5rem;
      align-items: start;
    }

    .selection-helper-material-grid {
      display: flex;
      flex-wrap: wrap;
      gap: .5rem;
      align-items: start;
    }

    .selection-helper-item-grid > [data-helper-name="item"] {
      flex: 1 1 320px;
      min-width: 220px;
    }

    .selection-helper-item-grid > [data-helper-name="item_value"] {
      flex: 0 0 140px;
      min-width: 120px;
    }

    .selection-helper-item-grid > [data-helper-name="item_output_qty"] {
      flex: 0 0 140px;
      min-width: 120px;
    }

    .selection-helper-material-grid > [data-helper-name="material"] {
      flex: 1 1 300px;
      min-width: 220px;
    }

    .selection-helper-material-grid > [data-helper-name="material_item_value"] {
      flex: 0 0 130px;
      min-width: 110px;
    }

    .selection-helper-material-grid > [data-helper-name="material_recipe_qty"] {
      flex: 0 0 110px;
      min-width: 100px;
    }

    .selection-helper-material-grid > [data-helper-name="material_weight"] {
      flex: 0 0 110px;
      min-width: 100px;
    }

    .selection-helper-material-grid > [data-helper-name="material_return_type"] {
      flex: 0 0 160px;
      min-width: 140px;
    }

    .selection-helper-remove-material {
      font-size: .75rem;
      line-height: 1rem;
      padding: .35rem .55rem;
    }

    .selection-helper-remove-material-spacer {
      visibility: hidden;
      pointer-events: none;
    }

    .recipe-autocomplete-shell {
      position: relative;
    }

    .recipe-autocomplete-list {
      position: absolute;
      z-index: 40;
      top: calc(100% + .35rem);
      left: 0;
      right: 0;
      display: grid;
      gap: .5rem;
      max-height: 22rem;
      overflow-y: auto;
      padding: .6rem;
      border: 1px solid #bfdbfe;
      border-radius: 1rem;
      background: rgba(255, 255, 255, .98);
      box-shadow: 0 18px 36px rgba(15, 23, 42, .16);
    }

    .recipe-autocomplete-list[hidden] {
      display: none !important;
    }

    .recipe-autocomplete-card {
      width: 100%;
      display: grid;
      gap: .3rem;
      text-align: left;
      padding: .8rem .9rem;
      border: 1px solid #dbeafe;
      border-radius: .85rem;
      background: #f8fbff;
      transition: border-color .2s, background-color .2s, transform .2s;
    }

    .recipe-autocomplete-card:hover,
    .recipe-autocomplete-card.is-active {
      border-color: #93c5fd;
      background: #eff6ff;
      transform: translateY(-1px);
    }

    .recipe-autocomplete-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
    }

    .recipe-autocomplete-name {
      font-size: .92rem;
      font-weight: 700;
      color: #0f172a;
    }

    .recipe-autocomplete-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: .18rem .55rem;
      border-radius: 999px;
      background: #dbeafe;
      color: #1d4ed8;
      font-size: .7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .03em;
      white-space: nowrap;
    }

    .recipe-autocomplete-meta {
      display: flex;
      flex-wrap: wrap;
      gap: .4rem .8rem;
      font-size: .78rem;
      color: #475569;
    }

    .recipe-autocomplete-empty {
      padding: .8rem .9rem;
      border: 1px dashed #bfdbfe;
      border-radius: .85rem;
      color: #64748b;
      font-size: .82rem;
      background: #f8fbff;
    }

    .manual-attention-wrap .field-label {
      color: #b91c1c;
    }

    .manual-attention-field {
      border-color: #fca5a5 !important;
      background: #fff5f5 !important;
      box-shadow: 0 0 0 3px rgba(254, 226, 226, .82) !important;
    }

    .manual-attention-field:hover,
    .manual-attention-field:focus {
      border-color: #dc2626 !important;
      box-shadow: 0 0 0 3px rgba(254, 202, 202, .92) !important;
    }

    @media (max-width: 640px) {
      .selection-helper-item-grid > *,
      .selection-helper-material-grid > * {
        flex: 1 1 100%;
        min-width: 0;
      }

      .tooltip-popover {
        left: .75rem !important;
        right: .75rem !important;
        width: auto;
      }

      .image-modal-panel {
        width: calc(100vw - .5rem);
        height: 86vh;
      }

      .image-modal-toolbar {
        flex-wrap: wrap;
      }
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
$calculatorTooltips = [
  'recipe_item' => [
    'title' => 'Database Recipe Item',
    'body' => "Pilih item recipe dari database agar nama item, material, dan beberapa harga pendukung bisa terisi lebih cepat.\nCocok dipakai kalau item yang ingin dihitung sudah tersedia di library.",
    'image' => '',
  ],
  'recipe_autofill' => [
    'title' => 'Auto Fill',
    'body' => "Setelah memilih item recipe, tekan Load Recipe untuk mengisi field secara otomatis.\nKota bonus local, craft price, market price, dan kota beli akan ikut dipilihkan otomatis bila data tersedia.",
    'image' => '',
  ],
  'rounding_mode' => [
    'title' => 'Rounding Mode',
    'body' => "Spreadsheet: mengikuti pembulatan spreadsheet referensi.\nIn-game: mendekati pembulatan per craft di game.\nJika ingin hasil paling konsisten dengan docs, pakai Spreadsheet.",
  ],
  'item_name' => [
    'title' => 'Nama Item',
    'body' => "Nama item hasil craft yang sedang dihitung.\nContoh: RAMUAN PENYEMBUH MINOR T2.",
    'image' => '/assets/images/nama_item.png',
  ],
  'item_value' => [
    'title' => 'Item Value',
    'body' => "Base item value yang dipakai untuk menghitung tax dan biaya tertentu di Albion.\nBiasanya mengikuti data item / recipe.",
    'image' => '/assets/images/item_value.png',
  ],
  'output_qty' => [
    'title' => 'Output Quantity / Recipe',
    'body' => "Jumlah item jadi yang keluar dari 1 kali craft recipe.\nKalau 1 craft menghasilkan 10 item, isi 10.",
    'image' => '/assets/images/output_quantity.png',
  ],
  'target_output_qty' => [
    'title' => 'Target Jumlah Output',
    'body' => "Target total item jadi yang ingin kamu produksi.\nJika helper tunggangan aktif, nilai ini dihitung dari craft base + bonus craft RRR, lalu dikali output per recipe.",
  ],
  'premium_status' => [
    'title' => 'Premium',
    'body' => "Pilih Yes jika karakter crafting memakai premium.\nStatus ini memengaruhi bonus return rate sesuai aturan perhitungan.",
  ],
  'bonus_basic' => [
    'title' => 'Bonus Basic',
    'body' => "Bonus dasar crafting station dalam persen.\nIsi angka saja, misalnya 18 untuk 18%.",
  ],
  'bonus_local' => [
    'title' => 'Bonus Local',
    'body' => "Bonus tambahan dari kota tertentu.\nKalau tidak ada bonus lokal, isi 0, contoh seperti di gambar kita ingin membuat batu jadi isi 40.",
    'image' => '/assets/images/bonus_local.png',
  ],
  'bonus_local_city' => [
    'title' => 'Kota Bonus Local',
    'body' => "Wajib dipilih bila Bonus Local lebih dari 0.\nGunanya untuk menandai kota sumber bonus tersebut.",
  ],
  'bonus_daily' => [
    'title' => 'Bonus Daily',
    'body' => "Bonus event harian dalam persen.\nKalau tidak ada event bonus, isi 0.",
    'image' => '/assets/images/bonus_daily.png',
  ],
  'usage_fee' => [
    'title' => 'Craft Price',
    'body' => "Biaya penggunaan station per craft recipe.\nIni akan masuk ke total biaya produksi.",
    'image' => '/assets/images/craft_price.png',
  ],
  'craft_fee_city' => [
    'title' => 'Kota Craft Price',
    'body' => "Kota tempat kamu mengambil data biaya craft.\nGunakan kota yang sama dengan lokasi crafting yang akan dipakai.",
  ],
  'craft_with_focus' => [
    'title' => 'Craft With Focus',
    'body' => "Pilih Yes jika ingin simulasi craft memakai focus.\nJika No, field focus bisa dibiarkan 0.",
  ],
  'focus_points' => [
    'title' => 'Focus Points',
    'body' => "Total focus point yang tersedia.\nSistem akan menghitung berapa craft yang bisa dilakukan berdasarkan nilai ini.",
    'image' => '/assets/images/focus_points.png',
  ],
  'focus_per_craft' => [
    'title' => 'Focus per Craft',
    'body' => "Biaya focus untuk 1 kali craft recipe.\nPastikan nilainya tidak lebih besar dari total focus point.",
    'image' => '/assets/images/focus_percraft.png',
  ],
  'sell_price' => [
    'title' => 'Market Price',
    'body' => "Harga jual aktual yang ingin kamu simulasi di market.\nProfit, margin, dan status utama dihitung dari angka ini. Usahkan dibawah harga normal agar bisa bersaing contohnya digambar 3.663 kamu taruh harga 3.600",
    'image' => '/assets/images/market_price.png',
  ],
  'sell_price_city' => [
    'title' => 'Kota Market Price',
    'body' => "Kota tempat harga jual market diambil.\nPilih kota yang sama dengan target jual agar perbandingan lebih realistis.",
  ],
  'materials' => [
    'title' => 'Materials',
    'body' => "Isi satu baris per material yang dibutuhkan recipe.\nQty adalah kebutuhan per recipe, Harga adalah harga beli satuan kalian cek saja di market, dan RR menentukan apakah material mendapat return atau tidak. seperti contoh di gambar perlu 2 material jadi cukup 2 baris saja di input",
    'image' => '/assets/images/material.png',
  ],
];
if (! function_exists('renderCalculatorLabel')) {
  /**
   * @param array<string, array<string, string>> $tooltips
   */
  function renderCalculatorLabel(string $text, string $key, array $tooltips): void
  {
    $tip = $tooltips[$key] ?? null;
    echo '<span class="field-label field-label-wrap">';
    echo '<span>' . htmlspecialchars($text) . '</span>';
    if (is_array($tip)) {
      $title = htmlspecialchars((string) ($tip['title'] ?? $text));
      $body = htmlspecialchars((string) ($tip['body'] ?? ''));
      $image = htmlspecialchars((string) ($tip['image'] ?? ''));
      echo '<button class="field-help-trigger" type="button" aria-label="Bantuan ' . htmlspecialchars($text) . '" data-tooltip-title="' . $title . '" data-tooltip-body="' . $body . '" data-tooltip-image="' . $image . '">i</button>';
    }
    echo '</span>';
  }
}
require dirname(__DIR__) . '/partials/auth-shell-start.php';
?>
<section class="page-header">
  <h1 class="page-title">Quick Calculator</h1>
</section>

<section class="widgets">
  <article class="widget">
    <div class="widget-title">Total Profit</div>
    <div class="widget-value" id="calc-hero-total-profit">-</div>
    <div class="widget-muted" id="calc-hero-total-profit-note">Belum ada hasil kalkulasi</div>
  </article>
  <article class="widget">
    <div class="widget-title">Profit / Item</div>
    <div class="widget-value" id="calc-hero-profit-item">-</div>
    <div class="widget-muted" id="calc-hero-profit-item-note">Net per item setelah tax + setup fee</div>
  </article>
  <article class="widget">
    <div class="widget-title">Margin</div>
    <div class="widget-value" id="calc-hero-margin">-</div>
    <div class="widget-muted" id="calc-hero-margin-note">Status profit akan tampil setelah hitung</div>
  </article>
  <article class="widget">
    <div class="widget-title">Plan</div>
    <div class="widget-value"><?= htmlspecialchars($currentPlanCode) ?></div>
    <div class="widget-muted"><?= htmlspecialchars($currentPlanName . ' · ' . $currentUserLabel) ?></div>
  </article>
</section>

<section class="card animate-fade-in-up" id="selection-helper-card" hidden>
  <div class="card-title-row">
    <div>
      <h2 class="card-title">Bantu Seleksi Item</h2>
      <p class="selection-helper-note">Isi nama item, item value, output quantity per recipe, nama material, recipe/qty, bobot per item (kg), dan return type. Bandingkan data per kota, lalu tombol push akan memilih harga terbaik dan menghitung craft base, bonus craft dari RRR, serta target output aman bila mode tunggangan aktif.</p>
    </div>
    <button class="button button-ghost" type="button" id="close-selection-helper-btn">Kembali ke Input</button>
  </div>
  <div class="selection-helper-config">
    <button class="button button-secondary" type="button" id="helper-toggle-mount-btn">Tunggangan</button>
    <div class="selection-helper-mount-fields" id="helper-mount-fields" hidden>
      <label class="field">
        <span class="field-label">Muatan Maks Tunggangan</span>
        <input class="input" type="number" step="0.01" min="0" id="helper-mount-capacity" placeholder="Contoh: 1370">
      </label>
      <label class="field">
        <span class="field-label">Kapasitas Tambahan Karakter / Tas</span>
        <input class="input" type="number" step="0.01" min="0" id="helper-mounted-extra-capacity" placeholder="Opsional: contoh 223">
      </label>
    </div>
  </div>
  <div id="selection-helper-names" class="selection-helper-meta"></div>
  <div class="table-wrap">
    <table class="table selection-helper-table">
      <thead id="selection-helper-head"></thead>
      <tbody id="selection-helper-body"></tbody>
    </table>
  </div>
  <div id="selection-helper-summary" class="alert selection-helper-summary" hidden></div>
  <div class="actions selection-helper-actions">
    <div class="selection-helper-inline-actions">
      <button class="button button-secondary" type="button" id="helper-add-city-btn">Tambah Kota</button>
      <button class="button button-ghost" type="button" id="helper-add-material-btn">Tambah Material</button>
      <button class="button button-ghost" type="button" id="helper-clear-btn">Clear</button>
    </div>
    <button class="button button-primary" type="button" id="helper-push-btn">Push Input Parameters</button>
  </div>
</section>

<section class="card animate-fade-in-up" id="input-parameters-card">
  <div class="card-title-row">
    <h2 class="card-title">Input Parameters</h2>
    <button class="button button-secondary" type="button" id="open-selection-helper-btn">Bantu Seleksi Item</button>
  </div>

  <form id="calc-form" class="form">
    <input type="hidden" name="item_id" value="">
    <input type="hidden" name="recipe_city_id" value="">
    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">

    <?php if ($canUseRecipeDatabase): ?>
      <div class="card-subtitle">Recipe Database & Rekomendasi Kota</div>
      <div class="grid">
        <label class="field xl:col-span-3">
          <?php renderCalculatorLabel('Database Recipe Item', 'recipe_item', $calculatorTooltips); ?>
          <input type="hidden" id="recipe-item-id" name="recipe_item_id" value="">
          <div class="recipe-autocomplete-shell">
            <input class="input" id="recipe-item-search" name="recipe_item_label" type="text" value="" placeholder="Ketik nama item recipe..." autocomplete="off" spellcheck="false">
            <div class="recipe-autocomplete-list" id="recipe-item-autocomplete" hidden></div>
          </div>
        </label>
        <div class="field justify-end">
          <?php renderCalculatorLabel('Auto Fill', 'recipe_autofill', $calculatorTooltips); ?>
          <button class="button button-secondary" type="button" id="recipe-autofill-btn">Load Recipe</button>
        </div>
      </div>
    <?php endif; ?>

    <div class="calculator-inline-grid">
      <label class="field">
        <?php renderCalculatorLabel('Rounding Mode', 'rounding_mode', $calculatorTooltips); ?>
        <select class="select" name="return_rounding_mode">
          <option value="SPREADSHEET_BULK" selected>Spreadsheet (Default)</option>
          <option value="INGAME_PER_CRAFT">In-game (Per Craft)</option>
        </select>
      </label>
    </div>

    <div class="calculator-inline-grid cols-4 cols-4-item-main">
      <label class="field">
        <?php renderCalculatorLabel('Nama Item', 'item_name', $calculatorTooltips); ?>
        <input class="input" name="item_name" type="text" value="" placeholder="Contoh: BELATI PENGEMBARA T4.1" autocomplete="off" required>
      </label>
      <label class="field">
        <?php renderCalculatorLabel('Item Value', 'item_value', $calculatorTooltips); ?>
        <input class="input" name="item_value" type="number" step="0.01" value="64">
      </label>
      <label class="field">
        <?php renderCalculatorLabel('Output Quantity / Recipe', 'output_qty', $calculatorTooltips); ?>
        <input class="input" name="output_qty" type="number" step="1" value="1" min="1">
      </label>
      <label class="field">
        <?php renderCalculatorLabel('Target Jumlah Output', 'target_output_qty', $calculatorTooltips); ?>
        <input class="input" name="target_output_qty" type="number" step="1" value="100" min="1">
      </label>
    </div>

    <div class="calculator-inline-grid cols-5">
      <label class="field">
        <?php renderCalculatorLabel('Premium', 'premium_status', $calculatorTooltips); ?>
        <select class="select" name="premium_status">
          <option value="0" selected>No</option>
          <option value="1">Yes</option>
        </select>
      </label>
      <label class="field">
        <?php renderCalculatorLabel('Bonus Basic', 'bonus_basic', $calculatorTooltips); ?>
        <input class="input" name="bonus_basic" type="number" step="0.01" value="18">
      </label>
      <label class="field">
        <?php renderCalculatorLabel('Bonus Local', 'bonus_local', $calculatorTooltips); ?>
        <input class="input" name="bonus_local" type="number" step="0.01" value="0">
      </label>
      <label class="field">
        <?php renderCalculatorLabel('Kota Bonus Local', 'bonus_local_city', $calculatorTooltips); ?>
        <select class="select" id="bonus-local-city-select" name="bonus_local_city_id">
          <option value="">Tidak ada kota bonus local</option>
          <?php foreach (($recipe_cities ?? []) as $city): ?>
            <option value="<?= (int) ($city['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($city['name'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">
        <?php renderCalculatorLabel('Bonus Daily', 'bonus_daily', $calculatorTooltips); ?>
        <input class="input" name="bonus_daily" type="number" step="0.01" value="0">
      </label>
    </div>

    <div class="calculator-inline-grid cols-5">
      <label class="field">
        <?php renderCalculatorLabel('Craft Price', 'usage_fee', $calculatorTooltips); ?>
        <input class="input" name="usage_fee" type="number" step="0.01" value="200">
      </label>
      <label class="field">
        <?php renderCalculatorLabel('Kota Craft Price', 'craft_fee_city', $calculatorTooltips); ?>
        <select class="select" id="craft-fee-city-select" name="craft_fee_city_id">
          <option value="">Pilih kota craft fee</option>
          <?php foreach (($recipe_cities ?? []) as $city): ?>
            <option value="<?= (int) ($city['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($city['name'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">
        <?php renderCalculatorLabel('Craft With Focus', 'craft_with_focus', $calculatorTooltips); ?>
        <select class="select" name="craft_with_focus">
          <option value="0" selected>No</option>
          <option value="1">Yes</option>
        </select>
      </label>
      <label class="field">
        <?php renderCalculatorLabel('Focus Points', 'focus_points', $calculatorTooltips); ?>
        <input class="input" name="focus_points" type="number" step="1" value="0" min="0">
      </label>
      <label class="field">
        <?php renderCalculatorLabel('Focus per Craft', 'focus_per_craft', $calculatorTooltips); ?>
        <input class="input" name="focus_per_craft" type="number" step="1" value="0" min="0">
      </label>
    </div>
    <div id="recipe-recommendations" class="alert" hidden></div>

    <div class="calculator-inline-grid cols-5">
      <label class="field">
        <?php renderCalculatorLabel('Market Price', 'sell_price', $calculatorTooltips); ?>
        <input class="input" name="sell_price" type="number" step="0.01" value="" min="0" placeholder="Masukkan harga jual" required>
      </label>
      <label class="field">
        <?php renderCalculatorLabel('Kota Market Price', 'sell_price_city', $calculatorTooltips); ?>
        <select class="select" id="sell-price-city-select" name="sell_price_city_id">
          <option value="">Pilih kota market price</option>
          <?php foreach (($recipe_cities ?? []) as $city): ?>
            <option value="<?= (int) ($city['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($city['name'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <div class="card-subtitle"><?php renderCalculatorLabel('Materials', 'materials', $calculatorTooltips); ?></div>
    <div class="materials-shell">
      <div class="materials-scroll">
        <div class="materials-header">
          <div>Nama Item</div>
          <div>Item Value</div>
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
      <button class="button button-ghost" type="button" id="clear-local">Clear</button>
      <button class="button button-primary" type="submit" id="calc-submit">Hitung</button>
    </div>
    <div id="calc-error" class="alert alert-error" hidden></div>
  </form>
</section>

<section class="card animate-fade-in-up">
  <h2 class="card-title">Result</h2>
  <div id="analysis-recommendation" class="analysis-recommendation" hidden>
    <div class="analysis-recommendation-title">Rekomendasi Hasil Analisa</div>
    <div id="analysis-recommendation-list" class="analysis-recommendation-list"></div>
  </div>
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
          <th>AKSI</th>
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

<div class="tooltip-popover" id="calc-tooltip-popover" aria-hidden="true">
  <div class="tooltip-popover-title" id="calc-tooltip-title">Info</div>
  <div class="tooltip-popover-body" id="calc-tooltip-body"></div>
  <div class="tooltip-popover-actions">
    <button class="tooltip-preview-button" type="button" id="calc-tooltip-preview" hidden>Lihat gambar</button>
    <div class="tooltip-note">Klik di luar untuk menutup</div>
  </div>
</div>

<div class="modal" id="tooltip-image-modal" aria-hidden="true">
  <div class="modal-backdrop" id="tooltip-image-backdrop"></div>
  <div class="image-modal-panel">
    <div class="image-modal-header">
      <div class="image-modal-meta">
        <div class="image-modal-title" id="tooltip-image-title">Panduan</div>
        <div class="image-modal-hint">Scroll / pinch untuk zoom, drag untuk geser, `Esc` untuk tutup.</div>
      </div>
      <button class="button button-ghost" type="button" id="tooltip-image-close">Close</button>
    </div>
    <div class="image-modal-stage" id="tooltip-image-stage">
      <div class="image-modal-figure" id="tooltip-image-figure">
        <img id="tooltip-image-preview" src="" alt="Panduan pengisian kalkulator">
      </div>
    </div>
    <div class="image-modal-toolbar">
      <button class="button button-ghost" type="button" id="tooltip-image-zoom-out">Zoom Out</button>
      <button class="button button-ghost" type="button" id="tooltip-image-reset">Reset</button>
      <button class="button button-secondary" type="button" id="tooltip-image-zoom-in">Zoom In</button>
    </div>
  </div>
</div>

<script id="calculator-cities-data" type="application/json">
  <?= json_encode(array_values($recipe_cities ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<script id="calculator-tooltip-map" type="application/json">
  <?= json_encode($calculatorTooltips, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<script src="/assets/calculator.js?v=20260331-07"></script>
<?php require dirname(__DIR__) . '/partials/auth-shell-end.php'; ?>
