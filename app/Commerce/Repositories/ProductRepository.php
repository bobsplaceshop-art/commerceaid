<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\Product;
use CorporateAid\Commerce\Models\ProductVariant;

/**
 * Product repository.
 *
 * Every query is scoped to the current store. Products reference
 * brands, categories, tags, attributes, images, and variants - this
 * repository owns the product row plus the write paths for those
 * relationships. Reads for related entities live in their own
 * repositories (Brand, Category, etc.).
 *
 * Two pagination methods exist:
 *   - paginate()          admin: any status, admin filters, admin sort
 *   - paginatePublished() storefront: published only, storefront filters
 *
 * The two methods share the same table and the same model but have
 * different filter sets and different safety rules. Keeping them
 * separate makes each SQL statement easier to read and lets indexes
 * be optimized for each use case independently.
 */
final class ProductRepository extends CommerceRepository
{
    /** @var array<string,string> Sort key -> SQL ORDER BY fragment */
    private const PUBLISHED_SORT_MAP = [
        'newest'     => "p.`published_at` DESC, p.`id` DESC",
        'price_asc'  => "p.`price` ASC, p.`id` ASC",
        'price_desc' => "p.`price` DESC, p.`id` ASC",
        'name_asc'   => "p.`name` ASC, p.`id` ASC",
        'featured'   => "p.`is_featured` DESC, p.`published_at` DESC, p.`id` DESC",
    ];

    // =================================================================
    // Reads - admin
    // =================================================================

