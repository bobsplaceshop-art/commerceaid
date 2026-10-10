<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Models\CartLineItem;
use CorporateAid\Commerce\Repositories\TaxRepository;

/**
 * Tax computation.
 *
 * Given the cart lines, a destination, and the discount
 * applied, computes the tax per tax class and the order
 * total. Persists the per-class breakdown to
 * commerce_order_taxes so the invoice can show VAT by rate
 * (§ 14 UStG).
 *
 * Rate resolution: for each line, the product's tax class is
 * used. If the product has no class, the store's default
 * class is used. If no rate matches the destination, a zero
 * rate applies.
 *
 * Inclusive vs exclusive: for is_inclusive = 1 (Germany,
 * B2C), the unit price already contains the tax. The service
 * backs the tax out of the line subtotal:
 *   net = gross / (1 + rate/100)
 *   tax = gross - net
 * For is_inclusive = 0, the tax is added on top:
 *   net = gross
 *   tax = gross * rate/100
 *
 * Discount allocation: the discount is spread across lines
 * in proportion to their gross subtotal. A discount that
 * reduces the subtotal by 10% reduces each line's taxable
 * base by 10%.
 *
 * Shipping: when is_shipping_taxable = 1 for the resolved
 * rate, the shipping total joins the taxable base for that
 * class.
 *
 * See docs/stages/008k.5_order-pricing-engine-plan.md.
 */
final class TaxService
{
    public function __construct(
        private TaxRepository $taxes,
    ) {
    }

    /**
     * Resolve the tax rate applicable to a product at a
     * destination.
     *
     * Precedence:
     *   1. tax_status = 'none'              -> exempt (zero rate)
     *   2. tax_rate_override_percent set    -> use this rate,
     *                                          flags from the
     *                                          class's rate
     *   3. tax class rate for the destination
     *   4. zero rate (no class, no rate)
     *
     * The product-level override changes only the rate
     * percent. The is_inclusive and is_shipping_taxable
     * flags always come from the class's rate for the
     * destination, so the store's inclusive-pricing
     * convention is unchanged by an override.
     *
     * If the product's taxStatus is 'none', a zero rate is
     * returned regardless of class or override.
     *
     * @param \CorporateAid\Commerce\Models\Product|null $product
     * @return array{tax_class_id:?int, tax_rate_id:?int,
     *               label:string, rate_percent:float,
     *               is_inclusive:bool, is_shipping_taxable:bool}
     */
    public function resolveRateForLine(
        ?\CorporateAid\Commerce\Models\Product $product,
        string $countryCode,
        ?string $stateCode
    ): array {
        // Tax-exempt products short-circuit to a zero rate.
        if ($product !== null && $product->taxStatus === 'none') {
            return [
                'tax_class_id'         => null,
                'tax_rate_id'          => null,
                'label'                => 'Exempt',
                'rate_percent'         => 0.0,
                'is_inclusive'         => false,
                'is_shipping_taxable'  => false,
            ];
        }

        // Pick the class. Product class wins; fall back to
        // store default; fall back to nothing.
        $classId = null;
        $className = '';

        $productTaxClassId = $product !== null ? $product->taxClassId : null;

        if ($productTaxClassId !== null && $productTaxClassId > 0) {
            $class = $this->taxes->findClass($productTaxClassId);
            if ($class !== null) {
                $classId = (int) $class['id'];
                $className = (string) $class['name'];
            }
        }

        if ($classId === null) {
            $class = $this->taxes->defaultClass();
            if ($class !== null) {
                $classId = (int) $class['id'];
                $className = (string) $class['name'];
            }
        }

        // No class at all in the store: zero rate.
        if ($classId === null) {
            return [
                'tax_class_id'         => null,
                'tax_rate_id'          => null,
                'label'                => 'No tax',
                'rate_percent'         => 0.0,
                'is_inclusive'         => false,
                'is_shipping_taxable'  => false,
            ];
        }

        // Look up a rate for the destination.
        $rate = $this->taxes->resolveRate($classId, $countryCode, $stateCode);

        // Resolve the product-level override, if set. The
        // override replaces the rate_percent. For the
        // is_inclusive flag, precedence is:
        //   1. the override's own inclusive flag if set
        //   2. the class rate's is_inclusive
        //   3. false
        // is_shipping_taxable is always taken from the class
        // rate (or false when there is no rate).
        $override = $product !== null ? $product->taxRateOverridePercent : null;

        if ($override !== null) {
            $overrideInclusive = $product !== null
                ? $product->taxRateOverrideInclusive
                : null;

            $isInclusive = $overrideInclusive !== null
                ? $overrideInclusive
                : ($rate !== null
                    ? ((int) $rate['is_inclusive']) === 1
                    : false);

            return [
                'tax_class_id'         => $classId,
                'tax_rate_id'          => $rate !== null ? (int) $rate['id'] : null,
                'label'                => $className !== ''
                                            ? $className
                                            : 'Override',
                'rate_percent'         => (float) $override,
                'is_inclusive'         => $isInclusive,
                'is_shipping_taxable'  => $rate !== null
                                            ? ((int) $rate['is_shipping_taxable']) === 1
                                            : false,
            ];
        }

        if ($rate === null) {
            // No rate for this country/state. Zero rate on the
            // class.
            return [
                'tax_class_id'         => $classId,
                'tax_rate_id'          => null,
                'label'                => $className,
                'rate_percent'         => 0.0,
                'is_inclusive'         => false,
                'is_shipping_taxable'  => false,
            ];
        }

        return [
            'tax_class_id'         => $classId,
            'tax_rate_id'          => (int) $rate['id'],
            'label'                => $className,
            'rate_percent'         => (float) $rate['rate_percent'],
            'is_inclusive'         => ((int) $rate['is_inclusive']) === 1,
            'is_shipping_taxable'  => ((int) $rate['is_shipping_taxable']) === 1,
        ];
    }

