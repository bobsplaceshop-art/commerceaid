-- =====================================================================
-- 0338_backfill_log_store_ids.sql
-- =====================================================================
--
-- Both core_activities and core_audit_logs already carry a
-- store_id column from migration 0101. The column was added
-- when multi-tenant scoping was introduced but was never
-- written by the loggers: ActivityLogger and
-- AuditLoggerMiddleware did not read the store context.
--
-- That gap is now closed (see the code changes in this
-- session). New rows carry the store id. Existing rows have
-- NULL. This migration backfills them to the only store on
-- record.
--
-- On a single-store install this is correct. On a multi-store
-- install the true store of a pre-existing row is not
-- recorded anywhere; the value assigned is what the operator
-- would expect - the only store that existed at the time.
--
-- core_activities is empty today (0 rows). The UPDATE is a
-- no-op on that table but is here for symmetry and for a
-- multi-install baseline where activities may exist.
-- =====================================================================

UPDATE `core_activities`
   SET `store_id` = (SELECT `id` FROM `commerce_stores` ORDER BY `id` ASC LIMIT 1)
 WHERE `store_id` IS NULL;

UPDATE `core_audit_logs`
   SET `store_id` = (SELECT `id` FROM `commerce_stores` ORDER BY `id` ASC LIMIT 1)
 WHERE `store_id` IS NULL;