    /**
     * Paginated list with optional filters.
     *
     * @param array{
     *   q?: string,
     *   status?: string,
     *   brand_id?: int,
     *   category_id?: int,
     *   featured?: bool,
     *   type?: string,
     *   include_deleted?: bool
     * } $filters
     * @return array{items: array<int, Product>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->buildFilters($filters);

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_products` p WHERE {$where}",
            $params
        );

        $rows = $this->db->select(
            "SELECT p.* FROM `commerce_products` p
              WHERE {$where}
              ORDER BY p.`created_at` DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $items = array_map(fn ($r) => Product::fromRow($r), $rows);

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int) ceil($total / $perPage),
        ];
    }

    // =================================================================
    // Reads - storefront
    // =================================================================

    /**
     * Storefront product listing.
     *
     * Returns only products that are visible to anonymous visitors:
     *   - status = 'published'
     *   - published_at IS NULL OR published_at <= NOW()
     *   - deleted_at IS NULL
     *
     * Supported filters (all optional):
     *   q             - search across name, short_description,
     *                   description, sku
     *   category_id   - products assigned to this category
     *   brand_id      - direct column match
     *   in_stock_only - products with available > 0 in inventory
     *   price_min     - inclusive lower bound on price
     *   price_max     - inclusive upper bound on price
     *   sort          - 'newest' | 'price_asc' | 'price_desc' |
     *                   'name_asc' | 'featured'
     *
     * @param array{
     *   q?: string,
     *   category_id?: int,
     *   brand_id?: int,
     *   in_stock_only?: bool,
     *   price_min?: float,
     *   price_max?: float,
     *   sort?: string
     * } $filters
     * @return array{items: array<int, Product>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginatePublished(array $filters = [], int $page = 1, int $perPage = 24): array
    {
        $page = max(1, $page);
        $perPage = min(48, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->buildPublishedFilters($filters);
        $orderBy = $this->publishedOrderBy($filters);

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_products` p WHERE {$where}",
            $params
        );

        $rows = $this->db->select(
            "SELECT p.* FROM `commerce_products` p
              WHERE {$where}
              ORDER BY {$orderBy}
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $items = array_map(fn ($r) => Product::fromRow($r), $rows);

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => max(1, (int) ceil($total / $perPage)),
        ];
    }

    // =================================================================
    // Reads - individual records
    // =================================================================

    public function findById(int $id, bool $withVariants = false): ?Product
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_products`
              WHERE `id` = ? AND `store_id` = ? AND `deleted_at` IS NULL
              LIMIT 1",
            [$id, $this->storeId()]
        );
        if ($row === null) {
            return null;
        }
        $product = Product::fromRow($row);

        if ($withVariants && $product->isVariable()) {
            // Variants loaded separately by ProductVariantRepository.
            // This method exists for the common case (edit page needs
            // product only); variants are fetched on demand.
        }

        return $product;
    }

    public function findBySlug(string $slug): ?Product
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_products`
              WHERE `slug` = ? AND `store_id` = ? AND `deleted_at` IS NULL
              LIMIT 1",
            [$slug, $this->storeId()]
        );
        return $row ? Product::fromRow($row) : null;
    }

    public function findBySku(string $sku): ?Product
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_products`
              WHERE `sku` = ? AND `store_id` = ? AND `deleted_at` IS NULL
              LIMIT 1",
            [$sku, $this->storeId()]
        );
        return $row ? Product::fromRow($row) : null;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM `commerce_products`
                 WHERE `store_id` = ? AND `slug` = ? AND `deleted_at` IS NULL";
        $params = [$this->storeId(), $slug];

        if ($excludeId !== null) {
            $sql .= " AND `id` <> ?";
            $params[] = $excludeId;
        }

        return (int) $this->db->scalar($sql, $params) > 0;
    }

    public function skuExists(string $sku, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM `commerce_products`
                 WHERE `store_id` = ? AND `sku` = ? AND `deleted_at` IS NULL";
        $params = [$this->storeId(), $sku];

        if ($excludeId !== null) {
            $sql .= " AND `id` <> ?";
            $params[] = $excludeId;
        }

        return (int) $this->db->scalar($sql, $params) > 0;
    }

    // =================================================================
    // Writes
    // =================================================================

    public function create(Product $product): int
    {
        return $this->db->insert(
            "INSERT INTO `commerce_products`
                (`store_id`, `slug`, `sku`, `name`, `short_description`, `description`,
                 `brand_id`, `primary_category_id`, `product_type`,
                 `price`, `compare_at_price`, `cost_price`,
                 `tax_class_id`, `tax_status`,
                 `is_featured`, `is_new`,
                 `weight`, `length`, `width`, `height`, `requires_shipping`,
                 `stock_status`, `manage_stock`, `backorders_allowed`, `low_stock_threshold`,
                 `seo_title`, `seo_description`, `seo_keywords`, `og_image_file_id`,
                 `status`, `published_at`, `created_by`, `updated_by`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                     ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $this->storeId(),
                $product->slug,
                $product->sku,
                $product->name,
                $product->shortDescription,
                $product->description,
                $product->brandId,
                $product->primaryCategoryId,
                $product->productType,
                $product->price,
                $product->compareAtPrice,
                $product->costPrice,
                $product->taxClassId,
                $product->taxStatus,
                $product->isFeatured ? 1 : 0,
                $product->isNew ? 1 : 0,
                $product->weight,
                $product->length,
                $product->width,
                $product->height,
                $product->requiresShipping ? 1 : 0,
                $product->stockStatus,
                $product->manageStock ? 1 : 0,
                $product->backordersAllowed ? 1 : 0,
                $product->lowStockThreshold,
                $product->seoTitle,
                $product->seoDescription,
                $product->seoKeywords,
                $product->ogImageFileId,
                $product->status,
                $product->publishedAt,
                $product->createdBy,
                $product->updatedBy,
            ]
        );
    }

    public function update(Product $product): void
    {
        $this->db->execute(
            "UPDATE `commerce_products`
                SET `slug` = ?, `sku` = ?, `name` = ?, `short_description` = ?, `description` = ?,
                    `brand_id` = ?, `primary_category_id` = ?, `product_type` = ?,
                    `price` = ?, `compare_at_price` = ?, `cost_price` = ?,
                    `tax_class_id` = ?, `tax_status` = ?,
                    `is_featured` = ?, `is_new` = ?,
                    `weight` = ?, `length` = ?, `width` = ?, `height` = ?, `requires_shipping` = ?,
                    `stock_status` = ?, `manage_stock` = ?, `backorders_allowed` = ?, `low_stock_threshold` = ?,
                    `seo_title` = ?, `seo_description` = ?, `seo_keywords` = ?, `og_image_file_id` = ?,
                    `status` = ?, `published_at` = ?, `updated_by` = ?
              WHERE `id` = ? AND `store_id` = ?",
            [
                $product->slug,
                $product->sku,
                $product->name,
                $product->shortDescription,
                $product->description,
                $product->brandId,
                $product->primaryCategoryId,
                $product->productType,
                $product->price,
                $product->compareAtPrice,
                $product->costPrice,
                $product->taxClassId,
                $product->taxStatus,
                $product->isFeatured ? 1 : 0,
                $product->isNew ? 1 : 0,
                $product->weight,
                $product->length,
                $product->width,
                $product->height,
                $product->requiresShipping ? 1 : 0,
                $product->stockStatus,
                $product->manageStock ? 1 : 0,
                $product->backordersAllowed ? 1 : 0,
                $product->lowStockThreshold,
                $product->seoTitle,
                $product->seoDescription,
                $product->seoKeywords,
                $product->ogImageFileId,
                $product->status,
                $product->publishedAt,
                $product->updatedBy,
                $product->id,
                $this->storeId(),
            ]
        );
    }

    public function publish(int $id, ?int $userId = null): void
    {
        $this->db->execute(
            "UPDATE `commerce_products`
                SET `status` = 'published',
                    `published_at` = COALESCE(`published_at`, NOW()),
                    `updated_by` = ?
              WHERE `id` = ? AND `store_id` = ?",
            [$userId, $id, $this->storeId()]
        );
    }

    public function unpublish(int $id, ?int $userId = null): void
    {
        $this->db->execute(
            "UPDATE `commerce_products`
                SET `status` = 'draft', `updated_by` = ?
              WHERE `id` = ? AND `store_id` = ?",
            [$userId, $id, $this->storeId()]
        );
    }

    public function archive(int $id): void
    {
        $this->db->execute(
            "UPDATE `commerce_products` SET `status` = 'archived'
              WHERE `id` = ? AND `store_id` = ?",
            [$id, $this->storeId()]
        );
    }

    public function restore(int $id): void
    {
        $this->db->execute(
            "UPDATE `commerce_products` SET `status` = 'draft'
              WHERE `id` = ? AND `store_id` = ?",
            [$id, $this->storeId()]
        );
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `commerce_products` SET `deleted_at` = NOW()
              WHERE `id` = ? AND `store_id` = ?",
            [$id, $this->storeId()]
        );
    }

    // =================================================================
    // Product <-> Category junction
    // =================================================================

    /** @return int[] */
    public function categoryIdsFor(int $productId): array
    {
        $rows = $this->db->select(
            "SELECT `category_id` FROM `commerce_product_categories`
              WHERE `product_id` = ? AND `store_id` = ?",
            [$productId, $this->storeId()]
        );
        return array_map(fn ($r) => (int) $r['category_id'], $rows);
    }

