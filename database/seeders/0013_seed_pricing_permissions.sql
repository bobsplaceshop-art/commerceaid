-- ============================================================
-- 0013_seed_pricing_permissions.sql
-- Adds admin permissions for the three pricing subsystems
-- shipped in Stage 008K.5: coupons, tax, shipping.
--
-- Same shape as 0008 (reviews), 0010 (orders), 0011 (roles):
-- idempotent inserts, super-admin gets every permission
-- automatically, specific roles get specific slugs.
--
-- Grant policy:
--   super-admin  all eight
--   store-admin  all eight (a store admin runs the store's
--                pricing end to end)
--
-- No other seeded role gets pricing permissions by default.
-- A merchant can grant subsets through custom roles.
-- ============================================================

-- ---------- New permissions ----------
INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('coupons.view',     'coupons',  'See coupons'),
    ('coupons.create',   'coupons',  'Create coupons'),
    ('coupons.edit',     'coupons',  'Edit coupons'),
    ('coupons.delete',   'coupons',  'Delete or disable coupons'),
    ('tax.view',         'tax',      'See tax classes and rates'),
    ('tax.manage',       'tax',      'Create and edit tax classes and rates'),
    ('shipping.view',    'shipping', 'See shipping methods and zones'),
    ('shipping.manage',  'shipping', 'Create and edit shipping methods and zones')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- Super admin gets every permission ----------
-- Repeated from the base seeder so this one is idempotent
-- on its own and does not depend on the base seeder having
-- run first.
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r, `core_permissions` p
WHERE r.`slug` = 'super-admin';

-- ---------- Store admin gets all eight pricing slugs ----------
-- A store admin configures the store's coupons, tax rates,
-- and shipping methods. That is core to running the store.
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN (
    'coupons.view', 'coupons.create', 'coupons.edit', 'coupons.delete',
    'tax.view', 'tax.manage',
    'shipping.view', 'shipping.manage'
)
WHERE r.`slug` = 'store-admin';
