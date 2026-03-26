# Albion Crafting Profit Calculator

## Ringkasan
Albion Crafting Profit Calculator adalah aplikasi web berbasis PHP Native untuk membantu player Albion Online menghitung profit crafting, refining, food, dan alchemy dengan akurasi tinggi, UI ringan, dan performa stabil untuk ribuan user.

Dokumen ini menjadi gambaran produk tingkat tinggi sebelum implementasi kode. Semua modul kalkulasi wajib mengacu ke `docs/07-calculation-spec.md` dan `docs/11-calculation-engine-strict-rules.md`.

## Tujuan Produk
- Menghitung profit crafting dengan akurat dan konsisten dengan spreadsheet Famouzak free.
- Mengurangi kesalahan manual dalam menghitung bonus, return material, biaya craft, pajak, dan SRP.
- Menyediakan pengalaman cepat melalui kalkulasi real-time berbasis AJAX tanpa reload halaman.
- Menyediakan jalur upgrade fitur dari Free ke Lite, Medium, dan Pro.

## Persona Pengguna
- Crafter pemula yang ingin input manual dengan panduan minimum.
- Crafter menengah yang ingin semi otomatis untuk item tertentu.
- Crafter Pro yang butuh database item, recipe, item value, city bonus, dan input harga massal.

## Nilai Utama Produk
- Akurat: deviasi target maksimum 0.1% terhadap referensi spreadsheet.
- Cepat: kalkulasi dan pencarian tidak bergantung pada reload halaman penuh.
- Ringan: arsitektur PHP Native sederhana, tanpa framework besar.
- Scalable: struktur modular, query terindeks, pagination untuk tabel besar.

## Halaman Wajib
### 1. Auth
- Login
- Register
- Logout

### 2. Dashboard
- Ringkasan profit terakhir
- Quick calculator
- Status plan dan masa aktif
- Ringkasan referral

### 3. Calculator
- Input material
- Input bonus
- Focus mode
- Kalkulasi real-time
- Hasil per item dan total

### 4. Data Harga
- Khusus Pro
- Input harga material dan harga jual item secara massal
- Search, filter, pagination

### 5. Subscription
- Lihat plan aktif
- Pilih paket
- Extend plan
- Riwayat perubahan plan

### 6. Referral
- Lihat referral code
- Input referral code saat register
- Lihat history reward bonus hari

## Matrix Fitur Berdasarkan Plan
| Fitur | Free | Lite | Medium | Pro |
|---|---|---|---|---|
| Register/Login | Ya | Ya | Ya | Ya |
| Quick calculator manual | Ya | Ya | Ya | Ya |
| Simpan histori kalkulasi | Terbatas | Ya | Ya | Ya |
| Template recipe/input | Tidak | Ya | Ya | Ya |
| Auto-fill parsial item value/recipe | Tidak | Terbatas | Ya | Ya |
| Full database item/recipe/kota bonus | Tidak | Tidak | Sebagian | Ya |
| Bulk input harga | Tidak | Tidak | Tidak | Ya |
| Search/filter data besar | Tidak | Terbatas | Ya | Ya |

## Scope V1
- Auth native PHP dengan session.
- Dashboard, Calculator, Subscription, Referral.
- Data harga Pro.
- Schema database lengkap.
- SQL manual siap tempel.
- Sample seed untuk potion, refining, equipment.
- Calculation engine strict sebagai landasan implementasi berikutnya.

## Out of Scope V1
- Payment gateway.
- Sinkronisasi live dengan API game pihak ketiga.
- Import massal seluruh item Albion.
- Multi-language.
- Panel admin penuh. Untuk v1, aksi admin didesain via workflow internal dan tabel audit.

## Keputusan Produk yang Sudah Dikunci
- Aktivasi plan berbayar v1 dilakukan manual oleh admin.
- Plan berbayar tetap punya model data lengkap sejak awal.
- Spreadsheet Famouzak free adalah referensi utama akurasi kalkulasi.
- Jika ada konflik antara draft rules dan spreadsheet, dokumen final harus mengikuti spreadsheet.
