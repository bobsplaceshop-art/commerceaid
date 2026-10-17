<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

/**
 * Reads and writes commerce_flutterwave_requests.
 *
 * One row per Flutterwave payment submitted. The row
 * carries the tx_ref (the merchant reference the
 * platform supplies), the Flutterwave transaction_id
 * (written when the verify call returns it), the order
 * it was created for, and the pending / completed /
 * failed state.
 *
 * The provider (FlutterwaveProvider) writes the row
 * from charge(). The webhook controller reads it from
 * verifyWebhook() after checking the verif-hash header,
 * then calls GET /transactions/{id}/verify and updates
 * the row with the transaction_id when the payment
 * succeeds.
 *
 * Flutterwave's webhook uses a plain shared secret in
 * the verif-hash header. The table is the second
 * security layer: a webhook whose tx_ref is not in
 * this table is refused even if the header matched
 * (an attacker with the secret hash cannot forge a
 * transaction id for an order the store never
 * initiated).
 *
 * The cleanup cron (bin/cleanup-flutterwave-requests.php)
 * calls deleteStale(). Rows stay at most 24 hours past
 * their creation while still pending.
 *
 * Stage 010, sub-stage 010.3.
 */
final class FlutterwaveRequestRepository extends CommerceRepository
{
    /**
     * Insert a pending request row. Returns the new id.
     *
     * Called by FlutterwaveProvider::charge() after
     * POST /payments returns the hosted page URL. The
     * tx_ref is the value the platform supplied in the
     * request; the transaction_id is not yet known.
     *
     * @param array{
     *     order_id: int,
     *     tx_ref: string,
     *     amount: float,
     *     currency_code: string
     * } $data
     */
    public function record(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO `commerce_flutterwave_requests`
                (`store_id`, `order_id`, `tx_ref`, `amount`,
                 `currency_code`, `status`)
             VALUES (?, ?, ?, ?, ?, 'pending')",
            [
                $this->storeId(),
                (int) $data['order_id'],
                (string) $data['tx_ref'],
                (float) $data['amount'],
                strtoupper((string) $data['currency_code']),
            ]
        );
    }

    /**
     * Look up a request by its tx_ref. Returns null when
     * the ref is unknown for this store.
     *
     * This is the verification path. A webhook whose
     * tx_ref is not in this table is refused.
     *
     * @return array<string,mixed>|null
     */
    public function findByTxRef(string $txRef): ?array
    {
        if ($txRef === '') {
            return null;
        }
        return $this->db->selectOne(
            "SELECT * FROM `commerce_flutterwave_requests`
              WHERE `store_id` = ? AND `tx_ref` = ?
              LIMIT 1",
            [$this->storeId(), $txRef]
        );
    }

    /**
     * Write the Flutterwave transaction_id onto a row.
     *
     * Called after GET /transactions/{id}/verify returns
     * the transaction id, or after the webhook body
     * carries data.id. Idempotent: a second write with
     * the same transaction id is a no-op.
     */
    public function setTransactionId(string $txRef, string $transactionId): void
    {
        if ($txRef === '' || $transactionId === '') {
            return;
        }
        $this->db->execute(
            "UPDATE `commerce_flutterwave_requests`
                SET `transaction_id` = ?
              WHERE `store_id` = ? AND `tx_ref` = ?",
            [$transactionId, $this->storeId(), $txRef]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `commerce_flutterwave_requests`
              WHERE `store_id` = ? AND `id` = ?
              LIMIT 1",
            [$this->storeId(), $id]
        );
    }

    /**
     * Every pending request for an order. Used by the
     * abandoned-order cleanup and the admin order detail.
     *
     * @return array<int, array<string,mixed>>
     */
    public function listPendingForOrder(int $orderId): array
    {
        return $this->db->select(
            "SELECT * FROM `commerce_flutterwave_requests`
              WHERE `store_id` = ? AND `order_id` = ?
                AND `status` = 'pending'
              ORDER BY `id` DESC",
            [$this->storeId(), $orderId]
        );
    }

    /**
     * Mark a request completed. Called by
     * FlutterwaveProvider::handleWebhook() when
     * GET /verify reports status 'successful'.
     */
    public function markCompleted(string $txRef, string $transactionId): void
    {
        $this->db->execute(
            "UPDATE `commerce_flutterwave_requests`
                SET `status` = 'completed',
                    `transaction_id` = ?
              WHERE `store_id` = ? AND `tx_ref` = ?",
            [$transactionId, $this->storeId(), $txRef]
        );
    }

    /**
     * Mark a request failed. Called by
     * FlutterwaveProvider::handleWebhook() when
     * GET /verify reports a non-successful status, or by
     * the abandoned-order cleanup when the order is
     * cancelled before payment completes.
     */
    public function markFailed(string $txRef, ?string $failureReason): void
    {
        $this->db->execute(
            "UPDATE `commerce_flutterwave_requests`
                SET `status` = 'failed',
                    `failure_reason` = ?
              WHERE `store_id` = ? AND `tx_ref` = ?",
            [$failureReason, $this->storeId(), $txRef]
        );
    }

    /**
     * Delete every pending row older than the cutoff.
     * Called by bin/cleanup-flutterwave-requests.php.
     *
     * Rows in 'completed' or 'failed' state are never
     * deleted by this method. They stay for audit.
     *
     * @return int  the number of rows deleted
     */
    public function deleteStale(int $hours = 24): int
    {
        return $this->db->execute(
            "DELETE FROM `commerce_flutterwave_requests`
              WHERE `store_id` = ?
                AND `status` = 'pending'
                AND `created_at` < (NOW() - INTERVAL ? HOUR)",
            [$this->storeId(), $hours]
        );
    }
}
