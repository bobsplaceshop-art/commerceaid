<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

/**
 * Shipping repository.
 *
 * Owns two config tables:
 *   - commerce_shipping_methods  (the method: code, label,
 *                                  type, config_json)
 *   - commerce_shipping_zones    (where each method is
 *                                  available, with an
 *                                  optional rate override)
 *
 * Method resolution: a method is available at a destination
 * if a zone row matches the country and (region IS NULL OR
 * region = destination.region). methodsForDestination()
 * returns the active methods with the zone join already
 * applied so ShippingService computes rates without an
 * extra query.
 *
 * Order history references methods by code (a string), not
 * by ID. Deleting a method cascades its zones but does not
 * touch orders. The controller refuses to delete a method
 * referenced by recent orders; it disables (is_active = 0)
 * instead.
 *
 * See docs/stages/008k.5_order-pricing-engine-plan.md.
 */
final class ShippingRepository extends CommerceRepository
{
    // =================================================================
    // Shipping methods
    // =================================================================

    /** @return array<int,array<string,mixed>> */
    public function listMethods(): array
    {
        return $this->db->select(
            "SELECT * FROM `commerce_shipping_methods`
              WHERE `store_id` = ?
              ORDER BY `sort_order` ASC, `label` ASC",
            [$this->storeId()]
        );
    }

    /** @return array<string,mixed>|null */
    public function findMethod(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return $this->db->selectOne(
            "SELECT * FROM `commerce_shipping_methods`
              WHERE `store_id` = ? AND `id` = ?
              LIMIT 1",
            [$this->storeId(), $id]
        );
    }

    /** @return array<string,mixed>|null */
    public function findMethodByCode(string $code): ?array
    {
        if ($code === '') {
            return null;
        }
        return $this->db->selectOne(
            "SELECT * FROM `commerce_shipping_methods`
              WHERE `store_id` = ? AND `code` = ?
              LIMIT 1",
            [$this->storeId(), $code]
        );
    }

    /**
     * Active methods available at a destination, with the
     * zone's rate_override joined in when present.
     *
     * Match rule:
     *   - method.is_active = 1
     *   - zone.is_active = 1
     *   - zone.country_code = destination.countryCode
     *   - zone.region = '' OR zone.region = destination.region
     *
     * A destination with no region matches zones that carry
     * either '' or NULL region (both are normalized to '' by
     * the migration).
     *
     * Precedence: when more than one zone matches for the same
     * method (e.g., a Berlin zone and a Germany zone both
     * match a Berlin address), the zone with the lowest
     * sort_order wins. This is the industry-standard
     * most-specific-first rule. The merchant orders their
     * zones accordingly.
     *
     * The correlated subquery in the WHERE clause picks the
     * single winning zone id per method. Only that zone's row
     * survives the outer join condition. Without this, a
     * method with two matching zones would appear twice in
     * the result.
     *
     * @return array<int,array<string,mixed>>
     */
    public function methodsForDestination(string $countryCode, string $region = ''): array
    {
        if ($countryCode === '') {
            return [];
        }

        return $this->db->select(
            "SELECT m.*, z.`id` AS zone_id,
                    z.`rate_override` AS zone_rate_override,
                    z.`region` AS zone_region,
                    z.`sort_order` AS zone_sort_order
               FROM `commerce_shipping_methods` m
               INNER JOIN `commerce_shipping_zones` z
                 ON z.`method_id` = m.`id`
              WHERE m.`store_id` = ?
                AND m.`is_active` = 1
                AND z.`is_active` = 1
                AND z.`country_code` = ?
                AND (z.`region` = '' OR z.`region` = ?)
                AND z.`id` = (
                    SELECT z2.`id`
                      FROM `commerce_shipping_zones` z2
                     WHERE z2.`method_id` = m.`id`
                       AND z2.`is_active` = 1
                       AND z2.`country_code` = ?
                       AND (z2.`region` = '' OR z2.`region` = ?)
                     ORDER BY z2.`sort_order` ASC, z2.`id` ASC
                     LIMIT 1
                )
              ORDER BY m.`sort_order` ASC, m.`label` ASC",
            [
                $this->storeId(),
                $countryCode,
                $region,
                $countryCode,
                $region,
            ]
        );
    }

