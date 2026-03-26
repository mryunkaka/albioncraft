INSERT INTO plans (code, name, sort_order) VALUES
('FREE', 'Free', 1),
('LITE', 'Lite', 2),
('MEDIUM', 'Medium', 3),
('PRO', 'Pro', 4);

INSERT INTO plan_features (plan_id, feature_key, is_enabled)
SELECT id, 'calculator_manual', 1 FROM plans;

INSERT INTO plan_features (plan_id, feature_key, is_enabled)
SELECT id, 'price_bulk_input', CASE WHEN code = 'PRO' THEN 1 ELSE 0 END FROM plans;

INSERT INTO plan_features (plan_id, feature_key, is_enabled)
SELECT id, 'recipe_auto_fill', CASE WHEN code IN ('MEDIUM', 'PRO') THEN 1 ELSE 0 END FROM plans;

INSERT INTO cities (code, name, city_type) VALUES
('BRECILIEN', 'Brecilien', 'ROYAL'),
('BRIDGEWATCH', 'Bridgewatch', 'ROYAL'),
('CAERLEON', 'Caerleon', 'ROYAL'),
('FORT_STERLING', 'Fort Sterling', 'ROYAL'),
('LYMHURST', 'Lymhurst', 'ROYAL'),
('MARTLOCK', 'Martlock', 'ROYAL'),
('THETFORD', 'Thetford', 'ROYAL');

INSERT INTO item_categories (code, name, category_group) VALUES
('POTION', 'Potion', 'CRAFTING'),
('REFINING_LEATHER', 'Refining Leather', 'REFINING'),
('LEATHER_HELMET', 'Leather Helmet', 'CRAFTING'),
('RAW_RESOURCE', 'Raw Resource', 'MATERIAL'),
('RAW_MATERIAL', 'Raw Material', 'MATERIAL'),
('ARTIFACT', 'Artifact', 'MATERIAL');

INSERT INTO city_bonuses (city_id, category_id, bonus_percent)
SELECT c.id, ic.id, 15.00
FROM cities c
JOIN item_categories ic ON ic.code = 'POTION'
WHERE c.code = 'BRECILIEN';

INSERT INTO city_bonuses (city_id, category_id, bonus_percent)
SELECT c.id, ic.id, 40.00
FROM cities c
JOIN item_categories ic ON ic.code = 'REFINING_LEATHER'
WHERE c.code = 'MARTLOCK';

INSERT INTO city_bonuses (city_id, category_id, bonus_percent)
SELECT c.id, ic.id, 15.00
FROM cities c
JOIN item_categories ic ON ic.code = 'LEATHER_HELMET'
WHERE c.code = 'LYMHURST';

INSERT INTO items (item_code, name, slug, category_id, item_value, default_output_qty, tier, enchantment_level, is_database_ready)
SELECT 'RAW_HIDE_T3', 'Hide T3', 'hide-t3', id, 0.00, 1, 'T3', '0', 1
FROM item_categories WHERE code = 'RAW_RESOURCE';

INSERT INTO items (item_code, name, slug, category_id, item_value, default_output_qty, tier, enchantment_level, is_database_ready)
SELECT 'LEATHER_T2', 'Leather T2', 'leather-t2', id, 0.00, 1, 'T2', '0', 1
FROM item_categories WHERE code = 'RAW_MATERIAL';

INSERT INTO items (item_code, name, slug, category_id, item_value, default_output_qty, tier, enchantment_level, is_database_ready)
SELECT 'LEATHER_T3', 'Leather T3', 'leather-t3', id, 64.00, 1, 'T3', '0', 1
FROM item_categories WHERE code = 'REFINING_LEATHER';

INSERT INTO items (item_code, name, slug, category_id, item_value, default_output_qty, tier, enchantment_level, is_database_ready)
SELECT 'TEASEL', 'Teasel', 'teasel', id, 0.00, 1, 'T3', '0', 1
FROM item_categories WHERE code = 'RAW_RESOURCE';

