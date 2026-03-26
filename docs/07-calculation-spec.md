# Spesifikasi Kalkulasi

## Ringkasan
Dokumen ini menjelaskan perilaku kalkulasi yang harus dipetakan ke backend. Ini adalah dokumen naratif dan teknis yang menjembatani spreadsheet Famouzak free ke engine PHP strict.

Dokumen ini tidak boleh dibaca terpisah dari `docs/11-calculation-engine-strict-rules.md`. Jika ada konflik, versi final strict rules yang sudah diselaraskan menjadi sumber implementasi langsung.

## Referensi Utama
- Spreadsheet Famouzak free:
  - RRR: `1 - 1 / (1 + total_bonus / 100)`
  - Craft fee: `usage_fee x item_value x output_qty / 20 / (400/9)`
  - Tax premium 4%, non-premium 8%
  - Setup fee 2.5%
  - SRP harus memperhitungkan tax dan setup fee

## Input Domain
### Item Context
- `item_value`
- `output_qty`
- `usage_fee`
- `sell_price` (opsional, ini adalah **Market Price** di UI)

### Craft Context
- `target_output_qty`
- `premium_status`
- `craft_with_focus`
- `focus_points`
- `focus_per_craft`

### Bonus Context
- `bonus_basic`
- `bonus_local`
- `bonus_daily`
- `bonus_focus`

### Materials
Setiap material harus memiliki:
- `name`
- `qty_per_recipe`
- `buy_price`
- `return_type`

## Aturan Bonus
- Bonus basic default: 18
- Bonus local: tergantung kota atau manual input
- Bonus daily: manual input
- Bonus focus: 59 jika focus aktif, selain itu 0

## Total Bonus
`total_bonus = bonus_basic + bonus_local + bonus_daily + bonus_focus`

## RRR
`rrr = 1 - 1 / (1 + (total_bonus / 100))`

Catatan:
- Jangan dibulatkan terlalu cepat.
- Nilai ini dipakai untuk menghitung return material value.

## Material Return vs Non Return
Material berikut dianggap `NON_RETURN` secara domain:
- Artifact
- Heart
- Crest
- Sigil
- Avalonian Energy
- Rare Fish
- Item hasil crafting yang menjadi bahan craft lain

Namun untuk engine, keputusan final tetap memakai `return_type` yang dikirim atau yang tersimpan pada recipe. Engine tidak menginfer kategori item secara otomatis pada fase awal.

## Focus Logic
Jika focus aktif dan `focus_per_craft > 0`:
- `focus_craft_limit = floor(focus_points / focus_per_craft)`
- `craft_count` dibatasi `focus_craft_limit` pada saat simulasi iterasi.

Jika focus nonaktif:
- `focus_craft_limit = 0`
- simulasi tidak dibatasi focus.

Jika focus aktif tetapi `focus_per_craft <= 0`:
- dianggap invalid untuk strict mode
- engine harus stop dan return error

## Target Output dan Simulasi Spreadsheet
Untuk menyamai spreadsheet, `target_output_qty` adalah target jumlah item hasil (bukan jumlah craft).

Spreadsheet menghitung `material_to_buy` lebih dulu, lalu melakukan simulasi iterasi craft untuk mendapatkan `craft_count` aktual dan `total_output` aktual.

## Total Output
`total_output = craft_count x output_qty`

## Material Cost
Spreadsheet-style:
`material_cost = sum(material_to_buy x buy_price)`

## Material Return Value
Spreadsheet-style:
`material_return_value = sum(leftover_qty x buy_price)` dari sisa material setelah simulasi iterasi.

## Net Material Cost
`net_material_cost = material_cost - material_return_value`

## Craft Fee
### Per recipe
`craft_fee_per_recipe = (usage_fee x item_value x output_qty) / 20 / (400 / 9)`

### Total
`craft_fee_total = craft_fee_per_recipe x craft_count`

## Production Cost
- `production_cost = net_material_cost + craft_fee_total`
- `production_cost_per_item = production_cost / total_output`

## Tax dan Setup Fee
### Tax
- Premium: 4%
- Non-premium: 8%

### Setup Fee
- Tetap 2.5%

### Revenue per item
- `revenue_per_item = sell_price - (sell_price x tax_percent) - (sell_price x 2.5%)`

### Total revenue
- `total_revenue = revenue_per_item x total_output`

## Profit
- `profit_per_item = revenue_per_item - production_cost_per_item`
- `total_profit = total_revenue - production_cost`

## Margin
- `margin_percent = (profit_per_item / production_cost_per_item) x 100`

## SRP
Spreadsheet free menghitung SRP sebagai harga jual rekomendasi yang sudah memperhitungkan tax dan setup fee. Karena itu formula utama bukan markup sederhana.

Formula final:
`srp(target_margin) = production_cost_per_item x (1 + target_margin) / (1 - tax_percent - setup_fee_percent)`

Target margin:
- `srp_5`
- `srp_10`
- `srp_15`

Catatan implementasi (penting untuk UI):
- Jika `sell_price` (Market Price) tidak diisi, engine tetap menghitung SRP dan **selalu menyediakan** object `scenario` dengan mode `SRP_10_DEFAULT` (asumsi harga jual = `srp_10`) agar user tetap bisa melihat `profit`, `margin`, `status`.
- Jika `sell_price` diisi, `scenario.mode = MARKET` dan `scenario.*` mencerminkan profit/margin pada market tersebut.
- Field market seperti `profit_per_item`, `total_profit`, `margin_percent` bisa bernilai `null` jika `sell_price` tidak diisi (karena bukan market calculation).

## Return Rounding Mode
Engine mendukung pilihan mode rounding return:
- `SPREADSHEET_BULK` (default): meniru perilaku spreadsheet Famouzak free (bulk-per-iterasi).
- `INGAME_PER_CRAFT`: simulasi return per craft (lebih granular untuk mendekati pembulatan in-game).

Golden test `docs/12` mengunci akurasi untuk `SPREADSHEET_BULK`.

## Rounding
### Prinsip
- Precision internal dijaga tinggi.
- Output API final ditampilkan 2 desimal kecuali field integer.
- Field integer:
  - `effective_craft_count`
  - `focus_craft_limit`
  - `total_output`

### Otoritas rounding
- Jika nanti ditemukan perbedaan antara pembulatan generik dan perilaku spreadsheet/Excel, perilaku spreadsheet harus menang.
- Dokumen strict akan menetapkan aturan final per field.

## Acceptance Cases Minimum
- Refining Leather T3 dengan bonus basic 18 dan local 40.
- Potion sample dengan output 10.
- Equipment sample dengan campuran return dan non-return.
- Focus on vs focus off.
- Premium vs non-premium.
- SRP 5/10/15 net-aware.
