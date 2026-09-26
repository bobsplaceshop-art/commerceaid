<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Inventory DTO - current stock level for a single sellable unit.
 *
 * For simple products, variant_id is 0 (sentinel).
 * For variable products, variant_id is the variant's id.
 *
 * `available` is a GENERATED column: quantity - reserved.
 */
final class Inventory
{
    public int $id = 0;
    public int $storeId = 0;
    public int $productId = 0;
    public int $variantId = 0;              // 0 = simple product
    public int $quantity = 0;
    public int $reserved = 0;
    public int $available = 0;
    public ?int $lowStockThreshold = null;
    public bool $backordersAllowed = false;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $i = new self();
        $i->id                = (int) $row['id'];
        $i->storeId           = (int) $row['store_id'];
        $i->productId         = (int) $row['product_id'];
        $i->variantId         = (int) ($row['variant_id'] ?? 0);
        $i->quantity          = (int) ($row['quantity'] ?? 0);
        $i->reserved          = (int) ($row['reserved'] ?? 0);
        $i->available         = (int) ($row['available'] ?? ($row['quantity'] - $row['reserved']));
        $i->lowStockThreshold = isset($row['low_stock_threshold']) ? (int) $row['low_stock_threshold'] : null;
        $i->backordersAllowed = (bool) ($row['backorders_allowed'] ?? false);
        $i->updatedAt         = $row['updated_at'] ?? null;
        return $i;
    }

    public function isForSimpleProduct(): bool
    {
        return $this->variantId === 0;
    }

    public function isForVariant(): bool
    {
        return $this->variantId > 0;
    }

    public function isInStock(): bool
    {
        return $this->available > 0;
    }

    public function isOutOfStock(): bool
    {
        return $this->available <= 0 && !$this->backordersAllowed;
    }

    /**
     * Low stock when available > 0 but at or below the threshold.
     */
    public function isLowStock(): bool
    {
        if ($this->lowStockThreshold === null) {
            return false;
        }
        return $this->available > 0 && $this->available <= $this->lowStockThreshold;
    }

    public function canSell(int $requestedQty): bool
    {
        if ($this->available >= $requestedQty) {
            return true;
        }
        return $this->backordersAllowed;
    }
}
