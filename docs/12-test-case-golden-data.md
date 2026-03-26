# Test Case Golden Data (Spreadsheet-Synced)

## Tujuan
Dokumen ini berisi test case numerik sebagai pembanding hasil `CalculationEngineService`.

Semua angka pada dokumen ini diselaraskan dengan:
- `docs/11-calculation-engine-strict-rules.md` (mode simulasi spreadsheet)
- Perilaku spreadsheet Famouzak free (material_to_buy + simulasi iterasi + rounding integer)

Jika hasil backend berbeda lebih dari 0.1% (atau berbeda secara integer pada output integer), engine dianggap bug.

## Aturan Umum Test
- Semua perhitungan mengikuti `docs/11`.
- Target input adalah `target_output_qty` (jumlah item output yang diinginkan), bukan jumlah craft.
- Material yang dibeli dihitung dengan `CEILING` (integer).
- Return material dihitung lewat simulasi iterasi (bulk per iterasi) dengan rounding `ROUND_HALF_UP` ke integer.
- Output uang ditampilkan sebagai Rupiah di UI, tetapi test backend membandingkan nilai numerik.

## Test Case 1: Refining Leather T3
### Input
```text
bonus_basic = 18
bonus_local = 40
bonus_daily = 0
craft_with_focus = false

usage_fee = 200
item_value = 64
output_qty = 1
target_output_qty = 100

sell_price = 300
premium_status = true

materials:
- Hide T3: qty_per_recipe = 2, buy_price = 100, return_type = RETURN
- Leather T2: qty_per_recipe = 1, buy_price = 80, return_type = RETURN
```

### Expected Output
```text
rrr ~ 0.3670886076
craft_count = 99
total_output = 99
material_cost = 17820.00
material_return_value = 200.00
net_material_cost = 17620.00
craft_fee_total = 1425.60
production_cost = 19045.60
production_cost_per_item = 192.38
revenue_per_item = 280.50
profit_per_item = 88.12
total_profit = 8723.90
margin_percent = 45.81
```

## Test Case 2: Potion Output x10
### Input
```text
bonus_basic = 18
bonus_local = 15
bonus_daily = 10
craft_with_focus = false

usage_fee = 250
item_value = 180
output_qty = 10
target_output_qty = 500

sell_price = 1200
premium_status = true

materials:
- Teasel: qty_per_recipe = 4, buy_price = 200, return_type = RETURN
- Goose Egg: qty_per_recipe = 2, buy_price = 150, return_type = NON_RETURN
```

### Expected Output
```text
rrr ~ 0.3006993007
craft_count = 49
total_output = 490
material_cost = 43000.00
material_return_value = 900.00
net_material_cost = 42100.00
craft_fee_total = 24806.25
production_cost = 66906.25
production_cost_per_item = 136.54
revenue_per_item = 1122.00
profit_per_item = 985.46
total_profit = 482873.75
margin_percent = 721.72
```

## Test Case 3: Equipment dengan Non Return Material
### Input
```text
bonus_basic = 18
bonus_local = 15
bonus_daily = 0
craft_with_focus = false

usage_fee = 300
item_value = 240
output_qty = 1
target_output_qty = 100

sell_price = 5000
premium_status = true

materials:
- Leather T3: qty_per_recipe = 8, buy_price = 200, return_type = RETURN
- Artifact: qty_per_recipe = 1, buy_price = 2000, return_type = NON_RETURN
```

### Expected Output
```text
rrr ~ 0.2481203008
craft_count = 99
total_output = 99
material_cost = 320400.00
material_return_value = 3400.00
net_material_cost = 317000.00
craft_fee_total = 8019.00
production_cost = 325019.00
production_cost_per_item = 3283.02
revenue_per_item = 4675.00
profit_per_item = 1391.98
total_profit = 137806.00
margin_percent = 42.40
```

## Test Case 4: Focus Enabled
### Input
```text
bonus_basic = 18
bonus_local = 15
bonus_daily = 10
craft_with_focus = true

focus_points = 30000
focus_per_craft = 5000

usage_fee = 200
item_value = 180
output_qty = 1
target_output_qty = 20

sell_price = 2000
premium_status = true

materials:
- Material A: qty_per_recipe = 5, buy_price = 300, return_type = RETURN
```

### Expected Output
```text
focus_craft_limit = 6
craft_count = 6
total_output = 6
rrr ~ 0.5049504950
material_cost = 15000.00
material_return_value = 10500.00
production_cost = 4743.00
```

## Test Case 5: Non Premium Tax
### Input
```text
sell_price = 1000
premium_status = false
```

### Expected Output
```text
tax_percent = 8%
setup_fee_percent = 2.5%
revenue_per_item = 895.00
```

## Test Case 6: SRP Validation
### Input
```text
production_cost_per_item = 100
tax_percent = 4%
setup_fee_percent = 2.5%
target_margin = 10%
```

### Expected Output
```text
srp ~ 117.65
```

## Test Case 7: Spreadsheet Screenshot Like (Cocok Dengan Contoh Anda)
### Input
```text
bonus_basic = 18
bonus_local = 30
bonus_daily = 0
craft_with_focus = false

usage_fee = 300
item_value = 240
output_qty = 5
target_output_qty = 20

sell_price = 1850
premium_status = true

materials:
- Crenellated Burdock 4: qty_per_recipe = 24, buy_price = 300, return_type = RETURN
- Telur Ayam 3: qty_per_recipe = 6, buy_price = 100, return_type = RETURN
```

### Expected Output
```text
rrr ~ 0.3243243243
craft_count = 3
total_output = 15
material_cost = 21200.00
material_return_value = 5600.00
net_material_cost = 15600.00
craft_fee_total = 1215.00
production_cost = 16815.00
production_cost_per_item = 1121.00
profit_per_item = 608.75
total_profit = 9131.25
margin_percent = 54.30
srp_10 = 1318.82
```

## Rule Validasi
Jika hasil backend:
- deviasi angka > 0.1%
- integer field berbeda (misal `craft_count`, `total_output`)
- urutan kalkulasi tidak sesuai `docs/11`
- salah RRR atau salah SRP

maka engine dianggap error dan tidak boleh dipakai produksi.

