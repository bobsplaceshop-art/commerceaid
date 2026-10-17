-- ============================================================
-- 0345_create_flutterwave_requests.sql
--
-- Pending Flutterwave payments. Stage 010, sub-stage
-- 010.3.
--
-- Flutterwave's webhook carries a `verif-hash` header
-- that is the plain shared secret the merchant
-- configured. The verification is a string comparison,
-- not an HMAC of the payload. After the header matches,
-- the handler calls GET /transactions/{id}/verify to
-- read the authoritative payment state.
--
-- The lifecycle:
--
--   1. FlutterwaveProvider::charge() inserts a row with
--      status 'pending', the tx_ref (the merchant
--      reference the platform supplies), the order_id,
--      amount, and currency.
--   2. POST /payments returns a data.link (the hosted
--      page URL). The transaction_id is not present in
--      that response; it comes from the verify call.
--   3. The customer pays on Flutterwave's hosted page.
--   4. Flutterwave redirects the customer to
--      redirect_url with transaction_id, tx_ref, and
--      status as query parameters.
--   5. Flutterwave posts a webhook to
--      /webhooks/flutterwave with the JSON body and
--      the verif-hash header.
--   6. verifyWebhook() compares the header against the
--      store's secret_hash. A match confirms the
--      request came from Flutterwave.
--   7. handleWebhook() calls GET /verify, reads
--      data.status, and if 'successful',
--      markCompleted() writes the transaction_id. The
--      order's payment_status is written by the
--      handler, not by this table.
--
-- A cleanup cron (bin/cleanup-flutterwave-requests.php)
-- deletes pending rows older than 24 hours. Rows in
-- 'completed' or 'failed' state stay for audit.
--
-- The unique key on tx_ref is the idempotency guard.
-- Flutterwave delivers webhooks at least once; a
-- duplicate hits the unique key on the row and the
-- handler no-ops.
--
-- Cascade: store_id -> commerce_stores CASCADE. The
-- row dies with the store. order_id -> commerce_orders
-- CASCADE. The row is operational state, not audit.
--
-- See docs/stages/010.3_flutterwave-plan.md.
-- ============================================================

CREATE TABLE `commerce_flutterwave_requests` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`        BIGINT UNSIGNED NOT NULL,
  `order_id`        BIGINT UNSIGNED NOT NULL,
  `tx_ref`          VARCHAR(80) NOT NULL,
  `transaction_id`  VARCHAR(60) NULL,
  `amount`          DECIMAL(18,4) NOT NULL,
  `currency_code`   CHAR(3) NOT NULL,
  `status`          ENUM('pending','completed','failed')
                    NOT NULL DEFAULT 'pending',
  `failure_reason`  VARCHAR(255) NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_flutter_tx_ref` (`tx_ref`),
  KEY `idx_com_flutter_order` (`order_id`),
  KEY `idx_com_flutter_status_created` (`status`, `created_at`),
  CONSTRAINT `fk_com_flutter_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_flutter_order`
    FOREIGN KEY (`order_id`) REFERENCES `commerce_orders` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
