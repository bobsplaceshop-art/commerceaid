-- ============================================================
-- 0314_create_media_meta.sql
-- Image-specific metadata for uploaded files.
--
-- core_files stores file-level data: mime, size, sha256,
-- storage path. It does NOT store image dimensions, alt text,
-- or upload context. Those are image-specific - a PDF has no
-- dimensions, a CSV has no alt text.
--
-- This table holds them. One row per file that has image
-- metadata. Absent row = non-image file, or legacy file
-- uploaded before this stage.
--
-- Design decisions (Stage 008G.4a):
--   * file_id is INT UNSIGNED to match core_files.id, which
--     is INT UNSIGNED. See Pattern 17 in docs/REMEMBER.md.
--   * UNIQUE on file_id enforces one metadata row per file.
--     A file either has image metadata or it does not.
--   * ON DELETE CASCADE from core_files. A deleted file takes
--     its metadata. No orphan rows.
--   * width and height are NULL-able so non-image files could
--     theoretically have a meta row without dimensions. In
--     practice, only images get a meta row.
--   * aspect_ratio is DECIMAL(6,3) - enough for 4000.000 at
--     the high end (width / height). Stored explicitly so
--     queries that filter by aspect don't recompute.
--   * context is a free string, indexed. Values like
--     'store-logo', 'product-image', 'category-image',
--     'general'. Used by the media page to filter.
--   * alt_text is optional. Used by the storefront for
--     accessibility and by admin previews.
--   * No soft delete. A deleted file removes its meta via
--     cascade.
--
-- See docs/stages/008g.4a_upload-core-plan.md.
-- ============================================================

CREATE TABLE IF NOT EXISTS `core_media_meta` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_id`       INT UNSIGNED NOT NULL,
  `width`         INT UNSIGNED NULL,
  `height`        INT UNSIGNED NULL,
  `aspect_ratio`  DECIMAL(6,3) NULL,
  `alt_text`      VARCHAR(500) NULL,
  `context`       VARCHAR(60) NOT NULL DEFAULT 'general',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cmm_file` (`file_id`),
  KEY `idx_cmm_context` (`context`),

  CONSTRAINT `fk_cmm_file`
    FOREIGN KEY (`file_id`) REFERENCES `core_files` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
