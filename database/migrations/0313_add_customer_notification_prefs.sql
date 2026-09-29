-- ============================================================
-- 0313_add_customer_notification_prefs.sql
-- Two boolean preference columns on commerce_customers that
-- control which notification categories arrive.
--
-- Two booleans. Not a separate preferences table. A table
-- would add a join for no benefit at this scale. If
-- preferences grow past three or four booleans, extract them
-- then.
--
-- The existing accepts_marketing column is not touched. It
-- predates this stage, is used by the registration form, and
-- has its own meaning (marketing consent for GDPR). The new
-- notify_promotions column is separate: it controls whether
-- promotional notifications appear in the message center,
-- independent of the wider marketing consent.
--
-- See docs/stages/008g.3_message-center-plan.md for the full
-- design.
-- ============================================================

ALTER TABLE `commerce_customers`
  ADD COLUMN `notify_order_updates` TINYINT(1) NOT NULL DEFAULT 1
    AFTER `accepts_marketing`,
  ADD COLUMN `notify_promotions`    TINYINT(1) NOT NULL DEFAULT 0
    AFTER `notify_order_updates`;
