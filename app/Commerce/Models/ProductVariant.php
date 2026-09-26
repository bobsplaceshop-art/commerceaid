<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Product variant DTO.
 *
 * A variant is one concrete sellable combination of attribute values
 * (e.g. "Red / Medium"). Simple products have no variants.
 */
final class ProductVariant
{
    public int $id = 0;
    public int $storeId = 0;
    public int $productId = 0;
    public ?string $sku = null;
    public ?string $barcode = null;
    public float $price = 0.0;
    public ?float $compareAtPrice = null;
    public ?float $costPrice = null;
    public ?float $weight = null;
    public ?float $length = null;
    public ?float $width = null;
    public ?float $height = null;
    public ?int $imageFileId = null;
    public string $stockStatus = 'in_stock';     // 'in_stock' | 'out_of_stock' | 'backorder'
    public bool $manageStock = true;
    public bool $backordersAllowed = false;
    public ?int $lowStockThreshold = null;
    public bool $isDefault = false;
    public string $status = 'active';          // 'active' | 'archived'
    public int $sortOrder = 0;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $v = new self();
        $v->id                = (int) $row['id'];
        $v->storeId           = (int) $row['store_id'];
        $v->productId         = (int) $row['product_id'];
        $v->sku               = $row['sku'] ?? null;
        $v->barcode           = $row['barcode'] ?? null;
        $v->price             = (float) ($row['price'] ?? 0);
        $v->compareAtPrice    = isset($row['compare_at_price']) ? (float) $row['compare_at_price'] : null;
        $v->costPrice         = isset($row['cost_price']) ? (float) $row['cost_price'] : null;
        $v->weight            = isset($row['weight']) ? (float) $row['weight'] : null;
        $v->length            = isset($row['length']) ? (float) $row['length'] : null;
        $v->width             = isset($row['width']) ? (float) $row['width'] : null;
        $v->height            = isset($row['height']) ? (float) $row['height'] : null;
        $v->imageFileId       = isset($row['image_file_id']) ? (int) $row['image_file_id'] : null;
        $v->stockStatus       = (string) ($row['stock_status'] ?? 'in_stock');
        $v->manageStock       = (bool) ($row['manage_stock'] ?? true);
        $v->backordersAllowed = (bool) ($row['backorders_allowed'] ?? false);
        $v->lowStockThreshold = isset($row['low_stock_threshold']) ? (int) $row['low_stock_threshold'] : null;
        $v->isDefault         = (bool) ($row['is_default'] ?? false);
        $v->status            = (string) ($row['status'] ?? 'active');
        $v->sortOrder         = (int) ($row['sort_order'] ?? 0);
        $v->createdAt         = $row['created_at'] ?? null;
        $v->updatedAt         = $row['updated_at'] ?? null;
        return $v;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function isOnSale(): bool
    {
        return $this->compareAtPrice !== null && $this->compareAtPrice > $this->price;
    }

    public function discountPercent(): int
    {
        if (!$this->isOnSale() || $this->compareAtPrice <= 0) {
            return 0;
        }
        return (int) round((($this->compareAtPrice - $this->price) / $this->compareAtPrice) * 100);
    }

    public function isInStock(): bool
    {
        return $this->stockStatus === 'in_stock';
    }

    public function isOutOfStock(): bool
    {
        return $this->stockStatus === 'out_of_stock';
    }

    public function hasImage(): bool
    {
        return $this->imageFileId !== null && $this->imageFileId > 0;
    }

    /**
     * Attribute combination summary (e.g. "Red / Medium").
     * Populated after hydration by ProductVariantRepository::attachAttributes().
     */
    public string $attributeSummary = '';

    /**
     * Attribute values keyed by attribute slug (e.g. ['color' => 'Red', 'size' => 'M']).
     * Populated after hydration.
     *
     * @var array<string,string>
     */
    public array $attributeValues = [];

    /**
     * Attach the human-readable attribute info to this variant.
     * Called by the repository after fetch.
     *
     * @param array<string,string> $values Keyed by attribute slug
     */
    public function setAttributeInfo(array $values): void
    {
        $this->attributeValues = $values;
        $this->attributeSummary = implode(' / ', array_values($values));
    }
}