INSERT INTO items (item_code, name, slug, category_id, item_value, default_output_qty, tier, enchantment_level, is_database_ready)
SELECT 'GOOSE_EGG', 'Goose Egg', 'goose-egg', id, 0.00, 1, 'T3', '0', 1
FROM item_categories WHERE code = 'RAW_RESOURCE';

INSERT INTO items (item_code, name, slug, category_id, item_value, default_output_qty, tier, enchantment_level, is_database_ready)
SELECT 'T4_POTION_SAMPLE', 'Potion Sample T4', 'potion-sample-t4', id, 180.00, 10, 'T4', '0', 1
FROM item_categories WHERE code = 'POTION';

INSERT INTO items (item_code, name, slug, category_id, item_value, default_output_qty, tier, enchantment_level, is_database_ready)
SELECT 'LEATHER_HELMET_T4', 'Leather Helmet T4', 'leather-helmet-t4', id, 240.00, 1, 'T4', '0', 1
FROM item_categories WHERE code = 'LEATHER_HELMET';

INSERT INTO items (item_code, name, slug, category_id, item_value, default_output_qty, tier, enchantment_level, is_database_ready)
SELECT 'ANCIENT_ARTIFACT', 'Ancient Artifact', 'ancient-artifact', id, 0.00, 1, 'T4', '0', 1
FROM item_categories WHERE code = 'ARTIFACT';

INSERT INTO recipes (item_id, output_qty, is_active, notes)
SELECT id, 1, 1, 'Sample refining recipe'
FROM items WHERE item_code = 'LEATHER_T3';

INSERT INTO recipe_materials (recipe_id, material_item_id, qty_per_recipe, return_type, sort_order)
SELECT r.id, i.id, 2.0000, 'RETURN', 1
FROM recipes r
JOIN items p ON p.id = r.item_id AND p.item_code = 'LEATHER_T3'
JOIN items i ON i.item_code = 'RAW_HIDE_T3';

INSERT INTO recipe_materials (recipe_id, material_item_id, qty_per_recipe, return_type, sort_order)
SELECT r.id, i.id, 1.0000, 'RETURN', 2
FROM recipes r
JOIN items p ON p.id = r.item_id AND p.item_code = 'LEATHER_T3'
JOIN items i ON i.item_code = 'LEATHER_T2';

INSERT INTO recipes (item_id, output_qty, is_active, notes)
SELECT id, 10, 1, 'Sample potion recipe with x10 output'
FROM items WHERE item_code = 'T4_POTION_SAMPLE';

INSERT INTO recipe_materials (recipe_id, material_item_id, qty_per_recipe, return_type, sort_order)
SELECT r.id, i.id, 4.0000, 'RETURN', 1
FROM recipes r
JOIN items p ON p.id = r.item_id AND p.item_code = 'T4_POTION_SAMPLE'
JOIN items i ON i.item_code = 'TEASEL';

INSERT INTO recipe_materials (recipe_id, material_item_id, qty_per_recipe, return_type, sort_order)
SELECT r.id, i.id, 2.0000, 'NON_RETURN', 2
FROM recipes r
JOIN items p ON p.id = r.item_id AND p.item_code = 'T4_POTION_SAMPLE'
JOIN items i ON i.item_code = 'GOOSE_EGG';

INSERT INTO recipes (item_id, output_qty, is_active, notes)
SELECT id, 1, 1, 'Sample equipment recipe'
FROM items WHERE item_code = 'LEATHER_HELMET_T4';

INSERT INTO recipe_materials (recipe_id, material_item_id, qty_per_recipe, return_type, sort_order)
SELECT r.id, i.id, 8.0000, 'RETURN', 1
FROM recipes r
JOIN items p ON p.id = r.item_id AND p.item_code = 'LEATHER_HELMET_T4'
JOIN items i ON i.item_code = 'LEATHER_T3';

INSERT INTO recipe_materials (recipe_id, material_item_id, qty_per_recipe, return_type, sort_order)
SELECT r.id, i.id, 1.0000, 'NON_RETURN', 2
FROM recipes r
JOIN items p ON p.id = r.item_id AND p.item_code = 'LEATHER_HELMET_T4'
JOIN items i ON i.item_code = 'ANCIENT_ARTIFACT';
