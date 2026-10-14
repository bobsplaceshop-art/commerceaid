-- ============================================================
-- 0341_create_webhook_events.sql
--
-- Webhook event log. Stage 010.
--
-- Every verified webhook from every payment provider is
-- recorded here. The row is the idempotency guard: a
-- webhook delivered twice (which the providers do by
-- design, on retry, on network failure, on their own
-- internal replay) is stored once and processed once.
--
-- The unique key on (provider_key, event_id) is the
-- deduplication mechanism. Each provider supplies its own
-- stable event identifier:
--   Stripe  - the event id, e.g. "evt_1A2b3C..."
--   PayPal  - the event id in the webhook body
--            ("id" field on the event resource)
--
-- payload_json is the full body as received. Stored for
-- audit and for replay. If a handler bug corrupts the
-- order state, the raw payload is available to re-run.
--
-- event_type is the provider's event name:
--   Stripe  - "payment_intent.succeeded", "charge.refunded"
--   PayPal  - "PAYMENT.CAPTURE.COMPLETED", "PAYMENT.CAPTURE.REFUNDED"
--
-- order_id is nullable. Filled when the handler can
-- resolve the order from the payload. A webhook for an
-- order the handler cannot match (or a webhook for a
-- provider-side event unrelated to an order) is recorded
-- with order_id NULL and processed_at NULL. That is a
-- finding for an admin to review, not a silent drop.
--
-- processed_at is NULL until the handler finishes. A row
-- with processed_at IS NULL older than N minutes is a
-- webhook that arrived but was not fully processed. The
-- admin dashboard (Session 5) reads this to surface
-- stuck webhooks.
--
-- Cascade: store_id -> commerce_stores CASCADE.
-- The webhook log dies with the store. order_id -> 
-- commerce_orders SET NULL. A webhook record survives
-- an order deletion; the record is an audit artefact
-- about the store's payment activity, not a child of the
-- order.
--
-- See docs/stages/010_payments-deep-plan.md.
-- ============================================================

CREATE TABLE `commerce_webhook_events` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`      BIGINT UNSIGNED NOT NULL,
  `provider_key`  VARCHAR(50) NOT NULL,
  `event_id`      VARCHAR(120) NOT NULL,
  `event_type`    VARCHAR(120) NOT NULL,
  `order_id`      BIGINT UNSIGNED NULL,
  `payload_json`  JSON NOT NULL,
  `received_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at`  DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_wh_ev_provider_event` (`provider_key`, `event_id`),
  KEY `idx_com_wh_ev_order` (`order_id`),
  KEY `idx_com_wh_ev_status` (`processed_at`, `received_at`),
  CONSTRAINT `fk_com_wh_ev_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_wh_ev_order`
    FOREIGN KEY (`order_id`) REFERENCES `commerce_orders` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
