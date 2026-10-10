-- ============================================================
-- 0325_add_sort_order_to_shipping_zones.sql
-- Add sort_order to shipping zones for first-match-wins
-- precedence.
--
-- Why: a customer's shipping address may match more than
-- one zone (e.g., a "Berlin" zone and a "Germany" zone both
-- match a Berlin address). Without an order, the resolved
-- zone is arbitrary. The industry standard (WooCommerce,
-- Shopify) is first-zone-matched wins, with zones ordered
-- most-specific-first.
--
-- The merchant orders their zones accordingly: the Berlin
-- zone at sort_order 0, the Germany zone at sort_order 10.
-- A Berlin customer matches the Berlin zone (lowest
-- sort_order among matches).
--
-- Default 0 puts new zones first. Merchants who add zones
-- with different sort_order move them explicitly.
--
-- Index: the resolver orders by (store_id, sort_order).
--
-- Follow-up to 0324. Same stage.
-- ============================================================

ALTER TABLE `commerce_shipping_zones`
    ADD COLUMN `sort_order` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `region`,
    ADD KEY `idx_com_ship_zones_sort` (`store_id`, `sort_order`);
