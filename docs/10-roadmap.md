# Roadmap Implementasi

## Fase 1: Bootstrap Project
- Buat struktur folder sesuai `docs/03`.
- Tambah front controller `public/index.php`.
- Siapkan autoload sederhana.
- Siapkan config database dan env.
- Siapkan helper response, validator, session, csrf.

## Fase 1.1: Deploy Shared Hosting (cPanel)
- Set document root domain ke folder `public/`.
- Pastikan `.htaccess` bisa dibaca server (permission 644) dan folder 755.
- Siapkan deploy script sederhana + cron (lihat `docs/13-deployment-cpanel.md`).

## Fase 1.2: Tailwind + Component System
- Install Tailwind via NPM (tanpa CDN).
- Siapkan build pipeline ke `public/assets/app.css`.
- Pindahkan styling reusable ke `assets/components/`.
- Pastikan view memakai class komponen tanpa inline style.
- Status saat ini: selesai untuk calculator/auth/dashboard.

## Fase 2: Auth
- Register user.
- Generate referral code unik.
- Login.
- Logout.
- Session native.
- Validasi referral code saat register.
- Status saat ini: core auth flow + middleware auth/guest + CSRF form auth sudah terpasang.

## Fase 3: Subscription dan Referral
- Tabel plan, subscriptions, logs, referrals, referral rewards.
- Middleware auto-downgrade saat expired.
- Extend plan manual admin.
- Riwayat reward referral.

## Fase 4: Catalog dan Recipe
- Item categories.
- Items.
- Recipes.
- Recipe materials.
- City bonus.
- Feature gating per plan.

## Fase 5: Calculation Engine Strict
- Implementasi input validator.
- Implementasi urutan kalkulasi strict.
- Implementasi material return logic.
- Implementasi craft fee formula.
- Implementasi tax, setup fee, revenue, profit, margin, SRP.
- Implementasi JSON response standar.

## Fase 6: Calculator UI + AJAX
- Form input dinamis material.
- Kalkulasi real-time via AJAX.
- Hasil realtime tanpa reload.
- Error state dan loading state.

## Fase 7: Dashboard
- Quick calc.
- Summary profit.
- Histori kalkulasi terbaru.
- Status subscription.
- Status saat ini:
  - histori kalkulasi otomatis tersimpan saat user login melakukan kalkulasi
  - dashboard sudah menampilkan summary profit + recent history dari database

## Fase 8: Data Harga Pro
- Search.
- Filter.
- Pagination server-side.
- Bulk update harga.
- Status saat ini:
  - CRUD dasar + AJAX list/filter/pagination sudah selesai
  - bulk update/import massal masih menjadi next step
  - auto-fill recipe database di calculator sudah selesai untuk `MEDIUM/PRO`

## Fase 9: QA dan Validasi
- Uji sample refining.
- Uji sample potion.
- Uji sample equipment.
- Uji focus on/off.
- Uji premium vs non-premium.
- Uji SRP 5, 10, 15.
- Pastikan deviasi <= 0.1%.
- Status saat ini:
  - golden test calculator strict: `tests/run_calculation_engine_tests.php` (PASS)
  - integration test subscription/referral/admin approval: `tests/run_subscription_referral_admin_tests.php` (PASS)
  - integration test market price service: `tests/run_market_price_service_tests.php` (PASS)
  - integration test dashboard history: `tests/run_dashboard_history_tests.php` (PASS)
  - integration test recipe auto-fill: `tests/run_recipe_autofill_tests.php` (PASS)

## Fase 10: Hardening
- Optimasi query dan indexing.
- Logging error.
- CSRF untuk form penting.
- Sanitasi input.
- Rate limit sederhana untuk auth bila diperlukan.
- Status saat ini: rate limit auth sederhana sudah aktif untuk endpoint `POST /login` dan `POST /register`.
- Status saat ini tambahan: endpoint utilitas `/debug-db` dan `/setup/seed` hanya aktif saat `APP_DEBUG=1`.
- Status saat ini tambahan: patch index hardening sudah tersedia di `docs/15-sql-index-hardening.sql` dan baseline schema sudah diperbarui.

## Next Priority
- Integrasi auto harga dari `market_prices` user ke calculator `MEDIUM/PRO`
- Bulk import/update harga lebih efisien untuk plan PRO
- Test end-to-end autofill recipe -> calculate -> save history
