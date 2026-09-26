-- ============================================================
-- 0002_seed_roles_and_permissions.sql
-- Roles, permissions, and role-permission mappings.
--
-- Permission slugs follow {module}.{entity}.{action}.
-- The `module` column groups permissions for UI filtering.
-- ============================================================

-- ---------- Roles ----------
INSERT INTO `core_roles` (`name`, `slug`, `description`, `is_system`) VALUES
    ('Super Administrator', 'super-admin',     'Full platform access.', 1),
    ('Store Administrator', 'store-admin',     'Full access to a single store.', 0),
    ('Catalog Manager',     'catalog-manager', 'Products, categories, inventory.', 0),
    ('Order Manager',       'order-manager',   'Orders, fulfilment, refunds.', 0),
    ('Marketing Manager',   'marketing',       'Promotions, discounts, content.', 0),
    ('Customer Support',    'support',         'Customer and order assistance.', 0)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ---------- Permissions ----------
-- Columns: slug, module, description
INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('core.users.view',          'core', 'View admin users'),
    ('core.users.create',        'core', 'Add new admin users'),
    ('core.users.edit',          'core', 'Modify existing users'),
    ('core.users.delete',        'core', 'Archive or remove users'),
    ('core.settings.manage',     'core', 'System settings, notifications'),
    ('core.audit.view',          'core', 'Read the audit trail'),
    ('core.roles.manage',        'core', 'Roles and permissions'),

    ('commerce.store.view',      'commerce', 'See store configuration'),
    ('commerce.store.edit',      'commerce', 'Change store configuration'),
    ('commerce.dashboard.view',  'commerce', 'Access the admin dashboard'),
    ('commerce.setup.manage',    'commerce', 'Complete the setup wizard')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- Super admin gets every permission ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r, `core_permissions` p
WHERE r.`slug` = 'super-admin';

-- ---------- Store admin gets store + dashboard + setup ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN (
    'commerce.store.view',
    'commerce.store.edit',
    'commerce.dashboard.view',
    'commerce.setup.manage'
)
WHERE r.`slug` = 'store-admin';
