-- ============================================================
-- 0330_create_subscriber_list_members.sql
--
-- Subscriber <-> list membership. Stage 008I.
--
-- A subscriber can belong to any number of lists. The
-- unique key prevents a duplicate join. Removing a
-- subscription means deleting the row; re-subscribing
-- inserts a fresh row.
--
-- See docs/stages/008i_newsletter-plan.md.
-- ============================================================

CREATE TABLE `commerce_subscriber_list_members` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`      BIGINT UNSIGNED NOT NULL,
  `subscriber_id` BIGINT UNSIGNED NOT NULL,
  `list_id`       BIGINT UNSIGNED NOT NULL,
  `joined_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_slm_sub_list` (`subscriber_id`, `list_id`),
  KEY `idx_com_slm_list` (`list_id`),
  CONSTRAINT `fk_com_slm_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_slm_subscriber`
    FOREIGN KEY (`subscriber_id`) REFERENCES `commerce_subscribers` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_slm_list`
    FOREIGN KEY (`list_id`) REFERENCES `commerce_subscriber_lists` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
