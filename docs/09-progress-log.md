# Progress Log

## Tujuan
Dokumen ini dipakai sebagai handoff file utama agar AI lain atau engineer lain bisa melanjutkan pekerjaan tanpa kehilangan konteks.

## Status Saat Ini
Tanggal: 2026-03-26
Fase: MVP Calculator + Auth + Plan Gating Foundation
Status global: in-progress (calculator, auth, middleware subscription/plan gating sudah jalan; referral/subscription flow bisnis masih belum)

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
| 13-deployment-cpanel.md | done | Panduan deploy cPanel + cron (deploy script sederhana) |

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
- Auth foundation sudah diimplementasikan:
  - `app/Controllers/AuthController.php`
  - `app/Controllers/DashboardController.php`
  - `app/Services/AuthService.php`
  - `app/Repositories/UserRepository.php`
  - `app/Repositories/PlanRepository.php`
  - `app/Views/auth/login.php`
  - `app/Views/auth/register.php`
  - `app/Views/dashboard/index.php`
  - route baru: `/login`, `/register`, `/logout`, `/dashboard`
- Auth hardening tahap awal sudah diimplementasikan:
  - middleware: `AuthMiddleware`, `GuestMiddleware`, `CsrfMiddleware`
  - router sudah support route-level middleware
  - form `login/register/logout` sudah memakai CSRF token
  - fallback runtime error view untuk kasus konfigurasi DB belum siap: `app/Views/errors/runtime.php`
- Subscription/Plan gating foundation sudah diimplementasikan:
  - middleware baru: `SubscriptionMiddleware`, `PlanFeatureMiddleware`
  - service baru: `app/Services/SubscriptionService.php`
  - plan sync saat request protected: jika expired otomatis downgrade ke FREE
  - session auth sekarang menyimpan: `plan_code`, `plan_name`, `plan_expired_at`
  - route protected baru: `/price-data` (feature key: `price_bulk_input`)
  - controller/view placeholder PRO: `PriceDataController` + `app/Views/price-data/index.php`
- Subscription + Referral foundation (fase 3 awal) sudah masuk:
  - repository baru: `SubscriptionRepository`, `ReferralRepository`
  - service baru: `ReferralService`
  - halaman baru: `/subscription`, `/referral`
  - route post baru: `/subscription/request` (CSRF-protected)
  - register dengan referral code sekarang membuat relasi referral + reward ledger
  - reward referral otomatis memanggil extend hari subscription pada referrer
  - mode extend berbayar tetap `manual admin` (request disimpan di `admin_subscription_actions`)
- Admin approval flow untuk subscription request sudah ditambahkan:
  - middleware baru: `AdminMiddleware` (berbasis env `ADMIN_EMAILS`)
  - helper akses: `app/Support/AdminAccess.php`
  - halaman admin: `/admin/subscription-requests`
  - aksi admin:
    - `POST /admin/subscription-requests/approve`
    - `POST /admin/subscription-requests/reject`
  - approval menulis:
    - update `users.plan_id` dan `users.plan_expired_at`
    - insert `subscriptions`
    - insert `subscription_logs`
    - insert `admin_subscription_actions` action `APPROVE_EXTEND`
  - reject menulis `admin_subscription_actions` action `REJECT_EXTEND`
- Runtime foundation tambahan:
  - `app/Support/Env.php`, `Database.php`, `Session.php`, `View.php`
  - `bootstrap/app.php` sekarang load `.env` dan start session
  - `.env.example` sudah ada
- Tailwind pipeline sudah aktif:
  - `package.json`, `tailwind.config.js`, `postcss.config.js`
  - source stylesheet: `assets/tailwind/app.css`
  - reusable UI components di `assets/components/*`
  - build output ke `public/assets/app.css` via `npm run build`
  - script UI kalkulator sinkron: `assets/js/calculator.js` -> `public/assets/calculator.js`
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
- Middleware plan gating sudah ada; business flow subscription/referral (extend, reward ledger, logs) masih belum.
- Tailwind local build + assets/components convention masih belum diterapkan penuh.
- Tailwind local build + component system sudah terpasang untuk halaman utama (calculator/auth/dashboard); migrasi halaman fitur lanjutan mengikuti fase berikutnya.
- Deploy shared hosting (cPanel) sudah didokumentasikan di `docs/13`.
- Deploy script yang dipakai adalah versi sederhana (mirip `deploy-sigaji.php`) tanpa token/lock, dan log format 1 baris RUN + baris Deploy per commit.

## Catatan Hosting (Penting)
- Jika domain menampilkan 403 `Server unable to read htaccess file`, itu bukan masalah database atau PHP code.
  - Itu masalah permission/ownership atau konfigurasi `.htaccess`/AllowOverride di Apache.
  - Lihat troubleshooting di `docs/13-deployment-cpanel.md`.

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
3. Hardening Auth lanjutan:
   - tambah validasi/refactor flow flash error UX
   - tambah CSRF coverage untuk form lain yang nanti ditambahkan
4. Tambahkan automated test untuk flow subscription/referral/admin-approval.
5. Lanjutkan halaman PRO Price Data dari placeholder ke CRUD + pagination + search.
6. Tambahkan halaman khusus history admin action (opsional, read-only).
7. Tambahkan hardening validasi input & rate limit endpoint auth sensitif.
8. Tambahkan test edge case tambahan + verifikasi hasil vs spreadsheet.
