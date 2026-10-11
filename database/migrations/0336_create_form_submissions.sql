-- ============================================================
-- 0336_create_form_submissions.sql
--
-- Form submissions. Stage 009.
--
-- payload_json:
--   The submitted values keyed by field_key. One row per
--   submission. No joins, no schema change when a form
--   adds a field.
--
-- submitter_email / submitter_name:
--   Denormalised from the payload for the common filter
--   and export case. When the form has a field with
--   input_type = 'email' and the label contains "email"
--   (case-insensitive), its submitted value is copied
--   here. Same for a name field. This lets the admin
--   list filter by email without querying JSON.
--
-- status:
--   new       - unreviewed
--   read      - reviewed by the merchant
--   spam      - flagged as spam
--   archived  - hidden from the default list, preserved
--
-- customer_id:
--   Nullable. Set when the submitter is a logged-in
--   customer. FK to commerce_customers with ON DELETE
--   SET NULL, so a customer deletion does not lose the
--   submission.
--
-- No ON DELETE CASCADE from the form. Submissions are
-- business records; a form deletion must not silently
-- destroy them. The route that deletes a form first
-- confirms there are no submissions, or moves them to
-- archive (decision made at the controller layer).
-- Cascade is set for the store only.
--
-- See docs/stages/009_forms-plan.md.
-- ============================================================

CREATE TABLE `commerce_form_submissions` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`        BIGINT UNSIGNED NOT NULL,
  `form_id`         BIGINT UNSIGNED NOT NULL,
  `customer_id`     BIGINT UNSIGNED NULL,
  `submitter_email` VARCHAR(190) NULL,
  `submitter_name`  VARCHAR(190) NULL,
  `submitter_ip`    VARCHAR(45) NULL,
  `user_agent`      VARCHAR(500) NULL,
  `payload_json`    JSON NOT NULL,
  `status`          ENUM('new','read','spam','archived')
                    NOT NULL DEFAULT 'new',
  `notes`           TEXT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_com_fsub_form_created` (`form_id`, `created_at`),
  KEY `idx_com_fsub_store_status` (`store_id`, `status`),
  KEY `idx_com_fsub_email` (`store_id`, `submitter_email`),
  CONSTRAINT `fk_com_fsub_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_fsub_form`
    FOREIGN KEY (`form_id`) REFERENCES `commerce_forms` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_fsub_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `commerce_customers` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
