<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Exceptions\CheckoutException;
use CorporateAid\Commerce\Models\CartLineItem;

/**
 * Order pricing engine.
 *
 * The single source of truth for the six-step totals
 * sequence:
 *
 *   1. subtotal      = sum(line.effectiveSubtotal())
 *   2. discountTotal = coupon applied to subtotal
 *   3. taxableBase   = subtotal - discountTotal
 *   4. shippingTotal = shipping method rate
 *   5. taxTotal      = tax computed on (taxableBase + shipping
 *                       when the rate is shipping-taxable)
 *   6. grandTotal    = taxableBase + taxTotal + shippingTotal
 *
 * Every other service (CartService, CheckoutService, the
 * cart and checkout views) reads the totals from this
 * service. No other class computes the order pricing
 * arithmetic.
 *
 * Three public entry points:
 *   compute()       - the general form; never throws for
 *                     expected failures (invalid coupon,
 *                     unavailable shipping), returns a
 *                     structured result with ok:false
 *   preview()       - alias of compute() used by the views
 *   computeOrFail() - strict form used inside the checkout
 *                     transaction; throws CheckoutException
 *                     on any failure
 *
 * See docs/stages/008k.5_order-pricing-engine-plan.md.
 */
final class OrderPricingService
{
    public function __construct(
        private CouponService $coupons,
        private TaxService $taxes,
        private ShippingService $shipping,
    ) {
    }

    /**
     * Compute the full pricing breakdown.
     *
     * @param array<int,CartLineItem> $lines
     * @param array<string,mixed> $context {
     *   coupon_code:?string,
     *   shipping_method_code:?string,
     *   country_code:string,
     *   state_code:?string,
     *   customer_id:?int,
     *   guest_email:?string
     * }
     * @return array{
     *   ok:bool,
     *   reason?:string,
     *   subtotal:float,
     *   discount_total:float,
     *   taxable_base:float,
     *   tax_total:float,
     *   shipping_total:float,
     *   grand_total:float,
     *   coupon:?array<string,mixed>,
     *   shipping_method:?array<string,mixed>,
     *   tax_classes:array<int,array{tax_class_id:?int, tax_rate_id:?int, label:string, rate_percent:float, taxable_base:float, amount:float}>
     * }
     */
    public function compute(array $lines, array $context): array
    {
        $countryCode = (string) ($context['country_code'] ?? '');
        $stateCode   = $context['state_code'] ?? null;
        $customerId  = $context['customer_id'] ?? null;
        $guestEmail  = $context['guest_email'] ?? null;
        $couponCode  = (string) ($context['coupon_code'] ?? '');
        $methodCode  = (string) ($context['shipping_method_code'] ?? '');

        // -----------------------------------------------------------------
        // Step 1 - subtotal
        // -----------------------------------------------------------------
        $subtotal = 0.0;
        foreach ($lines as $line) {
            $subtotal += $line->effectiveSubtotal();
        }
        $subtotal = round($subtotal, 4);

        // -----------------------------------------------------------------
        // Step 2 - discount
        // -----------------------------------------------------------------
        $discountTotal = 0.0;
        $coupon        = null;
        $isFreeShipping = false;

        if ($couponCode !== '') {
            $validation = $this->coupons->validate(
                $couponCode,
                $lines,
                $subtotal,
                is_int($customerId) ? $customerId : null,
                is_string($guestEmail) ? $guestEmail : null,
            );

            if (!($validation['ok'] ?? false)) {
                return $this->failure(
                    $subtotal,
                    (string) ($validation['reason'] ?? 'Invalid coupon.')
                );
            }

            $coupon        = $validation['coupon'] ?? null;
            $discountTotal = (float) ($validation['discount'] ?? 0.0);
            $isFreeShipping = $this->coupons->isFreeShipping($coupon);
        }

        // -----------------------------------------------------------------
        // Step 3 - taxable base
        // -----------------------------------------------------------------
        $taxableBase = round($subtotal - $discountTotal, 4);
        if ($taxableBase < 0.0) {
            $taxableBase = 0.0;
        }

        // -----------------------------------------------------------------
        // Step 4 - shipping
        // -----------------------------------------------------------------
        $shippingTotal  = 0.0;
        $shippingMethod = null;

        if ($methodCode !== '' && $countryCode !== '') {
            $region = is_string($stateCode) ? $stateCode : '';
            $resolved = $this->shipping->resolveForCheckout(
                $methodCode,
                $countryCode,
                $region
            );

            if ($resolved === null) {
                return $this->failure(
                    $subtotal,
                    'The shipping option you selected is no longer available.'
                );
            }

            $shippingMethod = $resolved;

            // Free-shipping coupon zeroes the shipping charge.
            // Otherwise compute the rate as normal.
            if ($isFreeShipping) {
                $shippingTotal = 0.0;
            } else {
                $shippingTotal = $this->shipping->calculateRate(
                    $resolved,
                    $lines,
                    $subtotal
                );
            }
        }

        // -----------------------------------------------------------------
        // Step 5 - tax
        // -----------------------------------------------------------------
        $taxResult = $this->taxes->calculateTax(
            $lines,
            $taxableBase,
            $shippingTotal,
            $countryCode,
            is_string($stateCode) ? $stateCode : null,
            $discountTotal
        );

        $taxTotal = (float) ($taxResult['total'] ?? 0.0);
        $taxClasses = $taxResult['classes'] ?? [];

        // -----------------------------------------------------------------
        // Step 6 - grand total
        // -----------------------------------------------------------------
        $grandTotal = round($taxableBase + $taxTotal + $shippingTotal, 4);
        if ($grandTotal < 0.0) {
            $grandTotal = 0.0;
        }

        return [
            'ok'              => true,
            'subtotal'        => $subtotal,
            'discount_total'  => $discountTotal,
            'taxable_base'    => $taxableBase,
            'tax_total'       => $taxTotal,
            'shipping_total'  => $shippingTotal,
            'grand_total'     => $grandTotal,
            'coupon'          => $coupon,
            'shipping_method' => $shippingMethod,
            'tax_classes'     => $taxClasses,
        ];
    }

