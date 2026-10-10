<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Product DTO.
 *
 * Plain data object. Hydrated via fromRow(), written via
 * ProductRepository. Every property maps to a column on
 * `commerce_products`.
 *
 * Product type determines whether the product has variants:
 *   - simple   : single sellable unit, price on the product row
 *   - variable : one or more variants, price on each variant
 *
 * Soft delete via `deleted_at`; publishing state via `status`.
 */
final class Product
{
    public int $id = 0;
    public int $storeId = 0;
    public string $slug = '';
    public ?string $sku = null;
    public string $name = '';
    public ?string $shortDescription = null;
    public ?string $description = null;
    public ?int $brandId = 0;
    public ?int $primaryCategoryId = null;
    public string $productType = 'simple';     // 'simple' | 'variable'
    public float $price = 0.0;
    public ?float $compareAtPrice = null;
    public ?float $costPrice = null;
    public ?int $taxClassId = null;
    public string $taxStatus = 'taxable';       // 'taxable' | 'none'
    public ?float $taxRateOverridePercent = null; // 0.000 - 100.000, or null
    public ?bool $taxRateOverrideInclusive = null; // null=inherit, false=exclusive, true=inclusive
    public bool $isFeatured = false;
    public bool $isNew = false;
    public ?float $weight = null;
    public ?float $length = null;
    public ?float $width = null;
    public ?float $height = null;
    public bool $requiresShipping = true;
    public string $stockStatus = 'in_stock';     // 'in_stock' | 'out_of_stock' | 'backorder'
    public bool $manageStock = true;
    public bool $backordersAllowed = false;
    public ?int $lowStockThreshold = null;
    public int $minOrderQuantity = 1;
    public ?string $seoTitle = null;
    public ?string $seoDescription = null;
    public ?string $seoKeywords = null;
    public ?int $ogImageFileId = null;
    public string $status = 'active';          // 'draft' | 'published' | 'archived'
    public ?string $publishedAt = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
    public ?int $createdBy = null;
    public ?int $updatedBy = null;
    public ?string $deletedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $p = new self();
        $p->id                = (int) $row['id'];
        $p->storeId           = (int) $row['store_id'];
        $p->slug              = (string) $row['slug'];
        $p->sku               = $row['sku'] ?? null;
        $p->name              = (string) $row['name'];
        $p->shortDescription  = $row['short_description'] ?? null;
        $p->description       = $row['description'] ?? null;
        $p->brandId           = isset($row['brand_id']) ? (int) $row['brand_id'] : null;
        $p->primaryCategoryId = isset($row['primary_category_id']) ? (int) $row['primary_category_id'] : null;
        $p->productType       = (string) ($row['product_type'] ?? 'simple');
        $p->price             = (float) ($row['price'] ?? 0);
        $p->compareAtPrice    = isset($row['compare_at_price']) ? (float) $row['compare_at_price'] : null;
        $p->costPrice         = isset($row['cost_price']) ? (float) $row['cost_price'] : null;
        $p->taxClassId        = isset($row['tax_class_id']) ? (int) $row['tax_class_id'] : null;
        $p->taxStatus         = (string) ($row['tax_status'] ?? 'taxable');
        $p->taxRateOverridePercent = isset($row['tax_rate_override_percent'])
            ? (float) $row['tax_rate_override_percent']
            : null;
        $p->taxRateOverrideInclusive = isset($row['tax_rate_override_inclusive'])
            ? ((int) $row['tax_rate_override_inclusive']) === 1
            : null;
        $p->isFeatured        = (bool) ($row['is_featured'] ?? false);
        $p->isNew             = (bool) ($row['is_new'] ?? false);
        $p->weight            = isset($row['weight']) ? (float) $row['weight'] : null;
        $p->length            = isset($row['length']) ? (float) $row['length'] : null;
        $p->width             = isset($row['width']) ? (float) $row['width'] : null;
        $p->height            = isset($row['height']) ? (float) $row['height'] : null;
        $p->requiresShipping  = (bool) ($row['requires_shipping'] ?? true);
        $p->stockStatus       = (string) ($row['stock_status'] ?? 'in_stock');
        $p->manageStock       = (bool) ($row['manage_stock'] ?? true);
        $p->backordersAllowed = (bool) ($row['backorders_allowed'] ?? false);
        $p->lowStockThreshold = isset($row['low_stock_threshold']) ? (int) $row['low_stock_threshold'] : null;
        $p->minOrderQuantity = (int) ($row['min_order_quantity'] ?? 1);
        $p->seoTitle          = $row['seo_title'] ?? null;
        $p->seoDescription    = $row['seo_description'] ?? null;
        $p->seoKeywords       = $row['seo_keywords'] ?? null;
        $p->ogImageFileId     = isset($row['og_image_file_id']) ? (int) $row['og_image_file_id'] : null;
        $p->status            = (string) ($row['status'] ?? 'draft');
        $p->publishedAt       = $row['published_at'] ?? null;
        $p->createdAt         = $row['created_at'] ?? null;
        $p->updatedAt         = $row['updated_at'] ?? null;
        $p->createdBy         = isset($row['created_by']) ? (int) $row['created_by'] : null;
        $p->updatedBy         = isset($row['updated_by']) ? (int) $row['updated_by'] : null;
        $p->deletedAt         = $row['deleted_at'] ?? null;
        return $p;
    }

    // ---------- State helpers ----------

    public function isSimple(): bool
    {
        return $this->productType === 'simple';
    }

    public function isVariable(): bool
    {
        return $this->productType === 'variable';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function isOnSale(): bool
    {
        return $this->compareAtPrice !== null && $this->compareAtPrice > $this->price;
    }

    public function hasDiscount(): bool
    {
        return $this->isOnSale();
    }

    /**
     * Discount percentage as an integer, or 0 if no sale.
     */
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

    public function allowsBackorders(): bool
    {
        return $this->backordersAllowed;
    }
}
