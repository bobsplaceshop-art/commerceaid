<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Cart line view-model.
 *
 * Pairs a CartItem (the snapshot taken at add time) with the
 * current live Product and Inventory rows. The cart view renders
 * these, not raw CartItem rows.
 *
 * The reason for the split: the cart must show the customer what
 * they added even if the product has changed, but it must also
 * warn them when something has drifted (price change, product
 * removal, stock drop). CartItem carries the "what they added"
 * snapshot; CartLineItem carries the "what's true now" state
 * alongside.
 *
 * Warnings are:
 *   priceChanged   - live price differs from the snapshot price
 *   outOfStock     - the requested quantity cannot be fulfilled
 *   productRemoved - product was archived or hard-deleted
 *
 * A line with any warning is rendered with a badge in the cart
 * view. The customer can still remove it, but cannot proceed to
 * checkout with it (enforced at 008E).
 *
 * See docs/stages/008d_cart-plan.md.
 */
final class CartLineItem
{
    public CartItem $item;
    public ?Product $liveProduct;
    public ?Inventory $liveInventory;

    public bool $priceChanged = false;
    public bool $outOfStock = false;
    public bool $productRemoved = false;

    public function __construct(
        CartItem $item,
        ?Product $liveProduct,
        ?Inventory $liveInventory
    ) {
        $this->item          = $item;
        $this->liveProduct   = $liveProduct;
        $this->liveInventory = $liveInventory;

        // Product removed? Either the live product row is gone,
        // or it has been archived (status !== 'published').
        if ($liveProduct === null) {
            $this->productRemoved = true;
        } elseif ($liveProduct->status !== 'published') {
            $this->productRemoved = true;
        }

        // Price changed? Compare snapshot against live. Only if
        // the live product exists.
        if ($liveProduct !== null && !$this->productRemoved) {
            $livePrice = $liveProduct->price;
            if (abs($livePrice - $item->unitPrice) > 0.0001) {
                $this->priceChanged = true;
            }
        }

        // Out of stock? Inventory row may be absent (product has
        // no stock tracking) or present with insufficient quantity.
        if ($liveInventory !== null) {
            if (!$liveInventory->canSell($item->quantity)) {
                $this->outOfStock = true;
            }
        }
        // If liveInventory is null, the product does not track
        // stock. Treat as in stock.
    }

    public function hasWarning(): bool
    {
        return $this->priceChanged
            || $this->outOfStock
            || $this->productRemoved;
    }

    /**
     * Live unit price, falling back to the snapshot price if the
     * live product is gone. This is what the cart displays for
     * the row total.
     */
    public function effectiveUnitPrice(): float
    {
        if ($this->productRemoved || $this->liveProduct === null) {
            return $this->item->unitPrice;
        }
        return $this->liveProduct->price;
    }

    /**
     * Line subtotal using the effective price.
     */
    public function effectiveSubtotal(): float
    {
        return round($this->effectiveUnitPrice() * $this->item->quantity, 4);
    }
}
