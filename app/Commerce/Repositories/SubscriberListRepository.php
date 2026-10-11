<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\SubscriberList;

/**
 * Named subscriber lists.
 *
 * See docs/stages/008i_newsletter-plan.md.
 */
final class SubscriberListRepository extends CommerceRepository
{
    public function findById(int $id): ?SubscriberList
    {
        if ($id <= 0) return null;

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_subscriber_lists`
              WHERE `store_id` = ? AND `id` = ? LIMIT 1",
            [$this->storeId(), $id]
        );

        return $row !== null ? SubscriberList::fromRow($row) : null;
    }

    public function findBySlug(string $slug): ?SubscriberList
    {
        if ($slug === '') return null;

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_subscriber_lists`
              WHERE `store_id` = ? AND `slug` = ? LIMIT 1",
            [$this->storeId(), $slug]
        );

        return $row !== null ? SubscriberList::fromRow($row) : null;
    }

    public function defaultForStore(): ?SubscriberList
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_subscriber_lists`
              WHERE `store_id` = ? AND `is_default` = 1 LIMIT 1",
            [$this->storeId()]
        );

        return $row !== null ? SubscriberList::fromRow($row) : null;
    }

    /**
     * @return array<int,SubscriberList>
     */
    public function listForStore(): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_subscriber_lists`
              WHERE `store_id` = ?
              ORDER BY `is_default` DESC, `name` ASC, `id` ASC",
            [$this->storeId()]
        );

        return array_map(fn ($r) => SubscriberList::fromRow($r), $rows);
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql    = "SELECT COUNT(*) FROM `commerce_subscriber_lists`
                    WHERE `store_id` = ? AND `slug` = ?";
        $params = [$this->storeId(), $slug];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
        }

        return ((int) $this->db->scalar($sql, $params)) > 0;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        $data = array_merge(['store_id' => $this->storeId()], $data);

        $columns      = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList   = '`' . implode('`, `', $columns) . '`';

        return (int) $this->db->insert(
            "INSERT INTO `commerce_subscriber_lists` ({$columnList}) VALUES ({$placeholders})",
            array_values($data)
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): void
    {
        if ($id <= 0 || $data === []) return;

        $sets   = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[]   = "`{$col}` = ?";
            $params[] = $val;
        }
        $params[] = $this->storeId();
        $params[] = $id;

        $this->db->execute(
            "UPDATE `commerce_subscriber_lists`
                SET " . implode(', ', $sets) . "
              WHERE `store_id` = ? AND `id` = ?",
            $params
        );
    }

    public function delete(int $id): void
    {
        if ($id <= 0) return;

        // The default list cannot be deleted. The caller is
        // expected to have already prevented this; we guard
        // here as a defense in depth.
        $this->db->execute(
            "DELETE FROM `commerce_subscriber_lists`
              WHERE `store_id` = ? AND `id` = ? AND `is_default` = 0",
            [$this->storeId(), $id]
        );
    }

    public function memberCount(int $listId): int
    {
        if ($listId <= 0) return 0;

        return (int) $this->db->scalar(
            "SELECT COUNT(*)
               FROM `commerce_subscriber_list_members`
              WHERE `store_id` = ? AND `list_id` = ?",
            [$this->storeId(), $listId]
        );
    }
}
