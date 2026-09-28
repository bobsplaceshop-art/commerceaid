<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\Inventory;
use CorporateAid\Commerce\Models\InventoryMovement;

/**
 * Inventory repository.
 *
 * Owns both the current stock table (`commerce_inventory`) and the
 * append-only movement ledger (`commerce_inventory_movements`).
 *
 * Every mutation writes a movement AND updates the current stock
 * row inside a single transaction. If either fails, both roll back -
 * the ledger never drifts from the stock.
 *
 * Movements are never updated or deleted. Corrections use
 * compensating movements.
 */
final class InventoryRepository extends CommerceRepository
{
    // =================================================================
    // Reads
    // =================================================================

    /**
     * Current stock for a single sellable unit.
     * variantId = 0 for simple products.
     */
    public function forUnit(int $productId, int $variantId = 0): ?Inventory
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_inventory`
              WHERE `store_id` = ? AND `product_id` = ? AND `variant_id` = ?
              LIMIT 1",
            [$this->storeId(), $productId, $variantId]
        );
        return $row ? Inventory::fromRow($row) : null;
    }

    /**
     * All stock rows for a product (variants + optional simple row).
     * @return array<int, Inventory>
     */
    public function listForProduct(int $productId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_inventory`
              WHERE `store_id` = ? AND `product_id` = ?
              ORDER BY `variant_id` ASC",
            [$this->storeId(), $productId]
        );
        return array_map(fn ($r) => Inventory::fromRow($r), $rows);
    }

    /**
     * Products with available stock at or below threshold.
     * Used by the admin dashboard.
     * @return array<int, array<string,mixed>>
     */
    public function listLowStock(int $limit = 20): array
    {
        return $this->db->select(
            "SELECT i.*, p.`name` AS product_name, p.`sku` AS product_sku,
                    p.`low_stock_threshold` AS product_threshold,
                    v.`sku` AS variant_sku
               FROM `commerce_inventory` i
               JOIN `commerce_products` p
                 ON p.`id` = i.`product_id` AND p.`store_id` = i.`store_id`
          LEFT JOIN `commerce_product_variants` v
                 ON v.`id` = i.`variant_id` AND i.`variant_id` > 0
              WHERE i.`store_id` = ?
                AND p.`deleted_at` IS NULL
                AND p.`status` = 'published'
                AND i.`available` > 0
                AND i.`available` <= COALESCE(i.`low_stock_threshold`, p.`low_stock_threshold`, 5)
              ORDER BY i.`available` ASC
              LIMIT {$limit}",
            [$this->storeId()]
        );
    }

    /**
     * Products that are completely out of stock.
     * @return array<int, array<string,mixed>>
     */
    public function listOutOfStock(int $limit = 20): array
    {
        return $this->db->select(
            "SELECT i.*, p.`name` AS product_name, p.`sku` AS product_sku,
                    v.`sku` AS variant_sku
               FROM `commerce_inventory` i
               JOIN `commerce_products` p
                 ON p.`id` = i.`product_id` AND p.`store_id` = i.`store_id`
          LEFT JOIN `commerce_product_variants` v
                 ON v.`id` = i.`variant_id` AND i.`variant_id` > 0
              WHERE i.`store_id` = ?
                AND p.`deleted_at` IS NULL
                AND p.`status` = 'published'
                AND i.`available` <= 0
              ORDER BY i.`updated_at` DESC
              LIMIT {$limit}",
            [$this->storeId()]
        );
    }

    /**
     * Movement history for a product or specific variant.
     * @return array<int, InventoryMovement>
     */
    public function history(int $productId, ?int $variantId = null, int $limit = 50): array
    {
        $sql = "SELECT * FROM `commerce_inventory_movements`
                 WHERE `store_id` = ? AND `product_id` = ?";
        $params = [$this->storeId(), $productId];

        if ($variantId !== null) {
            $sql .= " AND `variant_id` = ?";
            $params[] = $variantId;
        }

        $sql .= " ORDER BY `created_at` DESC, `id` DESC LIMIT {$limit}";

        $rows = $this->db->select($sql, $params);
        return array_map(fn ($r) => InventoryMovement::fromRow($r), $rows);
    }

    // =================================================================
    // Writes
    // =================================================================

    /**
     * Record a stock movement and update the current stock row.
     * Atomic — either both commit or neither does.
     *
     * @param int    $productId
     * @param int    $variantId      0 for simple products
     * @param int    $delta          signed: positive adds, negative removes
     * @param string $movementType   one of: opening, adjustment, sale, return,
     *                               refund, cancel, transfer_in, transfer_out,
     *                               damage, stocktake
     * @param array  $options        optional: reference_type, reference_id, reason, created_by
     * @throws \InvalidArgumentException on unknown movement type
     */
    /** @param array<string,mixed> $options */
    public function adjust(
        int $productId,
        int $variantId,
        int $delta,
        string $movementType,
        array $options = []
    ): InventoryMovement {
        $validTypes = [
            'opening', 'adjustment', 'sale', 'return', 'refund',
            'cancel', 'transfer_in', 'transfer_out', 'damage', 'stocktake',
        ];
        if (!in_array($movementType, $validTypes, true)) {
            throw new \InvalidArgumentException("Unknown movement type: {$movementType}");
        }

        $this->db->beginTransaction();
        try {
            // 1) Ensure a current stock row exists.
            $this->ensureRow($productId, $variantId);

            // 2) Read the current quantity WITH A LOCK so concurrent
            //    adjustments don't race. This is the critical section.
            $locked = $this->db->selectOne(
                "SELECT `quantity` FROM `commerce_inventory`
                  WHERE `store_id` = ? AND `product_id` = ? AND `variant_id` = ?
                  FOR UPDATE",
                [$this->storeId(), $productId, $variantId]
            );
            $currentQty = (int) ($locked['quantity'] ?? 0);
            $newQty = $currentQty + $delta;

            // 3) Update the current stock row.
            $this->db->execute(
                "UPDATE `commerce_inventory`
                    SET `quantity` = ?
                  WHERE `store_id` = ? AND `product_id` = ? AND `variant_id` = ?",
                [$newQty, $this->storeId(), $productId, $variantId]
            );

            // 4) Append the movement record.
            $movementId = $this->db->insert(
                "INSERT INTO `commerce_inventory_movements`
                    (`store_id`, `product_id`, `variant_id`, `movement_type`,
                     `quantity_delta`, `quantity_after`,
                     `reference_type`, `reference_id`, `reason`, `created_by`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $this->storeId(),
                    $productId,
                    $variantId,
                    $movementType,
                    $delta,
                    $newQty,
                    $options['reference_type'] ?? null,
                    $options['reference_id']   ?? null,
                    $options['reason']         ?? null,
                    $options['created_by']     ?? null,
                ]
            );

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_inventory_movements` WHERE `id` = ?",
            [$movementId]
        );
        if ($row === null) {
            // The insert and commit both succeeded above, so the row
            // must exist. If it doesn't, something is very wrong -
            // a concurrent delete, a partial rollback, or schema
            // corruption. Fail loudly rather than return a broken
            // movement record.
            throw new \RuntimeException(
                'Inventory movement ' . $movementId . ' disappeared after commit.'
            );
        }
        return InventoryMovement::fromRow($row);
    }

    /**
     * Ensure an inventory row exists for the given sellable unit.
     * Creates one with quantity = 0 and reserved = 0 if missing.
     * Idempotent - a second call for the same unit is a no-op.
     *
     * Called by adjust() before any UPDATE, and by
     * ProductVariantRepository::create() when a new variant is
     * born. Every sellable unit gets exactly one row.
     */
    public function ensureRow(int $productId, int $variantId): void
    {
        $existing = $this->forUnit($productId, $variantId);
        if ($existing !== null) {
            return;
        }
        $this->db->execute(
            "INSERT INTO `commerce_inventory`
                (`store_id`, `product_id`, `variant_id`, `quantity`, `reserved`)
             VALUES (?, ?, ?, 0, 0)",
            [$this->storeId(), $productId, $variantId]
        );
    }

    /**
     * Reserve stock for a cart line. Reserving does not move stock,
     * it just holds it so the same unit can't be sold twice.
     */
    public function reserve(int $productId, int $variantId, int $qty): void
    {
        $this->db->execute(
            "UPDATE `commerce_inventory`
                SET `reserved` = `reserved` + ?
              WHERE `store_id` = ? AND `product_id` = ? AND `variant_id` = ?",
            [$qty, $this->storeId(), $productId, $variantId]
        );
    }

    public function release(int $productId, int $variantId, int $qty): void
    {
        $this->db->execute(
            "UPDATE `commerce_inventory`
                SET `reserved` = GREATEST(0, `reserved` - ?)
              WHERE `store_id` = ? AND `product_id` = ? AND `variant_id` = ?",
            [$qty, $this->storeId(), $productId, $variantId]
        );
    }

    /**
     * Bulk fetch for multiple units at once.
     * @param array<int, array{product_id:int, variant_id:int}> $units
     * @return array<string, Inventory> keyed by "productId:variantId"
     */
    public function forUnits(array $units): array
    {
        if ($units === []) {
            return [];
        }

        $or = [];
        $params = [$this->storeId()];
        foreach ($units as $u) {
            $or[] = "(`product_id` = ? AND `variant_id` = ?)";
            $params[] = (int) $u['product_id'];
            $params[] = (int) $u['variant_id'];
        }

        $sql = "SELECT * FROM `commerce_inventory`
                 WHERE `store_id` = ? AND (" . implode(' OR ', $or) . ")";

        $rows = $this->db->select($sql, $params);

        $indexed = [];
        foreach ($rows as $row) {
            $inv = Inventory::fromRow($row);
            $indexed[$inv->productId . ':' . $inv->variantId] = $inv;
        }
        return $indexed;
    }

    /**
     * Bulk fetch with FOR UPDATE row locks.
     *
     * Same shape as forUnits() - same return keys, same model -
     * but the query appends FOR UPDATE so the rows are locked
     * until the surrounding transaction commits or rolls back.
     *
     * MUST be called inside a transaction. Calling this outside
     * a transaction throws a PDO exception from MySQL (the lock
     * has nothing to hold it).
     *
     * Used by CheckoutService to serialize concurrent checkouts
     * of the same sellable unit. The lock is held from the
     * re-check (step 2 of the transaction) through the reserve
     * (step 7), preventing a race where two customers both pass
     * the stock check and both reserve.
     *
     * @param array<int, array{product_id:int, variant_id:int}> $units
     * @return array<string, Inventory> keyed by "productId:variantId"
     */
    public function forUnitsLocked(array $units): array
    {
        if ($units === []) {
            return [];
        }

        $or = [];
        $params = [$this->storeId()];
        foreach ($units as $u) {
            $or[] = "(`product_id` = ? AND `variant_id` = ?)";
            $params[] = (int) $u['product_id'];
            $params[] = (int) $u['variant_id'];
        }

        $sql = "SELECT * FROM `commerce_inventory`
                 WHERE `store_id` = ? AND (" . implode(' OR ', $or) . ")
                 FOR UPDATE";

        $rows = $this->db->select($sql, $params);

        $indexed = [];
        foreach ($rows as $row) {
            $inv = Inventory::fromRow($row);
            $indexed[$inv->productId . ':' . $inv->variantId] = $inv;
        }
        return $indexed;
    }

    // =================================================================
    // Counts
    // =================================================================

    public function countLowStock(): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*)
               FROM `commerce_inventory` i
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

    public function countOutOfStock(): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*)
               FROM `commerce_inventory` i
               JOIN `commerce_products` p
                 ON p.`id` = i.`product_id` AND p.`store_id` = i.`store_id`
              WHERE i.`store_id` = ?
                AND p.`deleted_at` IS NULL
                AND p.`status` = 'published'
                AND i.`available` <= 0",
            [$this->storeId()]
        );
    }

    /**
     * Total units on hand across the store.
     */
    public function totalUnitsOnHand(): int
    {
        return (int) $this->db->scalar(
            "SELECT COALESCE(SUM(`quantity`), 0) FROM `commerce_inventory`
              WHERE `store_id` = ?",
            [$this->storeId()]
        );
    }

    // =================================================================
    // Listings with filters + pagination (Stage 006G)
    // =================================================================

    /**
     * Paginated list of sellable units (product x variant combos).
     *
     * @param array{
     *   q?: string,
     *   status?: string,      // 'in_stock' | 'low_stock' | 'out_of_stock'
     *   category_id?: int,
     *   brand_id?: int
     * } $filters
     * @return array{items: array<int, array<string,mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->buildListFilters($filters);

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_inventory` i
               JOIN `commerce_products` p
                 ON p.`id` = i.`product_id` AND p.`store_id` = i.`store_id`
          LEFT JOIN `commerce_product_variants` v
                 ON v.`id` = i.`variant_id` AND i.`variant_id` > 0
              WHERE {$where}",
            $params
        );

        $rows = $this->db->select(
            "SELECT i.`id` AS inventory_id, i.`product_id`, i.`variant_id`,
                    i.`quantity`, i.`reserved`, i.`available`,
                    i.`low_stock_threshold` AS unit_threshold,
                    i.`backorders_allowed` AS unit_backorders,
                    i.`updated_at` AS inventory_updated,
                    p.`name` AS product_name, p.`sku` AS product_sku,
                    p.`low_stock_threshold` AS product_threshold,
                    p.`backorders_allowed` AS product_backorders,
                    p.`status` AS product_status,
                    v.`sku` AS variant_sku, v.`status` AS variant_status,
                    (SELECT GROUP_CONCAT(CONCAT(ad.`name`, ': ', av.`value`) ORDER BY ad.`sort_order` SEPARATOR ', ')
                       FROM `commerce_variant_attribute_values` vav
                       JOIN `commerce_attribute_definitions` ad ON ad.`id` = vav.`attribute_id`
                       JOIN `commerce_attribute_values` av ON av.`id` = vav.`attribute_value_id`
                      WHERE vav.`variant_id` = i.`variant_id`
                    ) AS variant_summary
               FROM `commerce_inventory` i
               JOIN `commerce_products` p
                 ON p.`id` = i.`product_id` AND p.`store_id` = i.`store_id`
          LEFT JOIN `commerce_product_variants` v
                 ON v.`id` = i.`variant_id` AND i.`variant_id` > 0
              WHERE {$where}
              ORDER BY i.`available` ASC, p.`name` ASC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items'    => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Paginated movement history with filters.
     *
     * @param array{
     *   q?: string,
     *   type?: string,
     *   from?: string,
     *   to?: string
     * } $filters
     * @return array{items: array<int, array<string,mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function movementsPaginate(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = min(200, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->buildMovementFilters($filters);

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_inventory_movements` m
               JOIN `commerce_products` p
                 ON p.`id` = m.`product_id` AND p.`store_id` = m.`store_id`
              WHERE {$where}",
            $params
        );

        $rows = $this->db->select(
            "SELECT m.*, p.`name` AS product_name,
                    v.`sku` AS variant_sku,
                    (SELECT GROUP_CONCAT(CONCAT(ad.`name`, ': ', av.`value`) ORDER BY ad.`sort_order` SEPARATOR ', ')
                       FROM `commerce_variant_attribute_values` vav
                       JOIN `commerce_attribute_definitions` ad ON ad.`id` = vav.`attribute_id`
                       JOIN `commerce_attribute_values` av ON av.`id` = vav.`attribute_value_id`
                      WHERE vav.`variant_id` = m.`variant_id`
                    ) AS variant_summary,
                    u.`name` AS actor_name
               FROM `commerce_inventory_movements` m
               JOIN `commerce_products` p
                 ON p.`id` = m.`product_id` AND p.`store_id` = m.`store_id`
          LEFT JOIN `commerce_product_variants` v
                 ON v.`id` = m.`variant_id` AND m.`variant_id` > 0
          LEFT JOIN `core_users` u ON u.`id` = m.`created_by`
              WHERE {$where}
              ORDER BY m.`created_at` DESC, m.`id` DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items'    => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * @return array{0:string, 1:array}
     */
    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildListFilters(array $filters): array
    {
        $where = ["i.`store_id` = ?", "p.`deleted_at` IS NULL"];
        $params = [$this->storeId()];

        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $where[] = "(p.`name` LIKE ? OR p.`sku` LIKE ? OR v.`sku` LIKE ?)";
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

        if (!empty($filters['status'])) {
            switch ($filters['status']) {
                case 'in_stock':
                    $where[] = "i.`available` > 0 AND i.`available` > COALESCE(i.`low_stock_threshold`, p.`low_stock_threshold`, 5)";
                    break;
                case 'low_stock':
                    $where[] = "i.`available` > 0 AND i.`available` <= COALESCE(i.`low_stock_threshold`, p.`low_stock_threshold`, 5)";
                    break;
                case 'out_of_stock':
                    $where[] = "i.`available` <= 0";
                    break;
            }
        }

        return [implode(' AND ', $where), $params];
    }

    // =================================================================
    // Product-grouped list (Stage 008D.2)
    // =================================================================

    /**
     * Paginated inventory list grouped by product.
     *
     * Every row represents one product, not one sellable unit.
     * Simple products produce one row. Variable products produce
     * one row with a variant count and aggregate stock. The
     * aggregate columns drive the list page's status badge.
     *
     * @param array<string,mixed> $filters Same filters as paginate()
     * @return array{
     *     items: array<int, array<string,mixed>>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     pages: int
     * }
     */
    public function paginateProductsWithStock(
        array $filters = [],
        int $page = 1,
        int $perPage = 25
    ): array {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildProductListFilters($filters);

        // Count of products (not sellable units) matching filters.
        $total = (int) $this->db->scalar(
            "SELECT COUNT(DISTINCT p.`id`)
               FROM `commerce_products` p
          LEFT JOIN `commerce_inventory` i
                 ON i.`product_id` = p.`id`
                AND i.`store_id` = p.`store_id`
              WHERE {$where}",
            $params
        );

        // One row per product with aggregate stock. The
        // HAVING clause is empty - filters already narrowed the
        // candidate set.
        $rows = $this->db->select(
            "SELECT
                p.`id`              AS product_id,
                p.`name`            AS product_name,
                p.`slug`            AS product_slug,
                p.`sku`             AS product_sku,
                p.`product_type`,
                p.`status`          AS product_status,
                p.`low_stock_threshold` AS product_threshold,
                p.`deleted_at`,
                COUNT(DISTINCT i.`id`)  AS unit_count,
                COALESCE(SUM(i.`quantity`), 0)  AS total_quantity,
                COALESCE(SUM(i.`reserved`), 0)  AS total_reserved,
                COALESCE(SUM(i.`available`), 0) AS total_available,
                SUM(CASE WHEN i.`available` > 0 THEN 1 ELSE 0 END)
                    AS units_in_stock,
                MAX(CASE
                        WHEN i.`available` > 0
                         AND i.`available` <= COALESCE(
                                i.`low_stock_threshold`,
                                p.`low_stock_threshold`,
                                5
                             )
                        THEN 1
                        ELSE 0
                    END) AS any_low_stock
               FROM `commerce_products` p
          LEFT JOIN `commerce_inventory` i
                 ON i.`product_id` = p.`id`
                AND i.`store_id` = p.`store_id`
              WHERE {$where}
              GROUP BY p.`id`
              ORDER BY p.`name` ASC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        // Compute the product-level status badge server-side so
        // the view doesn't repeat the logic.
        //
        // The semantics here are about the product as a whole,
        // not about individual sellable units:
        //
        //   out_of_stock  - zero sellable units have any stock.
        //                   The product cannot be bought at all.
        //   low_stock     - at least one sellable unit is in stock
        //                   but at or below threshold. The
        //                   product is buyable but some variant
        //                   needs attention.
        //   in_stock      - all sellable units are healthy.
        //   no_stock      - no inventory rows exist. Edge case;
        //                   should not occur after the 008D.1
        //                   migration.
        foreach ($rows as &$row) {
            $unitCount    = (int) $row['unit_count'];
            $unitsInStock = (int) $row['units_in_stock'];
            $anyLow       = (int) $row['any_low_stock'] === 1;

            if ($unitCount === 0) {
                $row['product_status_display'] = 'no_stock';
            } elseif ($unitsInStock === 0) {
                $row['product_status_display'] = 'out_of_stock';
            } elseif ($anyLow) {
                $row['product_status_display'] = 'low_stock';
            } else {
                $row['product_status_display'] = 'in_stock';
            }
        }
        unset($row);

        return [
            'items'    => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * All inventory rows for one product with joined variant info.
     * Used by the per-product detail page.
     *
     * @return array<int, array<string,mixed>>
     */
    public function stockForProduct(int $productId): array
    {
        return $this->db->select(
            "SELECT
                i.`id`              AS inventory_id,
                i.`product_id`,
                i.`variant_id`,
                i.`quantity`,
                i.`reserved`,
                i.`available`,
                i.`low_stock_threshold` AS unit_threshold,
                i.`backorders_allowed`  AS unit_backorders,
                i.`updated_at`          AS inventory_updated,
                p.`name`            AS product_name,
                p.`sku`             AS product_sku,
                p.`product_type`,
                p.`low_stock_threshold` AS product_threshold,
                v.`sku`             AS variant_sku,
                v.`status`          AS variant_status,
                v.`sort_order`      AS variant_sort,
                (SELECT GROUP_CONCAT(
                            CONCAT(ad.`name`, ': ', av.`value`)
                            ORDER BY ad.`sort_order`
                            SEPARATOR ', '
                        )
                   FROM `commerce_variant_attribute_values` vav
                   JOIN `commerce_attribute_definitions` ad
                     ON ad.`id` = vav.`attribute_id`
                   JOIN `commerce_attribute_values` av
                     ON av.`id` = vav.`attribute_value_id`
                  WHERE vav.`variant_id` = i.`variant_id`
                ) AS variant_summary
               FROM `commerce_inventory` i
               JOIN `commerce_products` p
                 ON p.`id` = i.`product_id`
                AND p.`store_id` = i.`store_id`
          LEFT JOIN `commerce_product_variants` v
                 ON v.`id` = i.`variant_id`
                AND i.`variant_id` > 0
              WHERE i.`store_id` = ?
                AND i.`product_id` = ?
              ORDER BY v.`sort_order` ASC, i.`variant_id` ASC",
            [$this->storeId(), $productId]
        );
    }

    /**
     * Filter builder for the product-grouped list. Same filter
     * names as buildListFilters() (q, category_id, brand_id,
     * status), but the status filter applies to the aggregate
     * product status, not per-unit.
     *
     * @param array<string,mixed> $filters
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildProductListFilters(array $filters): array
    {
        $where  = ["p.`store_id` = ?", "p.`deleted_at` IS NULL"];
        $params = [$this->storeId()];

        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $where[] = "(p.`name` LIKE ? OR p.`sku` LIKE ?)";
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

        if (!empty($filters['status'])) {
            // Aggregate-level status. Uses a HAVING-style
            // subquery so the filter applies after GROUP BY.
            // We express it as an EXISTS on the join so the
            // outer query stays one row per product.
            switch ($filters['status']) {
                case 'in_stock':
                    // At least one unit is in stock and none are
                    // low or out.
                    $where[] = "NOT EXISTS (
                        SELECT 1 FROM `commerce_inventory` ii
                         WHERE ii.`product_id` = p.`id`
                           AND ii.`store_id` = p.`store_id`
                           AND ii.`available` <= COALESCE(
                               ii.`low_stock_threshold`,
                               p.`low_stock_threshold`,
                               5
                           )
                    )";
                    $where[] = "EXISTS (
                        SELECT 1 FROM `commerce_inventory` ii
                         WHERE ii.`product_id` = p.`id`
                           AND ii.`store_id` = p.`store_id`
                    )";
                    break;
                case 'low_stock':
                    // Any unit is low (but not all are out).
                    $where[] = "EXISTS (
                        SELECT 1 FROM `commerce_inventory` ii
                         WHERE ii.`product_id` = p.`id`
                           AND ii.`store_id` = p.`store_id`
                           AND ii.`available` > 0
                           AND ii.`available` <= COALESCE(
                               ii.`low_stock_threshold`,
                               p.`low_stock_threshold`,
                               5
                           )
                    )";
                    break;
                case 'out_of_stock':
                    // Every unit is out of stock (or no units).
                    $where[] = "NOT EXISTS (
                        SELECT 1 FROM `commerce_inventory` ii
                         WHERE ii.`product_id` = p.`id`
                           AND ii.`store_id` = p.`store_id`
                           AND ii.`available` > 0
                    )";
                    break;
            }
        }

        return [implode(' AND ', $where), $params];
    }


    /**
     * @return array{0:string, 1:array}
     */
    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildMovementFilters(array $filters): array
    {
        $where = ["m.`store_id` = ?"];
        $params = [$this->storeId()];

        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $where[] = "(p.`name` LIKE ? OR p.`sku` LIKE ? OR m.`reason` LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['product_id'])) {
            $where[] = "m.`product_id` = ?";
            $params[] = (int) $filters['product_id'];
        }

        if (!empty($filters['type'])) {
            $where[] = "m.`movement_type` = ?";
            $params[] = (string) $filters['type'];
        }

        if (!empty($filters['from'])) {
            $where[] = "m.`created_at` >= ?";
            $params[] = $filters['from'] . ' 00:00:00';
        }

        if (!empty($filters['to'])) {
            $where[] = "m.`created_at` <= ?";
            $params[] = $filters['to'] . ' 23:59:59';
        }

        return [implode(' AND ', $where), $params];
    }

}