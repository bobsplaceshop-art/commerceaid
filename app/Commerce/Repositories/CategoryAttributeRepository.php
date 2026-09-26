<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

/**
 * Category <-> Attribute mapping repository.
 *
 * Direction: Category declares which attributes apply to its
 * products. A category has many attributes; an attribute can be
 * referenced by many categories.
 *
 * Store-scoped: each store has its own mappings.
 */
final class CategoryAttributeRepository extends CommerceRepository
{
    // =================================================================
    // Reads
    // =================================================================

    /**
     * Attribute IDs mapped to a given category.
     * @return int[]
     */
    public function attributeIdsForCategory(int $categoryId): array
    {
        $rows = $this->db->select(
            "SELECT `attribute_id` FROM `commerce_category_attributes`
              WHERE `store_id` = ? AND `category_id` = ?
              ORDER BY `sort_order` ASC, `attribute_id` ASC",
            [$this->storeId(), $categoryId]
        );
        return array_map(fn ($r) => (int) $r['attribute_id'], $rows);
    }

    /**
     * Attributes mapped to a category, with details attached.
     * @return array<int, array<string,mixed>>
     */
    public function attributesForCategory(int $categoryId): array
    {
        return $this->db->select(
            "SELECT ad.`id`, ad.`slug`, ad.`name`, ad.`input_type`,
                    ad.`is_variant_axis`, ad.`sort_order` AS attr_sort,
                    ca.`sort_order` AS map_sort
               FROM `commerce_category_attributes` ca
               JOIN `commerce_attribute_definitions` ad ON ad.`id` = ca.`attribute_id`
              WHERE ca.`store_id` = ? AND ca.`category_id` = ?
              ORDER BY ca.`sort_order` ASC, ad.`name` ASC",
            [$this->storeId(), $categoryId]
        );
    }

    /**
     * Categories that reference a given attribute. Store-scoped.
     * @return array<int, array<string,mixed>>
     */
    public function categoriesForAttribute(int $attributeId): array
    {
        return $this->db->select(
            "SELECT c.`id`, c.`slug`, c.`name`
               FROM `commerce_category_attributes` ca
               JOIN `commerce_categories` c ON c.`id` = ca.`category_id`
              WHERE ca.`store_id` = ? AND ca.`attribute_id` = ?
                AND c.`deleted_at` IS NULL
              ORDER BY c.`name` ASC",
            [$this->storeId(), $attributeId]
        );
    }

    /**
     * Union of attributes across multiple categories.
     * Deduplicated. Used by the variant generator.
     *
     * @param int[] $categoryIds
     * @return array<int, array<string,mixed>>
     */
    public function attributesForCategories(array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $params = array_merge([$this->storeId()], $categoryIds);

        return $this->db->select(
            "SELECT DISTINCT ad.`id`, ad.`slug`, ad.`name`, ad.`input_type`,
                    ad.`is_variant_axis`, ad.`sort_order` AS attr_sort,
                    MIN(ca.`sort_order`) AS map_sort
               FROM `commerce_category_attributes` ca
               JOIN `commerce_attribute_definitions` ad ON ad.`id` = ca.`attribute_id`
              WHERE ca.`store_id` = ? AND ca.`category_id` IN ({$placeholders})
              GROUP BY ad.`id`
              ORDER BY map_sort ASC, ad.`name` ASC",
            $params
        );
    }

    public function hasMapping(int $categoryId, int $attributeId): bool
    {
        $count = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_category_attributes`
              WHERE `store_id` = ? AND `category_id` = ? AND `attribute_id` = ?",
            [$this->storeId(), $categoryId, $attributeId]
        );
        return $count > 0;
    }

    // =================================================================
    // Writes
    // =================================================================

    public function attach(int $categoryId, int $attributeId): bool
    {
        if ($this->hasMapping($categoryId, $attributeId)) {
            return false;
        }

        $next = (int) $this->db->scalar(
            "SELECT COALESCE(MAX(`sort_order`), 0) + 1 FROM `commerce_category_attributes`
              WHERE `store_id` = ? AND `category_id` = ?",
            [$this->storeId(), $categoryId]
        );

        $this->db->execute(
            "INSERT INTO `commerce_category_attributes`
                (`store_id`, `category_id`, `attribute_id`, `sort_order`)
             VALUES (?, ?, ?, ?)",
            [$this->storeId(), $categoryId, $attributeId, $next]
        );

        return true;
    }

    public function detach(int $categoryId, int $attributeId): void
    {
        $this->db->execute(
            "DELETE FROM `commerce_category_attributes`
              WHERE `store_id` = ? AND `category_id` = ? AND `attribute_id` = ?",
            [$this->storeId(), $categoryId, $attributeId]
        );
    }

    /**
     * How many products in a category use any attribute value
     * belonging to the mapped attribute. Used for the warning modal
     * before detaching.
     */
    public function productUsageCount(int $categoryId, int $attributeId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(DISTINCT p.`id`)
               FROM `commerce_products` p
               JOIN `commerce_product_categories` pc
                 ON pc.`product_id` = p.`id` AND pc.`store_id` = p.`store_id`
               JOIN `commerce_product_attribute_values` pav
                 ON pav.`product_id` = p.`id` AND pav.`attribute_id` = ?
              WHERE pc.`category_id` = ?
                AND p.`store_id` = ?
                AND p.`deleted_at` IS NULL",
            [$attributeId, $categoryId, $this->storeId()]
        );
    }

    /**
     * Count of attributes mapped to a category.
     */
    public function countForCategory(int $categoryId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_category_attributes`
              WHERE `store_id` = ? AND `category_id` = ?",
            [$this->storeId(), $categoryId]
        );
    }

    /**
     * Count of categories that reference an attribute (this store only).
     */
    public function countForAttribute(int $attributeId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_category_attributes` ca
               JOIN `commerce_categories` c
                 ON c.`id` = ca.`category_id` AND c.`deleted_at` IS NULL
              WHERE ca.`store_id` = ? AND ca.`attribute_id` = ?",
            [$this->storeId(), $attributeId]
        );
    }
}
