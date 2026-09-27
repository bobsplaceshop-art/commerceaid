<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Cart item DTO.
 *
 * One row per (product, variant) in a cart. The snapshot fields
 * (unitPrice, productName, variantLabel, sku) are captured at
 * add time. They do not track live changes.
 *
 * The cart view pairs each CartItem with the current live
 * product/inventory state via CartLineItem, which flags any
 * drift (price changed, out of stock, product removed).
 *
 * variantId = 0 for simple products, matching the sentinel
 * convention used across the codebase.
 *
 * See docs/stages/008d_cart-plan.md.
 */
final class CartItem
{
    public int $id = 0;
    public int $cartId = 0;
    public int $productId = 0;
    public int $variantId = 0;
    public int $quantity = 1;
    public float $unitPrice = 0.0;
    public string $productName = '';
    public ?string $variantLabel = null;
    public ?string $sku = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $i = new self();
        $i->id           = (int) $row['id'];
        $i->cartId       = (int) $row['cart_id'];
        $i->productId    = (int) $row['product_id'];
        $i->variantId    = (int) $row['variant_id'];
        $i->quantity     = (int) $row['quantity'];
        $i->unitPrice    = (float) $row['unit_price'];
        $i->productName  = (string) $row['product_name'];
        $i->variantLabel = $row['variant_label'] ?? null;
        $i->sku          = $row['sku'] ?? null;
        $i->createdAt    = $row['created_at'] ?? null;
        $i->updatedAt    = $row['updated_at'] ?? null;
        return $i;
    }

    /**
     * Line subtotal - snapshot price times quantity. This is what
     * the customer sees in the cart. It is not a "live" total -
     * that gets recomputed at checkout.
     */
    public function lineSubtotal(): float
    {
        return round($this->unitPrice * $this->quantity, 4);
    }

    public function isForSimpleProduct(): bool
    {
        return $this->variantId === 0;
    }

    /**
     * Build a CartItem from a session-cart item array.
     *
     * The session item shape exactly mirrors the DB row shape,
     * so this is a direct field copy. Used by the merge on login.
     *
     * @param array<string,mixed> $sessionItem
     */
    public static function fromSessionItem(int $cartId, array $sessionItem): self
    {
        $i = new self();
        $i->cartId       = $cartId;
        $i->productId    = (int) ($sessionItem['product_id'] ?? 0);
        $i->variantId    = (int) ($sessionItem['variant_id'] ?? 0);
        $i->quantity     = (int) ($sessionItem['quantity'] ?? 1);
        $i->unitPrice    = (float) ($sessionItem['unit_price'] ?? 0);
        $i->productName  = (string) ($sessionItem['product_name'] ?? '');
        $i->variantLabel = $sessionItem['variant_label'] ?? null;
        $i->sku          = $sessionItem['sku'] ?? null;
        return $i;
    }

    /**
     * Convert to the session storage shape.
     *
     * @return array<string,mixed>
     */
    public function toSessionArray(): array
    {
        return [
            'product_id'    => $this->productId,
            'variant_id'    => $this->variantId,
            'quantity'      => $this->quantity,
            'unit_price'    => $this->unitPrice,
            'product_name'  => $this->productName,
            'variant_label' => $this->variantLabel,
            'sku'           => $this->sku,
        ];
    }
}
