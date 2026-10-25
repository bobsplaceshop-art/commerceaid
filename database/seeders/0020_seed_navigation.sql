-- =====================================================================
-- Seed navigation menus, columns, items, and contact details.
-- Idempotent: safe to run multiple times.
-- =====================================================================

-- ---------------------------------------------------------------
-- 1. Menus: header, footer. One per store.
-- ---------------------------------------------------------------
INSERT INTO `commerce_navigation_menus` (`store_id`, `name`, `slug`, `location`, `is_active`)
SELECT s.`id`, 'Header Navigation', 'header', 'header', 1 FROM `commerce_stores` s
WHERE NOT EXISTS (
  SELECT 1 FROM `commerce_navigation_menus` m
  WHERE m.`store_id` = s.`id` AND m.`slug` = 'header'
);

INSERT INTO `commerce_navigation_menus` (`store_id`, `name`, `slug`, `location`, `is_active`)
SELECT s.`id`, 'Footer Navigation', 'footer', 'footer', 1 FROM `commerce_stores` s
WHERE NOT EXISTS (
  SELECT 1 FROM `commerce_navigation_menus` m
  WHERE m.`store_id` = s.`id` AND m.`slug` = 'footer'
);

-- ---------------------------------------------------------------
-- 2. Header items. Editorial only. Categories render dynamically.
-- ---------------------------------------------------------------
INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `resource_type`,
   `resource_id`, `sort_order`, `is_highlight`)
SELECT m.`id`, NULL, NULL, 'Home', '/', NULL, NULL, 0, 0
FROM `commerce_navigation_menus` m
WHERE m.`slug` = 'header'
  AND NOT EXISTS (
    SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` IS NULL
      AND i.`parent_id` IS NULL AND i.`label` = 'Home'
  );

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `resource_type`,
   `resource_id`, `sort_order`, `is_highlight`)
SELECT m.`id`, NULL, NULL, 'New In', '/products?sort=newest', NULL, NULL, 20, 0
FROM `commerce_navigation_menus` m
WHERE m.`slug` = 'header'
  AND NOT EXISTS (
    SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` IS NULL
      AND i.`parent_id` IS NULL AND i.`label` = 'New In'
  );

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `resource_type`,
   `resource_id`, `sort_order`, `is_highlight`)
