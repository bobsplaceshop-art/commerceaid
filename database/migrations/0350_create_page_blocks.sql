-- ============================================================
-- 0350_create_page_blocks.sql
--
-- Page builder blocks. Stage 023.
--
-- One row per block on one page. Each block is a section
-- the merchant can add, remove, reorder, and configure.
--
-- page_slug identifies which page the block lives on.
--   'home'         the homepage
--   'about-us'     a custom page
--   'contact'      a custom page
--   etc.
--
-- block_type is the section type. The first shipped types:
--   'hero'           hero banner with headline + CTA
--   'product-grid'   a row of products
--   'category-grid'  the "Shop by Category" row
--   'promo-panel'    a single promotional tile
--   'banner'         a full-width banner
--   'rich-text'      arbitrary HTML
--   'newsletter'     the "Stay in Style" form
--
-- config_json carries block-type-specific settings. The
-- shape depends on block_type. Each block type declares
-- its own schema in the builder UI.
--
-- sort_order determines render order within a page.
-- is_enabled lets the merchant hide a block without
-- deleting it (and without losing its config).
--
-- (store_id, page_slug, sort_order) is the natural sort key.
-- The blocks for one page are fetched with a single query
-- and iterated in sort_order.
--
-- See docs/stages/023_page-builder-plan.md.
-- ============================================================

CREATE TABLE `commerce_page_blocks` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`    BIGINT UNSIGNED NOT NULL,
  `page_slug`   VARCHAR(120) NOT NULL,
  `block_type`  VARCHAR(40) NOT NULL,
  `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
  `config_json` JSON NOT NULL,
  `is_enabled`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_com_pb_store_page_sort` (`store_id`, `page_slug`, `sort_order`),
  KEY `idx_com_pb_store_type`      (`store_id`, `block_type`),
  CONSTRAINT `fk_com_pb_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
