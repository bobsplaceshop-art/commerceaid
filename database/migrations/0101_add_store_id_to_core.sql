-- ============================================================
-- 0101_add_store_id_to_core.sql
-- Multi-tenant scoping for shared core tables.
-- Recruit Aid leaves these NULL. Commerce Aid always sets them.
-- ============================================================

ALTER TABLE `core_audit_logs`
  ADD COLUMN `store_id` BIGINT UNSIGNED NULL AFTER `id`,
  ADD KEY `idx_core_al_store` (`store_id`);

ALTER TABLE `core_activities`
  ADD COLUMN `store_id` BIGINT UNSIGNED NULL AFTER `id`,
  ADD KEY `idx_core_act_store` (`store_id`);

ALTER TABLE `core_notification_rules`
  ADD COLUMN `store_id` BIGINT UNSIGNED NULL AFTER `id`,
  ADD KEY `idx_core_nr_store` (`store_id`);

-- core_sequence_counters has no `id` column and PK is on `name`.
-- We add store_id as a non-null column with default 0 (MySQL PKs
-- cannot include NULL) and expand the PK to (name, store_id).
ALTER TABLE `core_sequence_counters`
  ADD COLUMN `store_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `name`,
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (`name`, `store_id`);
