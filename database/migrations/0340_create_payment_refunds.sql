-- ============================================================
-- 0340_create_payment_refunds.sql
--
-- Payment refunds. Stage 010.
--
-- Refunds are entities, not a boolean on the order. This
-- table is the source of truth for what has actually been
-- refunded through a payment provider. The order's
-- refunded_total column becomes a derived value: the sum
-- of commerce_payment_refunds.amount where
-- status = 'succeeded' and order_id = the order.
--
-- The industry-standard shape. Stripe, PayPal, Shopify,
-- Magento, and WooCommerce all model refunds as first-class
-- objects with their own lifecycle. The order does not
-- carry a "refund pending" flag; the refund row does.
--
-- provider_key matches the PaymentProvider::key() of the
-- provider that processed the refund. Matches
-- commerce_orders.payment_provider_key for the original
-- charge.
--
-- provider_refund_id and provider_payment_id are the
-- provider's own identifiers. Filled when the provider
-- responds. NULL while a refund is pending or has failed.
--
-- idempotency_key is a caller-supplied unique string
-- (ULID or UUIDv4). It prevents a double-refund when a
-- retry fires after a network failure. The unique index
-- enforces one row per idempotency key.
--
-- status:
--   pending    - created, not yet dispatched to the
--                provider, or the provider call is in
--                flight
--   succeeded  - the provider confirmed the refund
--   failed     - the provider returned an error; the
--                retry cron attempts again
--   canceled   - manually abandoned before dispatch
--
-- return_request_id is nullable. A refund can originate
-- from a return request (the common case) or from an
-- admin-initiated manual refund (future). ON DELETE SET
-- NULL: the refund is a money-movement record; a return
-- request is a workflow object. The money moved; the
-- record survives.
--
-- Cascade rules:
--   store_id           -> commerce_stores  CASCADE
--   order_id           -> commerce_orders  CASCADE
--   return_request_id  -> commerce_return_requests SET NULL
--
-- The retry cron (bin/retry-refunds.php) selects rows where
-- status = 'failed' and attempts < 5, and re-dispatches.
--
-- See docs/stages/010_payments-deep-plan.md.
-- ============================================================

CREATE TABLE `commerce_payment_refunds` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`            BIGINT UNSIGNED NOT NULL,
  `order_id`            BIGINT UNSIGNED NOT NULL,
  `return_request_id`   BIGINT UNSIGNED NULL,
  `provider_key`        VARCHAR(50) NOT NULL,
  `provider_refund_id`  VARCHAR(120) NULL,
  `provider_payment_id` VARCHAR(120) NULL,
  `amount`              DECIMAL(18,4) NOT NULL,
  `currency_code`       CHAR(3) NOT NULL,
  `status`              ENUM('pending','succeeded','failed','canceled')
                        NOT NULL DEFAULT 'pending',
  `reason`              VARCHAR(255) NULL,
  `failure_message`     VARCHAR(500) NULL,
  `idempotency_key`     VARCHAR(80) NOT NULL,
  `attempts`            INT UNSIGNED NOT NULL DEFAULT 0,
  `last_attempt_at`     DATETIME NULL,
  `succeeded_at`        DATETIME NULL,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_pref_idem` (`idempotency_key`),
  KEY `idx_com_pref_order`  (`order_id`),
  KEY `idx_com_pref_return` (`return_request_id`),
  KEY `idx_com_pref_status` (`status`, `created_at`),
  CONSTRAINT `fk_com_pref_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_pref_order`
    FOREIGN KEY (`order_id`) REFERENCES `commerce_orders` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_pref_return`
    FOREIGN KEY (`return_request_id`) REFERENCES `commerce_return_requests` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
