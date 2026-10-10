<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

/**
 * Coupon repository.
 *
 * Every row is scoped to a store. The unique key
 * uniq_com_coupons_store_code makes codes unique within a
 * store but reusable across stores.
 *
 * Returns raw rows (array<string,mixed>), not a model. The
 * coupon has 15 columns and no behavior worth a class; the
 * service normalizes types when it reads.
 *
 * See docs/stages/008k.5_order-pricing-engine-plan.md.
 */
final class CouponRepository extends CommerceRepository
{
    /**
     * Find a coupon by its code. Case-insensitive: codes are
     * stored uppercase by the service.
     *
     * @return array<string,mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        if ($code === '') {
            return null;
        }
        return $this->db->selectOne(
            "SELECT * FROM `commerce_coupons`
              WHERE `store_id` = ? AND `code` = ?
              LIMIT 1",
            [$this->storeId(), $code]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return $this->db->selectOne(
            "SELECT * FROM `commerce_coupons`
              WHERE `store_id` = ? AND `id` = ?
              LIMIT 1",
            [$this->storeId(), $id]
        );
    }

    /**
     * Whether a coupon is currently inside its validity
     * window, and if not, whether it is before or after.
     *
     * Returns:
     *   'before'  valid_from is set and is in the future
     *   'after'   valid_to is set and is in the past
     *   'within'  both bounds pass (or are NULL)
     *
     * The comparison is done by MySQL NOW() so it respects
     * the time discipline. Never call this with a coupon id
     * that has not already been fetched and confirmed to
     * exist - it returns 'within' on any lookup failure, so
     * a missing coupon would look valid.
     */
    public function validityStatus(int $couponId): string
    {
        if ($couponId <= 0) {
            return 'within';
        }
        $row = $this->db->selectOne(
            "SELECT
                CASE
                  WHEN `valid_from` IS NOT NULL AND `valid_from` > NOW() THEN 'before'
                  WHEN `valid_to`   IS NOT NULL AND `valid_to`   < NOW() THEN 'after'
                  ELSE 'within'
                END AS `status`
               FROM `commerce_coupons`
              WHERE `store_id` = ? AND `id` = ?
              LIMIT 1",
            [$this->storeId(), $couponId]
        );
        return (string) ($row['status'] ?? 'within');
    }

    /**
     * Paginated list with optional filters.
     *
     * @param array<string,string> $filters
     *   q      - code or description LIKE
     *   status - active | disabled | all
     * @return array{items:array<int,array<string,mixed>>,
     *               total:int,page:int,per_page:int,pages:int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildFilters($filters);

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_coupons` c WHERE {$where}",
            $params
        );

        $rows = $this->db->select(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM `commerce_coupon_redemptions` r
                      WHERE r.`coupon_id` = c.`id`) AS redemption_count
               FROM `commerce_coupons` c
              WHERE {$where}
              ORDER BY c.`created_at` DESC, c.`id` DESC
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
     * Insert a new coupon. The caller normalizes the code
     * (uppercase, trim) and validates the type/value before
     * calling.
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO `commerce_coupons`
                (`store_id`, `code`, `description`, `type`, `value`,
                 `valid_from`, `valid_to`, `min_order_subtotal`,
                 `usage_limit`, `per_customer_limit`,
                 `applies_to`, `applies_to_ids_json`, `status`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $this->storeId(),
                (string) $data['code'],
                $data['description'] ?? null,
                (string) $data['type'],
                (float) ($data['value'] ?? 0),
                $data['valid_from'] ?? null,
                $data['valid_to'] ?? null,
                $data['min_order_subtotal'] ?? null,
                $data['usage_limit'] ?? null,
                $data['per_customer_limit'] ?? null,
                (string) ($data['applies_to'] ?? 'all_products'),
                $data['applies_to_ids_json'] ?? null,
                (string) ($data['status'] ?? 'active'),
            ]
        );
    }

    /**
     * Update a coupon's mutable fields. Code is updatable only
     * because the controller prevents it after creation (a
     * coupon code is the customer-facing identifier and must
     * stay stable). The repository itself does not enforce that.
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $this->db->execute(
            "UPDATE `commerce_coupons`
                SET `code` = ?,
                    `description` = ?,
                    `type` = ?,
                    `value` = ?,
                    `valid_from` = ?,
                    `valid_to` = ?,
                    `min_order_subtotal` = ?,
                    `usage_limit` = ?,
                    `per_customer_limit` = ?,
                    `applies_to` = ?,
                    `applies_to_ids_json` = ?,
                    `status` = ?
              WHERE `store_id` = ? AND `id` = ?",
            [
                (string) $data['code'],
                $data['description'] ?? null,
                (string) $data['type'],
                (float) ($data['value'] ?? 0),
                $data['valid_from'] ?? null,
                $data['valid_to'] ?? null,
                $data['min_order_subtotal'] ?? null,
                $data['usage_limit'] ?? null,
                $data['per_customer_limit'] ?? null,
                (string) ($data['applies_to'] ?? 'all_products'),
                $data['applies_to_ids_json'] ?? null,
                (string) ($data['status'] ?? 'active'),
                $this->storeId(),
                $id,
            ]
        );
    }

    /**
     * Hard delete. The controller refuses to call this when the
     * coupon has redemptions. ON DELETE CASCADE from
     * commerce_coupon_redemptions means any redemptions are
     * removed at the DB level, but the guard prevents the
     * controller from reaching this state.
     */
    public function delete(int $id): void
    {
        $this->db->execute(
            "DELETE FROM `commerce_coupons`
              WHERE `store_id` = ? AND `id` = ?",
            [$this->storeId(), $id]
        );
    }

    /**
     * Toggle status between 'active' and 'disabled'. Used
     * instead of delete for a coupon with redemptions.
     */
    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['active', 'disabled'], true)) {
            return;
        }
        $this->db->execute(
            "UPDATE `commerce_coupons`
                SET `status` = ?
              WHERE `store_id` = ? AND `id` = ?",
            [$status, $this->storeId(), $id]
        );
    }

    /**
     * Whether a code already exists for this store. Excludes
     * the given id (used by the edit form to allow saving a
     * coupon without changing its code).
     */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        if ($code === '') {
            return false;
        }
        if ($excludeId !== null && $excludeId > 0) {
            $row = $this->db->selectOne(
                "SELECT 1 FROM `commerce_coupons`
                  WHERE `store_id` = ? AND `code` = ? AND `id` <> ?
                  LIMIT 1",
                [$this->storeId(), $code, $excludeId]
            );
        } else {
            $row = $this->db->selectOne(
                "SELECT 1 FROM `commerce_coupons`
                  WHERE `store_id` = ? AND `code` = ?
                  LIMIT 1",
                [$this->storeId(), $code]
            );
        }
        return $row !== null;
    }

    /**
     * @param array<string,string> $filters
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildFilters(array $filters): array
    {
        $where  = ["`c`.`store_id` = ?"];
        $params = [$this->storeId()];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[]  = "(`c`.`code` LIKE ? OR `c`.`description` LIKE ?)";
            $params[] = $like;
            $params[] = $like;
        }

        $status = (string) ($filters['status'] ?? 'all');
        if ($status === 'active' || $status === 'disabled') {
            $where[]  = "`c`.`status` = ?";
            $params[] = $status;
        }

        return [implode(' AND ', $where), $params];
    }
}
