# Progress Log

## Tujuan
Dokumen ini dipakai sebagai handoff file utama agar AI lain atau engineer lain bisa melanjutkan pekerjaan tanpa kehilangan konteks.

## Status Saat Ini
Tanggal: 2026-03-26
Fase: MVP Calculator (Strict) Implemented
Status global: in-progress (core engine & UI sudah jalan, fitur besar lain belum)

## Checklist Dokumen
| File | Status | Catatan |
|---|---|---|
| 01-product-overview.md | done | Ruang lingkup produk dan plan matrix selesai |
| 02-system-architecture.md | done | Arsitektur PHP Native + AJAX + middleware selesai |
| 03-project-structure.md | done | Struktur folder final selesai |
| 04-database-design.md | done | Model data inti selesai |
| 05-sql-schema.sql | done | SQL schema manual selesai |
| 06-sql-seed-sample.sql | done | Seed sample awal selesai |
| 07-calculation-spec.md | done | Spec naratif kalkulasi selesai |
| 08-ui-ux-spec.md | done | Design system dan layout selesai |
| 09-progress-log.md | done | File handoff aktif |
| 10-roadmap.md | done | Roadmap implementasi selesai |
| 11-calculation-engine-strict-rules.md | done | Rules strict tersinkron dengan spreadsheet basis |
| 12-test-case-golden-data.md | done | Golden data numerik tersinkron dengan strict rules |

## Keputusan Final yang Sudah Dikunci
- Bahasa dokumen: Indonesia.
- Subscription activation v1: manual admin.
- PRO data v1: schema lengkap + sample seed, bukan full dataset.
- Spreadsheet Famouzak free menjadi referensi kalkulasi utama.
- SRP final harus include tax + setup fee.
- Rounding authority mengikuti perilaku spreadsheet jika berbeda dari aturan generik.
- `docs/11` menjadi single source of truth implementasi setelah sinkronisasi dengan `docs/07`.

## Konflik yang Sudah Diselesaikan
### Konflik 1: Source of truth kalkulasi
- Selesai.
- Keputusan: spreadsheet final menang atas draft strict rules yang mentah.

### Konflik 2: Formula SRP
- Selesai.
- Keputusan: pakai gross-up formula net-aware, bukan markup sederhana.

### Konflik 3: Rounding
- Selesai.
- Keputusan: perilaku spreadsheet/Excel menjadi otoritas akhir bila nanti ditemukan beda pada implementasi.

## Catatan Penting untuk Lanjutan
- Calculation engine minimal sudah diimplementasikan.
- File engine ada di `app/Services/CalculationEngineService.php`.
- Exception validasi ada di `app/Support/CalculationException.php`.
- Test runner golden data ada di `tests/run_calculation_engine_tests.php`.
- Semua golden tests saat ini PASS.
- Bootstrap autoload minimal sudah ada di `bootstrap/autoload.php`.
- Skeleton web minimal sudah ada:
  - `public/index.php` dan `bootstrap/app.php`
  - `app/Support/Router.php`, `app/Support/Request.php`, `app/Support/Response.php`
  - `app/Controllers/CalculatorController.php` dan `app/Views/calculator/index.php`
  - `public/assets/app.css` dan `public/assets/calculator.js`
- Engine sekarang mengikuti mode simulasi spreadsheet:
  - target input adalah `target_output_qty` (jumlah item output)
  - `material_to_buy` dihitung dengan CEILING dan multiplier (1 - RRR) untuk RETURN
  - simulasi iterasi craft (bulk) dengan rounding integer half-up
- Engine juga menyediakan opsi rounding:
  - `SPREADSHEET_BULK` (default, cocok Excel)
  - `INGAME_PER_CRAFT` (simulasi per craft untuk rounding lebih detail)
- UI result menampilkan format Rupiah (IDR) di browser.
- UI Calculator sekarang punya:
  - LocalStorage persist + tombol Clear
  - Tabel summary 1 baris (spreadsheet-like) + detail perhitungan via collapsible
  - Material list to buy bernomor + badge profit merah/hijau
- Belum ada auth, subscription, referral, database repository layer, dan UI Tailwind build pipeline.

## Cara Menjalankan (Dev)
Jalankan web (PHP built-in server):
```powershell
php -S 127.0.0.1:2042 -t public public/router.php
```

URL:
- Calculator: `http://127.0.0.1:2042/calculator`

Jalankan golden tests:
```powershell
php tests/run_calculation_engine_tests.php
```

## Status Akurasi
- Mode default engine: `return_rounding_mode = SPREADSHEET_BULK` (mengunci kecocokan dengan Famouzak free).
- Opsi tambahan: `INGAME_PER_CRAFT` untuk rounding lebih granular. Ini lebih dekat ke in-game, tetapi tidak dijamin 100% identik untuk semua situasi.

## Next Safe Continuation Point
1. Lanjutkan bootstrap aplikasi PHP Native penuh sesuai `docs/03-project-structure.md`.
2. Implementasikan schema database dari `docs/05-sql-schema.sql`.
3. Implementasikan Auth + Session + middleware sesuai `docs/02`.
4. Implementasikan Subscription + Referral sesuai `docs/04` dan `docs/05`.
5. Tambahkan Tailwind build pipeline (NPM) dan pindahkan styling ke `assets/components/` (tanpa CDN).
6. Tambahkan halaman Dashboard, Subscription, Referral, dan PRO Price Data.
7. Tambahkan test edge case tambahan + verifikasi hasil vs spreadsheet.
