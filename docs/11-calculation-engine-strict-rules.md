# Calculation Engine Strict Rules

## Tujuan
Dokumen ini adalah single source of truth untuk implementasi calculation engine backend. Semua implementasi wajib mengikuti urutan, formula, validasi, dan aturan rounding di bawah ini. Jika ada hasil backend yang berbeda dari dokumen ini, maka itu dianggap bug.

Dokumen ini sudah diselaraskan dengan spreadsheet Famouzak free sebagai referensi utama.

## 1. Urutan Perhitungan
Urutan ini wajib dan tidak boleh diubah.

1. Validasi input
2. Tentukan bonus focus
3. Hitung total bonus
4. Hitung RRR
5. Hitung focus limit
6. Hitung estimasi material to buy (CEILING)
7. Simulasi iterasi craft (bulk per iterasi)
8. Hitung total output aktual
9. Hitung material cost dari material to buy
10. Hitung material return value dari sisa material
11. Hitung net material cost
12. Hitung craft fee per recipe
13. Hitung craft fee total (berdasarkan craft_count aktual)
14. Hitung production cost
15. Hitung production cost per item
16. Tentukan tax dan setup fee
17. Hitung revenue per item
18. Hitung total revenue
19. Hitung profit
20. Hitung margin
21. Hitung SRP 5, 10, 15
22. Format output final

## 2. Validasi Input
Semua input harus lolos validasi sebelum ada kalkulasi.

### Aturan umum
- Null tidak boleh dipakai sebagai nilai hitung.
- Nilai kosong di-normalisasi menjadi `0` hanya untuk field yang memang boleh nol.
- Nilai negatif tidak diperbolehkan.
- Jika invalid, engine harus stop dan return error terstruktur.

### Field numerik
- `bonus_basic`: minimum 0, maksimum 100
- `bonus_local`: minimum 0, maksimum 100
- `bonus_daily`: minimum 0, maksimum 100
- `usage_fee`: minimum 0
- `item_value`: minimum 0
- `output_qty`: minimum 1
- `target_output_qty`: minimum 1
- `sell_price`: opsional, jika diisi minimum 0 (Market Price)
- `focus_points`: minimum 0
- `focus_per_craft`: minimum 0

### Material
Setiap material wajib memiliki:
- `name` string non-empty
- `qty_per_recipe >= 0`
- `buy_price >= 0`
- `return_type` hanya `RETURN` atau `NON_RETURN`

### Edge validation
- Jika `craft_with_focus = true` dan `focus_per_craft <= 0`, engine harus stop dan return error.
- Jika `materials` kosong, engine harus stop dan return error.
- Jika `total_output` nanti menjadi 0, engine harus stop dan return error.

## 3. Bonus Focus
- Jika `craft_with_focus = true`, maka `bonus_focus = 59`.
- Jika `craft_with_focus = false`, maka `bonus_focus = 0`.

Nilai `bonus_focus` tidak boleh diinput bebas oleh user pada strict mode.

## 4. Total Bonus
Formula:

```text
total_bonus = bonus_basic + bonus_local + bonus_daily + bonus_focus
```

Default:
- `bonus_basic = 18` bila tidak diisi eksplisit.

## 5. RRR
Formula wajib:

```text
rrr = 1 - 1 / (1 + (total_bonus / 100))
```

Aturan:
- Gunakan precision internal tinggi.
- Jangan bulatkan agresif di tahap ini.
- `rrr > 0.9` tetap dianggap valid.

## 6. Focus Limit
Jika focus aktif:

```text
focus_craft_limit = floor(focus_points / focus_per_craft)
```

Jika focus nonaktif:

```text
focus_craft_limit = 0
```

Aturan:
- `focus_craft_limit` integer.
- Jika `craft_with_focus = true` dan `focus_per_craft <= 0`, stop dan return error.

## 7. Estimasi Material To Buy (Spreadsheet)
Definisi:
- Target input adalah `target_output_qty` (jumlah item output yang diinginkan), bukan jumlah craft.
- Kebutuhan craft dalam bentuk float:

```text
crafts_needed_float = target_output_qty / output_qty
```

Untuk setiap material:

```text
material_to_buy = CEILING(qty_per_recipe * crafts_needed_float * multiplier)
multiplier = (1 - rrr) jika RETURN, else 1
```

