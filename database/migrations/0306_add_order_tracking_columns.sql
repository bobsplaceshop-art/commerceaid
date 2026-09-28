-- =====================================================================
-- Stage 008F - Order tracking columns
-- =====================================================================
--
-- Three new columns on commerce_orders to support customer-
-- facing order tracking.
--
-- carrier_key      VARCHAR(40) NULL
--   Identifies the shipping carrier. Maps to a URL template
--   in app/Commerce/Support/CarrierTemplates.php. Values are
--   lowercase keys: dhl, fedex, ups, dpd, hermes, royal_mail,
--   posta_kenya. Unknown values are tolerated (the tracking
--   block falls back to showing the raw number).
--
-- tracking_number  VARCHAR(120) NULL
--   The carrier's tracking identifier. Format varies by
--   carrier. Never validated - we accept whatever the admin
--   enters.
--
-- tracking_url     VARCHAR(500) NULL
--   Optional override. When null (the normal case), the URL
--   is computed from carrier_key + tracking_number via the
--   template map. When set, it is used verbatim. The override
--   exists so a future enhancement can store non-template URLs
--   (custom carrier portals, redirect services).
--
-- These columns are populated by an admin UI (future stage) or
-- by direct SQL until then. The customer-facing display is
-- what this stage ships.
--
-- See docs/stages/008f_order-lifecycle-plan.md.
-- =====================================================================

ALTER TABLE `commerce_orders`
    ADD COLUMN `carrier_key`     VARCHAR(40)  NULL AFTER `shipping_method_label`,
    ADD COLUMN `tracking_number` VARCHAR(120) NULL AFTER `carrier_key`,
    ADD COLUMN `tracking_url`    VARCHAR(500) NULL AFTER `tracking_number`;
