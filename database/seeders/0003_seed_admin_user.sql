-- ============================================================
-- 0003_seed_admin_user.sql
-- Creates the default admin user if no users exist.
-- Credentials:
--   Email:    admin@anayaglobal.test
--   Password: ChangeMe!2026
-- The setup wizard prompts a password change on first login.
-- ============================================================

INSERT INTO `core_users`
    (`name`, `email`, `password_hash`, `status`, `is_client_user`, `timezone`, `locale`, `theme_preference`)
SELECT
    'Anaya Admin',
    'admin@anayaglobal.test',
    '$argon2id$v=19$m=65536,t=4,p=1$L2RFUTVTdEpMLmtjOVhHLg$fFos/TMysXaMorGSyeyqRyESmjeVAsg/9yZxZKrIj9w',
    'active',
    0,
    'Africa/Nairobi',
    'en',
    'system'
WHERE NOT EXISTS (SELECT 1 FROM `core_users` WHERE `email` = 'admin@anayaglobal.test');

-- Attach the super-admin role
INSERT IGNORE INTO `core_user_roles` (`user_id`, `role_id`)
SELECT u.`id`, r.`id`
FROM `core_users` u, `core_roles` r
WHERE u.`email` = 'admin@anayaglobal.test'
  AND r.`slug` = 'super-admin';
