<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Product image DTO.
 *
 * One row in commerce_product_images. Maps a file to a
 * product, and optionally to a variant combination or an
 * attribute value (for the swatch selector).
 *
 * The three shapes:
 *
 *   Gallery image     - variantId null, attributeValueId null
 *                       A general product photo. Shown in the
 *                       gallery and as the card image when
 *                       this row is primary.
 *
 *   Swatch preview    - attributeValueId set
 *                       The image shown on the product page
 *                       when the customer selects this
 *                       attribute value ("Red"). Distinct
 *                       from a variant image - the customer
 *                       has not picked a size yet.
 *
 *   Variant override  - variantId set
 *                       The image shown when this exact
 *                       combination (Red + M) is selected.
 *                       Takes precedence over the swatch
 *                       preview when both exist.
 *
 * is_primary flags the image used for product cards and the
 * default hero on the product detail page. Exactly one row
 * per product should have is_primary = 1. The repository
 * enforces this on write.
 *
 * The model does not carry the file itself. It carries the
 * file_id. The repository pairs it with a MediaItem for
 * rendering.
 *
 * See docs/stages/008g.4a.2_product-media-plan.md.
 */
final class ProductImage
{
    public int $id = 0;
    public int $storeId = 0;
    public int $productId = 0;
    public ?int $variantId = null;
    public ?int $attributeValueId = null;
    public int $fileId = 0;
    public ?string $altText = null;
    public int $sortOrder = 0;
    public bool $isPrimary = false;
    public ?string $createdAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $i = new self();
        $i->id               = (int) $row['id'];
        $i->storeId          = (int) $row['store_id'];
        $i->productId        = (int) $row['product_id'];
        // A database row always contains these columns. The
        // values may be NULL. Use array_key_exists so that a
        // NULL value is handled as a null property, not as
        // "the column is missing".
        $i->variantId        = array_key_exists('variant_id', $row) && $row['variant_id'] !== null
            ? (int) $row['variant_id']
            : null;
        $i->attributeValueId = array_key_exists('attribute_value_id', $row) && $row['attribute_value_id'] !== null
            ? (int) $row['attribute_value_id']
            : null;
        $i->fileId           = (int) $row['file_id'];
        $i->altText          = $row['alt_text'] !== null ? (string) $row['alt_text'] : null;
        $i->sortOrder        = (int) $row['sort_order'];
        $i->isPrimary        = (bool) ($row['is_primary'] ?? false);
        $i->createdAt        = $row['created_at'] !== null ? (string) $row['created_at'] : null;
        return $i;
    }

    /**
     * True when this image is specific to a variant
     * combination (Red + M).
     */
    public function isVariantImage(): bool
    {
        return $this->variantId !== null && $this->variantId > 0;
    }

    /**
     * True when this image is a swatch preview for an
     * attribute value (Red, M).
     */
    public function isSwatchPreview(): bool
    {
        return $this->attributeValueId !== null && $this->attributeValueId > 0;
    }

    /**
     * True when this image is a general gallery image with
     * no specific assignment.
     */
    public function isGalleryImage(): bool
    {
        return !$this->isVariantImage() && !$this->isSwatchPreview();
    }

    /**
     * Alt text with a fallback. When the image has no
     * explicit alt, the caller's fallback is used (usually
     * the product name).
     */
    public function altTextOr(string $fallback): string
    {
        if ($this->altText !== null && $this->altText !== '') {
            return $this->altText;
        }
        return $fallback;
    }
}
