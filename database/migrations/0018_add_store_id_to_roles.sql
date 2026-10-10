-- ============================================================
-- 0018_add_store_id_to_roles.sql
--
-- Multi-store scoping for roles.
--
-- Core roles (the seeded system roles and any global custom
-- role) have store_id = NULL. They are visible to every store
-- and apply on every store.
--
-- Store-scoped custom roles have store_id = the owning store.
-- They are visible only to admins of that store.
--
-- The seeded roles in 0002 and the store-admin role in 0010
-- are global by design: a super-admin role that only existed
-- on one store would leave every other store without a
-- recovery path.
--
-- Type: BIGINT UNSIGNED matches commerce_stores.id.
--
-- The Core tables (core_audit_logs, core_activities,
-- core_notification_rules, core_sequence_counters) added by
-- migration 0101 use the same type for the same reason. No
-- FK constraint is added, matching that precedent.
--
-- Index: the roles list filters by
--   store_id IN (NULL, current_store_id)
-- and the composite is common enough to warrant its own index.
-- ============================================================

ALTER TABLE `core_roles`
    ADD COLUMN `store_id` BIGINT UNSIGNED NULL AFTER `id`,
    ADD KEY `idx_core_roles_store` (`store_id`);
