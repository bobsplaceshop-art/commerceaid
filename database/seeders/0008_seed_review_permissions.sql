-- ============================================================
-- 0008_seed_review_permissions.sql
-- Adds review moderation permissions.
--
-- Same shape as 0002_seed_roles_and_permissions.sql:
-- idempotent inserts, super-admin gets every permission
-- automatically, specific roles get specific slugs.
--
-- Stage 008G.5.
-- ============================================================

-- ---------- New permissions ----------
INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('review.view',   'review', 'See product reviews in the admin'),
    ('review.moderate', 'review', 'Approve, reject, or delete product reviews')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- Super admin gets every permission ----------
-- Repeated from the base seeder so this one is idempotent
-- on its own and does not depend on the base seeder having
-- run first.
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r, `core_permissions` p
WHERE r.`slug` = 'super-admin';

-- ---------- Customer Support gets review.view ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN ('review.view')
WHERE r.`slug` = 'support';
