-- =====================================================================
-- Stage 008E.1 - Store settings
-- =====================================================================
--
-- Per-store key-value settings table. The storage layer for
-- every configurable toggle, notice, and preference that
-- belongs to a store.
--
-- Key-value rather than columns on commerce_stores: adding a
-- new toggle requires one line in
-- StoreSettingDefinitions::DEFINITIONS and no migration. The
-- shape matches the future Feature Flag System's storage
-- layer, so migrating from settings to flags is a code
-- change, not a schema change.
--
-- Column is key_name (not key) because key is a reserved
-- word in MySQL 8.
--
-- See docs/stages/008e.1_german-compliance-plan.md.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `commerce_store_settings` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`   BIGINT UNSIGNED NOT NULL,
  `key_name`   VARCHAR(120) NOT NULL,
  `value`      TEXT NULL,
  `updated_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_ss_store_key` (`store_id`, `key_name`),
  KEY `idx_com_ss_store` (`store_id`),

  CONSTRAINT `fk_com_ss_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,

  CONSTRAINT `fk_com_ss_user`
    FOREIGN KEY (`updated_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
