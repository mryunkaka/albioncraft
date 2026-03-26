# Arsitektur Sistem

## Ringkasan
Aplikasi dibangun dengan PHP Native tanpa framework, menggunakan pola MVC ringan dengan `Controller`, `Service`, `Repository`, dan `View`. Semua request masuk melalui `public/index.php` sebagai single front controller.

## Prinsip Arsitektur
- Sederhana untuk dijalankan di shared hosting maupun VPS.
- Modular agar mudah dikembangkan per fitur.
- Performa tinggi untuk akses baca dan kalkulasi.
- View bersih, tanpa logika bisnis berat.
- Kalkulasi dipusatkan pada service strict agar tidak terduplikasi.

## Request Lifecycle
1. Request masuk ke `public/index.php`.
2. Bootstrap memuat config, autoloader, session, env, dan koneksi database.
3. Resolver internal memetakan method + path ke controller action.
4. Controller memvalidasi akses user dan plan.
5. Controller memanggil service atau repository.
6. Response dikembalikan sebagai HTML view atau JSON AJAX.

## Lapisan Sistem
### Front Controller
- Satu file entry point.
- Menangani bootstrap, route dispatch, middleware dasar, dan error fallback.

### Controller
- Menangani request/response.
- Memutuskan view HTML atau response JSON.
- Tidak berisi query SQL dan rumus kalkulasi langsung.

### Service
- Menangani business logic.
- Contoh: AuthService, SubscriptionService, ReferralService, CalculationEngineService.

### Repository
- Menangani akses database.
- Semua query berat dan reusable ditempatkan di sini.

### View
- Template PHP sederhana.
- Tidak boleh mengandung styling inline.
- Hanya boleh memakai reusable classes dari `assets/components`.

## Middleware Internal
- Auth middleware: memastikan user login.
- Guest middleware: mencegah user login mengakses login/register.
- Subscription middleware: jika plan expired, otomatis downgrade ke Free.
- Plan feature middleware: membatasi akses Data Harga atau auto-fill berdasarkan plan.
- CSRF middleware sederhana untuk request form dan AJAX penting.

## Session & Auth
- Session native PHP.
- Password disimpan dengan `password_hash`.
- Session berisi `user_id`, `username`, `plan_code`, dan metadata minimum.
- Session direfresh setelah login, logout, dan update subscription.

## Kalkulasi Strict
- Semua kalkulasi wajib lewat `CalculationEngineService`.
- Tidak ada kalkulasi final di JavaScript.
- Frontend hanya mengirim input dan menampilkan output JSON dari backend.
- Rumus, urutan langkah, dan rounding mengikuti `docs/11`.

## Database
- MySQL/MariaDB.
- Query parameterized melalui PDO.
- Indeks untuk email, referral code, plan, item lookup, city bonus, dan harga market.

## AJAX Strategy
- `fetch` API untuk kalkulasi real-time.
- `fetch` API untuk tabel besar dengan pagination server-side.
- Debounce 300-500 ms untuk pencarian harga/item.
- JSON response standar untuk sukses dan error.

## Asset Strategy
- Semua asset lokal, tanpa CDN.
- Tailwind di-build lokal via NPM ke folder publik assets.
- Heroicons disimpan sebagai SVG lokal di `assets/icons`.
- Reusable utility abstraction ada di `assets/components`.

## Error Handling
- HTML request: tampilkan halaman error ringan.
- AJAX request: response JSON dengan `success`, `message`, `errors`, `data`.
- Semua validasi input kalkulator harus return error sebelum proses lanjut.

## Catatan Skalabilitas
- Pagination server-side untuk data besar.
- Query lookup recipe dan harga memakai indeks.
- History kalkulasi dan log referral bisa diarsipkan jika membesar.
- Service dan repository dipisah untuk memudahkan caching di fase berikutnya.
