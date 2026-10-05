-- =====================================================================
-- Stage 008H - Returns & Refunds
-- Two tables: the request header and its per-line items.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `commerce_return_requests` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`       BIGINT UNSIGNED NOT NULL,
  `order_id`       BIGINT UNSIGNED NOT NULL,
  `customer_id`    BIGINT UNSIGNED NOT NULL,
  `request_number` VARCHAR(40) NOT NULL,
  `status`         ENUM(
                     'requested',
                     'approved',
                     'rejected',
                     'received',
                     'refunded',
                     'cancelled'
                   ) NOT NULL DEFAULT 'requested',
  `reason_code`    VARCHAR(40) NOT NULL,
  `reason_note`    TEXT NULL,
  `admin_note`     TEXT NULL,
  `refund_total`   DECIMAL(18,4) NOT NULL DEFAULT 0,
  `requested_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at`    DATETIME NULL,
  `reviewed_by`    INT UNSIGNED NULL,
  `resolved_at`    DATETIME NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                   ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_rr_store_number` (`store_id`, `request_number`),
  KEY `idx_com_rr_store_status` (`store_id`, `status`, `requested_at`),
  KEY `idx_com_rr_order` (`order_id`),
  KEY `idx_com_rr_customer` (`customer_id`, `requested_at`),
  KEY `idx_com_rr_reviewer` (`reviewed_by`),

  CONSTRAINT `fk_com_rr_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_rr_order`
    FOREIGN KEY (`order_id`) REFERENCES `commerce_orders` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_rr_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `commerce_customers` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_rr_reviewer`
    FOREIGN KEY (`reviewed_by`) REFERENCES `core_users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_return_request_items` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`      BIGINT UNSIGNED NOT NULL,
  `request_id`    BIGINT UNSIGNED NOT NULL,
  `order_item_id` BIGINT UNSIGNED NOT NULL,
  `quantity`      INT UNSIGNED NOT NULL,
  `unit_refund`   DECIMAL(18,4) NOT NULL,
  `line_refund`   DECIMAL(18,4) NOT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_com_rri_request` (`request_id`),
  KEY `idx_com_rri_order_item` (`order_item_id`),

  CONSTRAINT `fk_com_rri_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_rri_request`
    FOREIGN KEY (`request_id`) REFERENCES `commerce_return_requests` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_rri_order_item`
    FOREIGN KEY (`order_item_id`) REFERENCES `commerce_order_items` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
