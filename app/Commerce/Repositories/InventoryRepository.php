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
            $current = $this->forUnit($productId, $variantId);
            if ($current === null) {
                $this->db->execute(
                    "INSERT INTO `commerce_inventory`
                        (`store_id`, `product_id`, `variant_id`, `quantity`, `reserved`)
                     VALUES (?, ?, ?, 0, 0)",
                    [$this->storeId(), $productId, $variantId]
                );
            }

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
        return InventoryMovement::fromRow($row);
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

    /**
     * @return array{0:string, 1:array}
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