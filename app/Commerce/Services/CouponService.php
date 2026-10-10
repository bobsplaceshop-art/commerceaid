<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Models\CartLineItem;
use CorporateAid\Commerce\Repositories\CouponRedemptionRepository;
use CorporateAid\Commerce\Repositories\CouponRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;

/**
 * Coupon validation and discount calculation.
 *
 * The service is the sole writer to commerce_coupons and
 * commerce_coupon_redemptions. Reads and writes go through
 * the two injected repositories.
 *
 * Validation produces a specific failure reason for each
 * rule so the customer sees "Coupon expired" rather than
 * "Invalid coupon". The order of rules is documented in the
 * plan; the first failure short-circuits.
 *
 * Discount computation supports two scopes:
 *   all_products          discount applies to the whole cart
 *                         subtotal
 *   specific_categories   discount applies only to line
 *                         subtotals whose product belongs
 *                         to one of the listed categories
 *
 * Free shipping coupons return 0.0 from calculateDiscount.
 * Their effect is applied by ShippingService, which zeroes
 * the shipping total when it sees a free_shipping coupon in
 * the pricing context.
 *
 * See docs/stages/008k.5_order-pricing-engine-plan.md.
 */
final class CouponService
{
    public function __construct(
        private CouponRepository $coupons,
        private CouponRedemptionRepository $redemptions,
        private ProductRepository $products,
    ) {
    }

    /**
     * Validate a code against the current cart. Returns a
     * structured result: ok flag, either the applicable coupon
     * and discount or a human-readable failure reason.
     *
     * @param array<int,CartLineItem> $lines
     * @return array{ok:bool, reason?:string,
     *               coupon?:array<string,mixed>,
     *               discount:float}
     */
    public function validate(
        string $code,
        array $lines,
        float $subtotal,
        ?int $customerId,
        ?string $guestEmail
    ): array {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return ['ok' => false, 'reason' => 'Enter a coupon code.', 'discount' => 0.0];
        }

        $coupon = $this->coupons->findByCode($code);
        if ($coupon === null) {
            return ['ok' => false, 'reason' => 'Coupon code not recognised.', 'discount' => 0.0];
        }

        $couponId = (int) $coupon['id'];

        // 1. Status
        if ((string) $coupon['status'] !== 'active') {
            return ['ok' => false, 'reason' => 'This coupon is no longer active.', 'discount' => 0.0];
        }

        // 2. Validity window (uses MySQL NOW() via the repository)
        $validity = $this->coupons->validityStatus($couponId);
        if ($validity === 'before') {
            return ['ok' => false, 'reason' => 'This coupon is not yet valid.', 'discount' => 0.0];
        }
        if ($validity === 'after') {
            return ['ok' => false, 'reason' => 'This coupon has expired.', 'discount' => 0.0];
        }

        // 3. Global usage limit
        $usageLimit = $coupon['usage_limit'] !== null ? (int) $coupon['usage_limit'] : null;
        if ($usageLimit !== null && $usageLimit > 0) {
            $count = $this->redemptions->countForCoupon($couponId);
            if ($count >= $usageLimit) {
                return [
                    'ok'       => false,
                    'reason'   => 'This coupon has reached its usage limit.',
                    'discount' => 0.0,
                ];
            }
        }

        // 4. Per-customer limit
        $perCustomer = $coupon['per_customer_limit'] !== null
            ? (int) $coupon['per_customer_limit']
            : null;
        if ($perCustomer !== null && $perCustomer > 0) {
            $count = $this->customerRedemptionCount($couponId, $customerId, $guestEmail);
            if ($count >= $perCustomer) {
                return [
                    'ok'       => false,
                    'reason'   => 'You have already used this coupon.',
                    'discount' => 0.0,
                ];
            }
        }

        // 5. Minimum order subtotal
        $minOrder = $coupon['min_order_subtotal'] !== null
            ? (float) $coupon['min_order_subtotal']
            : null;
        if ($minOrder !== null && $minOrder > 0 && $subtotal < $minOrder) {
            return [
                'ok'       => false,
                'reason'   => sprintf(
                    'Minimum order of %s required for this coupon.',
                    number_format($minOrder, 2)
                ),
                'discount' => 0.0,
            ];
        }

