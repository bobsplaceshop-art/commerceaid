<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

/**
 * Reads and writes commerce_webhook_events.
 *
 * Every verified webhook from every payment provider is
 * recorded here. The row is the idempotency guard: a
 * webhook delivered twice is stored once and processed
 * once. The unique key on (provider_key, event_id)
 * enforces the deduplication at the database level.
 *
 * The webhook controller checks existsByProviderEvent()
 * before calling the provider's handleWebhook(). A
 * duplicate delivery is a no-op: the row already exists,
 * the handler does not run again.
 *
 * Stage 010.
 */
final class WebhookEventRepository extends CommerceRepository
{
    /**
     * Insert a new webhook event row. Returns the new id.
     *
     * @param array{
     *     provider_key: string,
     *     event_id: string,
     *     event_type: string,
     *     order_id: ?int,
     *     payload_json: string
     * } $data
     */
    public function record(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO `commerce_webhook_events`
                (`store_id`, `provider_key`, `event_id`,
                 `event_type`, `order_id`, `payload_json`)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $this->storeId(),
                $data['provider_key'],
                $data['event_id'],
                $data['event_type'],
                $data['order_id'],
                $data['payload_json'],
            ]
        );
    }

    /**
     * Whether an event with this (provider_key, event_id)
     * pair already exists for the current store.
     */
    public function existsByProviderEvent(string $providerKey, string $eventId): bool
    {
        if ($providerKey === '' || $eventId === '') {
            return false;
        }
        $row = $this->db->selectOne(
            "SELECT `id` FROM `commerce_webhook_events`
              WHERE `store_id` = ? AND `provider_key` = ? AND `event_id` = ?
              LIMIT 1",
            [$this->storeId(), $providerKey, $eventId]
        );
        return $row !== null;
    }

    /**
     * Read one event by id.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return $this->db->selectOne(
            "SELECT * FROM `commerce_webhook_events`
              WHERE `store_id` = ? AND `id` = ?
              LIMIT 1",
            [$this->storeId(), $id]
        );
    }

    /**
     * Attach the resolved order id to an event row.
     *
     * The row is created with order_id = NULL because the
     * controller does not know the order id until the
     * provider's handleWebhook() reads the payload and
     * returns it. This method backfills the row.
     */
    public function backfillOrderId(int $id, int $orderId): void
    {
        if ($id <= 0 || $orderId <= 0) {
            return;
        }
        $this->db->execute(
            "UPDATE `commerce_webhook_events`
                SET `order_id` = ?
              WHERE `store_id` = ? AND `id` = ?",
            [$orderId, $this->storeId(), $id]
        );
    }

    /**
     * Mark the row processed. Called by the webhook controller
     * after the provider's handleWebhook() returns. A row
     * whose processed_at stays NULL is a webhook that arrived
     * but did not finish its handler - a finding to surface
     * in the admin dashboard (Session 5).
     */
    public function markProcessed(int $id): void
    {
        if ($id <= 0) {
            return;
        }
        $this->db->execute(
            "UPDATE `commerce_webhook_events`
                SET `processed_at` = NOW()
              WHERE `store_id` = ? AND `id` = ?",
            [$this->storeId(), $id]
        );
    }
}
