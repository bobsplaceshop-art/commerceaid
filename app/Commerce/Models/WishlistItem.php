<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Wishlist item DTO.
 *
 * One row per (customer, product) in a wishlist. Created
 * lazily on first heart click for a logged-in customer. Guest
 * wishlists live in the customer session and never touch this
 * model.
 *
 * Deliberately minimal compared to CartItem:
 *
 *   - No quantity. A wishlist is not a cart.
 *   - No snapshot fields (unit_price, product_name, sku,
 *     variant_label). Live product data is the source of
 *     truth at render time.
 *   - No status enum. No conversion lifecycle.
 *
 * The model carries the ID of the product, nothing more. The
 * wishlist page fetches the Product for each row and renders
 * live data.
 *
 * variantId is reserved for a future sub-stage (variant-level
 * wishlisting). It defaults to 0 and no code path in 008G.1
 * reads or writes it.
 *
 * See docs/stages/008g.1_wishlist-plan.md.
 */
final class WishlistItem
{
    public int $id = 0;
    public int $storeId = 0;
    public int $customerId = 0;
    public int $productId = 0;
    public int $variantId = 0;
    public ?string $createdAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $w = new self();
        $w->id         = (int) $row['id'];
        $w->storeId    = (int) $row['store_id'];
        $w->customerId = (int) $row['customer_id'];
        $w->productId  = (int) $row['product_id'];
        $w->variantId  = (int) ($row['variant_id'] ?? 0);
        $w->createdAt  = $row['created_at'] ?? null;
        return $w;
    }
}