    /**
     * Replace the set of categories for a product.
     * @param int[] $categoryIds
     */
    public function syncCategories(int $productId, array $categoryIds): void
    {
        $this->db->execute(
            "DELETE FROM `commerce_product_categories`
              WHERE `product_id` = ? AND `store_id` = ?",
            [$productId, $this->storeId()]
        );

        $seen = [];
        foreach ($categoryIds as $cid) {
            $cid = (int) $cid;
            if ($cid <= 0 || isset($seen[$cid])) {
                continue;
            }
            $seen[$cid] = true;
            $this->db->insert(
                "INSERT INTO `commerce_product_categories`
                    (`store_id`, `product_id`, `category_id`)
                 VALUES (?, ?, ?)",
                [$this->storeId(), $productId, $cid]
            );
        }
    }

    // =================================================================
    // Product <-> Tag junction
    // =================================================================

    /** @return int[] */
    public function tagIdsFor(int $productId): array
    {
        $rows = $this->db->select(
            "SELECT `tag_id` FROM `commerce_product_tag_links`
              WHERE `product_id` = ? AND `store_id` = ?",
            [$productId, $this->storeId()]
        );
        return array_map(fn ($r) => (int) $r['tag_id'], $rows);
    }

    /**
     * Replace the set of tags for a product.
     * @param int[] $tagIds
     */
    public function syncTags(int $productId, array $tagIds): void
    {
        $this->db->execute(
            "DELETE FROM `commerce_product_tag_links`
              WHERE `product_id` = ? AND `store_id` = ?",
            [$productId, $this->storeId()]
        );

        $seen = [];
        foreach ($tagIds as $tid) {
            $tid = (int) $tid;
            if ($tid <= 0 || isset($seen[$tid])) {
                continue;
            }
            $seen[$tid] = true;
            $this->db->insert(
                "INSERT INTO `commerce_product_tag_links`
                    (`store_id`, `product_id`, `tag_id`)
                 VALUES (?, ?, ?)",
                [$this->storeId(), $productId, $tid]
            );
        }
    }

    // =================================================================
    // Counts (for dashboards)
    // =================================================================

