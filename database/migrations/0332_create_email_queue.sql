-- ============================================================
-- 0332_create_email_queue.sql
--
-- The pending-send queue. Stage 008I.
--
-- When a campaign is sent, one row is enqueued per
-- confirmed subscriber in the target list. The CLI
-- worker bin/send-emails.php drains the queue.
--
-- to_email and to_name are denormalised at enqueue time.
-- If the subscriber's email changes between enqueue and
-- send, the campaign still goes to the original address.
-- That is correct: the subscriber agreed to receive mail
-- at that address.
--
-- unsubscribe_token is signed per row. See the plan
-- doc's "Token strategy" section.
--
-- attempts increments on every send failure. At
-- attempts >= 5, status='failed' and the subscriber
-- moves to 'suppressed'.
--
-- scheduled_at is the earliest time the row may be
-- drained. Retry backoff pushes it forward.
--
-- status lifecycle:
--   pending  - waiting to be drained
--   sending  - claimed by a worker
--   sent     - delivered to the SMTP provider
--   failed   - exhausted retries
--
-- See docs/stages/008i_newsletter-plan.md.
-- ============================================================

CREATE TABLE `commerce_email_queue` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`          BIGINT UNSIGNED NOT NULL,
  `campaign_id`       BIGINT UNSIGNED NOT NULL,
  `subscriber_id`     BIGINT UNSIGNED NOT NULL,
  `to_email`          VARCHAR(190) NOT NULL,
  `to_name`           VARCHAR(120) NULL,
  `unsubscribe_token` VARCHAR(255) NOT NULL,
  `attempts`          INT UNSIGNED NOT NULL DEFAULT 0,
  `last_error`        VARCHAR(500) NULL,
  `status`            ENUM('pending','sending','sent','failed')
                      NOT NULL DEFAULT 'pending',
  `scheduled_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at`           DATETIME NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_com_eq_status_scheduled` (`status`, `scheduled_at`),
  KEY `idx_com_eq_campaign` (`campaign_id`),
  CONSTRAINT `fk_com_eq_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_eq_campaign`
    FOREIGN KEY (`campaign_id`) REFERENCES `commerce_email_campaigns` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_eq_subscriber`
    FOREIGN KEY (`subscriber_id`) REFERENCES `commerce_subscribers` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