    /** @param array<string,mixed> $data */
    public function createMethod(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO `commerce_shipping_methods`
                (`store_id`, `code`, `label`, `description`,
                 `type`, `config_json`, `is_active`, `sort_order`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $this->storeId(),
                (string) $data['code'],
                (string) $data['label'],
                $data['description'] ?? null,
                (string) $data['type'],
                $data['config_json'] ?? null,
                !empty($data['is_active']) ? 1 : 0,
                (int) ($data['sort_order'] ?? 0),
            ]
        );
    }

    /** @param array<string,mixed> $data */
    public function updateMethod(int $id, array $data): void
    {
        $this->db->execute(
            "UPDATE `commerce_shipping_methods`
                SET `code` = ?,
                    `label` = ?,
                    `description` = ?,
                    `type` = ?,
                    `config_json` = ?,
                    `is_active` = ?,
                    `sort_order` = ?
              WHERE `store_id` = ? AND `id` = ?",
            [
                (string) $data['code'],
                (string) $data['label'],
                $data['description'] ?? null,
                (string) $data['type'],
                $data['config_json'] ?? null,
                !empty($data['is_active']) ? 1 : 0,
                (int) ($data['sort_order'] ?? 0),
                $this->storeId(),
                $id,
            ]
        );
    }

    public function deleteMethod(int $id): void
    {
        $this->db->execute(
            "DELETE FROM `commerce_shipping_methods`
              WHERE `store_id` = ? AND `id` = ?",
            [$this->storeId(), $id]
        );
    }

    public function methodCodeExists(string $code, ?int $excludeId = null): bool
    {
        if ($code === '') {
            return false;
        }
        if ($excludeId !== null && $excludeId > 0) {
            $row = $this->db->selectOne(
                "SELECT 1 FROM `commerce_shipping_methods`
                  WHERE `store_id` = ? AND `code` = ? AND `id` <> ?
                  LIMIT 1",
                [$this->storeId(), $code, $excludeId]
            );
        } else {
            $row = $this->db->selectOne(
                "SELECT 1 FROM `commerce_shipping_methods`
                  WHERE `store_id` = ? AND `code` = ?
                  LIMIT 1",
                [$this->storeId(), $code]
            );
        }
        return $row !== null;
    }

    /**
     * Number of orders in the last N days that used a method
     * (matched by code, since that is what the order stores).
     * Delete guard.
     */
    public function countRecentOrdersForMethod(string $code, int $days = 30): int
    {
        if ($code === '') {
            return 0;
        }
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_orders`
              WHERE `store_id` = ?
                AND `shipping_method_key` = ?
                AND `placed_at` >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$this->storeId(), $code, $days]
        );
    }

    // =================================================================
    // Shipping zones
    // =================================================================

    /** @return array<int,array<string,mixed>> */
    public function listZones(?int $methodId = null): array
    {
        if ($methodId !== null && $methodId > 0) {
            return $this->db->select(
                "SELECT * FROM `commerce_shipping_zones`
                  WHERE `store_id` = ? AND `method_id` = ?
                  ORDER BY `country_code` ASC, `region` ASC",
                [$this->storeId(), $methodId]
            );
        }
        return $this->db->select(
            "SELECT z.*, m.`code` AS method_code, m.`label` AS method_label
               FROM `commerce_shipping_zones` z
               INNER JOIN `commerce_shipping_methods` m
                 ON m.`id` = z.`method_id`
              WHERE z.`store_id` = ?
              ORDER BY z.`method_id` ASC, z.`country_code` ASC, z.`region` ASC",
            [$this->storeId()]
        );
    }

    /** @return array<string,mixed>|null */
    public function findZone(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return $this->db->selectOne(
            "SELECT * FROM `commerce_shipping_zones`
              WHERE `store_id` = ? AND `id` = ?
              LIMIT 1",
            [$this->storeId(), $id]
        );
    }

    /** @param array<string,mixed> $data */
    public function createZone(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO `commerce_shipping_zones`
                (`store_id`, `method_id`, `country_code`, `region`,
                 `sort_order`, `rate_override`, `is_active`)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $this->storeId(),
                (int) $data['method_id'],
                (string) $data['country_code'],
                (string) ($data['region'] ?? ''),
                (int) ($data['sort_order'] ?? 0),
                $data['rate_override'] ?? null,
                !empty($data['is_active']) ? 1 : 0,
            ]
        );
    }

    /** @param array<string,mixed> $data */
    public function updateZone(int $id, array $data): void
    {
        $this->db->execute(
            "UPDATE `commerce_shipping_zones`
                SET `method_id` = ?,
                    `country_code` = ?,
                    `region` = ?,
                    `sort_order` = ?,
                    `rate_override` = ?,
                    `is_active` = ?
              WHERE `store_id` = ? AND `id` = ?",
            [
                (int) $data['method_id'],
                (string) $data['country_code'],
                (string) ($data['region'] ?? ''),
                (int) ($data['sort_order'] ?? 0),
                $data['rate_override'] ?? null,
                !empty($data['is_active']) ? 1 : 0,
                $this->storeId(),
                $id,
            ]
        );
    }

    public function deleteZone(int $id): void
    {
        $this->db->execute(
            "DELETE FROM `commerce_shipping_zones`
              WHERE `store_id` = ? AND `id` = ?",
            [$this->storeId(), $id]
        );
    }

    /**
     * Whether a zone already exists for the (method, country,
     * region) tuple. Excludes the given id for edit.
     */
    public function zoneExists(
        int $methodId,
        string $countryCode,
        string $region,
        ?int $excludeId = null
    ): bool {
        if ($excludeId !== null && $excludeId > 0) {
            $row = $this->db->selectOne(
                "SELECT 1 FROM `commerce_shipping_zones`
                  WHERE `store_id` = ? AND `method_id` = ?
                    AND `country_code` = ? AND `region` = ?
                    AND `id` <> ?
                  LIMIT 1",
                [$this->storeId(), $methodId, $countryCode, $region, $excludeId]
            );
        } else {
            $row = $this->db->selectOne(
                "SELECT 1 FROM `commerce_shipping_zones`
                  WHERE `store_id` = ? AND `method_id` = ?
                    AND `country_code` = ? AND `region` = ?
                  LIMIT 1",
                [$this->storeId(), $methodId, $countryCode, $region]
            );
        }
        return $row !== null;
    }
}
