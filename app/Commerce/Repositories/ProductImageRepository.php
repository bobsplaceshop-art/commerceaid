<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\ProductImage;

/**
 * Product image repository.
 *
 * Every row is scoped to (store_id, product_id). The
 * store_id comes from StoreContext; the product_id is a
 * parameter on every method.
 *
 * An image row can carry more than one role at once:
 *
 *   - is_primary = 1           hero image
 *   - attribute_value_id set   swatch preview for that
 *                              attribute value (e.g. Red)
 *   - variant_id set           override for that specific
 *                              combination
 *
 * These are not mutually exclusive. A single row can be
 * the primary image AND the swatch preview for Red. The
 * merchant uploads one image; the storefront renders it
 * wherever it is needed (hero, thumbnail strip, color
 * swatch).
 *
 * Readers by intent:
 *   listForProduct()     - admin Images tab. Product
 *                          photos only (rows with no
 *                          attribute_value_id), so the
 *                          admin grid does not mix product
 *                          photos with swatch previews.
 *   listAllForProduct()  - storefront gallery (hero +
 *                          thumbnail strip). Every row
 *                          for the product.
 *   swatchPreviewsFor()  - storefront swatch row. Only
 *                          rows with attribute_value_id,
 *                          keyed by value id.
 *   variantImagesFor()   - variant combination overrides.
 *
 * The repository does not resolve file_id to a MediaItem.
 * Callers pair the ProductImage rows with MediaItem via
 * MediaRepository::findByIds() in a single batch query.
 *
 * The primary image is flagged with is_primary = 1. The
 * repository enforces the invariant that exactly one row per
 * product carries the flag on every write that could break
 * it (add, remove, setPrimary).
 *
 * See docs/stages/008g.4a.2_product-media-plan.md.
 */
final class ProductImageRepository extends CommerceRepository
{
    // =================================================================
    // Reads
    // =================================================================

    /**
     * Gallery images for a product, ordered by sort_order, id.
     * Gallery rows are those with attribute_value_id IS NULL -
     * product photos that show in the storefront gallery and
     * the admin gallery grid.
     *
     * Swatch previews (attribute_value_id set) are read
     * separately by swatchPreviewsFor(). Variant overrides
     * (variant_id set) are not in use yet.
     *
     * @return array<int, ProductImage>
     */
    public function listForProduct(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $rows = $this->db->select(
            "SELECT * FROM `commerce_product_images`
              WHERE `store_id` = ? AND `product_id` = ?
                AND `attribute_value_id` IS NULL
              ORDER BY `sort_order` ASC, `id` ASC",
            [$this->storeId(), $productId]
        );

        return array_map(fn ($r) => ProductImage::fromRow($r), $rows);
    }

    /**
     * Every image assigned to a product, regardless of
     * whether it is also assigned to an attribute value or
     * a variant. This is what the storefront gallery reads
     * (hero + thumbnail strip).
     *
     * The admin Images tab uses listForProduct() instead,
     * which filters to rows with no attribute_value_id so
     * that product photos and swatch previews are not
     * visually mixed in the admin grid. That filter must
     * not reach the storefront: a merchant can upload a
     * photo that is both a product image and a swatch
     * preview, and the storefront must show it in both
     * places.
     *
     * Ordered primary-first, then by sort_order, then id,
     * so the hero picks the primary row and the strip
     * preserves the merchant's chosen order.
     *
     * @return array<int, ProductImage>
     */
    public function listAllForProduct(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $rows = $this->db->select(
            "SELECT * FROM `commerce_product_images`
              WHERE `store_id` = ? AND `product_id` = ?
              ORDER BY `is_primary` DESC, `sort_order` ASC, `id` ASC",
            [$this->storeId(), $productId]
        );

        return array_map(fn ($r) => ProductImage::fromRow($r), $rows);
    }

    /**
     * Images assigned to a specific variant combination.
     *
     * @return array<int, ProductImage>
     */
    public function listForVariant(int $variantId): array
    {
        if ($variantId <= 0) {
            return [];
        }

        $rows = $this->db->select(
            "SELECT * FROM `commerce_product_images`
              WHERE `store_id` = ? AND `variant_id` = ?
              ORDER BY `sort_order` ASC, `id` ASC",
            [$this->storeId(), $variantId]
        );

        return array_map(fn ($r) => ProductImage::fromRow($r), $rows);
    }

