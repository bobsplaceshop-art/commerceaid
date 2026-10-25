-- ============================================================
-- 0021_seed_navigation_permissions.sql
-- Adds navigation.view and navigation.manage.
-- ============================================================

INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('navigation.view',   'navigation', 'View Header, Footer, and Menu builders'),
    ('navigation.manage', 'navigation', 'Create, edit, and delete navigation items and menus')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- Super admin gets both ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r, `core_permissions` p
WHERE r.`slug` = 'super-admin'
  AND p.`module` = 'navigation';

-- ---------- Store admin gets both ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r, `core_permissions` p
WHERE r.`slug` = 'store-admin'
  AND p.`module` = 'navigation';
