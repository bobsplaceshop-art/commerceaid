-- Migration 0342 - backfill offline payment methods
--
-- Before this migration, the storefront offered Cash on
-- delivery and Bank transfer through a hardcoded pair in
-- CheckoutController::availablePaymentMethods(). No row
-- in commerce_store_payment_methods was needed.
--
-- The 010.4 / 010.5 / 010.6 sub-stage promotes the three
-- offline methods (cod, bank_transfer, manual) to real
-- PaymentProvider implementations. The checkout method
-- list stops hardcoding them and resolves them through
-- PaymentProviderRegistry::enabledForStore(), which
-- filters on commerce_store_payment_methods.is_enabled.
--
-- This migration inserts the three rows for every store
-- so no existing store loses its COD or bank transfer
-- option at checkout. The insertion is strictly additive:
-- ON DUPLICATE KEY UPDATE is a no-op on any row that
-- already exists, so a merchant who has already toggled
-- one of these keys keeps their chosen state.
--
-- Defaults:
--   cod           is_enabled = 1  (preserves today's behaviour)
--   bank_transfer is_enabled = 1  (preserves today's behaviour)
--   manual        is_enabled = 0  (opt-in; new provider)
--
-- sort_order:
--   100, 110, 120 place the three offline methods after
--   any provider already configured with a lower number.
--   stripe = 0 and paypal = 1 in the dev store, so the
--   offline methods appear after them.

-- cod
INSERT INTO `commerce_store_payment_methods`
    (`store_id`, `provider_key`, `is_enabled`, `sort_order`)
SELECT `id`, 'cod', 1, 100
  FROM `commerce_stores`
ON DUPLICATE KEY UPDATE `provider_key` = `provider_key`;

-- bank_transfer
INSERT INTO `commerce_store_payment_methods`
    (`store_id`, `provider_key`, `is_enabled`, `sort_order`)
SELECT `id`, 'bank_transfer', 1, 110
  FROM `commerce_stores`
ON DUPLICATE KEY UPDATE `provider_key` = `provider_key`;

-- manual
INSERT INTO `commerce_store_payment_methods`
    (`store_id`, `provider_key`, `is_enabled`, `sort_order`)
SELECT `id`, 'manual', 0, 120
  FROM `commerce_stores`
ON DUPLICATE KEY UPDATE `provider_key` = `provider_key`;
