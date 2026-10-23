-- =====================================================================
-- Store pages: metadata for Page Builder pages.
-- =====================================================================
-- Until now, Page Builder pages existed only as rows in
-- commerce_page_blocks with a distinct page_slug. That model has
-- no place to store title, meta description, OG image, robots,
-- or publish status. Those are required for SEO, for the storefront
-- /pages/{slug} route, and for the page-settings editor.
--
-- This migration adds one row per page (slug, title, SEO fields,
-- status). The backfill seeds it from existing block rows so no
-- merchant loses a page.
-- =====================================================================

CREATE TABLE `commerce_store_pages` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`         BIGINT UNSIGNED NOT NULL,
  `slug`             VARCHAR(120) NOT NULL,
  `title`            VARCHAR(190) NOT NULL,
  `meta_description` VARCHAR(320) NULL,
  `og_image_file_id` INT UNSIGNED NULL,
  `status`           ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  `noindex`          TINYINT(1) NOT NULL DEFAULT 0,
  `published_at`     DATETIME NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                     ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_store_pages_store_slug` (`store_id`, `slug`),
  KEY `idx_store_pages_store_status` (`store_id`, `status`),
  CONSTRAINT `fk_store_pages_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_store_pages_og`
    FOREIGN KEY (`og_image_file_id`) REFERENCES `core_files` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill: one row per non-home page_slug present in blocks.
-- Derive a readable title from the slug ('about-us' -> 'About Us').
-- Mark everything published because existing block rows were live.
INSERT INTO `commerce_store_pages`
  (`store_id`, `slug`, `title`, `status`, `published_at`)
SELECT DISTINCT
  b.`store_id`,
  b.`page_slug`,
  CONCAT(UPPER(LEFT(REPLACE(b.`page_slug`, '-', ' '), 1)),
         SUBSTRING(REPLACE(b.`page_slug`, '-', ' '), 2)),
  'published',
  CURRENT_TIMESTAMP
FROM `commerce_page_blocks` b
WHERE b.`page_slug` <> 'home';