        // 6. Compute the discount. A free_shipping coupon
        //    returns 0 here - the shipping total is zeroed
        //    by ShippingService.
        $discount = $this->calculateDiscount($coupon, $lines, $subtotal);

        $isFreeShipping = (string) $coupon['type'] === 'free_shipping';

        if (!$isFreeShipping && $discount <= 0.0) {
            return [
                'ok'       => false,
                'reason'   => 'This coupon does not apply to the items in your cart.',
                'discount' => 0.0,
            ];
        }

        return [
            'ok'       => true,
            'coupon'   => $coupon,
            'discount' => $discount,
        ];
    }

    /**
     * Compute the discount amount for a validated coupon.
     * Does not re-validate. Called from the checkout
     * transaction where validation was already performed.
     *
     * @param array<string,mixed> $coupon
     * @param array<int,CartLineItem> $lines
     */
    public function calculateDiscount(array $coupon, array $lines, float $subtotal): float
    {
        $type  = (string) $coupon['type'];
        $value = (float) $coupon['value'];

        // Free shipping is applied on the shipping side, not
        // on the merchandise subtotal. Return zero here; the
        // orchestrator reads the coupon type and zeroes the
        // shipping total when this coupon type is in play.
        if ($type === 'free_shipping') {
            return 0.0;
        }

        // Determine the eligible base.
        $base = $subtotal;

        $appliesTo = (string) $coupon['applies_to'];
        if ($appliesTo === 'specific_categories') {
            $categoryIds = $this->decodeCategoryIds($coupon['applies_to_ids_json'] ?? null);
            if ($categoryIds === []) {
                return 0.0;
            }
            $base = 0.0;
            foreach ($lines as $line) {
                $productId = (int) $line->item->productId;
                if ($productId <= 0) {
                    continue;
                }
                $productCategories = $this->products->categoryIdsFor($productId);
                if (array_intersect($productCategories, $categoryIds) === []) {
                    continue;
                }
                $base += $line->effectiveSubtotal();
            }
            $base = round($base, 4);
            if ($base <= 0.0) {
                return 0.0;
            }
        }

        // Compute per type.
        $discount = match ($type) {
            'percentage'   => round($base * ($value / 100.0), 4),
            'fixed_amount' => min($value, $base),
            default        => 0.0,
        };

        if ($discount < 0.0) {
            $discount = 0.0;
        }
        if ($discount > $base) {
            $discount = $base;
        }

        return round($discount, 4);
    }

    /**
     * Record a redemption. Called inside the checkout
     * transaction, once the order header exists.
     */
    public function recordRedemption(
        int $couponId,
        int $orderId,
        ?int $customerId,
        ?string $guestEmail,
        float $amount
    ): void {
        $this->redemptions->create(
            $couponId,
            $orderId,
            $customerId,
            $guestEmail,
            $amount
        );
    }

    /**
     * Total redemptions for a coupon.
     */
    public function redemptionCount(int $couponId): int
    {
        return $this->redemptions->countForCoupon($couponId);
    }

    /**
     * Redemptions for a specific customer, or by a guest
     * email when customerId is null.
     */
    public function customerRedemptionCount(
        int $couponId,
        ?int $customerId,
        ?string $guestEmail
    ): int {
        if ($customerId !== null && $customerId > 0) {
            return $this->redemptions->countForCustomer($couponId, $customerId);
        }
        if ($guestEmail !== null && $guestEmail !== '') {
            return $this->redemptions->countForGuestEmail($couponId, $guestEmail);
        }
        return 0;
    }

    /**
     * Whether a coupon is a free-shipping coupon. Used by
     * OrderPricingService to zero the shipping total.
     *
     * @param array<string,mixed>|null $coupon
     */
    public function isFreeShipping(?array $coupon): bool
    {
        if ($coupon === null) {
            return false;
        }
        return (string) ($coupon['type'] ?? '') === 'free_shipping';
    }

    /**
     * Decode the JSON category IDs from a coupon. Returns
     * an empty array on any decode failure.
     *
     * @return array<int,int>
     */
    private function decodeCategoryIds(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $ids = [];
        foreach ($decoded as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        return array_keys($ids);
    }
}
