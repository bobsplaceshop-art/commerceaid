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
