-- 0355_alter_page_drafts_parent_id_signed.sql
--
-- The draft table's parent_id must be signed.
-- Published blocks always have positive ids; draft children
-- reference a pending_add parent by a negative draft id
-- (e.g., -42). BIGINT UNSIGNED cannot store negatives; the
-- value truncates or throws in strict mode. This migration
-- changes the column to signed.
--
-- The published table's parent_id stays BIGINT UNSIGNED --
-- published parents are always positive ids.

ALTER TABLE `commerce_page_drafts`
  MODIFY COLUMN `parent_id` BIGINT NULL DEFAULT NULL;