    /**
     * Preview form. Never throws for expected failures. The
     * cart and checkout views call this to render the totals
     * breakdown.
     *
     * Same return shape as compute().
     *
     * @param array<int,CartLineItem> $lines
     * @param array<string,mixed> $context
     * @return array{
     *   ok:bool,
     *   reason?:string,
     *   subtotal:float,
     *   discount_total:float,
     *   taxable_base:float,
     *   tax_total:float,
     *   shipping_total:float,
     *   grand_total:float,
     *   coupon:?array<string,mixed>,
     *   shipping_method:?array<string,mixed>,
     *   tax_classes:array<int,array{tax_class_id:?int, tax_rate_id:?int, label:string, rate_percent:float, taxable_base:float, amount:float}>
     * }
     */
    public function preview(array $lines, array $context): array
    {
        return $this->compute($lines, $context);
    }

    /**
     * Strict form. Throws CheckoutException on any pricing
     * failure. Used inside the checkout transaction where a
     * failure must roll back the order.
     *
     * @param array<int,CartLineItem> $lines
     * @param array<string,mixed> $context
     * @return array{
     *   ok:bool,
     *   subtotal:float,
     *   discount_total:float,
     *   taxable_base:float,
     *   tax_total:float,
     *   shipping_total:float,
     *   grand_total:float,
     *   coupon:?array<string,mixed>,
     *   shipping_method:?array<string,mixed>,
     *   tax_classes:array<int,array{tax_class_id:?int, tax_rate_id:?int, label:string, rate_percent:float, taxable_base:float, amount:float}>
     * }
     * @throws CheckoutException
     */
    public function computeOrFail(array $lines, array $context): array
    {
        $result = $this->compute($lines, $context);
        if (!($result['ok'] ?? false)) {
            throw new CheckoutException(
                (string) ($result['reason'] ?? 'Pricing failed.')
            );
        }
        return $result;
    }

    /**
     * A structured failure result. The subtotal is preserved
     * so the caller can still render the cart.
     *
     * @return array{
     *   ok:bool,
     *   reason:string,
     *   subtotal:float,
     *   discount_total:float,
     *   taxable_base:float,
     *   tax_total:float,
     *   shipping_total:float,
     *   grand_total:float,
     *   coupon:?array<string,mixed>,
     *   shipping_method:?array<string,mixed>,
     *   tax_classes:array<int,array{tax_class_id:?int, tax_rate_id:?int, label:string, rate_percent:float, taxable_base:float, amount:float}>
     * }
     */
    private function failure(float $subtotal, string $reason): array
    {
        return [
            'ok'              => false,
            'reason'          => $reason,
            'subtotal'        => $subtotal,
            'discount_total'  => 0.0,
            'taxable_base'    => $subtotal,
            'tax_total'       => 0.0,
            'shipping_total'  => 0.0,
            'grand_total'     => $subtotal,
            'coupon'          => null,
            'shipping_method' => null,
            'tax_classes'     => [],
        ];
    }
}
