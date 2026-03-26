CREATE TABLE plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE plan_features (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id BIGINT UNSIGNED NOT NULL,
    feature_key VARCHAR(100) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_plan_feature (plan_id, feature_key),
    CONSTRAINT fk_plan_features_plan FOREIGN KEY (plan_id) REFERENCES plans(id)
);

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    referral_code VARCHAR(30) NOT NULL UNIQUE,
    referred_by_code VARCHAR(30) NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    plan_expired_at DATETIME NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_plan FOREIGN KEY (plan_id) REFERENCES plans(id)
);

CREATE TABLE subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    duration_type VARCHAR(20) NOT NULL,
    duration_days INT NOT NULL DEFAULT 0,
    started_at DATETIME NOT NULL,
    expired_at DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    source_type VARCHAR(30) NOT NULL DEFAULT 'MANUAL_ADMIN',
    source_reference VARCHAR(100) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_subscriptions_user_status (user_id, status, expired_at),
    CONSTRAINT fk_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES plans(id)
);

CREATE TABLE subscription_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    action_type VARCHAR(30) NOT NULL,
    old_plan_id BIGINT UNSIGNED NULL,
    new_plan_id BIGINT UNSIGNED NULL,
    old_expired_at DATETIME NULL,
    new_expired_at DATETIME NULL,
    actor_label VARCHAR(100) NOT NULL DEFAULT 'SYSTEM',
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_subscription_logs_user (user_id, created_at),
    CONSTRAINT fk_subscription_logs_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id),
    CONSTRAINT fk_subscription_logs_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE referrals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referrer_user_id BIGINT UNSIGNED NOT NULL,
    referred_user_id BIGINT UNSIGNED NOT NULL,
    referral_code_used VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'VALID',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_referral_referred (referred_user_id),
    KEY idx_referrals_referrer (referrer_user_id, created_at),
    CONSTRAINT fk_referrals_referrer FOREIGN KEY (referrer_user_id) REFERENCES users(id),
    CONSTRAINT fk_referrals_referred FOREIGN KEY (referred_user_id) REFERENCES users(id)
);

CREATE TABLE referral_rewards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referral_id BIGINT UNSIGNED NOT NULL,
    rewarded_user_id BIGINT UNSIGNED NOT NULL,
    reward_type VARCHAR(30) NOT NULL DEFAULT 'SUBSCRIPTION_DAYS',
    reward_days INT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_referral_rewards_user (rewarded_user_id, created_at),
    CONSTRAINT fk_referral_rewards_referral FOREIGN KEY (referral_id) REFERENCES referrals(id),
    CONSTRAINT fk_referral_rewards_user FOREIGN KEY (rewarded_user_id) REFERENCES users(id)
);

CREATE TABLE cities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    city_type VARCHAR(20) NOT NULL DEFAULT 'ROYAL',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE item_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    category_group VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE city_bonuses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    city_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    bonus_percent DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_city_bonus (city_id, category_id),
    CONSTRAINT fk_city_bonuses_city FOREIGN KEY (city_id) REFERENCES cities(id),
    CONSTRAINT fk_city_bonuses_category FOREIGN KEY (category_id) REFERENCES item_categories(id)
);

CREATE TABLE items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(160) NOT NULL UNIQUE,
    category_id BIGINT UNSIGNED NOT NULL,
    item_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    default_output_qty INT NOT NULL DEFAULT 1,
    tier VARCHAR(20) NULL,
    enchantment_level VARCHAR(20) NULL,
    is_database_ready TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_items_category (category_id, name),
    CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES item_categories(id)
);

CREATE TABLE recipes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT UNSIGNED NOT NULL,
    output_qty INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_recipes_item (item_id),
    CONSTRAINT fk_recipes_item FOREIGN KEY (item_id) REFERENCES items(id)
);

CREATE TABLE recipe_materials (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipe_id BIGINT UNSIGNED NOT NULL,
    material_item_id BIGINT UNSIGNED NOT NULL,
    qty_per_recipe DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    return_type VARCHAR(20) NOT NULL DEFAULT 'RETURN',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_recipe_materials_recipe (recipe_id, sort_order),
    CONSTRAINT fk_recipe_materials_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id),
    CONSTRAINT fk_recipe_materials_item FOREIGN KEY (material_item_id) REFERENCES items(id)
);

CREATE TABLE market_prices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    city_id BIGINT UNSIGNED NULL,
    price_type VARCHAR(20) NOT NULL,
    price_value DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    observed_at DATETIME NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_market_prices_lookup (user_id, item_id, city_id, price_type),
    CONSTRAINT fk_market_prices_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_market_prices_item FOREIGN KEY (item_id) REFERENCES items(id),
    CONSTRAINT fk_market_prices_city FOREIGN KEY (city_id) REFERENCES cities(id)
);

CREATE TABLE calculation_histories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NULL,
    plan_code VARCHAR(20) NOT NULL,
    calculation_mode VARCHAR(20) NOT NULL DEFAULT 'MANUAL',
    input_snapshot JSON NOT NULL,
    output_snapshot JSON NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_calculation_histories_user (user_id, created_at),
    CONSTRAINT fk_calculation_histories_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_calculation_histories_item FOREIGN KEY (item_id) REFERENCES items(id)
);

CREATE TABLE admin_subscription_actions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    action_type VARCHAR(30) NOT NULL,
    plan_id BIGINT UNSIGNED NULL,
    duration_type VARCHAR(20) NULL,
    duration_days INT NULL,
    actor_label VARCHAR(100) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_admin_subscription_actions_user (user_id, created_at),
    CONSTRAINT fk_admin_subscription_actions_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_admin_subscription_actions_plan FOREIGN KEY (plan_id) REFERENCES plans(id)
);
