# Desain Database

## Ringkasan
Database dirancang untuk mendukung auth, subscription, referral, kalkulasi, market price, dan katalog item/recipe. Desain dipilih agar Pro dapat berkembang ke database item penuh tanpa mengubah struktur dasar.

## Entitas Utama
### users
- Menyimpan akun user.
- Menyimpan referral code unik.
- Menyimpan plan aktif dan expired_at agar middleware cepat membaca status.

### plans
- Master data plan: FREE, MEDIUM, PRO.

### plan_features
- Menyimpan fitur yang aktif per plan.
- Memudahkan gating fitur tanpa hardcode berlebihan.

### subscriptions
- Riwayat subscription user.
- Menyimpan durasi, start_at, expired_at, status, dan sumber aktivasi.

### subscription_logs
- Audit setiap perubahan plan dan extend.

### referrals
- Menghubungkan referrer dan referred user.

### referral_rewards
- Ledger reward tambahan hari subscription.

### cities
- Master kota crafting/refining.

### city_bonuses
- Menyimpan bonus lokal per kota dan kategori/subkategori item.

### item_categories
- Kategori item seperti REFINING, POTION, FOOD, LEATHER HELMET, FIRE STAFF.

### items
- Master item.
- Menyimpan kode item, nama, category, item value, output qty default, serta metadata plan support.

### recipes
- Header recipe per item.

### recipe_materials
- Detail material recipe.
- Menyimpan qty per recipe dan return_type.

### market_prices
- Harga beli material dan harga jual item per user.

### calculation_histories
- Menyimpan snapshot input dan output kalkulasi.

### admin_subscription_actions
- Menyimpan aksi manual admin untuk create, extend, downgrade, atau koreksi.

## Relasi Utama
- `users.plan_id -> plans.id`
- `subscriptions.user_id -> users.id`
- `subscriptions.plan_id -> plans.id`
- `subscription_logs.subscription_id -> subscriptions.id`
- `referrals.referrer_user_id -> users.id`
- `referrals.referred_user_id -> users.id`
- `referral_rewards.referral_id -> referrals.id`
- `items.category_id -> item_categories.id`
- `recipes.item_id -> items.id`
- `recipe_materials.recipe_id -> recipes.id`
- `recipe_materials.material_item_id -> items.id`
- `city_bonuses.city_id -> cities.id`
- `city_bonuses.category_id -> item_categories.id`
- `market_prices.user_id -> users.id`
- `market_prices.item_id -> items.id`
- `market_prices.city_id -> cities.id`
- `calculation_histories.user_id -> users.id`
- `calculation_histories.item_id -> items.id`

## Rancangan Field Penting
### users
- `email`, `username`, `password_hash`
- `referral_code`
- `referred_by_code`
- `plan_id`
- `plan_expired_at`
- `status`

### subscriptions
- `plan_id`
- `duration_type`
- `duration_days`
- `started_at`
- `expired_at`
- `status`
- `source_type`

### items
- `item_code`
- `name`
- `slug`
- `category_id`
- `item_value`
- `default_output_qty`
- `tier`
- `enchantment_level`
- `is_database_ready`

### recipe_materials
- `material_item_id`
- `qty_per_recipe`
- `return_type`
- `sort_order`

### market_prices
- `buy_price`
- `sell_price`
- `price_type`
- `observed_at`

## Indexing
- `users.email` unique
- `users.username` unique
- `users.referral_code` unique
- `subscriptions(user_id, status, expired_at)`
- `referrals(referred_user_id)` unique
- `items.item_code` unique
- `items.slug` unique
- `city_bonuses(city_id, category_id)`
- `market_prices(user_id, item_id, city_id, price_type)`
- `calculation_histories(user_id, created_at)`

## Strategi Plan
- Free: item/recipe tidak dipakai sebagai basis utama kalkulasi.
- Lite: item tertentu bisa punya preset/template terbatas.
- Medium: mulai memanfaatkan item, item value, recipe, dan city bonus parsial.
- Pro: full akses database item, recipe, bonus, dan bulk pricing.

## Aturan Subscription
- User baru default ke plan FREE.
- Jika `plan_expired_at < now()`, middleware mengubah user kembali ke FREE.
- Extend subscription menambah durasi dari `expired_at` jika masih aktif, atau dari waktu sekarang jika sudah expired.

## Aturan Referral
- Setiap user memiliki `referral_code`.
- Saat register, user boleh mengisi `referral_code`.
- Jika valid, dibuat relasi referral.
- Reward disimpan di ledger `referral_rewards` dan memicu extend subscription referrer.

## Catatan Desain
- Riwayat kalkulasi disimpan dalam JSON snapshot agar mudah audit.
- Seed awal hanya contoh, bukan full dataset Albion.
- Struktur ini cukup stabil untuk ekspansi importer massal di fase berikutnya.
