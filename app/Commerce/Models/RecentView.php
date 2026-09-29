<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Recent-view DTO.
 *
 * One row per (customer, product) in a customer's recently-
 * viewed history. Created lazily on the first view for a
 * logged-in customer. Guest history lives in the customer
 * session and never touches this model.
 *
 * Deliberately minimal, matching WishlistItem:
 *
 *   - No quantity. Not a cart.
 *   - No snapshot fields. Live product data is the source
 *     of truth at render time.
 *   - No variant. A view is product-level. The customer has
 *     not chosen a variant yet.
 *   - No status enum. No conversion lifecycle.
 *
 * The viewedAt timestamp drives list ordering. It is set on
 * insert and bumped on every subsequent view by the schema's
 * ON UPDATE CURRENT_TIMESTAMP modifier. No code path needs
 * to write it explicitly.
 *
 * See docs/stages/008g.2_recently-viewed-plan.md.
 */
final class RecentView
{
    public int $id = 0;
    public int $storeId = 0;
    public int $customerId = 0;
    public int $productId = 0;
    public ?string $viewedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $r = new self();
        $r->id         = (int) $row['id'];
        $r->storeId    = (int) $row['store_id'];
        $r->customerId = (int) $row['customer_id'];
        $r->productId  = (int) $row['product_id'];
        $r->viewedAt   = $row['viewed_at'] ?? null;
        return $r;
    }
}
