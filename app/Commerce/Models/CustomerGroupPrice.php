<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Customer group price override DTO.
 *
 * Represents one price record for a (group, product, variant) tuple.
 * Supports tier pricing via min_quantity and max_quantity.
 *
 * variantId = 0 for simple products.
 * price = null means "use base price" (override hidden but not deleted).
 */
final class CustomerGroupPrice
{
    public int $id = 0;
    public int $storeId = 0;
    public int $customerGroupId = 0;
    public int $productId = 0;
    public int $variantId = 0;
    public ?float $price = null;
    public ?float $compareAtPrice = null;
    public int $minQuantity = 1;
    public ?int $maxQuantity = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $p = new self();
        $p->id                = (int) $row['id'];
        $p->storeId           = (int) $row['store_id'];
        $p->customerGroupId   = (int) $row['customer_group_id'];
        $p->productId         = (int) $row['product_id'];
        $p->variantId         = (int) ($row['variant_id'] ?? 0);
        $p->price             = isset($row['price']) ? (float) $row['price'] : null;
        $p->compareAtPrice    = isset($row['compare_at_price']) ? (float) $row['compare_at_price'] : null;
        $p->minQuantity       = (int) ($row['min_quantity'] ?? 1);
        $p->maxQuantity       = isset($row['max_quantity']) ? (int) $row['max_quantity'] : null;
        $p->createdAt         = $row['created_at'] ?? null;
        $p->updatedAt         = $row['updated_at'] ?? null;
        return $p;
    }

    public function isForSimpleProduct(): bool
    {
        return $this->variantId === 0;
    }

    public function hasTier(): bool
    {
        return $this->minQuantity > 1 || $this->maxQuantity !== null;
    }

    public function tierLabel(): string
    {
        if (!$this->hasTier()) {
            return 'All quantities';
        }
        if ($this->maxQuantity === null) {
            return $this->minQuantity . '+';
        }
        return $this->minQuantity . ' - ' . $this->maxQuantity;
    }

    public function appliesToQuantity(int $qty): bool
    {
        if ($qty < $this->minQuantity) {
            return false;
        }
        if ($this->maxQuantity !== null && $qty > $this->maxQuantity) {
            return false;
        }
        return true;
    }
}
