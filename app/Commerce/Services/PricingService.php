<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Repositories\CustomerGroupPriceRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Repositories\ProductVariantRepository;

/**
 * Pricing resolution service.
 *
 * Resolves the effective price for a product/variant for a
 * specific customer group and quantity.
 *
 * Fallback chain (most specific wins):
 *   1. Variant + group + tier matching quantity
 *   2. Variant + group (min_quantity <= qty, no upper tier match)
 *   3. Product + group + tier
 *   4. Product + group (base override)
 *   5. Base product/variant price (no group)
 *
 * Anonymous visitors (no group) always get base price.
 */
final class PricingService
{
    public function __construct(
        private ProductRepository $products,
        private ProductVariantRepository $variants,
        private CustomerGroupPriceRepository $groupPrices
    ) {
    }

    /**
     * Resolve the effective price.
     *
     * @return array{
     *   unit_price: float,
     *   compare_at_price: ?float,
     *   base_price: float,
     *   source: string,
     *   tier_label: ?string,
     *   discount_percent: int
     * }
     */
    public function resolvePrice(
        int $productId,
        int $variantId = 0,
        ?int $customerGroupId = null,
        int $quantity = 1
    ): array {
        // Load base price
        $basePrice = 0.0;
        $compareAt = null;

        if ($variantId > 0) {
            $variant = $this->variants->findById($variantId);
            if ($variant !== null) {
                $basePrice = $variant->price;
                $compareAt = $variant->compareAtPrice;
            }
        } else {
            $product = $this->products->findById($productId);
            if ($product !== null) {
                $basePrice = $product->price;
                $compareAt = $product->compareAtPrice;
            }
        }

        // No group = base price
        if ($customerGroupId === null || $customerGroupId <= 0) {
            return [
                'unit_price'        => $basePrice,
                'compare_at_price'  => $compareAt,
                'base_price'        => $basePrice,
                'source'            => 'base',
                'tier_label'        => null,
                'discount_percent'  => 0,
            ];
        }

        // Try variant-level override
        if ($variantId > 0) {
            $tier = $this->groupPrices->resolveForQuantity(
                $customerGroupId, $productId, $variantId, $quantity
            );
            if ($tier !== null && $tier->price !== null) {
                return $this->result($tier->price, $tier->compareAtPrice ?? $compareAt, $basePrice, $tier->hasTier() ? $tier->tierLabel() : null);
            }
        }

        // Try product-level override (with sentinel variant_id = 0)
        $tier = $this->groupPrices->resolveForQuantity(
            $customerGroupId, $productId, 0, $quantity
        );
        if ($tier !== null && $tier->price !== null) {
            return $this->result($tier->price, $tier->compareAtPrice ?? $compareAt, $basePrice, $tier->hasTier() ? $tier->tierLabel() : null);
        }

        // No override found — base price
        return [
            'unit_price'        => $basePrice,
            'compare_at_price'  => $compareAt,
            'base_price'        => $basePrice,
            'source'            => 'base',
            'tier_label'        => null,
            'discount_percent'  => 0,
        ];
    }

    private function result(float $unit, ?float $compare, float $base, ?string $tierLabel): array
    {
        $discount = 0;
        if ($base > 0 && $unit < $base) {
            $discount = (int) round((($base - $unit) / $base) * 100);
        }

        return [
            'unit_price'        => $unit,
            'compare_at_price'  => $compare,
            'base_price'        => $base,
            'source'            => 'group',
            'tier_label'        => $tierLabel,
            'discount_percent'  => $discount,
        ];
    }
}
