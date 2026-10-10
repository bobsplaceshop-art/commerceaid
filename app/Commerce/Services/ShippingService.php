<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Models\CartLineItem;
use CorporateAid\Commerce\Repositories\ShippingRepository;

/**
 * Shipping rate resolution.
 *
 * Given a cart and a destination, lists the methods that are
 * available and computes the rate each one would charge.
 *
 * Rate computation per method type:
 *   flat          config.rate
 *   free_over     subtotal >= config.threshold ? 0 : config.rate
 *   weight_based  sum(line.weight * qty) * config.per_kg
 *                 floored at config.min_charge
 *   price_based   subtotal / config.unit_size * config.per_unit
 *                 floored at config.min_charge
 *   local_pickup  0.0
 *
 * Zone override: if the zone row that made the method
 * available carries a rate_override, that value replaces the
 * computed rate.
 *
 * Weight-based shipping sums only lines whose product has
 * requiresShipping = true. Null weight is treated as 0. If a
 * cart has no shipping-requiring lines, only local_pickup
 * methods are offered (if any are configured).
 *
 * See docs/stages/008k.5_order-pricing-engine-plan.md.
 */
final class ShippingService
{
    public function __construct(
        private ShippingRepository $shipping,
    ) {
    }

    /**
     * List the methods available to a destination, with the
     * computed rate for each against this cart.
     *
     * @param array<int,CartLineItem> $lines
     * @return array<int,array{
     *   method_id:int, code:string, label:string,
     *   type:string, rate:float, is_pickup:bool
     * }>
     */
    public function availableMethods(
        array $lines,
        float $subtotal,
        string $countryCode,
        ?string $region = ''
    ): array {
        if ($countryCode === '') {
            return [];
        }

        $region = $region ?? '';
        $methods = $this->shipping->methodsForDestination($countryCode, $region);

        $requiresShipping = $this->cartRequiresShipping($lines);

        $available = [];
        foreach ($methods as $method) {
            $type = (string) $method['type'];

            // A cart with no shipping-requiring lines only
            // gets local_pickup methods.
            if (!$requiresShipping && $type !== 'local_pickup') {
                continue;
            }

            $rate = $this->calculateRate($method, $lines, $subtotal);

            $available[] = [
                'method_id' => (int) $method['id'],
                'code'      => (string) $method['code'],
                'label'     => (string) $method['label'],
                'type'      => $type,
                'rate'      => $rate,
                'is_pickup' => $type === 'local_pickup',
            ];
        }

        return $available;
    }

    /**
     * Compute the rate one method would charge for this cart.
     * Does not check zone availability - the caller is
     * responsible for that.
     *
     * @param array<string,mixed> $method  A row from
     *                                     methodsForDestination, possibly
     *                                     including zone_rate_override.
     * @param array<int,CartLineItem> $lines
     */
    public function calculateRate(array $method, array $lines, float $subtotal): float
    {
        $type = (string) ($method['type'] ?? '');

        // Zone override wins when present. isset() already
        // excludes null, so no second check is needed.
        if (isset($method['zone_rate_override'])) {
            return round((float) $method['zone_rate_override'], 4);
        }

        $config = $this->decodeConfig($method['config_json'] ?? null);

        $rate = match ($type) {
            'flat'         => (float) ($config['rate'] ?? 0.0),
            'free_over'    => $this->rateFreeOver($config, $subtotal),
            'weight_based' => $this->rateWeightBased($config, $lines),
            'price_based'  => $this->ratePriceBased($config, $subtotal),
            'local_pickup' => 0.0,
            default        => 0.0,
        };

        if ($rate < 0.0) {
            $rate = 0.0;
        }

        return round($rate, 4);
    }

    /**
     * Resolve which method the customer chose, validating
     * that it is available at this destination.
     *
     * Returns null when:
     *   - the code is unknown for this store
     *   - the method is not offered at this destination
     *   - the method is inactive
     *
     * The caller distinguishes these cases by calling
     * findMethodByCode separately when it needs to know which
     * one failed. This method returns null for all three so
     * that the checkout flow refuses identically - the
     * message shown to the customer is
     * "the shipping option you selected is no longer available".
     *
     * @return array<string,mixed>|null  The method row with
     *                                   zone_rate_override when
     *                                   present.
     */
    public function resolveForCheckout(
        string $methodCode,
        string $countryCode,
        ?string $region = ''
    ): ?array {
        if ($methodCode === '' || $countryCode === '') {
            return null;
        }

        $method = $this->shipping->findMethodByCode($methodCode);
        if ($method === null) {
            return null;
        }

        if (((int) ($method['is_active'] ?? 0)) !== 1) {
            return null;
        }

        // Confirm a zone row matches the destination.
        $region = $region ?? '';
        $available = $this->shipping->methodsForDestination($countryCode, $region);

        foreach ($available as $row) {
            if ((int) $row['id'] === (int) $method['id']) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Whether any line in the cart requires shipping.
     *
     * @param array<int,CartLineItem> $lines
     */
    public function cartRequiresShipping(array $lines): bool
    {
        foreach ($lines as $line) {
            $product = $line->liveProduct;
            if ($product !== null && $product->requiresShipping) {
                return true;
            }
        }
        return false;
    }

    // =================================================================
    // Rate computation per type
    // =================================================================

    /**
     * @param array<string,mixed> $config
     */
    private function rateFreeOver(array $config, float $subtotal): float
    {
        $threshold = (float) ($config['threshold'] ?? 0.0);
        $rate      = (float) ($config['rate'] ?? 0.0);

        if ($threshold > 0.0 && $subtotal >= $threshold) {
            return 0.0;
        }
        return $rate;
    }

    /**
     * Sum of (weight * qty) across shipping-requiring lines,
     * multiplied by per_kg, floored at min_charge.
     *
     * Null weight is treated as 0.
     *
     * @param array<string,mixed> $config
     * @param array<int,CartLineItem> $lines
     */
    private function rateWeightBased(array $config, array $lines): float
    {
        $perKg    = (float) ($config['per_kg'] ?? 0.0);
        $minCharge = (float) ($config['min_charge'] ?? 0.0);

        $totalWeight = 0.0;
        foreach ($lines as $line) {
            $product = $line->liveProduct;
            if ($product === null || !$product->requiresShipping) {
                continue;
            }
            $weight = $product->weight ?? 0.0;
            $qty    = (int) $line->item->quantity;
            $totalWeight += ($weight * $qty);
        }

        $computed = $totalWeight * $perKg;
        return max($computed, $minCharge);
    }

    /**
     * subtotal / unit_size * per_unit, floored at min_charge.
     *
     * @param array<string,mixed> $config
     */
    private function ratePriceBased(array $config, float $subtotal): float
    {
        $perUnit  = (float) ($config['per_unit'] ?? 0.0);
        $unitSize = (float) ($config['unit_size'] ?? 1.0);
        $minCharge = (float) ($config['min_charge'] ?? 0.0);

        if ($unitSize <= 0.0) {
            $unitSize = 1.0;
        }

        $computed = ($subtotal / $unitSize) * $perUnit;
        return max($computed, $minCharge);
    }

    /**
     * Decode a config_json blob. Returns an empty array on
     * any failure.
     *
     * @return array<string,mixed>
     */
    private function decodeConfig(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
