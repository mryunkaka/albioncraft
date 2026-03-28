# Item Master Source Strategy

Tanggal catatan: 2026-03-28

## Ringkas

Untuk kebutuhan project ini, strategi paling aman saat ini adalah:

1. Auto-create item master manual dari input user di helper calculator.
2. Simpan harga `CRAFT_FEE`, `SELL`, dan `BUY` langsung ke `market_prices`.
3. Anggap input user sebagai sumber kebenaran praktis jika user memang mengecek langsung dari client Albion.

Alasan utama:

- project belum punya master item lengkap
- project juga belum punya recipe resmi lengkap
- full bulk seed ribuan item tanpa mapping kategori/recipe yang benar berisiko membuat database terlihat penuh tetapi tidak benar-benar sinkron untuk kalkulator

## Hasil riset sumber data

### 1. Official Albion Online

Saat pengecekan saat ini, tidak ditemukan public item master catalog yang jelas di domain resmi `albiononline.com` untuk dipakai langsung sebagai seed `items + recipes + recipe_materials`.

Catatan:

- hasil pencarian web untuk endpoint item catalog resmi tidak memberikan dokumentasi item master yang bisa dipakai langsung
- ini berarti "sinkron 100% otomatis dari official" belum bisa diasumsikan tersedia untuk import SQL project ini

### 2. Albion Online Data Project

Sumber komunitas yang paling layak untuk metadata item saat ini:

- API page: `https://www.albion-online-data.com/api/`
- Items page: `https://www.albion-online-data.com/items`

Yang relevan:

- halaman API mereka menjelaskan bahwa item IDs bisa diambil dari `items.txt` / `items.json` di repository `ao-bin-dumps`
- halaman Items menyatakan item list berasal dari `ao-bin-dumps` dan searchable by UniqueName / display name

Catatan penting:

- mereka sendiri menyatakan tidak terafiliasi dengan Albion Online / Sandbox Interactive
- bagus untuk metadata item dan market data
- belum otomatis memberi mapping final yang siap dipakai untuk semua `category_id`, `recipe`, dan `recipe_materials` di schema project ini

### 3. Albion Database

Sumber komunitas lain yang aktif:

- `https://www.albiondatabase.com/`

Saat dicek, situs ini menampilkan data item yang di-update baru-baru ini dan bisa berguna sebagai cross-check manual.

Catatan:

- juga bukan sumber resmi
- bagus untuk verifikasi manual
- tetap bukan paket SQL siap-import untuk schema project ini

## Kenapa default project memilih auto-create manual

Auto-create manual lebih jujur dan lebih aman untuk kondisi project sekarang:

- nama item memang diinput user berdasarkan item yang sedang dilihat langsung di game
- harga juga diinput user langsung dari market kota yang relevan
- item master bisa dibuat saat dibutuhkan, bukan menunggu seed ribuan row
- data harga langsung bisa tersimpan ke `market_prices`
- user `MEDIUM/PRO` tetap mendapat manfaat karena data harga milik user tersimpan dan bisa dipakai ulang

## Batasan auto-create manual saat ini

Auto-create manual tidak otomatis berarti recipe resmi lengkap tersedia.

Yang dibuat otomatis:

- kategori generic manual craft/material jika belum ada
- item master craft
- item master material
- harga craft fee / sell / buy

Yang belum otomatis dibuat dari helper:

- recipe resmi lengkap
- material quantity per recipe
- return type per material
- city bonus by category resmi

Karena itu helper tetap dianggap workflow manual-terverifikasi user, bukan mirror penuh database Albion.

## Rekomendasi lanjutan

Kalau nanti ingin bulk import besar, urutan paling aman:

1. Import metadata item dari `ao-bin-dumps` / sumber komunitas aktif lain.
2. Normalisasi `item_code`, `name`, `slug`, `tier`, `enchantment_level`.
3. Siapkan strategi mapping kategori internal project.
4. Baru bangun seed `recipes` dan `recipe_materials`.

Tanpa langkah 3 dan 4, import ribuan item hanya akan membantu lookup harga, tetapi belum cukup untuk recipe autofill yang benar-benar kaya.
