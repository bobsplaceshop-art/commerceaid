-- ============================================================
-- 0011_seed_role_management_permissions.sql
--
-- Adds the four granular role-management permissions and
-- retires the single 'core.roles.manage' permission that
-- shipped with the original RBAC seed (0002).
--
-- Slugs follow the {module}.{entity}.{action} convention
-- defined in docs/NAMING-CONVENTION.md. The prefix is 'core'
-- matching the sibling user-management slugs
-- (core.users.view / .create / .edit / .delete).
--
-- Only super-admin gets these. Role editing is a platform-level
-- concern; delegating it to store-admin or a custom role would
-- let a delegate grant themselves or others elevated rights.
-- A future stage can add a curated subset if a merchant wants
-- a role auditor.
--
-- Idempotent. Safe to run on a fresh install (after 0002) and
-- on an existing install (removes the old slug once).
-- ============================================================

-- ---------- New granular permissions ----------
INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('core.roles.view',   'core', 'See roles and their permission sets'),
    ('core.roles.create', 'core', 'Create custom roles'),
    ('core.roles.edit',   'core', 'Edit role names, descriptions, and permissions'),
    ('core.roles.delete', 'core', 'Delete custom roles')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- Super admin gets every permission ----------
-- Repeated from the base seeder so this one is idempotent
-- on its own and does not depend on 0002 having run first.
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r, `core_permissions` p
WHERE r.`slug` = 'super-admin';

-- ---------- Retire the old 'core.roles.manage' slug ----------
-- The four granular permissions above replace it. Nothing in
-- the codebase references 'core.roles.manage' after Stage
-- 008K.4 ships.
--
-- The FK from core_role_permissions to core_permissions is
-- ON DELETE CASCADE, so any role that had .manage attached
-- loses the attachment cleanly.
--
-- Idempotent: deleting a non-existent row is a no-op.
DELETE FROM `core_permissions` WHERE `slug` = 'core.roles.manage';
