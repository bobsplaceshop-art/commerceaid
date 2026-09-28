-- =====================================================================
-- Stage 008E.2 - Guest order claim columns
-- =====================================================================
--
-- Two new columns on commerce_orders to support reconciling
-- guest orders with existing customer accounts after the fact.
--
-- claimable_by_customer_id: set when a guest order's email
--   matches an existing customer AND the merchant has enabled
--   the guest-claim toggle. Records a tentative email match,
--   NOT asserted ownership. Ownership is established only when
--   the customer explicitly claims (which sets customer_id and
--   claimed_at, and clears this column).
--
-- claimed_at: timestamp of the moment the customer claimed
--   the order. NULL means the claim has not been made.
--
-- Column name is claimable_by_customer_id, not customer_id or
-- belongs_to_customer_id. The DSGVO framing depends on this
-- distinction: the platform asserts only that the entered
-- email matched an account email, not that the customer owns
-- the order.
--
-- FK ON DELETE SET NULL so a customer deletion clears the
-- pending claim rather than orphaning the reference.
--
-- Index on (claimable_by_customer_id, claimed_at) supports
-- the dashboard query "does this customer have any unclaimed
-- orders?" without a full scan.
--
-- See docs/stages/008e.2_guest-order-claim-plan.md.
-- =====================================================================

ALTER TABLE `commerce_orders`
    ADD COLUMN `claimable_by_customer_id` BIGINT UNSIGNED NULL
        AFTER `customer_id`,
    ADD COLUMN `claimed_at` DATETIME NULL
        AFTER `claimable_by_customer_id`,
    ADD KEY `idx_com_order_claimable`
        (`claimable_by_customer_id`, `claimed_at`),
    ADD CONSTRAINT `fk_com_order_claimable`
        FOREIGN KEY (`claimable_by_customer_id`)
        REFERENCES `commerce_customers` (`id`)
        ON DELETE SET NULL;
