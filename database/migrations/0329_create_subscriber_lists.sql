-- ============================================================
-- 0329_create_subscriber_lists.sql
--
-- Named subscriber lists. Stage 008I.
--
-- The footer signup form joins the store's default list
-- (is_default = 1). The admin can create more
-- (Newsletter, Product updates, Promotions, etc.).
--
-- Exactly one list per store carries is_default = 1.
-- Enforced in code, not MySQL. Same pattern as
-- commerce_tax_classes.is_default.
--
-- See docs/stages/008i_newsletter-plan.md.
-- ============================================================

CREATE TABLE `commerce_subscriber_lists` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`    BIGINT UNSIGNED NOT NULL,
  `name`        VARCHAR(80) NOT NULL,
  `slug`        VARCHAR(80) NOT NULL,
  `description` VARCHAR(255) NULL,
  `is_default`  TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_sub_lists_store_slug` (`store_id`, `slug`),
  KEY `idx_com_sub_lists_store` (`store_id`),
  CONSTRAINT `fk_com_sub_lists_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
