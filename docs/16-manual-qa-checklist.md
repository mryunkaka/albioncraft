# Manual QA Checklist

## Tujuan
Dokumen ini dipakai untuk verifikasi manual UI pada flow yang sudah selesai secara backend/integration test, terutama:
- `price-data` CRUD + bulk import
- `recipe auto-fill`
- integrasi `market_prices` user ke calculator
- save history ke dashboard

## Prasyarat
- Database sudah migrate/siap.
- Seed sample item/recipe/city bonus sudah tersedia.
- App berjalan di local:

```powershell
php -S 127.0.0.1:2042 -t public public/router.php
```

- Siapkan minimal 2 akun:
  - user `PRO` untuk halaman `price-data`
  - user `MEDIUM` atau `PRO` untuk `recipe auto-fill`

## Seed Sample yang Dipakai
- Item result:
  - `T4_POTION_SAMPLE`
  - `LEATHER_T3`
  - `LEATHER_HELMET_T4`
- Material:
  - `TEASEL`
  - `GOOSE_EGG`
- City sample:
  - `BRECILIEN`
  - `LYMHURST`

## Flow 1: Price Data CRUD
### 1.1 Create global BUY
- Login sebagai user `PRO`.
- Buka `/price-data`.
- Input:
  - item: `TEASEL`
  - city: kosong
  - type: `BUY`
  - price: `444`
- Submit.
- Expected:
  - tampil success message
  - row baru muncul di table
  - city tampil `-`
  - type `BUY`
  - price sesuai

### 1.2 Create city SELL
- Input:
  - item: `T4_POTION_SAMPLE`
  - city: `BRECILIEN`
  - type: `SELL`
  - price: `1888`
- Submit.
- Expected:
  - row baru muncul
  - city tampil `Brecilien`
  - type `SELL`

### 1.3 Edit row
- Klik `Edit` pada salah satu row.
- Ubah price.
- Submit.
- Expected:
  - form berubah ke mode update
  - setelah submit, row yang sama berubah nilainya
  - tidak membuat duplicate row

### 1.4 Delete row
- Klik `Delete`.
- Confirm.
- Expected:
  - row hilang dari table
  - total list berkurang

## Flow 2: Bulk Import / Update
### 2.1 Bulk insert/update sukses
- Paste data berikut ke bulk textarea:

```text
item_code,city_code,price_type,price_value,observed_at,notes
TEASEL,,BUY,444,,global price
GOOSE_EGG,BRECILIEN,BUY,555,2026-03-27 11:10:00,city buy
T4_POTION_SAMPLE,BRECILIEN,SELL,1888,2026-03-27 11:05:00,city sell
```

- Klik `Proses Bulk`.
- Expected:
  - result summary tampil
  - error list kosong
  - table refresh otomatis
  - row insert/update terlihat di table

### 2.2 Bulk partial error
- Paste data berikut:

```text
item_code,city_code,price_type,price_value
TEASEL,,BUY,450
UNKNOWN_ITEM,,BUY,10
T4_POTION_SAMPLE,BRECILIEN,SELL,1900
```

- Klik `Proses Bulk`.
- Expected:
  - summary tetap tampil
  - error list menampilkan row invalid
  - textarea tidak auto-clear
  - row valid tetap masuk/update

### 2.3 Bulk total error
- Paste data berikut:

```text
item_code,city_code,price_type,price_value
UNKNOWN_ITEM,,BUY,10
UNKNOWN_ITEM_2,,SELL,20
```

- Klik `Proses Bulk`.
- Expected:
  - response dianggap gagal
  - error list tampil
  - tidak ada row baru di table

### 2.4 Bulk helper buttons
- Klik `Isi Contoh`.
- Expected:
  - textarea terisi contoh template
- Klik `Clear Bulk`.
- Expected:
  - textarea kosong
  - feedback hilang

## Flow 3: Recipe Auto-Fill + Market Prices
### 3.1 Auto-fill recipe dengan city price
- Pastikan user `MEDIUM/PRO` punya:
  - `TEASEL` global `BUY = 444`
  - `GOOSE_EGG` city `BRECILIEN` `BUY = 555`
  - `T4_POTION_SAMPLE` city `BRECILIEN` `SELL = 1888`
- Buka `/calculator`.
- Pada panel `Recipe Auto Fill`:
  - item: `T4_POTION_SAMPLE`
  - city: `Brecilien`
  - klik `Load Recipe`
- Expected:
  - `item_name`, `item_value`, `output_qty`, `bonus_local` terisi
  - `bonus_local = 15`
  - material `TEASEL` terisi `buy_price = 444`
  - material `GOOSE_EGG` terisi `buy_price = 555`
  - field `Market Price` terisi `1888`

### 3.2 Fallback city -> global
- Hapus price city untuk `TEASEL` jika ada, sisakan global `BUY`.
- Ulangi `Load Recipe`.
- Expected:
  - `TEASEL` tetap terisi dari global price

### 3.3 Tanpa market sell price
- Kosongkan row `SELL` untuk `T4_POTION_SAMPLE`.
- Ulangi `Load Recipe`.
- Expected:
  - material tetap auto-fill
  - field `Market Price` kosong

## Flow 4: Calculate -> Save History -> Dashboard
### 4.1 Calculate dari hasil auto-fill
- Setelah flow 3.1 berhasil, klik `Hitung`.
- Expected:
  - result tampil
  - `MARKET MODE = MARKET`
  - `MARKET PRICE = 1888`
  - summary table terisi

### 4.2 Dashboard history
- Setelah kalkulasi berhasil, buka `/dashboard`.
- Expected:
  - histori terbaru bertambah
  - item terbaru sesuai item hasil auto-fill
  - sell price/scenario terbaca di ringkasan terbaru

## Flow 5: Plan Gating
### 5.1 User FREE
- Login sebagai user `FREE`.
- Buka `/calculator`.
- Expected:
  - panel `Recipe Auto Fill` tidak tersedia
- Buka `/price-data`.
- Expected:
  - akses ditolak / diarahkan sesuai middleware feature

### 5.2 User MEDIUM
- Login sebagai `MEDIUM`.
- Expected:
  - boleh pakai `recipe auto-fill`
  - tidak boleh akses `price-data` jika feature `price_bulk_input` hanya untuk `PRO`

### 5.3 User PRO
- Login sebagai `PRO`.
- Expected:
  - boleh akses `recipe auto-fill`
  - boleh akses `price-data`

## Exit Criteria
- Semua flow utama lolos tanpa mismatch data.
- Tidak ada duplicate row saat update single/bulk.
- Fallback city/global bekerja.
- History tersimpan setelah kalkulasi.
- Feature gating sesuai plan matrix.
