-- ============================================================
-- 0016_seed_newsletter_permissions.sql
--
-- Stage 008I. Adds the four newsletter permission slugs.
-- Same shape as 0008_seed_review_permissions.sql and
-- 0014_seed_return_permissions.sql.
-- ============================================================

INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('newsletter.subscribers.view',   'newsletter', 'See the subscriber list and individual consent records'),
    ('newsletter.subscribers.manage', 'newsletter', 'Unsubscribe, edit, and export subscribers'),
    ('newsletter.campaigns.view',     'newsletter', 'See campaigns and their stats'),
    ('newsletter.campaigns.manage',   'newsletter', 'Compose, edit, send, and delete campaigns')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- Super admin gets every permission ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r, `core_permissions` p
WHERE r.`slug` = 'super-admin';

-- ---------- Store admin gets all four ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN (
    'newsletter.subscribers.view',
    'newsletter.subscribers.manage',
    'newsletter.campaigns.view',
    'newsletter.campaigns.manage'
)
WHERE r.`slug` = 'store-admin';

-- ---------- Marketing Manager gets all four ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN (
    'newsletter.subscribers.view',
    'newsletter.subscribers.manage',
    'newsletter.campaigns.view',
    'newsletter.campaigns.manage'
)
WHERE r.`slug` = 'marketing';

-- ---------- Customer Support gets read-only ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN (
    'newsletter.subscribers.view',
    'newsletter.campaigns.view'
)
WHERE r.`slug` = 'support';
