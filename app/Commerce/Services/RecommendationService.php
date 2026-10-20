<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Core\Database;

final class RecommendationService
{
    public function __construct(
        private ProductRepository $products,
        private Database $db,
        private \CorporateAid\Commerce\Context\StoreContext $context,
    ) {
    }

    /**
     * @param array<int, \CorporateAid\Commerce\Models\CartLineItem> $cartLines
     * @return array<int, \CorporateAid\Commerce\Models\Product>
     */
    public function forCart(array $cartLines, int $limit = 4): array
    {
        if (empty($cartLines)) {
            return [];
        }

        $productIds = array_map(fn($line) => $line->item->productId, $cartLines);
        $productIds = array_filter($productIds);
        $excludeIds = implode(',', $productIds);

        // Find categories for the products in the cart
        $categoryIds = [];
        foreach ($productIds as $id) {
            $cats = $this->products->categoryIdsFor($id);
            $categoryIds = array_merge($categoryIds, $cats);
        }
        $categoryIds = array_unique($categoryIds);

        if (!empty($categoryIds)) {
            $inCats = implode(',', array_fill(0, count($categoryIds), '?'));
            $sql = "SELECT DISTINCT p.* 
                    FROM `commerce_products` p
                    JOIN `commerce_product_categories` pc ON pc.product_id = p.id
                    WHERE p.store_id = ? 
                      AND p.id NOT IN ({$excludeIds}) 
                      AND p.status = 'published' 
                      AND p.deleted_at IS NULL
                      AND pc.category_id IN ({$inCats})
                    ORDER BY RAND()
                    LIMIT ?";
            
            $params = array_merge([$this->context->id()], $categoryIds, [$limit]);
            $rows = $this->db->select($sql, $params);
            
            if (!empty($rows)) {
                return array_map([\CorporateAid\Commerce\Models\Product::class, 'fromRow'], $rows);
            }
        }

        // Fallback: newest products not in the cart
        $rows = $this->db->select(
            "SELECT * FROM `commerce_products` 
             WHERE `store_id` = ? 
               AND `id` NOT IN ({$excludeIds}) 
               AND `status` = 'published' 
               AND `deleted_at` IS NULL 
             ORDER BY `created_at` DESC 
             LIMIT ?",
            [$this->context->id(), $limit]
        );
        return array_map([\CorporateAid\Commerce\Models\Product::class, 'fromRow'], $rows);
    }
}
