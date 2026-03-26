-- AlbionCraft - SQL Index Hardening Patch (v1)
-- Tujuan: meningkatkan performa query list/filter/pagination untuk subscription, referral, market price, admin actions.
-- Jalankan di phpMyAdmin pada database target.
-- Catatan: bila index sudah ada, MySQL akan melempar duplicate key name. Itu aman, lanjutkan index berikutnya.

ALTER TABLE subscriptions
  ADD INDEX idx_subscriptions_user_latest (user_id, id);

ALTER TABLE subscription_logs
  ADD INDEX idx_subscription_logs_user_id (user_id, id);

ALTER TABLE referral_rewards
  ADD INDEX idx_referral_rewards_user_id (rewarded_user_id, id);

ALTER TABLE items
  ADD INDEX idx_items_name (name);

ALTER TABLE market_prices
  ADD INDEX idx_market_prices_user_updated (user_id, updated_at, id),
  ADD INDEX idx_market_prices_user_type_city_updated (user_id, price_type, city_id, updated_at, id);

ALTER TABLE admin_subscription_actions
  ADD INDEX idx_admin_subscription_actions_action (action_type, id),
  ADD INDEX idx_admin_subscription_actions_notes_prefix (notes(191));

-- Verifikasi cepat
SHOW INDEX FROM subscriptions;
SHOW INDEX FROM subscription_logs;
SHOW INDEX FROM referral_rewards;
SHOW INDEX FROM items;
SHOW INDEX FROM market_prices;
SHOW INDEX FROM admin_subscription_actions;
