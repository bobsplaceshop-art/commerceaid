-- ============================================================
-- 0209_add_site_type_to_stores.sql
-- Adds the site_type column to commerce_stores.
--
-- A store's site type determines:
--   - Which admin navigation groups are visible
--   - Which default theme is applied
--   - Which initial settings are written during onboarding
--   - Which modules are loaded (via the site type registry)
--
-- Values are keys from the SiteTypeRegistry. The initial value
-- is 'ecommerce' because the platform currently ships with one
-- site type (E-commerce). Future site types will be additional
-- registered keys.
--
-- Existing stores receive the default value ('ecommerce').
-- New stores get the value selected during onboarding.
--
-- Index: site_type is queried when the onboarding flow lists
-- stores by type, and when business logic needs to know which
-- site types exist in the installation.
-- ============================================================

ALTER TABLE `commerce_stores`
  ADD COLUMN `site_type` VARCHAR(50) NOT NULL DEFAULT 'ecommerce' AFTER `slug`,
  ADD KEY `idx_com_store_site_type` (`site_type`);
