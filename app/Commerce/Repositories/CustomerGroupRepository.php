<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\CustomerGroup;

/**
 * Customer group repository.
 *
 * Every query scoped by store_id.
 */
final class CustomerGroupRepository extends CommerceRepository
{
    /** @return array<int, CustomerGroup> */
    public function listAll(bool $includeArchived = false): array
    {
        $sql = "SELECT * FROM `commerce_customer_groups`
                 WHERE `store_id` = ?";
        if (!$includeArchived) {
            $sql .= " AND `status` = 'active'";
        }
        $sql .= " ORDER BY `sort_order` ASC, `name` ASC";

        $rows = $this->db->select($sql, [$this->storeId()]);
        return array_map(fn ($r) => CustomerGroup::fromRow($r), $rows);
    }

    /** @return array<int, array<string,mixed>> */
    public function listForSelect(): array
    {
        return $this->db->select(
            "SELECT `id`, `name`, `is_default`
               FROM `commerce_customer_groups`
              WHERE `store_id` = ? AND `status` = 'active'
              ORDER BY `sort_order` ASC, `name` ASC",
            [$this->storeId()]
        );
    }

    public function findById(int $id): ?CustomerGroup
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_customer_groups`
              WHERE `id` = ? AND `store_id` = ?
              LIMIT 1",
            [$id, $this->storeId()]
        );
        return $row ? CustomerGroup::fromRow($row) : null;
    }

    public function findBySlug(string $slug): ?CustomerGroup
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_customer_groups`
              WHERE `slug` = ? AND `store_id` = ?
              LIMIT 1",
            [$slug, $this->storeId()]
        );
        return $row ? CustomerGroup::fromRow($row) : null;
    }

    public function findDefault(): ?CustomerGroup
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_customer_groups`
              WHERE `store_id` = ? AND `is_default` = 1 AND `status` = 'active'
              LIMIT 1",
            [$this->storeId()]
        );
        return $row ? CustomerGroup::fromRow($row) : null;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM `commerce_customer_groups`
                 WHERE `store_id` = ? AND `slug` = ?";
        $params = [$this->storeId(), $slug];

        if ($excludeId !== null) {
            $sql .= " AND `id` <> ?";
            $params[] = $excludeId;
        }

        return (int) $this->db->scalar($sql, $params) > 0;
    }

    public function create(CustomerGroup $group): int
    {
        // If this is the first group or explicitly marked default,
        // clear the default flag from any other group first.
        if ($group->isDefault) {
            $this->clearDefault();
        } elseif (empty($this->listAll(true))) {
            $group->isDefault = true;
        }

        return $this->db->insert(
            "INSERT INTO `commerce_customer_groups`
                (`store_id`, `slug`, `name`, `description`, `is_default`, `is_b2b`,
                 `requires_approval`, `default_net_days`, `sort_order`, `status`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $this->storeId(),
                $group->slug,
                $group->name,
                $group->description,
                $group->isDefault ? 1 : 0,
                $group->isB2b ? 1 : 0,
                $group->requiresApproval ? 1 : 0,
                $group->defaultNetDays,
                $group->sortOrder,
                $group->status,
            ]
        );
    }

    public function update(CustomerGroup $group): void
    {
        if ($group->isDefault) {
            $this->clearDefault($group->id);
        }

        $this->db->execute(
            "UPDATE `commerce_customer_groups`
                SET `slug` = ?, `name` = ?, `description` = ?, `is_default` = ?,
                    `is_b2b` = ?, `requires_approval` = ?, `default_net_days` = ?,
                    `sort_order` = ?, `status` = ?
              WHERE `id` = ? AND `store_id` = ?",
            [
                $group->slug,
                $group->name,
                $group->description,
                $group->isDefault ? 1 : 0,
                $group->isB2b ? 1 : 0,
                $group->requiresApproval ? 1 : 0,
                $group->defaultNetDays,
                $group->sortOrder,
                $group->status,
                $group->id,
                $this->storeId(),
            ]
        );
    }

    public function archive(int $id): void
    {
        // Never archive the default group
        $group = $this->findById($id);
        if ($group !== null && $group->isDefault) {
            throw new \InvalidArgumentException('Cannot archive the default group. Set another group as default first.');
        }

        $this->db->execute(
            "UPDATE `commerce_customer_groups` SET `status` = 'archived'
              WHERE `id` = ? AND `store_id` = ?",
            [$id, $this->storeId()]
        );
    }

    public function restore(int $id): void
    {
        $this->db->execute(
            "UPDATE `commerce_customer_groups` SET `status` = 'active'
              WHERE `id` = ? AND `store_id` = ?",
            [$id, $this->storeId()]
        );
    }

    private function clearDefault(?int $excludeId = null): void
    {
        $sql = "UPDATE `commerce_customer_groups` SET `is_default` = 0 WHERE `store_id` = ?";
        $params = [$this->storeId()];

        if ($excludeId !== null) {
            $sql .= " AND `id` <> ?";
            $params[] = $excludeId;
        }

        $this->db->execute($sql, $params);
    }

    public function count(): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_customer_groups`
              WHERE `store_id` = ? AND `status` = 'active'",
            [$this->storeId()]
        );
    }
}
