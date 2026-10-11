-- ============================================================
-- 0333_create_email_events.sql
--
-- Per-subscriber email event log. Stage 008I.
--
-- Records sends, opens, clicks, bounces, unsubscribes,
-- and complaints. event_data carries per-type detail:
-- the URL clicked, the bounce reason text, the user
-- agent for an open.
--
-- campaign_id and subscriber_id are nullable with
-- ON DELETE SET NULL. If a campaign or subscriber is
-- deleted, the aggregate event survives. The event row
-- is the audit trail; the identity is disposable.
--
-- Open and click tracking are gated behind the store
-- setting newsletter.tracking_enabled (default off).
-- The 'sent' event is always written regardless.
--
-- See docs/stages/008i_newsletter-plan.md.
-- ============================================================

CREATE TABLE `commerce_email_events` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`      BIGINT UNSIGNED NOT NULL,
  `campaign_id`   BIGINT UNSIGNED NULL,
  `subscriber_id` BIGINT UNSIGNED NULL,
  `event_type`    ENUM('sent','open','click','bounce_hard','bounce_soft','unsubscribe','complaint')
                  NOT NULL,
  `event_data`    JSON NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_com_ee_campaign_type` (`campaign_id`, `event_type`),
  KEY `idx_com_ee_subscriber` (`subscriber_id`, `event_type`),
  KEY `idx_com_ee_created` (`created_at`),
  CONSTRAINT `fk_com_ee_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_ee_campaign`
    FOREIGN KEY (`campaign_id`) REFERENCES `commerce_email_campaigns` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_com_ee_subscriber`
    FOREIGN KEY (`subscriber_id`) REFERENCES `commerce_subscribers` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
