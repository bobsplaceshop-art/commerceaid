-- ============================================================
-- 0315_add_media_meta_credit.sql
-- Adds a credit column to core_media_meta.
--
-- UrhG § 13 (Germany) grants the author of a work the right
-- to be attributed. When a customer uploads a photograph or
-- illustration created by someone else, the correct practice
-- is to record the creator's name alongside the file.
--
-- The column is optional. Most uploads (a customer's own
-- phone photo, a logo the business owns) do not need a
-- credit. The column exists so the field is available if and
-- when the media page exposes it in the admin.
--
-- This is a follow-up migration rather than an amendment to
-- 0314_create_media_meta.sql. Per docs/NAMING-CONVENTION.md
-- §16, migrations are immutable once applied. Even in dev,
-- the discipline is the same: never edit a migration that
-- has been run.
--
-- See docs/stages/008g.4a_upload-core-plan.md.
-- ============================================================

ALTER TABLE `core_media_meta`
  ADD COLUMN `credit` VARCHAR(255) NULL
    AFTER `alt_text`;
