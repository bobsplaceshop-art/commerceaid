<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

/**
 * Coupon redemption ledger.
 *
 * Append-only. Never updated, never deleted by application
 * code. Mirrors the pattern of commerce_inventory_movements.
 * A coupon's redemption history is the record of who used it,
 * when, and for how much.
 *
 * Deletion happens only via ON DELETE CASCADE when the parent
 * coupon or order is deleted - and the coupon controller
 * refuses to delete a coupon with redemptions.
 *
 * See docs/stages/008k.5_order-pricing-engine-plan.md.
 */
final class CouponRedemptionRepository extends CommerceRepository
{
    /**
     * Record a redemption. Called inside the checkout
     * transaction, once the order header exists.
     */
    public function create(
        int $couponId,
        int $orderId,
        ?int $customerId,
        ?string $guestEmail,
        float $amount
    ): void {
        $this->db->execute(
            "INSERT INTO `commerce_coupon_redemptions`
                (`store_id`, `coupon_id`, `customer_id`,
                 `guest_email`, `order_id`, `amount`)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $this->storeId(),
                $couponId,
                $customerId,
                $guestEmail,
                $orderId,
                $amount,
            ]
        );
    }

    /**
     * Total redemptions for a coupon. Used to enforce the
     * coupon's global usage_limit.
     */
    public function countForCoupon(int $couponId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_coupon_redemptions`
              WHERE `store_id` = ? AND `coupon_id` = ?",
            [$this->storeId(), $couponId]
        );
    }

    /**
     * Redemptions of a coupon by one logged-in customer.
     * Used to enforce per_customer_limit.
     */
    public function countForCustomer(int $couponId, int $customerId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_coupon_redemptions`
              WHERE `store_id` = ? AND `coupon_id` = ?
                AND `customer_id` = ?",
            [$this->storeId(), $couponId, $customerId]
        );
    }

    /**
     * Redemptions of a coupon by one guest email. Used to
     * enforce per_customer_limit for guests.
     *
     * Comparison is case-insensitive to match the way emails
     * are normalized elsewhere (findByEmail lowercases).
     */
    public function countForGuestEmail(int $couponId, string $guestEmail): int
    {
        if ($guestEmail === '') {
            return 0;
        }
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_coupon_redemptions`
              WHERE `store_id` = ? AND `coupon_id` = ?
                AND LOWER(`guest_email`) = LOWER(?)",
            [$this->storeId(), $couponId, $guestEmail]
        );
    }

    /**
     * Full redemption history for a coupon, newest first.
     * Used on the admin coupon edit page.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listForCoupon(int $couponId): array
    {
        return $this->db->select(
            "SELECT r.*,
                    o.`order_number`,
                    o.`grand_total`,
                    c.`first_name` AS customer_first_name,
                    c.`last_name`  AS customer_last_name,
                    c.`email`      AS customer_email
               FROM `commerce_coupon_redemptions` r
               LEFT JOIN `commerce_orders` o
                 ON o.`id` = r.`order_id`
               LEFT JOIN `commerce_customers` c
                 ON c.`id` = r.`customer_id`
              WHERE r.`store_id` = ? AND r.`coupon_id` = ?
              ORDER BY r.`redeemed_at` DESC, r.`id` DESC",
            [$this->storeId(), $couponId]
        );
    }
}
