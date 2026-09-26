-- ============================================================
-- 0208_customer_approval_history.sql
-- Approval workflow history for commerce_customers.
--
-- Every state transition on commerce_customers.approval_status
-- (pending -> approved, approved -> pending, etc.) is recorded
-- here. This is a domain timeline, distinct from core_audit_logs
-- (which records HTTP requests for compliance).
--
-- Design:
--   * from_status is NULL on the initial creation event.
--   * to_status is always set.
--   * reason is optional admin text (admin-only visibility).
--   * actor_id is the user who performed the transition, or NULL
--     for system events (e.g. seeder, automated jobs).
--   * Rows are append-only. Never UPDATE or DELETE history.
--
-- Cascading deletes:
--   * Deleting a store cascades to its history.
--   * Deleting a customer cascades to their history.
--   * Deleting a user (core_users) sets actor_id to NULL but
--     preserves the history row.
--
-- FK types:
--   * store_id, customer_id -> BIGINT UNSIGNED (commerce_* PKs)
--   * actor_id              -> INT UNSIGNED    (core_users.id)
-- ============================================================

CREATE TABLE IF NOT EXISTS `commerce_customer_approval_history` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`    BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `from_status` VARCHAR(20) NULL,
  `to_status`   VARCHAR(20) NOT NULL,
  `reason`      VARCHAR(500) NULL,
  `actor_id`    INT UNSIGNED NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_com_cah_customer` (`customer_id`, `created_at`),
  KEY `idx_com_cah_store` (`store_id`, `created_at`),
  CONSTRAINT `fk_com_cah_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_cah_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `commerce_customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_cah_actor`
    FOREIGN KEY (`actor_id`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
