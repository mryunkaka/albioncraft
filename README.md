# Albion Crafting Profit Calculator (PHP Native)

## Jalankan Lokal
1. Copy `.env.example` menjadi `.env` lalu isi kredensial MySQL.
2. Import schema dan seed:
   - `docs/05-sql-schema.sql`
   - `docs/06-sql-seed-sample.sql`
3. Install frontend dependency:
   - `npm install`
4. Build asset Tailwind lokal:
   - `npm run build`
5. Jalankan server:
   - `php -S 127.0.0.1:2042 -t public public/router.php`

## URL
- Calculator: `http://127.0.0.1:2042/calculator`
- Login: `http://127.0.0.1:2042/login`
- Register: `http://127.0.0.1:2042/register`
- Dashboard (auth required): `http://127.0.0.1:2042/dashboard`

## Test
- `php tests/run_calculation_engine_tests.php`

## Frontend Build
- Development watch: `npm run dev`
- Production build: `npm run build`
