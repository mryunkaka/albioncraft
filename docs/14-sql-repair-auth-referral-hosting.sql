-- AlbionCraft - SQL Repair untuk masalah login/register/referral di shared hosting
-- Jalankan berurutan di phpMyAdmin (database: hark8423_albioncraft)

SET FOREIGN_KEY_CHECKS = 0;

-- 1) Pastikan engine InnoDB untuk tabel inti auth
ALTER TABLE plans ENGINE=InnoDB;
ALTER TABLE users ENGINE=InnoDB;
ALTER TABLE referrals ENGINE=InnoDB;
ALTER TABLE referral_rewards ENGINE=InnoDB;
ALTER TABLE plan_features ENGINE=InnoDB;

-- 2) Hapus data referral orphan (jika ada inkonsistensi lama)
DELETE rr
FROM referral_rewards rr
LEFT JOIN referrals r ON r.id = rr.referral_id
LEFT JOIN users u ON u.id = rr.rewarded_user_id
WHERE r.id IS NULL OR u.id IS NULL;

DELETE r
FROM referrals r
LEFT JOIN users u1 ON u1.id = r.referrer_user_id
LEFT JOIN users u2 ON u2.id = r.referred_user_id
WHERE u1.id IS NULL OR u2.id IS NULL;

-- 3) Normalisasi plans + seed minimum
INSERT INTO plans (code, name, sort_order) VALUES
('FREE', 'Free', 1),
('LITE', 'Lite', 2),
('MEDIUM', 'Medium', 3),
('PRO', 'Pro', 4)
ON DUPLICATE KEY UPDATE
name = VALUES(name),
sort_order = VALUES(sort_order);

-- 4) Sinkronkan user plan_id invalid ke FREE
UPDATE users u
LEFT JOIN plans p ON p.id = u.plan_id
JOIN plans pf ON pf.code = 'FREE'
SET u.plan_id = pf.id
WHERE p.id IS NULL;

-- 5) Seed feature minimal
INSERT INTO plan_features (plan_id, feature_key, is_enabled)
SELECT p.id, 'calculator_manual', 1
FROM plans p
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);

INSERT INTO plan_features (plan_id, feature_key, is_enabled)
SELECT p.id, 'price_bulk_input', CASE WHEN p.code = 'PRO' THEN 1 ELSE 0 END
FROM plans p
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);

INSERT INTO plan_features (plan_id, feature_key, is_enabled)
SELECT p.id, 'recipe_auto_fill', CASE WHEN p.code IN ('MEDIUM', 'PRO') THEN 1 ELSE 0 END
FROM plans p
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);

SET FOREIGN_KEY_CHECKS = 1;

-- 6) Verifikasi cepat
SELECT id, code, name, sort_order FROM plans ORDER BY sort_order, id;
SELECT id, username, email, plan_id, status FROM users ORDER BY id DESC LIMIT 20;
SELECT id, referrer_user_id, referred_user_id, referral_code_used FROM referrals ORDER BY id DESC LIMIT 20;
