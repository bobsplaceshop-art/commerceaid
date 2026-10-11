<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\EmailCampaign;

/**
 * Email campaigns.
 *
 * See docs/stages/008i_newsletter-plan.md.
 */
final class EmailCampaignRepository extends CommerceRepository
{
    public function findById(int $id): ?EmailCampaign
    {
        if ($id <= 0) return null;

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_email_campaigns`
              WHERE `store_id` = ? AND `id` = ? LIMIT 1",
            [$this->storeId(), $id]
        );

        return $row !== null ? EmailCampaign::fromRow($row) : null;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items: array<int,EmailCampaign>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset  = ($page - 1) * $perPage;

        $where  = ["`store_id` = ?"];
        $params = [$this->storeId()];

        if (!empty($filters['status'])) {
            $where[]  = "`status` = ?";
            $params[] = (string) $filters['status'];
        }

        if (!empty($filters['q'])) {
            $where[]  = "`subject` LIKE ?";
            $params[] = '%' . $filters['q'] . '%';
        }

        $whereSql = implode(' AND ', $where);

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_email_campaigns` WHERE {$whereSql}",
            $params
        );

        $rows = $this->db->select(
            "SELECT * FROM `commerce_email_campaigns`
              WHERE {$whereSql}
              ORDER BY `created_at` DESC, `id` DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items'    => array_map(fn ($r) => EmailCampaign::fromRow($r), $rows),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_email_campaigns`
              WHERE `store_id` = ? AND `status` = ?",
            [$this->storeId(), $status]
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
            "INSERT INTO `commerce_email_campaigns` ({$columnList}) VALUES ({$placeholders})",
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
            "UPDATE `commerce_email_campaigns`
                SET " . implode(', ', $sets) . "
              WHERE `store_id` = ? AND `id` = ?",
            $params
        );
    }

    public function markSending(int $id): void
    {
        $this->db->execute(
            "UPDATE `commerce_email_campaigns`
                SET `status` = 'sending', `updated_at` = NOW()
              WHERE `store_id` = ? AND `id` = ? AND `status` IN ('draft','cancelled')",
            [$this->storeId(), $id]
        );
    }

    public function markSent(int $id): void
    {
        $this->db->execute(
            "UPDATE `commerce_email_campaigns`
                SET `status` = 'sent', `sent_at` = NOW(), `updated_at` = NOW()
              WHERE `store_id` = ? AND `id` = ?",
            [$this->storeId(), $id]
        );
    }

    public function delete(int $id): void
    {
        if ($id <= 0) return;

        // Only draft or cancelled campaigns can be deleted.
        $this->db->execute(
            "DELETE FROM `commerce_email_campaigns`
              WHERE `store_id` = ? AND `id` = ? AND `status` IN ('draft','cancelled')",
            [$this->storeId(), $id]
        );
    }
}
