-- ============================================================
-- 0015_seed_newsletter_defaults.sql
--
-- Stage 008I. Seeds the default subscriber list per store.
--
-- Does NOT seed the newsletter store settings. Those follow
-- the code-declared-defaults pattern: the services call
-- StoreSettingsRepository::bool('newsletter.double_opt_in', true)
-- and similar, and the row is created only when the merchant
-- changes the value through the admin.
--
-- See docs/stages/008i_newsletter-plan.md.
-- ============================================================

-- One 'Newsletter' list per store, marked as default.
-- The footer signup form joins this list.
-- Idempotent: the UNIQUE key on (store_id, slug) makes the
-- second run a no-op.
INSERT IGNORE INTO `commerce_subscriber_lists`
    (`store_id`, `name`, `slug`, `description`, `is_default`)
SELECT
    s.`id`,
    'Newsletter',
    'newsletter',
    'General newsletter. The signup form in the footer joins this list.',
    1
FROM `commerce_stores` s;
