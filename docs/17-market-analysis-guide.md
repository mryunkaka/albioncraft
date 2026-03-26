# Panduan Baca Market dan Mapping ke Calculator

## Tujuan
Dokumen ini merangkum cara membaca market Albion dan menghubungkannya ke flow calculator/project ini.

Dokumen ini disusun dari materi referensi yang Anda lampirkan:
- panduan baca market
- panduan penggunaan spreadsheet Famouzak

Fokus dokumen ini bukan mengganti `docs/07`, `docs/11`, atau `docs/12`, tetapi menjelaskan:
- bagaimana memilih target item
- bagaimana membaca demand/supply market
- bagaimana mengambil data input yang benar untuk calculator

## 1. Prinsip Dasar Market Albion
Albion memiliki market per kota, dan tiap kota dipengaruhi oleh:
- biome
- supply resource lokal
- demand crafting/refining
- bonus crafting/refining kota
- hubungan kota dengan black zone / outland

Akibatnya, harga item yang sama bisa berbeda antar kota.

## 2. Biome dan Dampaknya
Contoh umum:
- `Lymhurst` kuat di biome forest
- `Fort Sterling` kuat di ore / mountain
- `Martlock` kuat di leather refining
- `Bridgewatch` kuat di steppe
- `Thetford` kuat di swamp

Implikasi praktis:
- resource utama kota sering punya demand refining tinggi
- hasil refining/crafting tertentu cenderung lebih kompetitif di kota bonusnya
- harga material dan harga jual item tidak boleh diasumsikan sama antar kota

## 3. Cara Baca Market
### 3.1 Mulai dari grafik penjualan
Gunakan grafik 4 minggu untuk melihat:
- item laku stabil atau tidak
- volume harian stabil atau spike sesaat
- apakah item cocok untuk market yang konsisten

Prinsip:
- item dengan penjualan harian stabil lebih aman untuk target crafting
- item yang hanya ramai sesaat lebih berisiko

### 3.2 Tanya: item ini dipakai siapa?
Sebelum crafting item, pahami dulu fungsi item:
- dipakai player PvP?
- dipakai refiner?
- dipakai crafter lain?
- dipakai sebagai bahan craft lanjutan?

Contoh:
- `Leather T2` dipakai untuk refining `Leather T3`
- `Leather T4+` lebih dekat ke target market crafter equipment

### 3.3 Bedakan target market per tier/enchant
Jangan anggap semua tier punya buyer yang sama.

Contoh:
- material refining tier awal sering dibeli refiner
- refined material tier lebih tinggi sering dibeli crafter equipment
- item non-enchant sering bentrok dengan suplai PvE / chest / loot

## 4. Catatan Penting Soal Black Market
Black Market cenderung menyerap item non-enchant tertentu.

Implikasi:
- item non-enchant bisa punya volume besar
- tetapi kompetisinya juga keras
- untuk banyak kategori equipment, item non-enchant bisa kurang menarik untuk crafter karena bersaing dengan supply loot PvE

Prinsip praktis:
- jangan otomatis asumsi semua item non-enchant cocok untuk crafting profit
- evaluasi lewat harga market, volume, dan production cost
- pengecualian umum yang disebut di materi: `food` dan `potion`

## 5. Cara Menentukan Item yang Layak Dihitung
Urutan kerja yang disarankan:
1. Cari item dengan penjualan stabil di grafik 4 minggu.
2. Identifikasi item itu dipakai oleh siapa.
3. Cek resep materialnya.
4. Ambil harga material dari kota bonus refining/crafting yang relevan.
5. Hitung production cost.
6. Bandingkan dengan harga jual market.
7. Jika margin jelek, cek apakah material harus diproduksi sendiri.

## 6. Mapping ke Calculator Project Ini
Project ini sekarang mendukung:
- input manual material
- auto-fill recipe database untuk `MEDIUM/PRO`
- auto harga dari `market_prices` user ke recipe auto-fill
- bulk import/update harga untuk `PRO`

