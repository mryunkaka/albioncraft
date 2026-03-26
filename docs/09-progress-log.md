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
| 14-sql-repair-auth-referral-hosting.sql | done | SQL repair khusus issue auth/referral di shared hosting |
| 15-sql-index-hardening.sql | done | SQL patch index hardening untuk query list/filter/pagination |
| 16-manual-qa-checklist.md | done | Checklist QA manual UI untuk price-data, recipe auto-fill, dan dashboard |

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
- Integration test flow subscription/referral/admin approval sudah ditambahkan:
  - `tests/run_subscription_referral_admin_tests.php`
  - mencakup:
    - referral reward saat register
    - request extend -> approve admin
    - request extend -> reject admin
    - auto-downgrade FREE saat expired
- Integration test market price service sudah ditambahkan:
  - `tests/run_market_price_service_tests.php`
  - mencakup:
    - validation gagal (item tidak valid)
    - upsert insert/update by unique key
    - update by id
    - filter/search/pagination list
    - ownership check delete
    - item options + city options
- Integration test dashboard history sudah ditambahkan:
  - `tests/run_dashboard_history_tests.php`
  - mencakup:
    - simpan histori kalkulasi
    - ringkasan dashboard dari histori
    - latest/recent rows
- Recipe auto-fill database untuk calculator sudah ditambahkan:
  - repository baru: `RecipeRepository`
  - service baru: `RecipeAutoFillService`
  - endpoint baru:
    - `GET /api/calculator/recipes/items`
    - `GET /api/calculator/recipes/detail`
  - feature gate: `recipe_auto_fill`
  - UI calculator untuk plan `MEDIUM/PRO` sekarang bisa:
    - pilih item recipe database
    - pilih kota bonus
    - auto-fill `item_name`, `item_value`, `output_qty`, `bonus_local`, dan materials
- Integration test recipe auto-fill sudah ditambahkan:
  - `tests/run_recipe_autofill_tests.php`
  - mencakup:
    - sample refining
    - sample potion + city bonus
    - sample equipment + NON_RETURN material
    - auto harga material/item dari `market_prices` user (city first, fallback global)
- Integration test end-to-end recipe auto-fill -> calculate -> save history sudah ditambahkan:
  - `tests/run_recipe_autofill_e2e_tests.php`
  - mencakup:
    - auto-fill recipe potion sample dengan harga user
    - kalkulasi dari payload hasil auto-fill
    - penyimpanan `calculation_histories`
    - verifikasi dashboard summary / latest history
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
- Auth hardening lanjutan sudah diimplementasikan:
  - middleware baru: `AuthRateLimitMiddleware`
  - rate limit endpoint sensitif: `POST /login` dan `POST /register`
  - konfigurasi env:
    - `AUTH_RATE_LIMIT_MAX_ATTEMPTS` (default `5`)
    - `AUTH_RATE_LIMIT_WINDOW_SECONDS` (default `900`)
  - behavior:
    - percobaan gagal berulang akan diblok sementara dengan flash error
    - pada login/register sukses counter akan di-reset
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
- Admin action history (read-only) sudah ditambahkan:
  - halaman: `GET /admin/subscription-actions`
  - filter: action_type + keyword + per_page
  - pagination server-side
  - sumber data: `admin_subscription_actions` join users/plans
- PRO Price Data sudah naik dari placeholder ke foundation CRUD:
  - repository/service baru:
    - `ItemRepository`, `CityRepository`, `MarketPriceRepository`
    - `MarketPriceService`
  - endpoint baru:
    - `GET /api/price-data/list` (pagination + search + filter)
    - `GET /api/price-data/items` (item options search)
    - `POST /price-data/save` (upsert market price, CSRF-protected)
  - UI halaman `/price-data`:
    - form input harga (item/city/type/value/observed_at/notes)
    - tabel data harga load via AJAX
    - debounce search/filter
    - pagination next/prev tanpa reload full page
- PRO Price Data sekarang sudah ada aksi lanjutan:
  - edit cepat: klik `Edit` pada row akan prefill form (mode update)
  - delete: klik `Delete` pada row (AJAX + CSRF)
  - endpoint baru: `POST /price-data/delete`
  - backend update by `id` + ownership check (`user_id`)
- PRO Price Data sekarang sudah punya bulk import/update dasar:
  - endpoint baru: `POST /price-data/bulk-save`
  - format input: paste CSV/TSV dari spreadsheet
  - kolom: `item_code,item_city_or_blank,price_type,price_value,observed_at,notes`
  - backend bulk upsert memprioritaskan update row existing berdasarkan unique key `(user_id, item_id, city_id, price_type)`
  - lookup item/city mendukung code dan exact name
  - response bulk menampilkan ringkasan created/updated/error
- Hardening UX bulk import/update tahap awal sudah ditambahkan:
  - tombol isi contoh bulk
  - tombol clear bulk
  - feedback result terpisah dari daftar error
  - textarea bulk tidak di-reset jika masih ada partial error
- Histori kalkulasi + dashboard summary real sudah ditambahkan:
  - repository baru: `CalculationHistoryRepository`
  - service baru: `CalculationHistoryService`, `DashboardService`
  - `POST /api/calculate` otomatis menyimpan histori jika user login
  - dashboard menampilkan:
    - total histori
    - akumulasi profit simulasi 20 histori terbaru
    - rata-rata margin
    - win/loss count
    - kalkulasi terakhir
    - recent calculation history table
- Runtime foundation tambahan:
  - `app/Support/Env.php`, `Database.php`, `Session.php`, `View.php`
  - `bootstrap/app.php` sekarang load `.env` dan start session
  - `.env.example` sudah ada
  - endpoint debug/setup sekarang hardening:
    - route `/debug-db` dan `/setup/seed` hanya diregister saat `APP_DEBUG=1`
    - saat production (`APP_DEBUG=0`) endpoint ini tidak tersedia (404)
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
- Hardening index SQL sudah disiapkan:
  - baseline schema sudah ditambah index performa di `docs/05-sql-schema.sql`
  - patch aman untuk DB existing tersedia di `docs/15-sql-index-hardening.sql`

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
1. Jalankan QA manual UI memakai `docs/16-manual-qa-checklist.md`.
2. Hardening UX bulk import/update harga untuk user PRO (lanjutan: preview/import summary yang lebih kaya bila diperlukan).
3. Hardening Auth lanjutan:
   - status saat ini: summary validation/auth error + old input auth lebih aman sudah ditambahkan
   - pertimbangkan throttling endpoint API sensitif non-auth
4. Tambahkan test edge case tambahan + verifikasi hasil vs spreadsheet.
5. Review cleanup endpoint debug/setup untuk deployment production.
