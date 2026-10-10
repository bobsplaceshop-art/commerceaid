-- ============================================================
-- 0012_seed_full_permission_vocabulary.sql
--
-- The complete permission vocabulary for the platform.
--
-- Modules:
--   core         - platform-level users, roles, settings, audit
--   commerce     - store configuration and dashboard
--   catalog      - products, categories, brands, attributes
--   inventory    - stock view, adjust, ledger, export
--   orders       - order view, order management
--   customers    - customer profile view, management
--   groups       - customer group view, management
--   approvals    - customer approval queue view, management
--   media        - media library view, upload, delete
--   review       - product review view, moderation
--   legal        - legal page view, management
--
-- Slugs follow {module}.{entity}.{action} per the naming
-- convention. The 'core' and 'commerce' prefixes are
-- explicit; the others are the module name alone.
--
-- Idempotent. Runs after 0002, 0008, 0010, 0011.
--
-- Also assigns a curated permission set to each seeded role:
--   super-admin     - every permission (safety net)
--   store-admin     - everything except user deletion and role management
--   catalog-manager - catalog, inventory, media, reviews, read-only users
--   order-manager   - orders, customers, approvals, read-only media
--   marketing       - catalog and customer view, media, reviews
--   support         - orders, customers, approvals, reviews (read-only)
-- ============================================================

-- ---------- core ----------
INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('core.users.view',      'core', 'View admin users'),
    ('core.users.create',    'core', 'Invite new admin users'),
    ('core.users.edit',      'core', 'Edit users, change roles, trigger password resets'),
    ('core.users.delete',    'core', 'Deactivate admin users'),
    ('core.roles.view',      'core', 'See roles and their permission sets'),
    ('core.roles.create',    'core', 'Create custom roles'),
    ('core.roles.edit',      'core', 'Edit role names, descriptions, and permissions'),
    ('core.roles.delete',    'core', 'Delete custom roles'),
    ('core.settings.manage', 'core', 'System settings and store configuration'),
    ('core.audit.view',      'core', 'Read the audit trail')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- commerce ----------
INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('commerce.store.view',     'commerce', 'See store configuration'),
    ('commerce.store.edit',     'commerce', 'Change store configuration'),
    ('commerce.dashboard.view', 'commerce', 'Access the admin dashboard'),
    ('commerce.setup.manage',   'commerce', 'Complete the setup wizard')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- catalog ----------
INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('catalog.products.view',        'catalog', 'See products'),
    ('catalog.products.create',      'catalog', 'Create products'),
    ('catalog.products.edit',        'catalog', 'Edit products and variations'),
    ('catalog.products.delete',      'catalog', 'Archive or delete products'),
    ('catalog.categories.view',      'catalog', 'See categories'),
    ('catalog.categories.create',    'catalog', 'Create categories'),
    ('catalog.categories.edit',      'catalog', 'Edit categories and category attributes'),
    ('catalog.categories.delete',    'catalog', 'Archive or delete categories'),
    ('catalog.brands.view',          'catalog', 'See brands'),
    ('catalog.brands.create',        'catalog', 'Create brands'),
    ('catalog.brands.edit',          'catalog', 'Edit brands'),
    ('catalog.brands.delete',        'catalog', 'Archive or delete brands'),
    ('catalog.attributes.view',      'catalog', 'See attributes and their values'),
    ('catalog.attributes.create',    'catalog', 'Create attributes'),
    ('catalog.attributes.edit',      'catalog', 'Edit attributes and their values'),
    ('catalog.attributes.delete',    'catalog', 'Delete attributes and their values')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- inventory ----------
INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('inventory.view',           'inventory', 'See stock levels'),
    ('inventory.adjust',         'inventory', 'Adjust stock and record movements'),
    ('inventory.movements.view', 'inventory', 'Read the movement ledger'),
    ('inventory.export',         'inventory', 'Export stock and movement data')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- orders ----------
INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('orders.view',   'orders', 'See orders in the admin'),
    ('orders.manage', 'orders', 'Change order status and add internal notes')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- customers ----------
INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('customers.view',   'customers', 'See customer profiles in the admin'),
    ('customers.manage', 'customers', 'Edit customer profiles, change group, disable account, manage addresses')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- groups ----------
INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('groups.view',   'groups', 'See customer groups and price lists'),
    ('groups.manage', 'groups', 'Create and edit customer groups, price lists, and net terms')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- approvals ----------
INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('approvals.view',   'approvals', 'See the customer approval queue'),
    ('approvals.manage', 'approvals', 'Approve, reject, and reset customer accounts')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- media ----------
INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('media.view',   'media', 'Browse the media library'),
    ('media.upload', 'media', 'Upload new files'),
    ('media.delete', 'media', 'Soft-delete media files')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- review ----------
INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('review.view',     'review', 'See product reviews in the admin'),
    ('review.moderate', 'review', 'Approve, reject, or delete product reviews')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- legal ----------
INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('legal.view',   'legal', 'See legal pages'),
    ('legal.manage', 'legal', 'Edit legal page content')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ============================================================
-- Role assignments
-- ============================================================

-- ---------- super-admin: everything ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r, `core_permissions` p
WHERE r.`slug` = 'super-admin';

-- ---------- store-admin: everything except core.users.delete and core.roles.* ----------
-- A store-admin can run a store day to day but cannot delete
-- other admins and cannot edit role definitions. That is the
-- boundary between "runs the store" and "runs the platform".
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` NOT IN (
    'core.users.delete',
    'core.roles.view',
    'core.roles.create',
    'core.roles.edit',
    'core.roles.delete'
)
WHERE r.`slug` = 'store-admin';

-- ---------- catalog-manager: catalog + inventory + media + reviews ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN (
    'commerce.dashboard.view',
    'commerce.store.view',
    'core.users.view',
    'catalog.products.view', 'catalog.products.create',
    'catalog.products.edit', 'catalog.products.delete',
    'catalog.categories.view', 'catalog.categories.create',
    'catalog.categories.edit', 'catalog.categories.delete',
    'catalog.brands.view', 'catalog.brands.create',
    'catalog.brands.edit', 'catalog.brands.delete',
    'catalog.attributes.view', 'catalog.attributes.create',
    'catalog.attributes.edit', 'catalog.attributes.delete',
    'inventory.view', 'inventory.adjust',
    'inventory.movements.view', 'inventory.export',
    'media.view', 'media.upload', 'media.delete',
    'review.view', 'review.moderate'
)
WHERE r.`slug` = 'catalog-manager';

-- ---------- order-manager: orders + customers + approvals ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN (
    'commerce.dashboard.view',
    'commerce.store.view',
    'orders.view', 'orders.manage',
    'customers.view', 'customers.manage',
    'approvals.view', 'approvals.manage',
    'media.view'
)
WHERE r.`slug` = 'order-manager';

-- ---------- marketing: catalog/customer view + media + reviews ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN (
    'commerce.dashboard.view',
    'commerce.store.view',
    'catalog.products.view',
    'catalog.categories.view',
    'catalog.brands.view',
    'customers.view',
    'review.view',
    'media.view', 'media.upload', 'media.delete'
)
WHERE r.`slug` = 'marketing';

-- ---------- support: read-only orders, customers, approvals, reviews ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN (
    'commerce.dashboard.view',
    'commerce.store.view',
    'orders.view',
    'customers.view',
    'approvals.view',
    'review.view',
    'media.view'
)
WHERE r.`slug` = 'support';
