<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

/**
 * Tax repository.
 *
 * Owns three tables:
 *   - commerce_tax_classes       (config)
 *   - commerce_tax_rates         (config)
 *   - commerce_order_taxes       (runtime ledger, written at
 *                                 checkout)
 *
 * The config tables are the merchant's setup. The runtime
 * table is the record of which rates were applied to which
 * order - the audit trail German VAT invoicing requires
 * (§ 14 UStG).
 *
 * Rate resolution: for a given (class, country, state), the
 * most specific match wins. A rate with state_code = ''
 * matches any state in that country. A rate with
 * state_code = 'BE' matches only Berlin.
 *
 * See docs/stages/008k.5_order-pricing-engine-plan.md.
 */
final class TaxRepository extends CommerceRepository
{
    // =================================================================
    // Tax classes
    // =================================================================

    /** @return array<int,array<string,mixed>> */
    public function listClasses(): array
    {
        return $this->db->select(
            "SELECT * FROM `commerce_tax_classes`
              WHERE `store_id` = ?
              ORDER BY `is_default` DESC, `name` ASC",
            [$this->storeId()]
        );
    }

    /** @return array<string,mixed>|null */
    public function findClass(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return $this->db->selectOne(
            "SELECT * FROM `commerce_tax_classes`
              WHERE `store_id` = ? AND `id` = ?
              LIMIT 1",
            [$this->storeId(), $id]
        );
    }

