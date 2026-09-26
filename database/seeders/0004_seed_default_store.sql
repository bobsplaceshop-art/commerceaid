-- ============================================================
-- 0004_seed_default_store.sql
-- Creates the default Anaya Global store if none exists.
-- The setup wizard will prompt the admin to complete the
-- mandatory fields (name, slug, currency, timezone, country).
-- ============================================================

INSERT INTO `commerce_stores`
    (`slug`, `name`, `locale`, `timezone`, `country_code`, `theme_slug`, `status`)
SELECT
    'anaya',
    'Anaya Global',
    'en_KE',
    'Africa/Nairobi',
    'KE',
    'modern',
    'active'
WHERE NOT EXISTS (SELECT 1 FROM `commerce_stores`);
