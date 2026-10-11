<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\EmailQueueEntry;

/**
 * The pending-send queue.
 *
 * See docs/stages/008i_newsletter-plan.md for the queue
 * lifecycle and the retry policy.
 */
final class EmailQueueRepository extends CommerceRepository
{
    /**
     * Enqueue one row per recipient. Called inside the
     * campaign send transaction. Returns the number of rows
     * inserted.
     *
     * @param array<int,array{
     *   campaign_id:int, subscriber_id:int,
     *   to_email:string, to_name:?string, unsubscribe_token:string
     * }> $entries
     */
    public function enqueue(array $entries): int
    {
        if ($entries === []) return 0;

        $count = 0;
        foreach ($entries as $entry) {
            $this->db->execute(
                "INSERT INTO `commerce_email_queue`
                    (`store_id`, `campaign_id`, `subscriber_id`,
                     `to_email`, `to_name`, `unsubscribe_token`,
                     `status`, `scheduled_at`)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())",
                [
                    $this->storeId(),
                    (int) $entry['campaign_id'],
                    (int) $entry['subscriber_id'],
                    (string) $entry['to_email'],
                    $entry['to_name'] ?? null,
                    (string) $entry['unsubscribe_token'],
                ]
            );
            $count++;
        }
        return $count;
    }

    /**
     * Claim up to $limit rows for sending.
     *
     * Uses SELECT ... FOR UPDATE inside a transaction to
     * prevent two workers claiming the same row. For a
     * single-worker deployment this is defensive; for
     * multiple workers it is required.
     *
     * @return array<int,EmailQueueEntry>
     */
    public function claimBatch(int $storeId, int $limit = 100): array
    {
        $limit = min(500, max(1, $limit));

        $this->db->beginTransaction();
        try {
            $rows = $this->db->select(
                "SELECT * FROM `commerce_email_queue`
                  WHERE `store_id` = ?
                    AND `status` = 'pending'
                    AND `scheduled_at` <= NOW()
                  ORDER BY `id` ASC
                  LIMIT {$limit}
                  FOR UPDATE",
                [$storeId]
            );

            if ($rows === []) {
                $this->db->commit();
                return [];
            }

            $ids = array_map(fn ($r) => (int) $r['id'], $rows);
            $placeholders = implode(', ', array_fill(0, count($ids), '?'));

            $params = array_merge([$storeId], $ids);
            $this->db->execute(
                "UPDATE `commerce_email_queue`
                    SET `status` = 'sending'
                  WHERE `store_id` = ?
                    AND `id` IN ({$placeholders})",
                $params
            );

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return array_map(fn ($r) => EmailQueueEntry::fromRow($r), $rows);
    }

    public function markSent(int $id): void
    {
        $this->db->execute(
            "UPDATE `commerce_email_queue`
                SET `status` = 'sent', `sent_at` = NOW()
              WHERE `store_id` = ? AND `id` = ?",
            [$this->storeId(), $id]
        );
    }

    /**
     * Record a failed attempt. Backs off exponentially
     * (attempts * 60 seconds, capped at 1 hour) unless the
     * attempts count has reached the max, in which case the
     * row moves to 'failed' and never retries.
     */
    public function markAttemptFailed(int $id, string $error, int $maxAttempts = 5): void
    {
        $entry = $this->findById($id);
        if ($entry === null) return;

        $nextAttempts = $entry->attempts + 1;

        if ($nextAttempts >= $maxAttempts) {
            $this->db->execute(
                "UPDATE `commerce_email_queue`
                    SET `status` = 'failed',
                        `attempts` = ?,
                        `last_error` = ?
                  WHERE `store_id` = ? AND `id` = ?",
                [$nextAttempts, mb_substr($error, 0, 500), $this->storeId(), $id]
            );
            return;
        }

        $backoffSeconds = min(3600, $nextAttempts * 60);

        $this->db->execute(
            "UPDATE `commerce_email_queue`
                SET `status` = 'pending',
                    `attempts` = ?,
                    `last_error` = ?,
                    `scheduled_at` = DATE_ADD(NOW(), INTERVAL ? SECOND)
              WHERE `store_id` = ? AND `id` = ?",
            [$nextAttempts, mb_substr($error, 0, 500), $backoffSeconds, $this->storeId(), $id]
        );
    }

    public function findById(int $id): ?EmailQueueEntry
    {
        if ($id <= 0) return null;

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_email_queue`
              WHERE `store_id` = ? AND `id` = ? LIMIT 1",
            [$this->storeId(), $id]
        );

        return $row !== null ? EmailQueueEntry::fromRow($row) : null;
    }

    public function countPending(int $storeId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_email_queue`
              WHERE `store_id` = ? AND `status` = 'pending'",
            [$storeId]
        );
    }

    public function countByStatusForCampaign(int $campaignId, string $status): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_email_queue`
              WHERE `store_id` = ? AND `campaign_id` = ? AND `status` = ?",
            [$this->storeId(), $campaignId, $status]
        );
    }
}
