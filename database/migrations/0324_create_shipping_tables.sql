-- ============================================================
-- 0324_create_shipping_tables.sql
-- Shipping methods and their zone availability.
--
-- Design decisions (Stage 008K.5):
--   * A method is a reusable shipping option with a code
--     (stable identifier) and a label (customer-facing).
--   * Five method types: flat, free_over, weight_based,
--     price_based, local_pickup. Rate computation is per
--     type; parameters live in config_json.
--   * config_json shape depends on type. Documented in the
--     plan doc. Examples:
--       flat          {"rate": 5.00}
--       free_over     {"rate": 5.00, "threshold": 50.00}
--       weight_based  {"per_kg": 2.50, "min_charge": 5.00}
--       price_based   {"per_unit": 0.05, "min_charge": 5.00,
--                      "unit_size": 1.00}
--       local_pickup  {}
--   * A method is available in a country/region only if a
--     zone row matches. Absence of a zone row means the
--     method is not offered there.
--   * zone.region is NOT NULL DEFAULT '' so the unique key
--     uniq_com_ship_zones_method_country_region enforces
--     uniqueness across both country-wide and region-
--     specific zones. Same reasoning as commerce_tax_rates
--     state_code in 0322/0323.
--   * zone.rate_override, when set, replaces the method's
--     computed rate for that specific zone. Useful for
--     remote-region surcharges or nearby-region discounts.
--   * is_active = 0 hides a method without deleting it.
--     Order history references methods by code
--     (commerce_orders.shipping_method_key), so a disabled
--     method still renders correctly on old orders.
--   * ON DELETE CASCADE from store. Deleting a method
--     cascades its zones. The controller refuses to delete
--     a method referenced by orders in the last 30 days.
--
-- See docs/stages/008k.5_order-pricing-engine-plan.md for
-- the full design.
-- ============================================================

CREATE TABLE IF NOT EXISTS `commerce_shipping_methods` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`     BIGINT UNSIGNED NOT NULL,
  `code`         VARCHAR(60) NOT NULL,
  `label`        VARCHAR(120) NOT NULL,
  `description`  VARCHAR(255) NULL,
  `type`         ENUM('flat','free_over','weight_based',
                      'price_based','local_pickup') NOT NULL,
  `config_json`  JSON NULL,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_ship_methods_store_code` (`store_id`, `code`),
  KEY `idx_com_ship_methods_store_active`
    (`store_id`, `is_active`, `sort_order`),

  CONSTRAINT `fk_com_ship_methods_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_shipping_zones` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`       BIGINT UNSIGNED NOT NULL,
  `method_id`      BIGINT UNSIGNED NOT NULL,
  `country_code`   CHAR(2) NOT NULL,
  `region`         VARCHAR(60) NOT NULL DEFAULT '',
  `rate_override`  DECIMAL(18,4) NULL,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_ship_zones_method_country_region`
    (`method_id`, `country_code`, `region`),
  KEY `idx_com_ship_zones_lookup`
    (`store_id`, `country_code`, `region`),

  CONSTRAINT `fk_com_ship_zones_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_ship_zones_method`
    FOREIGN KEY (`method_id`) REFERENCES `commerce_shipping_methods` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