SELECT m.`id`, NULL, NULL, 'Sale', '/products?filter=sale', NULL, NULL, 30, 1
FROM `commerce_navigation_menus` m
WHERE m.`slug` = 'header'
  AND NOT EXISTS (
    SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` IS NULL
      AND i.`parent_id` IS NULL AND i.`label` = 'Sale'
  );

-- ---------------------------------------------------------------
-- 3. Footer columns.
-- ---------------------------------------------------------------
INSERT INTO `commerce_navigation_columns` (`menu_id`, `column_key`, `heading`, `sort_order`)
SELECT m.`id`, 'quick_links', 'Quick Links', 0
FROM `commerce_navigation_menus` m
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (
    SELECT 1 FROM `commerce_navigation_columns` c
    WHERE c.`menu_id` = m.`id` AND c.`column_key` = 'quick_links'
  );

INSERT INTO `commerce_navigation_columns` (`menu_id`, `column_key`, `heading`, `sort_order`)
SELECT m.`id`, 'customer_service', 'Customer Service', 10
FROM `commerce_navigation_menus` m
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (
    SELECT 1 FROM `commerce_navigation_columns` c
    WHERE c.`menu_id` = m.`id` AND c.`column_key` = 'customer_service'
  );

INSERT INTO `commerce_navigation_columns` (`menu_id`, `column_key`, `heading`, `sort_order`)
SELECT m.`id`, 'company', 'Company', 20
FROM `commerce_navigation_menus` m
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (
    SELECT 1 FROM `commerce_navigation_columns` c
    WHERE c.`menu_id` = m.`id` AND c.`column_key` = 'company'
  );

-- Contact is the fourth footer column. It renders from
-- commerce_store_contact_details, not from navigation_items.
-- The heading still lives here so the footer builder can manage
-- it alongside the other three.
INSERT INTO `commerce_navigation_columns` (`menu_id`, `column_key`, `heading`, `sort_order`)
SELECT m.`id`, 'contact', 'Contact', 30
FROM `commerce_navigation_menus` m
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (
    SELECT 1 FROM `commerce_navigation_columns` c
    WHERE c.`menu_id` = m.`id` AND c.`column_key` = 'contact'
  );

-- ---------------------------------------------------------------
-- 4. Footer items, column by column.
-- Insertion is idempotent: the WHERE NOT EXISTS clause checks
-- by (menu_id, column_id, label).
-- ---------------------------------------------------------------

-- Quick Links
INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Home', '/', 0, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'quick_links'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Home');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Women', '/categories/women', 10, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'quick_links'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Women');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Men', '/categories/men', 20, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'quick_links'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Men');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Kids', '/categories/children', 30, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'quick_links'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Kids');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Bags & Accessories', '/categories/bags-accessories', 40, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'quick_links'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Bags & Accessories');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Beauty & Lifestyle', '/categories/beauty-lifestyle', 50, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'quick_links'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Beauty & Lifestyle');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Gifts', '/categories/gifts', 60, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'quick_links'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Gifts');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Sale', '/products?filter=sale', 70, 1
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'quick_links'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Sale');

-- Customer Service
INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Contact Us', '/pages/contact', 0, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'customer_service'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Contact Us');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Shipping Policy', '/pages/shipping', 10, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'customer_service'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Shipping Policy');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Return & Refunds', '/pages/returns', 20, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'customer_service'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Return & Refunds');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'FAQ', '/pages/faq', 30, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'customer_service'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'FAQ');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Track Your Order', '/account/orders', 40, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'customer_service'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Track Your Order');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Size Guide', '/pages/size-guide', 50, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'customer_service'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Size Guide');

-- Company
INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'About ANAYA GLOBAL', '/pages/about', 0, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'company'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'About ANAYA GLOBAL');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Impressum', '/legal/impressum', 10, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'company'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Impressum');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Terms & Conditions', '/legal/agb', 20, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'company'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Terms & Conditions');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Withdrawal Policy', '/legal/widerruf', 30, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'company'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Withdrawal Policy');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Privacy Policy', '/legal/datenschutz', 40, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'company'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Privacy Policy');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `url`, `sort_order`, `is_highlight`)
SELECT m.`id`, c.`id`, NULL, 'Cookie Policy', '/pages/cookies', 50, 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'company'
WHERE m.`slug` = 'footer'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i
    WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Cookie Policy');

-- ---------------------------------------------------------------
-- 5. Contact details. Structured rows, one per line item.
-- ---------------------------------------------------------------
INSERT INTO `commerce_store_contact_details` (`store_id`, `type`, `label`, `value`, `sort_order`)
SELECT s.`id`, 'email', 'Email', 'hello@anayaglobal.com', 0
FROM `commerce_stores` s
WHERE NOT EXISTS (SELECT 1 FROM `commerce_store_contact_details` d
  WHERE d.`store_id` = s.`id` AND d.`type` = 'email'
    AND d.`value` = 'hello@anayaglobal.com');

INSERT INTO `commerce_store_contact_details` (`store_id`, `type`, `label`, `value`, `sort_order`)
SELECT s.`id`, 'phone', 'Phone', '+254 700 000 000', 10
FROM `commerce_stores` s
WHERE NOT EXISTS (SELECT 1 FROM `commerce_store_contact_details` d
  WHERE d.`store_id` = s.`id` AND d.`type` = 'phone'
    AND d.`value` = '+254 700 000 000');

INSERT INTO `commerce_store_contact_details` (`store_id`, `type`, `label`, `value`, `sort_order`)
SELECT s.`id`, 'address', 'Address', 'Nairobi, Kenya', 20
FROM `commerce_stores` s
WHERE NOT EXISTS (SELECT 1 FROM `commerce_store_contact_details` d
  WHERE d.`store_id` = s.`id` AND d.`type` = 'address'
    AND d.`value` = 'Nairobi, Kenya');
