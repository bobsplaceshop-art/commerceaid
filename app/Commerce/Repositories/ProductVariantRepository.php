<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\ProductVariant;

/**
 * Product variant repository.
 *
 * Variants belong to variable products. Each variant represents one
 * concrete combination of attribute values (e.g. Red + Medium).
 *
 * Inventory lives in `commerce_inventory` with variant_id = variant's id.
 * Ledger lives in `commerce_inventory_movements` (same pattern).
 */
final class ProductVariantRepository extends CommerceRepository
{
    /**
     * @param \CorporateAid\Core\Database $db
     * @param \CorporateAid\Commerce\Context\StoreContext $context
     * @param InventoryRepository $inventory
     */
    public function __construct(
        \CorporateAid\Core\Database $db,
        \CorporateAid\Commerce\Context\StoreContext $context,
        private InventoryRepository $inventory
    ) {
        parent::__construct($db, $context);
    }

    // =================================================================
    // Reads
    // =================================================================

    /** @return array<int, ProductVariant> */
    public function listForProduct(int $productId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_product_variants`
              WHERE `store_id` = ? AND `product_id` = ?
              ORDER BY `sort_order` ASC, `id` ASC",
            [$this->storeId(), $productId]
        );

        $variants = array_map(fn ($r) => ProductVariant::fromRow($r), $rows);

        // Attach attribute info to each variant
        foreach ($variants as $v) {
            $v->setAttributeInfo($this->attributeMapFor($v->id));
        }

        return $variants;
    }

    public function findById(int $id): ?ProductVariant
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_product_variants`
              WHERE `id` = ? AND `store_id` = ?
              LIMIT 1",
            [$id, $this->storeId()]
        );
        if ($row === null) {
            return null;
        }
        $v = ProductVariant::fromRow($row);
        $v->setAttributeInfo($this->attributeMapFor($v->id));
        return $v;
    }

    /**
     * Find a variant of a given product by SKU.
     */
    public function findBySku(int $productId, string $sku): ?ProductVariant
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_product_variants`
              WHERE `store_id` = ? AND `product_id` = ? AND `sku` = ?
              LIMIT 1",
            [$this->storeId(), $productId, $sku]
        );
        if ($row === null) {
            return null;
        }
        $v = ProductVariant::fromRow($row);
        $v->setAttributeInfo($this->attributeMapFor($v->id));
        return $v;
    }

    /**
     * Attribute map for a variant: ['color' => 'Red', 'size' => 'M'].
     * @return array<string,string>
     */
    public function attributeMapFor(int $variantId): array
    {
        $rows = $this->db->select(
            "SELECT ad.`slug` AS attr_slug, av.`value` AS attr_value
               FROM `commerce_variant_attribute_values` vav
               JOIN `commerce_attribute_definitions` ad ON ad.`id` = vav.`attribute_id`
               JOIN `commerce_attribute_values`      av ON av.`id` = vav.`attribute_value_id`
              WHERE vav.`variant_id` = ?
              ORDER BY ad.`sort_order` ASC, ad.`name` ASC",
            [$variantId]
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['attr_slug']] = (string) $row['attr_value'];
        }
        return $map;
    }

    /**
     * Variant axes for a product, with values and the variant
     * ids per value.
     *
     * Used by the storefront swatch selector and the admin
     * variant-images card. Both read the same shape.
     *
     * Excludes archived variants. Only attributes with
     * is_variant_axis = 1 are returned. Only values that are
     * actually in use by at least one active variant are
     * returned.
     *
     * @return array<int, array{
     *     attribute_id: int,
     *     slug: string,
     *     name: string,
     *     is_color_axis: bool,
     *     values: array<int, array{
     *         attribute_value_id: int,
     *         value: string,
     *         color_hex: ?string,
     *         sort_order: int,
     *         variant_ids: array<int, int>
     *     }>
     * }>
     */
    public function swatchAxesFor(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $rows = $this->db->select(
            "SELECT
                ad.`id`              AS attribute_id,
                ad.`slug`            AS attribute_slug,
                ad.`name`            AS attribute_name,
                ad.`input_type`      AS input_type,
                av.`id`              AS value_id,
                av.`value`           AS value_label,
                av.`color_hex`       AS value_color_hex,
                av.`sort_order`      AS value_sort_order,
                vav.`variant_id`     AS variant_id
               FROM `commerce_variant_attribute_values` vav
               JOIN `commerce_attribute_definitions` ad
                    ON ad.`id` = vav.`attribute_id`
               JOIN `commerce_attribute_values` av
                    ON av.`id` = vav.`attribute_value_id`
               JOIN `commerce_product_variants` pv
                    ON pv.`id` = vav.`variant_id`
              WHERE vav.`store_id` = ?
                AND pv.`product_id` = ?
                AND pv.`status` = 'active'
                AND ad.`is_variant_axis` = 1
              ORDER BY ad.`sort_order` ASC,
                       ad.`name` ASC,
                       av.`sort_order` ASC,
                       av.`id` ASC",
            [$this->storeId(), $productId]
        );

        // Group rows into axes and values.
        $axes = [];
        foreach ($rows as $row) {
            $attrId  = (int) $row['attribute_id'];
            $valueId = (int) $row['value_id'];

            if (!isset($axes[$attrId])) {
                $axes[$attrId] = [
                    'attribute_id'  => $attrId,
                    'slug'          => (string) $row['attribute_slug'],
                    'name'          => (string) $row['attribute_name'],
                    'is_color_axis' => $this->isColorAxis(
                        (string) $row['attribute_slug'],
                        (string) $row['attribute_name'],
                        (string) ($row['input_type'] ?? '')
                    ),
                    'values'        => [],
                ];
            }

            if (!isset($axes[$attrId]['values'][$valueId])) {
                $axes[$attrId]['values'][$valueId] = [
                    'attribute_value_id' => $valueId,
                    'value'              => (string) $row['value_label'],
                    'color_hex'          => $row['value_color_hex'] !== null
                        ? (string) $row['value_color_hex']
                        : null,
                    'sort_order'         => (int) $row['value_sort_order'],
                    'variant_ids'        => [],
                ];
            }

            $axes[$attrId]['values'][$valueId]['variant_ids'][] = (int) $row['variant_id'];
        }

        // Re-index values so the result is a list (safe for
        // JSON encoding and simple iteration).
        $out = [];
        foreach ($axes as $axis) {
            $axis['values'] = array_values($axis['values']);
            $out[] = $axis;
        }
        return $out;
    }

    /**
     * Combination map for the swatch selector JS.
     *
     * Keys are sorted attribute_value_id lists joined by ":".
     *   Single-axis:  "12"      => variant_id
     *   Two-axis:     "12:34"   => variant_id
     *   Three-axis:   "12:34:56"=> variant_id
     *
     * Sorted ascending so the JS can build the key from the
     * selected values in any order.
     *
     * Excludes archived variants.
     *
     * @return array<string, int>
     */
    public function combinationsFor(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $rows = $this->db->select(
            "SELECT vav.`variant_id`, vav.`attribute_value_id`
               FROM `commerce_variant_attribute_values` vav
               JOIN `commerce_product_variants` pv
                    ON pv.`id` = vav.`variant_id`
              WHERE vav.`store_id` = ?
                AND pv.`product_id` = ?
                AND pv.`status` = 'active'",
            [$this->storeId(), $productId]
        );

        // Group values per variant_id.
        $byVariant = [];
        foreach ($rows as $row) {
            $vid = (int) $row['variant_id'];
            $byVariant[$vid][] = (int) $row['attribute_value_id'];
        }

        // Build the combination map.
        $out = [];
        foreach ($byVariant as $vid => $valueIds) {
            sort($valueIds, SORT_NUMERIC);
            $key = implode(':', $valueIds);
            $out[$key] = $vid;
        }
        return $out;
    }

    /**
     * Best-effort color-axis detection.
     *
     * The strongest signal is the attribute's input_type.
     * Falls back to a slug or name match. Kept deliberately
     * permissive: a false positive renders a swatch that uses
     * the value's color_hex as a fill; a false negative hides
     * the swatch row. A miss on the color axis is worse than
     * a miss on a generic axis.
     */
    private function isColorAxis(string $slug, string $name, string $inputType): bool
    {
        if ($inputType === 'color') {
            return true;
        }
        $slugLower = strtolower($slug);
        $nameLower = strtolower($name);
        return str_contains($slugLower, 'color')
            || str_contains($slugLower, 'colour')
            || str_contains($nameLower, 'color')
            || str_contains($nameLower, 'colour');
    }

    /**
     * Return existing variant combinations as a hashmap, so we can
     * skip regenerating ones that already exist.
     *
     * @return array<string, int>  key = "attrValueId1:attrValueId2" → variant id
     */
    public function existingCombinations(int $productId): array
    {
        $rows = $this->db->select(
            "SELECT vav.`variant_id`, vav.`attribute_value_id`
               FROM `commerce_variant_attribute_values` vav
               JOIN `commerce_product_variants` v ON v.`id` = vav.`variant_id`
              WHERE v.`product_id` = ? AND v.`store_id` = ?",
            [$productId, $this->storeId()]
        );

        // Group value ids by variant id
        $byVariant = [];
        foreach ($rows as $row) {
            $byVariant[(int) $row['variant_id']][] = (int) $row['attribute_value_id'];
        }

        // Flip: combination key → variant id
        $out = [];
        foreach ($byVariant as $variantId => $valueIds) {
            sort($valueIds);
            $out[implode(':', $valueIds)] = $variantId;
        }
        return $out;
    }

    // =================================================================
    // Writes
    // =================================================================

    /**
     * Create a variant with its attribute value links.
     *
     * @param array<int, int> $attributeValueIds Ordered list of attribute_value ids
     */
    public function create(
        int $productId,
        array $attributeValueIds,
        string $sku = '',
        float $price = 0.0,
        int $sortOrder = 0
    ): int {
        $variantId = $this->db->insert(
            "INSERT INTO `commerce_product_variants`
                (`store_id`, `product_id`, `sku`, `price`, `stock_status`,
                 `manage_stock`, `backorders_allowed`, `status`, `sort_order`)
             VALUES (?, ?, ?, ?, 'in_stock', 1, 0, 'active', ?)",
            [
                $this->storeId(),
                $productId,
                $sku !== '' ? $sku : null,
                $price,
                $sortOrder,
            ]
        );

        $this->linkAttributeValues($variantId, $attributeValueIds);

        // Every sellable unit has exactly one inventory row. The
        // variant is the sellable unit for variable products, so
        // its inventory row is created here, at the moment the
        // variant is born. Quantity starts at 0; the merchant
        // assigns real stock through the variations editor.
        $this->inventory->ensureRow($productId, $variantId);

        return $variantId;
    }

    /**
     * Update the fields of a variant (SKU, price, compare-at, barcode,
     * weight, etc.). Does not touch attribute links or inventory.
     */
    public function update(ProductVariant $variant): void
    {
        $this->db->execute(
            "UPDATE `commerce_product_variants`
                SET `sku` = ?, `barcode` = ?, `price` = ?, `compare_at_price` = ?,
                    `cost_price` = ?, `weight` = ?,
                    `length` = ?, `width` = ?, `height` = ?,
                    `backorders_allowed` = ?, `low_stock_threshold` = ?,
                    `is_default` = ?, `status` = ?, `sort_order` = ?
              WHERE `id` = ? AND `store_id` = ?",
            [
                $variant->sku,
                $variant->barcode,
                $variant->price,
                $variant->compareAtPrice,
                $variant->costPrice,
                $variant->weight,
                $variant->length,
                $variant->width,
                $variant->height,
                $variant->backordersAllowed ? 1 : 0,
                $variant->lowStockThreshold,
                $variant->isDefault ? 1 : 0,
                $variant->status,
                $variant->sortOrder,
                $variant->id,
                $this->storeId(),
            ]
        );
    }

    /**
     * Replace a variant's attribute value links.
     * @param array<int, int> $attributeValueIds
     */
    private function linkAttributeValues(int $variantId, array $attributeValueIds): void
    {
        $this->db->execute(
            "DELETE FROM `commerce_variant_attribute_values` WHERE `variant_id` = ?",
            [$variantId]
        );

        foreach ($attributeValueIds as $valueId) {
            // Look up the attribute_id from the value
            $attributeId = (int) $this->db->scalar(
                "SELECT `attribute_id` FROM `commerce_attribute_values` WHERE `id` = ?",
                [$valueId]
            );
            if ($attributeId <= 0) continue;

            $this->db->insert(
                "INSERT INTO `commerce_variant_attribute_values`
                    (`store_id`, `variant_id`, `attribute_id`, `attribute_value_id`)
                 VALUES (?, ?, ?, ?)",
                [$this->storeId(), $variantId, $attributeId, $valueId]
            );
        }
    }

    /**
     * Archive (soft-hide) a variant. Inventory row stays but the
     * variant won't appear on the storefront.
     */
    public function archive(int $id): void
    {
        $this->db->execute(
            "UPDATE `commerce_product_variants` SET `status` = 'archived'
              WHERE `id` = ? AND `store_id` = ?",
            [$id, $this->storeId()]
        );
    }

    public function restore(int $id): void
    {
        $this->db->execute(
            "UPDATE `commerce_product_variants` SET `status` = 'active'
              WHERE `id` = ? AND `store_id` = ?",
            [$id, $this->storeId()]
        );
    }

    /**
     * Hard-delete a variant. Removes its attribute links and inventory
     * rows. Movements are kept for the audit trail.
     */
    public function delete(int $id): void
    {
        // Movements reference the variant but are kept for the ledger.
        // We only drop the live data.
        $this->db->execute(
            "DELETE FROM `commerce_variant_attribute_values` WHERE `variant_id` = ?",
            [$id]
        );
        $this->db->execute(
            "DELETE FROM `commerce_inventory`
              WHERE `store_id` = ? AND `variant_id` = ?",
            [$this->storeId(), $id]
        );
        $this->db->execute(
            "DELETE FROM `commerce_product_variants`
              WHERE `id` = ? AND `store_id` = ?",
            [$id, $this->storeId()]
        );
    }

    // =================================================================
    // Counts
    // =================================================================

    public function countForProduct(int $productId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_product_variants`
              WHERE `store_id` = ? AND `product_id` = ? AND `status` = 'active'",
            [$this->storeId(), $productId]
        );
    }
}