    /**
     * The product's primary image, or the lowest-sort_order
     * gallery image when no row carries the primary flag.
     *
     * Returns null when the product has no images.
     */
    public function primaryFor(int $productId): ?ProductImage
    {
        if ($productId <= 0) {
            return null;
        }

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_product_images`
              WHERE `store_id` = ? AND `product_id` = ?
              ORDER BY `is_primary` DESC, `sort_order` ASC, `id` ASC
              LIMIT 1",
            [$this->storeId(), $productId]
        );

        return $row !== null ? ProductImage::fromRow($row) : null;
    }

    public function findById(int $id): ?ProductImage
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_product_images`
              WHERE `id` = ? AND `store_id` = ?
              LIMIT 1",
            [$id, $this->storeId()]
        );

        return $row !== null ? ProductImage::fromRow($row) : null;
    }

    /**
     * Swatch preview images keyed by attribute_value_id.
     *
     * Used by the product detail page to build the color
     * swatches. Only rows with a non-null attribute_value_id
     * are returned.
     *
     * @return array<int, ProductImage>  keyed by attribute_value_id
     */
    public function swatchPreviewsFor(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $rows = $this->db->select(
            "SELECT * FROM `commerce_product_images`
              WHERE `store_id` = ? AND `product_id` = ?
                AND `attribute_value_id` IS NOT NULL
              ORDER BY `sort_order` ASC, `id` ASC",
            [$this->storeId(), $productId]
        );

        $out = [];
        foreach ($rows as $r) {
            $img = ProductImage::fromRow($r);
            if ($img->attributeValueId !== null) {
                $out[$img->attributeValueId] = $img;
            }
        }
        return $out;
    }

    /**
     * Variant override images keyed by variant_id.
     *
     * Used by the product detail page when a specific
     * combination is selected. Takes precedence over a swatch
     * preview when both exist for the same view.
     *
     * @return array<int, ProductImage>  keyed by variant_id
     */
    public function variantImagesFor(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $rows = $this->db->select(
            "SELECT * FROM `commerce_product_images`
              WHERE `store_id` = ? AND `product_id` = ?
                AND `variant_id` IS NOT NULL
              ORDER BY `sort_order` ASC, `id` ASC",
            [$this->storeId(), $productId]
        );

        $out = [];
        foreach ($rows as $r) {
            $img = ProductImage::fromRow($r);
            if ($img->variantId !== null) {
                $out[$img->variantId] = $img;
            }
        }
        return $out;
    }

    public function countForProduct(int $productId): int
    {
        if ($productId <= 0) {
            return 0;
        }

        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_product_images`
              WHERE `store_id` = ? AND `product_id` = ?",
            [$this->storeId(), $productId]
        );
    }

    // =================================================================
    // Writes
    // =================================================================

    /**
     * Insert a new image row. Returns the new row id.
     *
     * If this is the product's first image, it is marked
     * primary automatically. Otherwise the caller must call
     * setPrimary() explicitly to change the primary flag.
     */
    public function add(
        int $productId,
        int $fileId,
        ?int $variantId = null,
        ?int $attributeValueId = null,
        ?string $altText = null
    ): int {
        if ($productId <= 0 || $fileId <= 0) {
            return 0;
        }

        $storeId = $this->storeId();

        // Next sort order = max + 1 for this product.
        $nextSort = (int) $this->db->scalar(
            "SELECT COALESCE(MAX(`sort_order`), -1) + 1
               FROM `commerce_product_images`
              WHERE `store_id` = ? AND `product_id` = ?",
            [$storeId, $productId]
        );

        // Is this the first image for the product?
        $isFirst = $this->countForProduct($productId) === 0;

        $id = (int) $this->db->insert(
            "INSERT INTO `commerce_product_images`
                (`store_id`, `product_id`, `variant_id`,
                 `attribute_value_id`, `file_id`, `alt_text`,
                 `sort_order`, `is_primary`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $storeId,
                $productId,
                $variantId,
                $attributeValueId,
                $fileId,
                $altText,
                $nextSort,
                $isFirst ? 1 : 0,
            ]
        );

        return $id;
    }

    /**
     * Update an existing image row. Only the fields provided
     * are updated. Missing keys are left unchanged.
     *
     * @param array{file_id?:int, alt_text?:?string, sort_order?:int,
     *              is_primary?:bool, variant_id?:?int,
     *              attribute_value_id?:?int} $fields
     */
    public function update(int $id, array $fields): void
    {
        if ($id <= 0 || $fields === []) {
            return;
        }

        $sets   = [];
        $params = [];

        if (array_key_exists('file_id', $fields)) {
            $sets[]   = "`file_id` = ?";
            $params[] = (int) $fields['file_id'];
        }
        if (array_key_exists('alt_text', $fields)) {
            $sets[]   = "`alt_text` = ?";
            $params[] = $fields['alt_text'];
        }
        if (array_key_exists('sort_order', $fields)) {
            $sets[]   = "`sort_order` = ?";
            $params[] = (int) $fields['sort_order'];
        }
        if (array_key_exists('is_primary', $fields)) {
            $sets[]   = "`is_primary` = ?";
            $params[] = $fields['is_primary'] ? 1 : 0;
        }
        if (array_key_exists('variant_id', $fields)) {
            $sets[]   = "`variant_id` = ?";
            $params[] = $fields['variant_id'];
        }
        if (array_key_exists('attribute_value_id', $fields)) {
            $sets[]   = "`attribute_value_id` = ?";
            $params[] = $fields['attribute_value_id'];
        }

        if ($sets === []) {
            return;
        }

        $params[] = $id;
        $params[] = $this->storeId();

        $this->db->execute(
            "UPDATE `commerce_product_images`
                SET " . implode(', ', $sets) . "
              WHERE `id` = ? AND `store_id` = ?",
            $params
        );

        // If this update marked the row primary, clear the
        // flag on every other row for the same product.
        if (!empty($fields['is_primary'])) {
            $image = $this->findById($id);
            if ($image !== null) {
                $this->db->execute(
                    "UPDATE `commerce_product_images`
                        SET `is_primary` = 0
                      WHERE `store_id` = ?
                        AND `product_id` = ?
                        AND `id` != ?",
                    [$this->storeId(), $image->productId, $id]
                );
            }
        }
    }

    /**
     * Delete an image row.
     *
     * If the deleted row was the product's primary image, the
     * next-lowest-sort_order row is promoted to primary.
     */
    public function remove(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        $image = $this->findById($id);
        if ($image === null) {
            return;
        }

        $wasPrimary = $image->isPrimary;
        $productId  = $image->productId;

        $this->db->execute(
            "DELETE FROM `commerce_product_images`
              WHERE `id` = ? AND `store_id` = ?",
            [$id, $this->storeId()]
        );

        // Promote the next image when the primary one was
        // deleted and other images remain.
        if ($wasPrimary) {
            $next = $this->primaryFor($productId);
            if ($next !== null) {
                $this->setPrimary($productId, $next->id);
            }
        }
    }

    /**
     * Clear the primary flag on every other image of the
     * product and set it on the target.
     */
    public function setPrimary(int $productId, int $imageId): void
    {
        if ($productId <= 0 || $imageId <= 0) {
            return;
        }

        $storeId = $this->storeId();

        $this->db->execute(
            "UPDATE `commerce_product_images`
                SET `is_primary` = 0
              WHERE `store_id` = ? AND `product_id` = ?",
            [$storeId, $productId]
        );

        $this->db->execute(
            "UPDATE `commerce_product_images`
                SET `is_primary` = 1
              WHERE `id` = ? AND `store_id` = ? AND `product_id` = ?",
            [$imageId, $storeId, $productId]
        );
    }

    /**
     * Reorder the product's images.
     *
     * $orderedIds is the list of image IDs in the desired
     * order. IDs not in the list are left at their existing
     * sort_order. IDs in the list get sort_order 0, 1, 2, ...
     * in the given order.
     *
     * @param array<int,int> $orderedIds
     */
    public function reorder(int $productId, array $orderedIds): void
    {
        if ($productId <= 0 || $orderedIds === []) {
            return;
        }

        $storeId = $this->storeId();
        $order   = 0;

        foreach ($orderedIds as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $this->db->execute(
                "UPDATE `commerce_product_images`
                    SET `sort_order` = ?
                  WHERE `id` = ? AND `store_id` = ? AND `product_id` = ?",
                [$order, $id, $storeId, $productId]
            );
            $order++;
        }
    }

    /**
     * Delete every image for a product. Used when a product
     * is deleted and the FK cascade does not fire (soft
     * delete path).
     */
    public function removeAllForProduct(int $productId): void
    {
        if ($productId <= 0) {
            return;
        }

        $this->db->execute(
            "DELETE FROM `commerce_product_images`
              WHERE `store_id` = ? AND `product_id` = ?",
            [$this->storeId(), $productId]
        );
    }
}
