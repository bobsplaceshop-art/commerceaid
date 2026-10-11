-- ============================================================
-- 0017_seed_form_permissions.sql
--
-- Stage 009. Adds the four form permission slugs.
-- Same shape as 0016_seed_newsletter_permissions.sql.
-- ============================================================

INSERT INTO `core_permissions` (`slug`, `module`, `description`) VALUES
    ('forms.view',                  'forms', 'See the form list and the form editor'),
    ('forms.manage',                'forms', 'Create, edit, publish, archive, and delete forms'),
    ('forms.submissions.view',      'forms', 'See submissions and export them as CSV'),
    ('forms.submissions.manage',    'forms', 'Change submission status, add notes, and delete submissions')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ---------- Super admin gets every permission ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r, `core_permissions` p
WHERE r.`slug` = 'super-admin'
  AND p.`module` = 'forms';

-- ---------- Store admin gets all four ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN (
    'forms.view',
    'forms.manage',
    'forms.submissions.view',
    'forms.submissions.manage'
)
WHERE r.`slug` = 'store-admin';

-- ---------- Marketing Manager gets all four ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN (
    'forms.view',
    'forms.manage',
    'forms.submissions.view',
    'forms.submissions.manage'
)
WHERE r.`slug` = 'marketing';

-- ---------- Customer Support gets submissions view ----------
INSERT IGNORE INTO `core_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `core_roles` r
JOIN `core_permissions` p ON p.`slug` IN (
    'forms.submissions.view'
)
WHERE r.`slug` = 'support';
