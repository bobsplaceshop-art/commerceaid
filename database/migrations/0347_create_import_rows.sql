-- ============================================================
-- 0347_create_import_rows.sql
--
-- Data import rows. Stage 016.
--
-- One row per CSV row in a batch. The row table is the
-- per-row record:
--
--   - its natural key (for idempotency: the SKU, slug, or
--     email that identifies the entity),
--   - its parsed payload (the CSV row as associative JSON),
--   - its previous values (for rollback of an update),
--   - the entity id it created or updated,
--   - its status, and
--   - the validation or commit error if any.
--
-- status lifecycle:
--
--   pending     row parsed but not yet validated
--   valid       passed validation, will commit
--   invalid     failed validation, will not commit
--   committed   created or updated the entity row
--   rolled_back the commit was reversed
--
-- previous_values_json is only set for committed rows
-- that were updates (not creates). It carries the prior
-- value of every column the batch changed, keyed by
-- column name, so the rollback can restore them exactly.
--
-- The two keys split read paths: rows for a batch ordered
-- by row number (the preview, the export), rows for a
-- batch filtered by status (the failed-row export, the
-- rollback).
--
-- See docs/stages/016_data-import-plan.md.
-- ============================================================

CREATE TABLE `commerce_import_rows` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`             BIGINT UNSIGNED NOT NULL,
  `batch_id`             BIGINT UNSIGNED NOT NULL,
  `row_number`           INT UNSIGNED NOT NULL,
  `natural_key`          VARCHAR(190) NOT NULL,
  `payload_json`         JSON NOT NULL,
  `previous_values_json` JSON NULL,
  `entity_id`            BIGINT UNSIGNED NULL,
  `status`               ENUM('pending','valid','invalid',
                              'committed','rolled_back')
                         NOT NULL DEFAULT 'pending',
  `error`                VARCHAR(500) NULL,
  `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_com_imp_rows_batch_row`    (`batch_id`, `row_number`),
  KEY `idx_com_imp_rows_batch_status` (`batch_id`, `status`),
  CONSTRAINT `fk_com_imp_rows_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_imp_rows_batch`
    FOREIGN KEY (`batch_id`) REFERENCES `commerce_import_batches` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