    /**
     * Compute the full tax breakdown for an order.
     *
     * @param array<int,CartLineItem> $lines
     * @param float $discountedSubtotal  subtotal - discountTotal
     * @param float $shippingTotal       the shipping charge to
     *                                   be taxed when
     *                                   is_shipping_taxable
     * @param float $discountTotal       the discount applied
     * The total field is the sum of every class's tax amount.
     * The inclusive_total and exclusive_total fields split
     * that sum by regime:
     *
     *   inclusive_total  - tax that is already inside the
     *                      gross amounts (German PAngV). The
     *                      pricing engine does NOT add this
     *                      to the grand total.
     *   exclusive_total  - tax that must be added on top of
     *                      the gross amounts (US-style net
     *                      pricing). The pricing engine adds
     *                      this to the grand total.
     *
     * A cart can carry both if products use different tax
     * classes with different is_inclusive flags. The pricing
     * engine uses the split to compute the grand total
     * correctly per line.
     *
     * @return array{
     *   total:float,
     *   inclusive_total:float,
     *   exclusive_total:float,
     *   classes:array<int,array{
     *     tax_class_id:?int, tax_rate_id:?int,
     *     label:string, rate_percent:float,
     *     taxable_base:float, amount:float,
     *     is_inclusive:bool
     *   }>
     * }
     */
    public function calculateTax(
        array $lines,
        float $discountedSubtotal,
        float $shippingTotal,
        string $countryCode,
        ?string $stateCode,
        float $discountTotal
    ): array {
        // Compute the raw subtotal from the lines. This is the
        // denominator for the per-class proportional split.
        $rawSubtotal = 0.0;
        foreach ($lines as $line) {
            $rawSubtotal += $line->effectiveSubtotal();
        }
        $rawSubtotal = round($rawSubtotal, 4);

        // Walk each line, resolve its rate, and accumulate the
        // line's net and gross into per-class buckets.
        // Structure:
        //   byClass[classId] = [
        //     class_id, rate_id, label, rate_percent,
        //     is_inclusive, is_shipping_taxable,
        //     gross_total, tax_total
        //   ]
        $byClass = [];

        foreach ($lines as $line) {
            $product = $line->liveProduct;

            // resolveRateForLine handles the taxStatus='none'
            // case and the class-resolution fallback chain
            // internally.
            $rate = $this->resolveRateForLine($product, $countryCode, $stateCode);
            $classKey = $rate['tax_class_id'] ?? 0;

            $lineGross = $line->effectiveSubtotal();

            // Back out the tax from a gross line if inclusive.
            if ($rate['is_inclusive'] && $rate['rate_percent'] > 0) {
                $lineTax = round($lineGross - ($lineGross / (1 + $rate['rate_percent'] / 100)), 4);
            } else {
                $lineTax = round($lineGross * ($rate['rate_percent'] / 100), 4);
            }

            if (!isset($byClass[$classKey])) {
                $byClass[$classKey] = [
                    'tax_class_id'         => $rate['tax_class_id'],
                    'tax_rate_id'          => $rate['tax_rate_id'],
                    'label'                => $rate['label'],
                    'rate_percent'         => $rate['rate_percent'],
                    'is_inclusive'         => $rate['is_inclusive'],
                    'is_shipping_taxable'  => $rate['is_shipping_taxable'],
                    'gross_total'          => 0.0,
                    'tax_total'            => 0.0,
                ];
            }

            $byClass[$classKey]['gross_total'] = round(
                $byClass[$classKey]['gross_total'] + $lineGross,
                4
            );
            $byClass[$classKey]['tax_total'] = round(
                $byClass[$classKey]['tax_total'] + $lineTax,
                4
            );
        }

        // Apply the discount proportionally. Each class's
        // effective taxable base shrinks by the same ratio the
        // overall subtotal did.
        $ratio = $rawSubtotal > 0.0
            ? max(0.0, min(1.0, $discountedSubtotal / $rawSubtotal))
            : 1.0;

        // Shipping: split across classes that opted in, weighted
        // by gross_total. If multiple classes opt in, each gets
        // a proportional share of the shipping charge.
        $shippingOptInTotal = 0.0;
        foreach ($byClass as $c) {
            if ($c['is_shipping_taxable']) {
                $shippingOptInTotal += $c['gross_total'];
            }
        }

        /** @var array<int,array{
         *   tax_class_id:?int, tax_rate_id:?int,
         *   label:string, rate_percent:float,
         *   taxable_base:float, amount:float,
         *   is_inclusive:bool
         * }> $classes */
        $classes = [];
        $grandTax = 0.0;
        $inclusiveTaxTotal = 0.0;
        $exclusiveTaxTotal = 0.0;

        foreach ($byClass as $c) {
            // Scaled gross after discount.
            $scaledGross = round($c['gross_total'] * $ratio, 4);

            // Shipping share for this class.
            $shippingShare = 0.0;
            if ($c['is_shipping_taxable'] && $shippingTotal > 0.0 && $shippingOptInTotal > 0.0) {
                $shippingShare = round(
                    $shippingTotal * ($c['gross_total'] / $shippingOptInTotal),
                    4
                );
            }

            // Recompute tax on the discounted base (+ shipping
            // share for opted-in classes).
            $taxableGross = $scaledGross + $shippingShare;
            if ($c['is_inclusive'] && $c['rate_percent'] > 0) {
                $taxAmount = round(
                    $taxableGross - ($taxableGross / (1 + $c['rate_percent'] / 100)),
                    4
                );
            } else {
                $taxAmount = round($taxableGross * ($c['rate_percent'] / 100), 4);
            }

            $classes[] = [
                'tax_class_id' => $c['tax_class_id'],
                'tax_rate_id'  => $c['tax_rate_id'],
                'label'        => $c['label'],
                'rate_percent' => $c['rate_percent'],
                'taxable_base' => $taxableGross,
                'amount'       => $taxAmount,
                'is_inclusive' => (bool) $c['is_inclusive'],
            ];

            $grandTax = round($grandTax + $taxAmount, 4);

            if ($c['is_inclusive']) {
                $inclusiveTaxTotal = round($inclusiveTaxTotal + $taxAmount, 4);
            } else {
                $exclusiveTaxTotal = round($exclusiveTaxTotal + $taxAmount, 4);
            }
        }

        return [
            'total'           => $grandTax,
            'inclusive_total' => $inclusiveTaxTotal,
            'exclusive_total' => $exclusiveTaxTotal,
            'classes'         => $classes,
        ];
    }

    /**
     * Persist the per-class breakdown. Delegates to the
     * repository which writes to commerce_order_taxes.
     *
     * @param array<int,array{
     *   tax_class_id:?int, tax_rate_id:?int,
     *   label:string, rate_percent:float,
     *   amount:float
     * }> $classes
     */
    public function persistOrderTaxes(int $orderId, array $classes): void
    {
        $this->taxes->recordOrderTaxes($orderId, $classes);
    }

    /**
     * The tax breakdown for an already-placed order.
     * Used by the order detail and invoice rendering.
     *
     * @return array<int,array<string,mixed>>
     */
    public function forOrder(int $orderId): array
    {
        return $this->taxes->listForOrder($orderId);
    }
}
