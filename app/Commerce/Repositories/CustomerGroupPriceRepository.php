<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\CustomerGroupPrice;

/**
 * Customer group price repository.
 *
 * Handles per-group overrides and tier pricing. The PricingService
 * uses this to resolve the effective price for a customer.
 */
final class CustomerGroupPriceRepository extends CommerceRepository
{
    /**
     * All price overrides for a group, with product/variant data joined.
     * @return array<int, array<string,mixed>>
     */
    public function listForGroup(int $groupId, int $limit = 200): array
    {
        return $this->db->select(
            "SELECT cgp.*,
                    p.`name` AS product_name, p.`sku` AS product_sku,
                    p.`price` AS base_price,
                    v.`sku` AS variant_sku,
                    (SELECT GROUP_CONCAT(CONCAT(ad.`name`, ': ', av.`value`) ORDER BY ad.`sort_order` SEPARATOR ', ')
                       FROM `commerce_variant_attribute_values` vav
                       JOIN `commerce_attribute_definitions` ad ON ad.`id` = vav.`attribute_id`
                       JOIN `commerce_attribute_values` av ON av.`id` = vav.`attribute_value_id`
                      WHERE vav.`variant_id` = cgp.`variant_id`
                    ) AS variant_summary
               FROM `commerce_customer_group_prices` cgp
               JOIN `commerce_products` p
                 ON p.`id` = cgp.`product_id` AND p.`store_id` = cgp.`store_id`
          LEFT JOIN `commerce_product_variants` v
                 ON v.`id` = cgp.`variant_id` AND cgp.`variant_id` > 0
              WHERE cgp.`customer_group_id` = ? AND cgp.`store_id` = ?
                AND p.`deleted_at` IS NULL
              ORDER BY p.`name` ASC, cgp.`variant_id` ASC, cgp.`min_quantity` ASC
              LIMIT {$limit}",
            [$groupId, $this->storeId()]
        );
    }

    /**
     * All overrides for a (group, product, variant) tuple, ordered by min_quantity.
     * @return array<int, CustomerGroupPrice>
     */
    public function tiersFor(int $groupId, int $productId, int $variantId = 0): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_customer_group_prices`
              WHERE `store_id` = ? AND `customer_group_id` = ?
                AND `product_id` = ? AND `variant_id` = ?
              ORDER BY `min_quantity` ASC",
            [$this->storeId(), $groupId, $productId, $variantId]
        );
        return array_map(fn ($r) => CustomerGroupPrice::fromRow($r), $rows);
    }

    /**
     * Find the matching override for a specific quantity.
     * Returns null if no tier matches.
     */
    public function resolveForQuantity(int $groupId, int $productId, int $variantId, int $qty): ?CustomerGroupPrice
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_customer_group_prices`
              WHERE `store_id` = ? AND `customer_group_id` = ?
                AND `product_id` = ? AND `variant_id` = ?
                AND `min_quantity` <= ?
                AND (`max_quantity` IS NULL OR `max_quantity` >= ?)
                AND `price` IS NOT NULL
              ORDER BY `min_quantity` DESC
              LIMIT 1",
            [$this->storeId(), $groupId, $productId, $variantId, $qty, $qty]
        );
        return $row ? CustomerGroupPrice::fromRow($row) : null;
    }

    /**
     * Add a new tier override for a group.
     */
    public function create(CustomerGroupPrice $price): int
    {
        return $this->db->insert(
            "INSERT INTO `commerce_customer_group_prices`
                (`store_id`, `customer_group_id`, `product_id`, `variant_id`,
                 `price`, `compare_at_price`, `min_quantity`, `max_quantity`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $this->storeId(),
                $price->customerGroupId,
                $price->productId,
                $price->variantId,
                $price->price,
                $price->compareAtPrice,
                $price->minQuantity,
                $price->maxQuantity,
            ]
        );
    }

    public function update(CustomerGroupPrice $price): void
    {
        $this->db->execute(
            "UPDATE `commerce_customer_group_prices`
                SET `price` = ?, `compare_at_price` = ?, `min_quantity` = ?, `max_quantity` = ?
              WHERE `id` = ? AND `store_id` = ?",
            [
                $price->price,
                $price->compareAtPrice,
                $price->minQuantity,
                $price->maxQuantity,
                $price->id,
                $this->storeId(),
            ]
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute(
            "DELETE FROM `commerce_customer_group_prices`
              WHERE `id` = ? AND `store_id` = ?",
            [$id, $this->storeId()]
        );
    }

    public function countForGroup(int $groupId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_customer_group_prices`
              WHERE `customer_group_id` = ? AND `store_id` = ?",
            [$groupId, $this->storeId()]
        );
    }
}
