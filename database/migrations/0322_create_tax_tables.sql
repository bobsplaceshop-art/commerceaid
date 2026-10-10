-- ============================================================
-- 0322_create_tax_tables.sql
-- Tax classes and rates.
--
-- Design decisions (Stage 008K.5):
--   * A tax class is a bucket (Standard, Reduced, Zero).
--     A product without an assigned tax_class_id uses the
--     store's default class.
--   * Exactly one class per store has is_default = 1.
--     Enforced in code, not in MySQL.
--   * A rate belongs to exactly one class and one
--     (country_code, state_code) pair. The unique key
--     enforces that.
--   * state_code is nullable. NULL means "this rate
--     applies to any state in this country". A rate with
--     a non-null state_code overrides the null-state rate
--     for that subdivision.
--   * is_inclusive says whether the product price already
--     contains the tax. Germany (B2C): true, per PAngV § 4.
--     Most B2B and US tax regimes: false.
--   * is_shipping_taxable says whether the shipping charge
--     is added to the taxable base for this rate. Germany:
--     true when shipping is separately listed.
--   * priority is reserved for a future override mechanism.
--     Not used in v1 beyond ordering.
--   * ON DELETE CASCADE from the class. Deleting a class
--     removes its rates. The controller refuses to delete a
--     class that has products assigned or orders in the
--     last N days referencing it.
--
-- See docs/stages/008k.5_order-pricing-engine-plan.md for
-- the full design.
-- ============================================================

CREATE TABLE IF NOT EXISTS `commerce_tax_classes` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`    BIGINT UNSIGNED NOT NULL,
  `name`        VARCHAR(80) NOT NULL,
  `is_default`  TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_tax_classes_store_name` (`store_id`, `name`),
  KEY `idx_com_tax_classes_store_default` (`store_id`, `is_default`),

  CONSTRAINT `fk_com_tax_classes_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_tax_rates` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`             BIGINT UNSIGNED NOT NULL,
  `tax_class_id`         BIGINT UNSIGNED NOT NULL,
  `country_code`         CHAR(2) NOT NULL,
  `state_code`           VARCHAR(10) NULL,
  `rate_percent`         DECIMAL(6,3) NOT NULL,
  `is_inclusive`         TINYINT(1) NOT NULL DEFAULT 0,
  `is_shipping_taxable`  TINYINT(1) NOT NULL DEFAULT 0,
  `priority`             INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_tax_rates_store_class_country_state`
    (`store_id`, `tax_class_id`, `country_code`, `state_code`),
  KEY `idx_com_tax_rates_lookup`
    (`store_id`, `country_code`, `state_code`, `tax_class_id`),

  CONSTRAINT `fk_com_tax_rates_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_tax_rates_class`
    FOREIGN KEY (`tax_class_id`) REFERENCES `commerce_tax_classes` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