Aturan:
- `CEILING` ke integer (minimal 0).
- Inilah alasan hasil bisa berbeda dari pendekatan "qty * craft_count" biasa.

## 8. Simulasi Iterasi Craft (Bulk per Iterasi)
Mulai dengan `stock = material_to_buy` untuk setiap material.

Loop:
1. Hitung `possible_crafts` = minimum dari `floor(stock_i / qty_i)` untuk semua material dengan `qty_i > 0`.
2. Jika focus aktif, `possible_crafts` dibatasi oleh sisa focus: `focus_craft_limit - craft_count_so_far`.
3. Jika `possible_crafts <= 0`, stop.
4. Untuk setiap material:
   - `consumed = qty_per_recipe * possible_crafts`
   - `returned = ROUND_HALF_UP(consumed * rrr)` jika RETURN, else 0
   - `stock = stock - consumed + returned`
5. Tambahkan `craft_count += possible_crafts`

Aturan rounding:
- `ROUND_HALF_UP` ke integer, meniru perilaku Excel rounding pada spreadsheet.

## 9. Total Output Aktual
```text
total_output = craft_count * output_qty
```

Jika `total_output = 0`, stop dan return error.

## 10. Material Cost (dari Material To Buy)
```text
material_cost = SUM(material_to_buy * buy_price)
```

## 11. Material Return Value (dari Sisa Material)
```text
material_return_value = SUM(leftover_stock * buy_price)
```

Catatan:
- Ini bukan "RRR x cost" seperti pendekatan continuous.
- Ini menghitung nilai material sisa setelah simulasi iterasi.

## 12. Net Material Cost
```text
net_material_cost = material_cost - material_return_value
```

## 13. Craft Fee
Formula wajib persis:

```text
craft_fee_per_recipe = (usage_fee * item_value * output_qty) / 20 / (400 / 9)
craft_fee_total = craft_fee_per_recipe * craft_count
```

Aturan:
- Formula tidak boleh disederhanakan atau diubah urutannya.
- `craft_fee_per_recipe` boleh bernilai desimal.

## 14. Production Cost
Formula:

```text
production_cost = net_material_cost + craft_fee_total
production_cost_per_item = production_cost / total_output
```

Aturan:
- Jika `total_output = 0`, proses harus sudah dihentikan lebih awal.

## 15. Tax dan Setup Fee
### Tax
- Premium: 4%
- Non-premium: 8%

Formula:

```text
tax_value_per_item = sell_price * tax_percent
```

### Setup fee
- Tetap 2.5%

Formula:

```text
setup_fee_value_per_item = sell_price * 0.025
```

## 16. Revenue
Formula:

```text
revenue_per_item = sell_price - tax_value_per_item - setup_fee_value_per_item
total_revenue = revenue_per_item * total_output
```

Aturan:
- `sell_price = 0` tetap valid.
- Dalam kondisi itu revenue menjadi 0 dan profit kemungkinan negatif.

## 17. Profit
Formula:

```text
profit_per_item = revenue_per_item - production_cost_per_item
total_profit = total_revenue - production_cost
```

## 18. Margin
Formula:

```text
margin_percent = (profit_per_item / production_cost_per_item) * 100
```

Aturan:
- Jika `production_cost_per_item <= 0`, engine harus return error karena margin tidak valid secara bisnis.

## 19. SRP
SRP strict harus sudah memperhitungkan tax dan setup fee. Formula utama bukan markup sederhana.

Formula final:

```text
srp = production_cost_per_item * (1 + target_margin) / (1 - tax_percent - setup_fee_percent)
```

Target margin:
- `srp_5` memakai `0.05`
- `srp_10` memakai `0.10`
- `srp_15` memakai `0.15`

Aturan:
- `tax_percent` pada formula dinyatakan sebagai bentuk desimal, misalnya premium `0.04`.
- `setup_fee_percent` tetap `0.025`.
- SRP wajib net-aware dan tidak boleh diganti markup sederhana.

## 20. Rounding Rules
### Prinsip dasar
- Simpan precision internal setinggi mungkin selama kalkulasi.
- Untuk output API dan tampilan UI, gunakan 2 digit desimal.
- Field integer tetap integer.

