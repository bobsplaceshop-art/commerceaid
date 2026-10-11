-- ============================================================
-- 0014_seed_return_permissions.sql
--
-- Adds the two permission slugs the admin returns queue
-- (Stage 008H, Session 3) needs.
--
-- Shape follows 0008_seed_review_permissions.sql:
--   - idempotent INSERT ... ON DUPLICATE KEY UPDATE
--   - super-admin gets every permission automatically
--   - specific roles get specific slugs
--
-- Stage 008H. See docs/ROADMAP.md and
-- docs/stages/008h_returns-refunds-plan.md.
-- ============================================================

-- ---------- New permissions ----------
INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('returns.view',     'returns', 'See the customer return queue'),
    ('returns.moderate', 'returns', 'Approve, reject, receive, and refund returns')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- Super admin gets every permission ----------
-- Repeated so this seeder is idempotent on its own and does
-- not depend on the base seeder having run first.
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r, `core_permissions` p
WHERE r.`slug` = 'super-admin';

-- ---------- Store admin gets both ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN (
    'returns.view',
    'returns.moderate'
)
WHERE r.`slug` = 'store-admin';

-- ---------- Customer Support gets view-only ----------
-- Support staff need to look up the status of a return
-- when a customer contacts them, but should not be able
-- to approve, reject, or refund.
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN ('returns.view')
WHERE r.`slug` = 'support';
