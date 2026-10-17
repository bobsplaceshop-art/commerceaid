-- ============================================================
-- 0344_create_pesapal_requests.sql
--
-- Pending Pesapal orders. Stage 010, sub-stage 010.2.
--
-- Pesapal does not sign its IPN notifications. The
-- verification path is a lookup plus an outbound call:
-- the IPN carries an order_tracking_id, the provider
-- confirms the id matches a pending request this store
-- initiated, then calls GetTransactionStatus to read the
-- authoritative payment state.
--
-- The lifecycle:
--
--   1. PesapalProvider::charge() inserts a row with
--      status 'pending' and the order_id, amount,
--      currency.
--   2. The SubmitOrderRequest call to Pesapal returns an
--      order_tracking_id and a redirect_url. Both are
--      written to the row.
--   3. The customer pays on Pesapal's hosted page.
--   4. Pesapal redirects the customer to the callback URL
--      and posts an IPN to /webhooks/pesapal.
--   5. verifyWebhook() reads the order_tracking_id from
--      the IPN and calls findByTrackingId(). A match
--      confirms the callback is genuine.
--   6. handleWebhook() calls GetTransactionStatus. If the
--      state is COMPLETED, markCompleted() writes the
--      confirmation_code to the row. The order's
--      payment_status is written by the handler, not by
--      this table.
--
-- A cleanup cron (bin/cleanup-pesapal-requests.php)
-- deletes pending rows older than 24 hours. Rows in
-- 'completed' or 'failed' state stay for audit.
--
-- The unique key on order_tracking_id is the idempotency
-- guard. Pesapal delivers IPNs at least once; a duplicate
-- hits the unique key on the row and the handler no-ops
-- on the second pass.
--
-- Cascade: store_id -> commerce_stores CASCADE. The row
-- dies with the store. order_id -> commerce_orders
-- CASCADE. The row is operational state, not audit.
--
-- See docs/stages/010.2_pesapal-plan.md.
-- ============================================================

CREATE TABLE `commerce_pesapal_requests` (
  `id`                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`                    BIGINT UNSIGNED NOT NULL,
  `order_id`                    BIGINT UNSIGNED NOT NULL,
  `order_tracking_id`           VARCHAR(80) NOT NULL,
  `merchant_reference`          VARCHAR(80) NOT NULL,
  `amount`                      DECIMAL(18,4) NOT NULL,
  `currency_code`               CHAR(3) NOT NULL,
  `status`                      ENUM('pending','completed','failed')
                                NOT NULL DEFAULT 'pending',
  `payment_status_description`  VARCHAR(60) NULL,
  `confirmation_code`           VARCHAR(60) NULL,
  `created_at`                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_pesapal_tracking` (`order_tracking_id`),
  KEY `idx_com_pesapal_order` (`order_id`),
  KEY `idx_com_pesapal_status_created` (`status`, `created_at`),
  CONSTRAINT `fk_com_pesapal_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_pesapal_order`
    FOREIGN KEY (`order_id`) REFERENCES `commerce_orders` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
