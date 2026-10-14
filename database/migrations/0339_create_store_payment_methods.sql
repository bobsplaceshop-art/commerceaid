-- ============================================================
-- 0339_create_store_payment_methods.sql
--
-- Per-store payment provider enablement. Stage 010.
--
-- One row per provider per store. Missing row = the
-- provider is not configured for the store = it is not
-- offered at checkout.
--
-- provider_key matches:
--   - commerce_orders.payment_provider_key
--   - the key() of the PaymentProvider implementation
--   - the registry's provider map
--
-- is_enabled is the store-level on/off toggle. It is
-- separate from isConfigured() on the provider, which
-- reflects whether the provider has valid credentials.
-- A provider is offered at checkout only when BOTH are
-- true:
--   - is_enabled = 1 in this table
--   - PaymentProvider::isConfigured() returns true
--
-- sort_order controls the display order in the checkout
-- method list. Lower numbers first.
--
-- Cascade is ON DELETE CASCADE from the store. Store
-- payment configuration dies with the store. Same shape
-- as every other commerce_* table that references
-- commerce_stores.
--
-- No FK to a payment method catalog table. The catalog
-- is the PaymentProviderRegistry's provider map in code,
-- not a database table. Adding a provider is a code
-- change, not a schema change.
--
-- See docs/stages/010_payments-deep-plan.md.
-- ============================================================

CREATE TABLE `commerce_store_payment_methods` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`     BIGINT UNSIGNED NOT NULL,
  `provider_key` VARCHAR(50) NOT NULL,
  `is_enabled`   TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_spm_store_key` (`store_id`, `provider_key`),
  KEY `idx_com_spm_store_enabled` (`store_id`, `is_enabled`, `sort_order`),
  CONSTRAINT `fk_com_spm_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