    public function count(): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_products`
              WHERE `store_id` = ? AND `deleted_at` IS NULL",
            [$this->storeId()]
        );
    }

    public function countPublished(): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_products`
              WHERE `store_id` = ? AND `status` = 'published' AND `deleted_at` IS NULL",
            [$this->storeId()]
        );
    }

    public function countDraft(): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_products`
              WHERE `store_id` = ? AND `status` = 'draft' AND `deleted_at` IS NULL",
            [$this->storeId()]
        );
    }

    public function countLowStock(): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_inventory` i
              JOIN `commerce_products` p
                ON p.`id` = i.`product_id` AND p.`store_id` = i.`store_id`
              WHERE i.`store_id` = ?
                AND p.`deleted_at` IS NULL
                AND p.`status` = 'published'
                AND i.`available` > 0
                AND i.`available` <= COALESCE(i.`low_stock_threshold`, p.`low_stock_threshold`, 5)",
            [$this->storeId()]
        );
    }

    // =================================================================
    // Helpers
    // =================================================================

    /**
     * Build the WHERE clause + params for admin paginate().
     * @return array{0:string, 1:array}
     */
    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildFilters(array $filters): array
    {
        $where = ["p.`store_id` = ?"];
        $params = [$this->storeId()];

        if (empty($filters['include_deleted'])) {
            $where[] = "p.`deleted_at` IS NULL";
        }

        if (!empty($filters['status'])) {
            $where[] = "p.`status` = ?";
            $params[] = (string) $filters['status'];
        }

        if (!empty($filters['brand_id'])) {
            $where[] = "p.`brand_id` = ?";
            $params[] = (int) $filters['brand_id'];
        }

        if (!empty($filters['category_id'])) {
            $where[] = "EXISTS (
                SELECT 1 FROM `commerce_product_categories` pc
                 WHERE pc.`product_id` = p.`id`
                   AND pc.`store_id` = p.`store_id`
                   AND pc.`category_id` = ?
            )";
            $params[] = (int) $filters['category_id'];
        }

        if (isset($filters['featured']) && $filters['featured']) {
            $where[] = "p.`is_featured` = 1";
        }

        if (!empty($filters['type'])) {
            $where[] = "p.`product_type` = ?";
            $params[] = (string) $filters['type'];
        }

        if (!empty($filters['q'])) {
            $where[] = "(p.`name` LIKE ? OR p.`sku` LIKE ?)";
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Build the WHERE clause + params for storefront paginatePublished().
     *
     * Always includes the "published and visible to public" guard.
     * Everything else is opt-in.
     *
     * @return array{0:string, 1:array}
     */
    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildPublishedFilters(array $filters): array
    {
        $where = [
            "p.`store_id` = ?",
            "p.`deleted_at` IS NULL",
            "p.`status` = 'published'",
            "(p.`published_at` IS NULL OR p.`published_at` <= NOW())",
        ];
        $params = [$this->storeId()];

        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $where[] = "(p.`name` LIKE ? OR p.`short_description` LIKE ? OR p.`description` LIKE ? OR p.`sku` LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['category_id'])) {
            $where[] = "EXISTS (
                SELECT 1 FROM `commerce_product_categories` pc
                 WHERE pc.`product_id` = p.`id`
                   AND pc.`store_id` = p.`store_id`
                   AND pc.`category_id` = ?
            )";
            $params[] = (int) $filters['category_id'];
        }

        if (!empty($filters['brand_id'])) {
            $where[] = "p.`brand_id` = ?";
            $params[] = (int) $filters['brand_id'];
        }

        if (!empty($filters['price_min']) && is_numeric($filters['price_min'])) {
            $where[] = "p.`price` >= ?";
            $params[] = (float) $filters['price_min'];
        }

        if (!empty($filters['price_max']) && is_numeric($filters['price_max'])) {
            $where[] = "p.`price` <= ?";
            $params[] = (float) $filters['price_max'];
        }

        // Hide products with no available stock (Stage 008G).
        // Products that do not track stock (manage_stock = 0)
        // are always visible - digital goods have no inventory
        // row. Products that do track stock are visible only
        // when at least one sellable unit has available > 0.
        //
        // The subquery reads commerce_inventory, which holds
        // one row per sellable unit: simple products have
        // variant_id = 0, variable products have one row per
        // variant. The subquery is complete for both.
        if (!empty($filters['hide_out_of_stock'])) {
            $where[] = "(
                p.`manage_stock` = 0
                OR EXISTS (
                    SELECT 1 FROM `commerce_inventory` i
                     WHERE i.`product_id` = p.`id`
                       AND i.`store_id` = p.`store_id`
                       AND i.`available` > 0
                )
            )";
        }

        if (!empty($filters['in_stock_only'])) {
            $where[] = "EXISTS (
                SELECT 1 FROM `commerce_inventory` i
                 WHERE i.`product_id` = p.`id`
                   AND i.`store_id` = p.`store_id`
                   AND i.`variant_id` = 0
                   AND i.`available` > 0
            )";
        }

        if (!empty($filters['featured'])) {
            $where[] = "p.`is_featured` = 1";
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Resolve the ORDER BY fragment for a storefront listing.
     * Unknown or missing sort keys default to 'newest'.
     */
    /** @param array<string,mixed> $filters */
    private function publishedOrderBy(array $filters): string
    {
        $key = (string) ($filters['sort'] ?? 'newest');
        return self::PUBLISHED_SORT_MAP[$key] ?? self::PUBLISHED_SORT_MAP['newest'];
    }
}
