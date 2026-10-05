-- ============================================================
-- 0010_seed_order_permissions.sql
-- Adds admin order permissions.
--
-- Same shape as 0008_seed_review_permissions.sql: idempotent
-- inserts, super-admin gets every permission automatically,
-- specific roles get specific slugs.
--
-- Stage 008K.1.
-- ============================================================

-- ---------- New permissions ----------
INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('orders.view',      'orders',    'See orders in the admin'),
    ('orders.manage',    'orders',    'Change order status and add internal notes'),
    ('customers.view',   'customers', 'See customer profiles in the admin')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- Super admin gets every permission ----------
-- Repeated from the base seeder so this one is idempotent
-- on its own and does not depend on the base seeder having
-- run first.
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r, `core_permissions` p
WHERE r.`slug` = 'super-admin';

-- ---------- Store admin gets orders.view, orders.manage, customers.view ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN ('orders.view', 'orders.manage', 'customers.view')
WHERE r.`slug` = 'store-admin';

-- ---------- Order manager gets orders.view and orders.manage ----------
-- Also customers.view: order managers need to see who placed
-- the order.
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN ('orders.view', 'orders.manage', 'customers.view')
WHERE r.`slug` = 'order-manager';

-- ---------- Customer Support gets orders.view and customers.view ----------
-- Support staff need to look at orders and customer profiles
-- to answer questions. They do not get manage - changing
-- status, adding notes, and approving accounts are a
-- manager's call.
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN ('orders.view', 'customers.view')
WHERE r.`slug` = 'support';
