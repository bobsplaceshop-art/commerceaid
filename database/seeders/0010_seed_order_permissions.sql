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
    ('orders.view',       'orders',    'See orders in the admin'),
    ('orders.manage',     'orders',    'Change order status and add internal notes'),
    ('customers.view',    'customers', 'See customer profiles in the admin'),
    ('customers.manage',  'customers', 'Edit customer profiles, change group, disable account, manage addresses')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- Super admin gets every permission ----------
-- Repeated from the base seeder so this one is idempotent
-- on its own and does not depend on the base seeder having
-- run first.
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r, `core_permissions` p
WHERE r.`slug` = 'super-admin';

-- ---------- Store admin gets orders.view, orders.manage,
-- customers.view, customers.manage ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN (
    'orders.view', 'orders.manage',
    'customers.view', 'customers.manage'
)
WHERE r.`slug` = 'store-admin';

-- ---------- Order manager gets orders.view, orders.manage,
-- customers.view, customers.manage ----------
-- Order managers need to edit customer details (fix a typo in
-- a name, change the group for a phone-in order) and manage
-- addresses when talking a customer through a delivery.
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN (
    'orders.view', 'orders.manage',
    'customers.view', 'customers.manage'
)
WHERE r.`slug` = 'order-manager';

-- ---------- Customer Support gets orders.view and customers.view ----------
-- Support staff read orders and customer profiles to answer
-- questions. They do not get manage - editing profiles,
-- changing groups, disabling accounts, and managing
-- addresses are a manager's call.
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN ('orders.view', 'customers.view')
WHERE r.`slug` = 'support';
