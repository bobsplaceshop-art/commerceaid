-- ============================================================
-- 0322b_fix_tax_state_code_default.sql
-- Normalize state_code on commerce_tax_rates to NOT NULL
-- DEFAULT '' so the unique key
-- uniq_com_tax_rates_store_class_country_state
-- enforces uniqueness across the whole table.
--
-- Why this matters: MySQL treats NULL as distinct in a
-- UNIQUE key. With state_code NULL, two rates with the
-- same (store_id, tax_class_id, country_code) but
-- state_code = NULL would both be accepted, and the
-- resolver would pick one arbitrarily. That produces the
-- wrong tax rate in rare cases - a compliance issue, not
-- just a data-quality issue.
--
-- With state_code NOT NULL DEFAULT '', an empty string
-- means "no state" (country-wide rate), and the unique
-- key prevents duplicates across both the country-wide
-- row and any state-specific rows.
--
-- Follow-up to 0322. Same stage.
-- ============================================================

-- 1. Update any existing NULLs to ''.
UPDATE `commerce_tax_rates` SET `state_code` = '' WHERE `state_code` IS NULL;

-- 2. Change the column to NOT NULL DEFAULT ''.
ALTER TABLE `commerce_tax_rates`
    MODIFY COLUMN `state_code` VARCHAR(10) NOT NULL DEFAULT '';
