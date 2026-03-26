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

## Fase 2: Auth
- Register user.
- Generate referral code unik.
- Login.
- Logout.
- Session native.
- Validasi referral code saat register.

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

## Fase 8: Data Harga Pro
- Search.
- Filter.
- Pagination server-side.
- Bulk update harga.

## Fase 9: QA dan Validasi
- Uji sample refining.
- Uji sample potion.
- Uji sample equipment.
- Uji focus on/off.
- Uji premium vs non-premium.
- Uji SRP 5, 10, 15.
- Pastikan deviasi <= 0.1%.

## Fase 10: Hardening
- Optimasi query dan indexing.
- Logging error.
- CSRF untuk form penting.
- Sanitasi input.
- Rate limit sederhana untuk auth bila diperlukan.
