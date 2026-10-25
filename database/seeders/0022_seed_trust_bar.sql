-- Create a pseudo-column for the header trust bar.
INSERT INTO `commerce_navigation_columns` (`menu_id`, `column_key`, `heading`, `sort_order`)
SELECT m.`id`, 'trust_bar', 'Trust Bar', 0
FROM `commerce_navigation_menus` m
WHERE m.`slug` = 'header'
  AND NOT EXISTS (
    SELECT 1 FROM `commerce_navigation_columns` c
    WHERE c.`menu_id` = m.`id` AND c.`column_key` = 'trust_bar'
  );

-- Insert the 4 items.
INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `icon`, `subtitle`, `url`, `sort_order`)
SELECT m.`id`, c.`id`, NULL, 'Worldwide Shipping', 'bi-globe2', 'Global reach', '/', 0
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'trust_bar'
WHERE m.`slug` = 'header'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Worldwide Shipping');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `icon`, `subtitle`, `url`, `sort_order`)
SELECT m.`id`, c.`id`, NULL, 'Secure Payment', 'bi-shield-check', '100% protected', '/', 10
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'trust_bar'
WHERE m.`slug` = 'header'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Secure Payment');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `icon`, `subtitle`, `url`, `sort_order`)
SELECT m.`id`, c.`id`, NULL, 'Fast & Safe Delivery', 'bi-truck', 'On time, every time', '/', 20
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'trust_bar'
WHERE m.`slug` = 'header'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = 'Fast & Safe Delivery');

INSERT INTO `commerce_navigation_items`
  (`menu_id`, `column_id`, `parent_id`, `label`, `icon`, `subtitle`, `url`, `sort_order`)
SELECT m.`id`, c.`id`, NULL, '24/7 Support', 'bi-headset', 'Here for you', '/', 30
FROM `commerce_navigation_menus` m
JOIN `commerce_navigation_columns` c ON c.`menu_id` = m.`id` AND c.`column_key` = 'trust_bar'
WHERE m.`slug` = 'header'
  AND NOT EXISTS (SELECT 1 FROM `commerce_navigation_items` i WHERE i.`menu_id` = m.`id` AND i.`column_id` = c.`id` AND i.`label` = '24/7 Support');