Flow yang disarankan:
1. Isi harga material `BUY` di `price-data`.
2. Isi harga jual item `SELL` di `price-data`.
3. Gunakan `recipe auto-fill` di calculator.
4. Pilih kota bonus yang relevan.
5. Jalankan kalkulasi.
6. Evaluasi:
   - `production_cost`
   - `production_cost_per_item`
   - `scenario.total_profit`
   - `scenario.margin_percent`
   - `srp_5 / srp_10 / srp_15 / srp_20`

## 7. Mapping Input Spreadsheet/Famouzak ke App
### Bonus
Spreadsheet/Famouzak:
- bonus basic
- bonus local
- bonus daily
- bonus focus

App:
- `bonus_basic`
- `bonus_local`
- `bonus_daily`
- `craft_with_focus`

Jika `craft_with_focus = true`, engine otomatis menambah bonus focus `59`.

### Resource Return Rate
Formula tetap sama:

```text
RRR = 1 - 1 / (1 + (bonus_basic + bonus_local + bonus_daily + bonus_focus) / 100)
```

### Craft Fee
Formula project ini sudah mengikuti referensi spreadsheet:

```text
craft_fee = usage_fee x item_value x output_qty / 20 / (400 / 9)
```

### Material Return vs Non Return
Project ini mengikuti `return_type` pada recipe/input material.

Artinya:
- untuk database recipe, `return_type` berasal dari data recipe
- untuk manual input, user tetap bisa menentukan `RETURN` / `NON_RETURN`

## 8. Aturan Praktis Input Harga
### BUY price
Isi `BUY` untuk material yang benar-benar akan dibeli dari market.

### SELL price
Isi `SELL` untuk item hasil craft yang akan dijual.

### City
Gunakan city jika harga memang spesifik kota.
Jika harga global/manual tidak spesifik kota, biarkan city kosong.

Sistem saat ini:
- saat `recipe auto-fill`, lookup harga memprioritaskan city terpilih
- jika tidak ada, fallback ke harga global user

## 9. Interpretasi Hasil Calculator
### Production Cost per Item
Ini angka utama untuk tahu biaya real crafting per item.

### Scenario MARKET
Jika `sell_price` tersedia, hasil akan dihitung memakai market price user.

### Scenario SRP_10_DEFAULT
Jika `sell_price` kosong, sistem tetap memberi skenario default berdasarkan `SRP 10%`.

### SRP
Gunakan SRP untuk:
- menentukan harga jual minimum yang masih sehat
- menghindari perang harga yang membuat margin terlalu tipis

## 10. Strategi Penggunaan App
Mulai dari:
- item yang Anda pahami fungsinya
- item yang punya bonus kota yang cocok dengan basis operasi Anda
- item dengan demand stabil

Jangan mulai dari:
- item yang tidak Anda pahami target marketnya
- item non-enchant yang ternyata sangat dipengaruhi suplai loot PvE, kecuali memang sudah dihitung dan terbukti masuk

## 11. Batasan Project Saat Ini
Project ini membantu hitung profit, tetapi belum otomatis:
- menarik live market data dari Albion API/market scraper
- memberi rekomendasi item terbaik lintas kota secara otomatis
- membaca grafik volume penjualan 4 minggu dari game

Artinya, keputusan final tetap membutuhkan:
- observasi market manual
- input harga yang benar
- pemahaman target market item

## 12. Checklist Pakai Calculator Dengan Benar
- Pastikan item yang dihitung memang punya demand stabil.
- Pastikan kota bonus sudah benar.
- Pastikan material `NON_RETURN` tidak salah ditandai `RETURN`.
- Pastikan `BUY` material dan `SELL` item berasal dari market yang benar.
- Bandingkan hasil `MARKET` dengan `SRP`.
- Jangan ambil keputusan hanya dari satu snapshot harga.

## Penutup
Calculator ini sekarang sudah cukup kuat untuk:
- menghitung biaya produksi
- mengintegrasikan recipe database
- memanfaatkan market price per user
- menyimpan history simulasi

Namun keunggulan real tetap datang dari kualitas analisa market user:
- paham siapa buyer item
- paham biome dan bonus kota
- paham supply vs demand
- paham kapan harus buy material, kapan harus refine sendiri, dan kapan harus craft
