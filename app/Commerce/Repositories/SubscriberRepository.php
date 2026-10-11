<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\Subscriber;

/**
 * Newsletter subscribers.
 *
 * Every query is scoped to the current store via
 * CommerceRepository::storeId().
 *
 * See docs/stages/008i_newsletter-plan.md.
 */
final class SubscriberRepository extends CommerceRepository
{
    public function findById(int $id): ?Subscriber
    {
        if ($id <= 0) return null;

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_subscribers`
              WHERE `store_id` = ? AND `id` = ? LIMIT 1",
            [$this->storeId(), $id]
        );

        return $row !== null ? Subscriber::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?Subscriber
    {
        $email = trim($email);
        if ($email === '') return null;

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_subscribers`
              WHERE `store_id` = ? AND `email` = ? LIMIT 1",
            [$this->storeId(), $email]
        );

        return $row !== null ? Subscriber::fromRow($row) : null;
    }

    public function findByConfirmationToken(string $token): ?Subscriber
    {
        if ($token === '') return null;

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_subscribers`
              WHERE `store_id` = ? AND `confirmation_token` = ? LIMIT 1",
            [$this->storeId(), $token]
        );

        return $row !== null ? Subscriber::fromRow($row) : null;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items: array<int,Subscriber>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildFilters($filters);

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_subscribers` WHERE {$where}",
            $params
        );

        $rows = $this->db->select(
            "SELECT * FROM `commerce_subscribers`
              WHERE {$where}
              ORDER BY `created_at` DESC, `id` DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items'    => array_map(fn ($r) => Subscriber::fromRow($r), $rows),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_subscribers`
              WHERE `store_id` = ? AND `status` = ?",
            [$this->storeId(), $status]
        );
    }

    /**
     * All confirmed subscribers on a given list, ordered by id
     * so the campaign enqueue is deterministic.
     *
     * @return array<int,Subscriber>
     */
    public function confirmedOnList(int $listId): array
    {
        if ($listId <= 0) return [];

        $rows = $this->db->select(
            "SELECT s.*
               FROM `commerce_subscribers` s
               INNER JOIN `commerce_subscriber_list_members` m
                 ON m.`subscriber_id` = s.`id`
              WHERE s.`store_id` = ?
                AND m.`list_id` = ?
                AND s.`status` = 'confirmed'
              ORDER BY s.`id` ASC",
            [$this->storeId(), $listId]
        );

        return array_map(fn ($r) => Subscriber::fromRow($r), $rows);
    }

    public function countConfirmedOnList(int $listId): int
    {
        if ($listId <= 0) return 0;

        return (int) $this->db->scalar(
            "SELECT COUNT(*)
               FROM `commerce_subscribers` s
               INNER JOIN `commerce_subscriber_list_members` m
                 ON m.`subscriber_id` = s.`id`
              WHERE s.`store_id` = ?
                AND m.`list_id` = ?
                AND s.`status` = 'confirmed'",
            [$this->storeId(), $listId]
        );
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
            "INSERT INTO `commerce_subscribers` ({$columnList}) VALUES ({$placeholders})",
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
            "UPDATE `commerce_subscribers`
                SET " . implode(', ', $sets) . "
              WHERE `store_id` = ? AND `id` = ?",
            $params
        );
    }

    public function delete(int $id): void
    {
        if ($id <= 0) return;
        $this->db->execute(
            "DELETE FROM `commerce_subscribers`
              WHERE `store_id` = ? AND `id` = ?",
            [$this->storeId(), $id]
        );
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildFilters(array $filters): array
    {
        $where  = ["`store_id` = ?"];
        $params = [$this->storeId()];

        if (!empty($filters['status'])) {
            $where[]  = "`status` = ?";
            $params[] = (string) $filters['status'];
        }

        if (!empty($filters['source'])) {
            $where[]  = "`consent_source` = ?";
            $params[] = (string) $filters['source'];
        }

        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $where[]  = "(`email` LIKE ? OR `name` LIKE ?)";
            $params[] = $like;
            $params[] = $like;
        }

        return [implode(' AND ', $where), $params];
    }
}
