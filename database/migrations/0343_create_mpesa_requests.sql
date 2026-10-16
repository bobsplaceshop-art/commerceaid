-- ============================================================
-- 0343_create_mpesa_requests.sql
--
-- Pending M-Pesa STK push requests. Stage 010, sub-stage
-- 010.1.
--
-- M-Pesa does not sign its callbacks. There is no HMAC
-- header and no certificate to verify against. The
-- verification path is a lookup: the callback carries a
-- CheckoutRequestID, and the provider confirms the id
-- matches a request it initiated. That is what this table
-- holds.
--
-- The lifecycle:
--
--   1. MpesaProvider::charge() inserts a row with
--      status 'pending' and the order_id, phone, amount.
--   2. The STK push call to Safaricom returns a
--      CheckoutRequestID and a MerchantRequestID. Both
--      are written to the row.
--   3. Safaricom POSTs the callback to /webhooks/mpesa.
--      verifyWebhook() reads the CheckoutRequestID and
--      calls findByCheckoutRequestId(). A match confirms
--      the callback is genuine; no match means forgery
--      or replay.
--   4. handleWebhook() reads the ResultCode from the
--      callback. ResultCode 0 marks the row 'completed'
--      and stores the M-Pesa receipt. Any other code
--      marks the row 'failed' with the description.
--   5. The order's payment_status is written by the
--      webhook handler, not by this table.
--
-- A cleanup cron (bin/cleanup-mpesa-requests.php) deletes
-- pending rows older than 24 hours. That bounds the
-- table's growth without needing an archive.
--
-- The unique key on checkout_request_id is the
-- idempotency guard. Safaricom delivers callbacks at
-- least once; a duplicate callback hits the unique key
-- on the row and the handler no-ops on the second pass.
--
-- Cascade: store_id -> commerce_stores CASCADE. The row
-- dies with the store. order_id -> commerce_orders
-- CASCADE. The row is a child of the order it was
-- created for; an order deletion removes the pending
-- request with it. That differs from
-- commerce_webhook_events, which uses SET NULL because
-- the event log is an audit artefact. This table is
-- operational state, not audit.
--
-- See docs/stages/010.1_mpesa-plan.md.
-- ============================================================

CREATE TABLE `commerce_mpesa_requests` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`             BIGINT UNSIGNED NOT NULL,
  `order_id`             BIGINT UNSIGNED NOT NULL,
  `checkout_request_id`  VARCHAR(80) NOT NULL,
  `merchant_request_id`  VARCHAR(80) NULL,
  `phone_number`         VARCHAR(20) NOT NULL,
  `amount`               DECIMAL(18,4) NOT NULL,
  `status`               ENUM('pending','completed','failed')
                         NOT NULL DEFAULT 'pending',
  `result_code`          INT NULL,
  `result_description`   VARCHAR(255) NULL,
  `mpesa_receipt`        VARCHAR(60) NULL,
  `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_mpesa_checkout` (`checkout_request_id`),
  KEY `idx_com_mpesa_order` (`order_id`),
  KEY `idx_com_mpesa_status_created` (`status`, `created_at`),
  CONSTRAINT `fk_com_mpesa_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_mpesa_order`
    FOREIGN KEY (`order_id`) REFERENCES `commerce_orders` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
