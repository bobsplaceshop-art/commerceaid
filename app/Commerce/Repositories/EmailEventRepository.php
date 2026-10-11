<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\EmailEvent;

/**
 * The email event log.
 *
 * See docs/stages/008i_newsletter-plan.md.
 */
final class EmailEventRepository extends CommerceRepository
{
    /**
     * @param array<string,mixed>|null $data
     */
    public function record(
        int $campaignId,
        ?int $subscriberId,
        string $eventType,
        ?array $data = null
    ): void {
        $json = $data !== null
            ? json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : null;

        $this->db->execute(
            "INSERT INTO `commerce_email_events`
                (`store_id`, `campaign_id`, `subscriber_id`,
                 `event_type`, `event_data`)
             VALUES (?, ?, ?, ?, ?)",
            [
                $this->storeId(),
                $campaignId > 0 ? $campaignId : null,
                $subscriberId,
                $eventType,
                $json === false ? null : $json,
            ]
        );
    }

    /**
     * @return array<int,EmailEvent>
     */
    public function listForCampaign(int $campaignId, int $limit = 100): array
    {
        if ($campaignId <= 0) return [];

        $limit = min(1000, max(1, $limit));

        $rows = $this->db->select(
            "SELECT * FROM `commerce_email_events`
              WHERE `store_id` = ? AND `campaign_id` = ?
              ORDER BY `created_at` DESC, `id` DESC
              LIMIT {$limit}",
            [$this->storeId(), $campaignId]
        );

        return array_map(fn ($r) => EmailEvent::fromRow($r), $rows);
    }

    public function countByTypeForCampaign(int $campaignId, string $eventType): int
    {
        if ($campaignId <= 0) return 0;

        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_email_events`
              WHERE `store_id` = ? AND `campaign_id` = ? AND `event_type` = ?",
            [$this->storeId(), $campaignId, $eventType]
        );
    }

    /**
     * @return array<int,EmailEvent>
     */
    public function listForSubscriber(int $subscriberId, int $limit = 100): array
    {
        if ($subscriberId <= 0) return [];

        $limit = min(1000, max(1, $limit));

        $rows = $this->db->select(
            "SELECT * FROM `commerce_email_events`
              WHERE `store_id` = ? AND `subscriber_id` = ?
              ORDER BY `created_at` DESC, `id` DESC
              LIMIT {$limit}",
            [$this->storeId(), $subscriberId]
        );

        return array_map(fn ($r) => EmailEvent::fromRow($r), $rows);
    }
}
