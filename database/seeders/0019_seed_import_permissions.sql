-- ============================================================
-- 0019_seed_import_permissions.sql
--
-- Stage 016. Adds the one import permission slug.
-- Same shape as 0017_seed_form_permissions.sql.
-- ============================================================

INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('catalog.import', 'catalog', 'Upload CSV files to bulk-import catalog entities')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- Super admin gets the permission ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r, `core_permissions` p
WHERE r.`slug` = 'super-admin'
  AND p.`module` = 'catalog';

-- ---------- Store admin gets the permission ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` = 'catalog.import'
WHERE r.`slug` = 'store-admin';

-- ---------- Catalog manager gets the permission ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` = 'catalog.import'
WHERE r.`slug` = 'catalog-manager';
