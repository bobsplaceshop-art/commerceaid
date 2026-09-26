-- ============================================================
-- 0202_create_inventory.sql
-- Inventory: current stock levels + immutable movement ledger.
--
-- Design decisions (Stage 006A):
--   * variant_id = 0 sentinel for simple products (not NULL) so
--     the UNIQUE KEY works. MySQL PK/unique treats NULL as distinct,
--     so a product with multiple NULL variant rows would be possible.
--     Sentinel 0 makes the tuple deterministic.
--   * quantity, reserved, available: available is GENERATED as
--     quantity - reserved. Never written directly.
--   * Movements are append-only. No UPDATE, no DELETE. Corrections
--     happen via compensating movements.
-- ============================================================

-- ------------------------------------------------------------
-- Current inventory (one row per sellable unit)
-- For simple products: variant_id = 0
-- For variable products: variant_id = the variant id
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commerce_inventory` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`              BIGINT UNSIGNED NOT NULL,
  `product_id`            BIGINT UNSIGNED NOT NULL,
  `variant_id`            BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `quantity`              INT NOT NULL DEFAULT 0,
  `reserved`              INT NOT NULL DEFAULT 0,
  `available`             INT GENERATED ALWAYS AS (`quantity` - `reserved`) STORED,
  `low_stock_threshold`   INT UNSIGNED NULL,
  `backorders_allowed`    TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_inv_unit` (`store_id`, `product_id`, `variant_id`),
  KEY `idx_com_inv_low` (`store_id`, `quantity`),
  KEY `idx_com_inv_product` (`product_id`),
  CONSTRAINT `fk_com_inv_store` FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_inv_product` FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Immutable movement ledger. Every stock change is a new row.
-- Never UPDATE. Never DELETE. Corrections are new rows with
-- movement_type = 'adjustment' and a note.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commerce_inventory_movements` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`          BIGINT UNSIGNED NOT NULL,
  `product_id`        BIGINT UNSIGNED NOT NULL,
  `variant_id`        BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `movement_type`     ENUM(
                        'opening',
                        'adjustment',
                        'sale',
                        'return',
                        'refund',
                        'cancel',
                        'transfer_in',
                        'transfer_out',
                        'damage',
                        'stocktake'
                      ) NOT NULL,
  `quantity_delta`    INT NOT NULL,
  `quantity_after`    INT NOT NULL,
  `reference_type`    VARCHAR(40) NULL,
  `reference_id`      BIGINT UNSIGNED NULL,
  `reason`            VARCHAR(500) NULL,
  `created_by`        INT UNSIGNED NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_com_imv_unit`      (`store_id`, `product_id`, `variant_id`, `created_at`),
  KEY `idx_com_imv_reference` (`reference_type`, `reference_id`),
  KEY `idx_com_imv_type`      (`store_id`, `movement_type`, `created_at`),
  KEY `idx_com_imv_created`   (`store_id`, `created_at`),
  CONSTRAINT `fk_com_imv_store`   FOREIGN KEY (`store_id`)   REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_imv_product` FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_imv_user`    FOREIGN KEY (`created_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
