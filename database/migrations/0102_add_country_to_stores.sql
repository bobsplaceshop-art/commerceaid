-- ============================================================
-- 0102_add_country_to_stores.sql
-- Adds the country code to commerce_stores. Country drives tax
-- and shipping reference defaults, and is a mandatory field in
-- the setup wizard.
--
-- ISO 3166-1 alpha-2 (2 uppercase letters, e.g. KE, DE, US).
-- Nullable for existing rows; new stores set it during setup.
-- ============================================================

ALTER TABLE `commerce_stores`
  ADD COLUMN `country_code` CHAR(2) NULL AFTER `timezone`,
  ADD KEY `idx_com_store_country` (`country_code`);
