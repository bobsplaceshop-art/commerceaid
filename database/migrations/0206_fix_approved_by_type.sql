-- ============================================================
-- 0206_fix_approved_by_type.sql
-- The first attempt at migration 0205 partially applied before
-- failing on a FK type mismatch. commerce_customers was created
-- with approved_by BIGINT UNSIGNED. The corrected migration could
-- not retroactively fix it because CREATE TABLE IF NOT EXISTS
-- skips existing tables.
--
-- This migration corrects the column type to INT UNSIGNED so it
-- matches core_users.id.
-- ============================================================

ALTER TABLE `commerce_customers`
  MODIFY COLUMN `approved_by` INT UNSIGNED NULL;