    /**
     * The store's default tax class. Used when a product has
     * no tax_class_id assigned. If no class is marked default,
     * returns the first class by name. If there are no classes
     * at all, returns null.
     *
     * @return array<string,mixed>|null
     */
    public function defaultClass(): ?array
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_tax_classes`
              WHERE `store_id` = ? AND `is_default` = 1
              LIMIT 1",
            [$this->storeId()]
        );
        if ($row !== null) {
            return $row;
        }
        return $this->db->selectOne(
            "SELECT * FROM `commerce_tax_classes`
              WHERE `store_id` = ?
              ORDER BY `name` ASC
              LIMIT 1",
            [$this->storeId()]
        );
    }

    /** @param array<string,mixed> $data */
    public function createClass(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO `commerce_tax_classes`
                (`store_id`, `name`, `is_default`)
             VALUES (?, ?, ?)",
            [
                $this->storeId(),
                (string) $data['name'],
                !empty($data['is_default']) ? 1 : 0,
            ]
        );
    }

    /** @param array<string,mixed> $data */
    public function updateClass(int $id, array $data): void
    {
        $this->db->execute(
            "UPDATE `commerce_tax_classes`
                SET `name` = ?, `is_default` = ?
              WHERE `store_id` = ? AND `id` = ?",
            [
                (string) $data['name'],
                !empty($data['is_default']) ? 1 : 0,
                $this->storeId(),
                $id,
            ]
        );
    }

    public function deleteClass(int $id): void
    {
        $this->db->execute(
            "DELETE FROM `commerce_tax_classes`
              WHERE `store_id` = ? AND `id` = ?",
            [$this->storeId(), $id]
        );
    }

    /**
     * How many products use this class. Delete guard.
     */
    public function countProductsForClass(int $classId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_products`
              WHERE `store_id` = ? AND `tax_class_id` = ?",
            [$this->storeId(), $classId]
        );
    }

    /**
     * Clear the is_default flag on every class for this store.
     * Used before setting a new default so exactly one class
     * carries the flag.
     */
    public function clearDefaultFlag(): void
    {
        $this->db->execute(
            "UPDATE `commerce_tax_classes`
                SET `is_default` = 0
              WHERE `store_id` = ?",
            [$this->storeId()]
        );
    }

    // =================================================================
    // Tax rates
    // =================================================================

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listRates(?int $classId = null): array
    {
        if ($classId !== null && $classId > 0) {
            return $this->db->select(
                "SELECT * FROM `commerce_tax_rates`
                  WHERE `store_id` = ? AND `tax_class_id` = ?
                  ORDER BY `country_code` ASC, `state_code` ASC",
                [$this->storeId(), $classId]
            );
        }
        return $this->db->select(
            "SELECT * FROM `commerce_tax_rates`
              WHERE `store_id` = ?
              ORDER BY `tax_class_id` ASC, `country_code` ASC, `state_code` ASC",
            [$this->storeId()]
        );
    }

    /** @return array<string,mixed>|null */
    public function findRate(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return $this->db->selectOne(
            "SELECT * FROM `commerce_tax_rates`
              WHERE `store_id` = ? AND `id` = ?
              LIMIT 1",
            [$this->storeId(), $id]
        );
    }

    /**
     * Resolve the rate for a class at a destination.
     *
     * Precedence:
     *   1. A rate with state_code = <destination state>
     *   2. A rate with state_code = '' (country-wide)
     *   3. null - no rate matches
     *
     * The destination state is passed as null for a country
     * without subdivisions; the caller normalizes '' to null.
     *
     * @return array<string,mixed>|null
     */
    public function resolveRate(int $classId, string $countryCode, ?string $stateCode): ?array
    {
        if ($classId <= 0 || $countryCode === '') {
            return null;
        }

        // State-specific first.
        if ($stateCode !== null && $stateCode !== '') {
            $row = $this->db->selectOne(
                "SELECT * FROM `commerce_tax_rates`
                  WHERE `store_id` = ? AND `tax_class_id` = ?
                    AND `country_code` = ? AND `state_code` = ?
                  ORDER BY `priority` DESC
                  LIMIT 1",
                [$this->storeId(), $classId, $countryCode, $stateCode]
            );
            if ($row !== null) {
                return $row;
            }
        }

        // Country-wide fallback.
        return $this->db->selectOne(
            "SELECT * FROM `commerce_tax_rates`
              WHERE `store_id` = ? AND `tax_class_id` = ?
                AND `country_code` = ? AND `state_code` = ''
              ORDER BY `priority` DESC
              LIMIT 1",
            [$this->storeId(), $classId, $countryCode]
        );
    }

    /** @param array<string,mixed> $data */
    public function createRate(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO `commerce_tax_rates`
                (`store_id`, `tax_class_id`, `country_code`,
                 `state_code`, `rate_percent`, `is_inclusive`,
                 `is_shipping_taxable`, `priority`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $this->storeId(),
                (int) $data['tax_class_id'],
                (string) $data['country_code'],
                (string) ($data['state_code'] ?? ''),
                (float) $data['rate_percent'],
                !empty($data['is_inclusive']) ? 1 : 0,
                !empty($data['is_shipping_taxable']) ? 1 : 0,
                (int) ($data['priority'] ?? 0),
            ]
        );
    }

    /** @param array<string,mixed> $data */
    public function updateRate(int $id, array $data): void
    {
        $this->db->execute(
            "UPDATE `commerce_tax_rates`
                SET `tax_class_id` = ?,
                    `country_code` = ?,
                    `state_code` = ?,
                    `rate_percent` = ?,
                    `is_inclusive` = ?,
                    `is_shipping_taxable` = ?,
                    `priority` = ?
              WHERE `store_id` = ? AND `id` = ?",
            [
                (int) $data['tax_class_id'],
                (string) $data['country_code'],
                (string) ($data['state_code'] ?? ''),
                (float) $data['rate_percent'],
                !empty($data['is_inclusive']) ? 1 : 0,
                !empty($data['is_shipping_taxable']) ? 1 : 0,
                (int) ($data['priority'] ?? 0),
                $this->storeId(),
                $id,
            ]
        );
    }

    public function deleteRate(int $id): void
    {
        $this->db->execute(
            "DELETE FROM `commerce_tax_rates`
              WHERE `store_id` = ? AND `id` = ?",
            [$this->storeId(), $id]
        );
    }

    /**
     * Number of orders in the last N days that used this
     * rate. Delete guard for a rate that is still being
     * referenced by recent orders.
     */
    public function countRecentOrdersForRate(int $rateId, int $days = 30): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_order_taxes`
              WHERE `store_id` = ? AND `tax_rate_id` = ?
                AND `created_at` >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$this->storeId(), $rateId, $days]
        );
    }

    /**
     * Whether a rate already exists for the (class, country,
     * state) tuple. Excludes the given id for edit.
     */
    public function rateExists(
        int $classId,
        string $countryCode,
        string $stateCode,
        ?int $excludeId = null
    ): bool {
        if ($excludeId !== null && $excludeId > 0) {
            $row = $this->db->selectOne(
                "SELECT 1 FROM `commerce_tax_rates`
                  WHERE `store_id` = ? AND `tax_class_id` = ?
                    AND `country_code` = ? AND `state_code` = ?
                    AND `id` <> ?
                  LIMIT 1",
                [$this->storeId(), $classId, $countryCode, $stateCode, $excludeId]
            );
        } else {
            $row = $this->db->selectOne(
                "SELECT 1 FROM `commerce_tax_rates`
                  WHERE `store_id` = ? AND `tax_class_id` = ?
                    AND `country_code` = ? AND `state_code` = ?
                  LIMIT 1",
                [$this->storeId(), $classId, $countryCode, $stateCode]
            );
        }
        return $row !== null;
    }

    // =================================================================
    // Order taxes (runtime ledger)
    // =================================================================

    /**
     * Write one row to commerce_order_taxes per tax class
     * applied to the order. Called inside the checkout
     * transaction.
     *
     * @param array<int,array{
     *   tax_class_id:?int, tax_rate_id:?int,
     *   label:string, rate_percent:float,
     *   amount:float
     * }> $classes
     */
    public function recordOrderTaxes(int $orderId, array $classes): void
    {
        if ($classes === [] || $orderId <= 0) {
            return;
        }

        $values = [];
        $params = [];
        foreach ($classes as $c) {
            // Skip zero-amount entries - a class that computed
            // to 0 has no invoice line.
            if ((float) ($c['amount'] ?? 0) === 0.0) {
                continue;
            }
            $values[] = '(?, ?, ?, ?, ?, ?, ?)';
            $params[] = $this->storeId();
            $params[] = $orderId;
            $params[] = $c['tax_class_id'] ?? null;
            $params[] = $c['tax_rate_id'] ?? null;
            $params[] = (string) ($c['label'] ?? '');
            $params[] = (float) ($c['rate_percent'] ?? 0);
            $params[] = (float) $c['amount'];
        }

        if ($values === []) {
            return;
        }

        $this->db->execute(
            "INSERT INTO `commerce_order_taxes`
                (`store_id`, `order_id`, `tax_class_id`,
                 `tax_rate_id`, `label`, `rate_percent`, `amount`)
             VALUES " . implode(', ', $values),
            $params
        );
    }

    /**
     * The tax breakdown for an order, for the invoice and
     * order detail rendering.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listForOrder(int $orderId): array
    {
        return $this->db->select(
            "SELECT * FROM `commerce_order_taxes`
              WHERE `store_id` = ? AND `order_id` = ?
              ORDER BY `id` ASC",
            [$this->storeId(), $orderId]
        );
    }
}
