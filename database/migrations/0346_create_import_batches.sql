-- ============================================================
-- 0346_create_import_batches.sql
--
-- Data import batches. Stage 016.
--
-- One row per CSV upload. The merchant picks an entity,
-- uploads a file, maps columns, validates, previews, and
-- commits. Each phase advances the status.
--
-- status lifecycle:
--
--   uploaded             file stored, no mapping yet
--   mapped               columns mapped, not validated
--   validated            rows parsed and checked, nothing
--                        written to the entity tables
--   committing           commit in progress
--   committed            commit finished, every valid row
--                        landed
--   partially_committed  commit finished, one or more
--                        chunks rolled back on error
--   rolled_back          a committed batch was reversed
--   cancelled            merchant abandoned the batch
--
-- mapping_json stores the merchant's column mapping:
-- an associative map of {entity_field => csv_header}.
--
-- rows_* columns are counters. rows_valid + rows_invalid
-- equals rows_total after validation. rows_created and
-- rows_updated are the counts the commit phase achieved.
--
-- The cascade deletes: a batch dies with its store, and
-- with the user who created it (SET NULL keeps the batch
-- but loses the attribution).
--
-- See docs/stages/016_data-import-plan.md.
-- ============================================================

CREATE TABLE `commerce_import_batches` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`           BIGINT UNSIGNED NOT NULL,
  `entity`             VARCHAR(60) NOT NULL,
  `filename`           VARCHAR(255) NOT NULL,
  `status`             ENUM('uploaded','mapped','validated',
                            'committing','committed',
                            'partially_committed','rolled_back',
                            'cancelled')
                       NOT NULL DEFAULT 'uploaded',
  `mapping_json`       JSON NULL,
  `rows_total`         INT UNSIGNED NOT NULL DEFAULT 0,
  `rows_valid`         INT UNSIGNED NOT NULL DEFAULT 0,
  `rows_invalid`       INT UNSIGNED NOT NULL DEFAULT 0,
  `rows_created`       INT UNSIGNED NOT NULL DEFAULT 0,
  `rows_updated`       INT UNSIGNED NOT NULL DEFAULT 0,
  `rows_failed_commit` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_by`         INT UNSIGNED NULL,
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `committed_at`       DATETIME NULL,
  `rolled_back_at`     DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_com_imp_batches_store_created` (`store_id`, `created_at`),
  KEY `idx_com_imp_batches_store_status`  (`store_id`, `status`),
  CONSTRAINT `fk_com_imp_batches_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_imp_batches_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `core_users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
