-- =============================================================
-- 0023_seed_footer_top.sql
-- Moves the hardcoded footer tagline and social links into the
-- navigation table. The footer_top column holds the tagline
-- (two items, order 0 and 10) and the social links (order 20+).
-- =============================================================

-- 1. Create the footer_top column on the footer menu.
INSERT INTO `commerce_navigation_columns` (`menu_id`, `column_key`, `heading`, `sort_order`)
SELECT m.`id`, 'footer_top', 'Footer Top', 100
FROM `commerce_navigation_menus` m
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (
    SELECT 1 FROM `commerce_navigation_columns` c
    WHERE c.`menu_id` = m.`id` AND c.`column_key` = 'footer_top'
  );

-- 2. Insert the two tagline lines.
INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Shop the World,', NULL, 0, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'footer_top'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Shop the World,');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Love Your Style', NULL, 10, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'footer_top'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Love Your Style');

-- 3. Insert the six social links.
INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `icon`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Facebook', 'bi-facebook', '#', 20, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'footer_top'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Facebook');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `icon`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Instagram', 'bi-instagram', '#', 30, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'footer_top'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Instagram');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `icon`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'TikTok', 'bi-tiktok', '#', 40, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'footer_top'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'TikTok');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `icon`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Pinterest', 'bi-pinterest', '#', 50, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'footer_top'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Pinterest');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `icon`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'YouTube', 'bi-youtube', '#', 60, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'footer_top'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'YouTube');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `icon`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'WhatsApp', 'bi-whatsapp', '#', 70, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'footer_top'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'WhatsApp');
