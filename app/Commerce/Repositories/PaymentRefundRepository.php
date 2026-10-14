<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

/**
 * Reads and writes commerce_payment_refunds.
 *
 * Every refund attempt is one row. The order's refunded_total
 * is derived from the sum of succeeded rows; this repository
 * provides the query that re-derives it.
 *
 * The refund row is the source of truth for the money movement.
 * The provider's response fills provider_refund_id and
 * transitions status from pending to succeeded or failed.
 *
 * Stages 010. The writer is PaymentRefundService; the reader
 * for the retry cron is bin/retry-refunds.php.
 */
final class PaymentRefundRepository extends CommerceRepository
{
    /**
     * Insert a new refund row. Returns the new id.
     *
     * The caller has already validated the fields. This method
     * does not check for a duplicate idempotency_key; the
     * caller should call findByIdempotencyKey() first when
     * idempotency matters.
     *
     * @param array{
     *     order_id: int,
     *     return_request_id: ?int,
     *     provider_key: string,
     *     provider_payment_id: ?string,
     *     amount: float,
     *     currency_code: string,
     *     reason: ?string,
     *     idempotency_key: string
     * } $data
     */
    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO `commerce_payment_refunds`
                (`store_id`, `order_id`, `return_request_id`,
                 `provider_key`, `provider_payment_id`,
                 `amount`, `currency_code`, `status`,
                 `reason`, `idempotency_key`)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)",
            [
                $this->storeId(),
                $data['order_id'],
                $data['return_request_id'],
                $data['provider_key'],
                $data['provider_payment_id'],
                $data['amount'],
                $data['currency_code'],
                $data['reason'],
                $data['idempotency_key'],
            ]
        );
    }

    /**
     * Find a refund by id, scoped to the current store.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return $this->db->selectOne(
            "SELECT * FROM `commerce_payment_refunds`
              WHERE `store_id` = ? AND `id` = ?
              LIMIT 1",
            [$this->storeId(), $id]
        );
    }

    /**
     * Find a refund by its idempotency key. Used by the retry
     * cron and the wrapper to avoid double-inserting a row
     * when the same logical refund attempt is replayed.
     *
     * @return array<string, mixed>|null
     */
    public function findByIdempotencyKey(string $key): ?array
    {
        if ($key === '') {
            return null;
        }
        return $this->db->selectOne(
            "SELECT * FROM `commerce_payment_refunds`
              WHERE `store_id` = ? AND `idempotency_key` = ?
              LIMIT 1",
            [$this->storeId(), $key]
        );
    }

    /**
     * Every refund row for an order, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForOrder(int $orderId): array
    {
        if ($orderId <= 0) {
            return [];
        }
        return $this->db->select(
            "SELECT * FROM `commerce_payment_refunds`
              WHERE `store_id` = ? AND `order_id` = ?
              ORDER BY `id` DESC",
            [$this->storeId(), $orderId]
        );
    }

    /**
     * Every refund row for a return request, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForReturnRequest(int $requestId): array
    {
        if ($requestId <= 0) {
            return [];
        }
        return $this->db->select(
            "SELECT * FROM `commerce_payment_refunds`
              WHERE `store_id` = ? AND `return_request_id` = ?
              ORDER BY `id` DESC",
            [$this->storeId(), $requestId]
        );
    }

    /**
     * Failed refunds with fewer than the maximum attempts.
     * The retry cron reads this list and re-dispatches each.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPendingForRetry(int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));
        return $this->db->select(
            "SELECT * FROM `commerce_payment_refunds`
              WHERE `store_id` = ? AND `status` = 'failed'
                AND `attempts` < 5
              ORDER BY `id` ASC
              LIMIT {$limit}",
            [$this->storeId()]
        );
    }

    /**
     * Transition a refund row from pending to succeeded.
     * Fills provider_refund_id and succeeded_at. Increments
     * attempts (each attempt — successful or not — is one
     * recorded try).
     */
    public function markSucceeded(int $id, string $providerRefundId): void
    {
        if ($id <= 0) {
            return;
        }
        $this->db->execute(
            "UPDATE `commerce_payment_refunds`
                SET `status` = 'succeeded',
                    `provider_refund_id` = ?,
                    `succeeded_at` = NOW(),
                    `last_attempt_at` = NOW(),
                    `attempts` = `attempts` + 1
              WHERE `store_id` = ? AND `id` = ?",
            [$providerRefundId, $this->storeId(), $id]
        );
    }

    /**
     * Transition a refund row from pending to failed.
     * Stores the provider's error message. Increments attempts.
     * The retry cron picks the row up while attempts < 5.
     */
    public function markFailed(int $id, string $failureMessage): void
    {
        if ($id <= 0) {
            return;
        }
        $this->db->execute(
            "UPDATE `commerce_payment_refunds`
                SET `status` = 'failed',
                    `failure_message` = ?,
                    `last_attempt_at` = NOW(),
                    `attempts` = `attempts` + 1
              WHERE `store_id` = ? AND `id` = ?",
            [$failureMessage, $this->storeId(), $id]
        );
    }

    /**
     * The sum of succeeded refunds for an order. Used by
     * PaymentRefundService to re-derive the order's
     * refunded_total and payment_status after a successful
     * refund.
     */
    public function sumSucceededForOrder(int $orderId): float
    {
        if ($orderId <= 0) {
            return 0.0;
        }
        $row = $this->db->selectOne(
            "SELECT COALESCE(SUM(`amount`), 0) AS `total`
               FROM `commerce_payment_refunds`
              WHERE `store_id` = ? AND `order_id` = ?
                AND `status` = 'succeeded'",
            [$this->storeId(), $orderId]
        );
        return isset($row['total']) ? round((float) $row['total'], 4) : 0.0;
    }
}