### Field integer
- `focus_craft_limit`
- `effective_craft_count`
- `total_output`

### Otoritas rounding
- Jika perilaku spreadsheet/Excel pada kasus uji menunjukkan pembulatan step tertentu berbeda dari pendekatan generik 4 desimal, implementasi backend wajib mengikuti perilaku spreadsheet.
- Dengan kata lain, spreadsheet adalah rounding authority final.

### Aturan praktis implementasi
- Jangan bulatkan `rrr`, `material_cost`, `material_return_value`, `craft_fee_total`, atau `production_cost` di tengah proses kecuali dibutuhkan secara eksplisit untuk meniru spreadsheet.
- Pembulatan presentasi dilakukan pada response akhir.

### Return Rounding Mode
Engine strict mendukung 2 mode rounding return:

1. `SPREADSHEET_BULK` (default)
- Mengikuti Step 8 pada dokumen ini persis: satu iterasi bisa melakukan banyak craft sekaligus (`possible_crafts`), lalu return dihitung dari total consumed per iterasi dan dibulatkan integer `ROUND_HALF_UP`.
- Mode ini yang dipakai untuk golden tests `docs/12`.

2. `INGAME_PER_CRAFT`
- Tujuan: lebih mendekati pembulatan in-game dengan menghitung return **per craft**.
- Implementasi: pada Step 8, alih-alih menghitung return dari total consumed per iterasi, lakukan loop `possible_crafts` kali:
  - untuk setiap craft: `returned = ROUND_HALF_UP(qty_per_recipe * rrr)` jika RETURN, lalu `stock = stock - qty + returned`
- Catatan: ini adalah pendekatan "lebih granular", tetapi tidak dijamin 100% identik dengan in-game pada semua kondisi karena faktor rounding internal game bisa berbeda.

## 21. Larangan
Dilarang:
- Mengubah urutan langkah.
- Menggabungkan langkah sehingga sulit diaudit.
- Melewati material return logic.
- Mengubah formula craft fee.
- Mengganti SRP menjadi markup sederhana.
- Membulatkan agresif di tengah proses tanpa dasar perilaku spreadsheet.

## 22. Output Wajib API
Response minimum wajib memuat:
- `rrr`
- `craft_fee_total`
- `material_cost`
- `material_return_value`
- `production_cost`
- `production_cost_per_item`
- `total_output`
- `profit_per_item` (nullable jika Market Price kosong)
- `total_profit` (nullable jika Market Price kosong)
- `margin_percent` (nullable jika Market Price kosong)
- `srp_5`
- `srp_10`
- `srp_15`
- `focus_craft_limit`

Wajib juga memuat (agar UI selalu punya profit/margin/status walau Market Price kosong):
- `scenario.mode` = `MARKET` atau `SRP_10_DEFAULT`
- `scenario.sell_price`
- `scenario.revenue_per_item`
- `scenario.profit_per_item`
- `scenario.total_profit`
- `scenario.margin_percent`

Aturan scenario:
- Jika `sell_price` diinput, maka `scenario.mode = MARKET` dan `scenario.*` dihitung dari `sell_price` tersebut.
- Jika `sell_price` kosong, maka `scenario.mode = SRP_10_DEFAULT` dan `scenario.sell_price = srp_10`.

Disarankan juga menambahkan:
- `craft_count`
- `craft_fee_per_recipe`
- `net_material_cost`
- `revenue_per_item` (nullable)
- `total_revenue` (nullable)
- `tax_percent`
- `setup_fee_percent`
- `materials[].material_to_buy`
- `materials[].leftover_qty`
 - `break_even_sell_price`
 - `srp_20`
 - `profit_targets[]` untuk margin 5/10/15/20
 - `return_rounding_mode`
 - `iterations[]` trace iterasi (maks 20, meniru spreadsheet)
 - `material_fields` untuk tabel Field/Material1..6

## 23. Acceptance Rule
- Hasil backend harus cocok dengan sample case dokumentasi.
- Deviasi maksimum yang diterima: 0.1%
- Jika lebih dari itu, status dianggap bug dan wajib diperbaiki.

## 24. Penutup
Dokumen ini adalah acuan utama implementasi calculation engine. Jika kode backend berbeda dari dokumen ini, maka kode yang salah dan harus disesuaikan.
