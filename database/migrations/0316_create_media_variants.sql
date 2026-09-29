-- ============================================================
-- 0316_create_media_variants.sql
-- Size variants of uploaded images.
--
-- A single source image is never served to the storefront.
-- Every image uploaded through the media pipeline generates a
-- set of size variants at lower quality, and the storefront
-- requests the variant that fits the display context.
--
-- One row per (source_file, variant_name, format). Each variant
-- is itself a file - a new row in core_files with its own
-- storage path, hash, and size. This table is the map from a
-- source to its derivatives.
--
-- Design decisions (Stage 008G.4a):
--   * Both FKs are INT UNSIGNED to match core_files.id.
--     See Pattern 17 in docs/REMEMBER.md.
--   * Two FKs to the same table (core_files): one for the
--     source, one for the variant file itself. Named
--     distinctly (fk_cmv_source, fk_cmv_variant_file) so the
--     DDL is unambiguous.
--   * ON DELETE CASCADE on both. Deleting the source removes
--     the variant link rows. Deleting a variant file removes
--     its link row too. Both directions cascade cleanly.
--     Physical file cleanup happens in FileService (soft
--     delete first, purge later).
--   * UNIQUE on (source, name, format). Prevents duplicate
--     generation if a re-upload of an identical source is
--     attempted. FileService dedups by SHA-256, so a
--     re-upload returns the existing file; the UNIQUE key
--     then makes the variant insert a no-op rather than an
--     error.
--   * format is VARCHAR(10), not ENUM. Allows adding new
--     formats (avif, jxl) without a schema change. Values in
--     use: 'webp', 'jpeg', 'png'.
--   * width and height are NOT NULL. Every variant has real
--     dimensions by construction.
--
-- See docs/stages/008g.4a_upload-core-plan.md, "Image Variant
-- Pipeline" section.
-- ============================================================

CREATE TABLE IF NOT EXISTS `core_media_variants` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_file_id`  INT UNSIGNED NOT NULL,
  `variant_file_id` INT UNSIGNED NOT NULL,
  `variant_name`    VARCHAR(40) NOT NULL,
  `width`           INT UNSIGNED NOT NULL,
  `height`          INT UNSIGNED NOT NULL,
  `format`          VARCHAR(10) NOT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cmv_source_name_format`
    (`source_file_id`, `variant_name`, `format`),
  KEY `idx_cmv_source` (`source_file_id`),
  KEY `idx_cmv_variant_file` (`variant_file_id`),

  CONSTRAINT `fk_cmv_source`
    FOREIGN KEY (`source_file_id`) REFERENCES `core_files` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_cmv_variant_file`
    FOREIGN KEY (`variant_file_id`) REFERENCES `core_files` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
