<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

/**
 * Subscriber <-> list membership.
 *
 * See docs/stages/008i_newsletter-plan.md.
 */
final class SubscriberListMemberRepository extends CommerceRepository
{
    public function addMember(int $subscriberId, int $listId): void
    {
        if ($subscriberId <= 0 || $listId <= 0) return;

        // INSERT IGNORE so a repeat call is a no-op thanks to
        // the unique key on (subscriber_id, list_id).
        $this->db->execute(
            "INSERT IGNORE INTO `commerce_subscriber_list_members`
                (`store_id`, `subscriber_id`, `list_id`, `joined_at`)
             VALUES (?, ?, ?, NOW())",
            [$this->storeId(), $subscriberId, $listId]
        );
    }

    public function removeMember(int $subscriberId, int $listId): void
    {
        if ($subscriberId <= 0 || $listId <= 0) return;

        $this->db->execute(
            "DELETE FROM `commerce_subscriber_list_members`
              WHERE `store_id` = ? AND `subscriber_id` = ? AND `list_id` = ?",
            [$this->storeId(), $subscriberId, $listId]
        );
    }

    /**
     * Remove a subscriber from every list. Used when the
     * subscriber unsubscribes globally or is suppressed.
     */
    public function removeAllForSubscriber(int $subscriberId): void
    {
        if ($subscriberId <= 0) return;

        $this->db->execute(
            "DELETE FROM `commerce_subscriber_list_members`
              WHERE `store_id` = ? AND `subscriber_id` = ?",
            [$this->storeId(), $subscriberId]
        );
    }

    /**
     * @return array<int,int> list ids
     */
    public function listIdsForSubscriber(int $subscriberId): array
    {
        if ($subscriberId <= 0) return [];

        $rows = $this->db->select(
            "SELECT `list_id`
               FROM `commerce_subscriber_list_members`
              WHERE `store_id` = ? AND `subscriber_id` = ?",
            [$this->storeId(), $subscriberId]
        );

        return array_map(fn ($r) => (int) $r['list_id'], $rows);
    }
}
