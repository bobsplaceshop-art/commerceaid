<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

/**
 * Reads and writes commerce_mpesa_requests.
 *
 * One row per STK push initiated. The row carries the
 * CheckoutRequestID Safaricom returned, the order it was
 * created for, and the pending / completed / failed state.
 *
 * The provider (MpesaProvider) writes the row from charge().
 * The webhook controller reads it from verifyWebhook() to
 * confirm the callback's CheckoutRequestID matches a
 * request this store initiated. The handler updates the
 * row from handleWebhook() when Safaricom reports a result.
 *
 * M-Pesa does not sign its callbacks. This table is the
 * verification mechanism; a callback whose
 * CheckoutRequestID is not in this table is a forgery or a
 * replay and is refused.
 *
 * The cleanup cron (bin/cleanup-mpesa-requests.php) calls
 * deleteStale() hourly. Rows stay at most 24 hours past
 * their creation while still pending.
 *
 * Stage 010, sub-stage 010.1.
 */
final class MpesaRequestRepository extends CommerceRepository
{
    /**
     * Insert a pending request row. Returns the new id.
     *
     * Called by MpesaProvider::charge() after the STK push
     * call returns a CheckoutRequestID. The row is the
     * record that a request is in flight.
     *
     * @param array{
     *     order_id: int,
     *     checkout_request_id: string,
     *     merchant_request_id: ?string,
     *     phone_number: string,
     *     amount: float
     * } $data
     */
    public function record(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO `commerce_mpesa_requests`
                (`store_id`, `order_id`, `checkout_request_id`,
                 `merchant_request_id`, `phone_number`, `amount`,
                 `status`)
             VALUES (?, ?, ?, ?, ?, ?, 'pending')",
            [
                $this->storeId(),
                (int) $data['order_id'],
                (string) $data['checkout_request_id'],
                $data['merchant_request_id'] ?? null,
                (string) $data['phone_number'],
                (float) $data['amount'],
            ]
        );
    }

    /**
     * Look up a request by its Safaricom-issued
     * CheckoutRequestID. Returns null when the id is unknown
     * for this store.
     *
     * This is the verification path. A callback whose
     * CheckoutRequestID is not in this table is refused.
     *
     * @return array<string,mixed>|null
     */
    public function findByCheckoutRequestId(string $checkoutRequestId): ?array
    {
        if ($checkoutRequestId === '') {
            return null;
        }
        return $this->db->selectOne(
            "SELECT * FROM `commerce_mpesa_requests`
              WHERE `store_id` = ? AND `checkout_request_id` = ?
              LIMIT 1",
            [$this->storeId(), $checkoutRequestId]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `commerce_mpesa_requests`
              WHERE `store_id` = ? AND `id` = ?
              LIMIT 1",
            [$this->storeId(), $id]
        );
    }

    /**
     * Every pending request for an order. Used by the
     * payment step's retry UI to show in-flight attempts.
     *
     * @return array<int, array<string,mixed>>
     */
    public function listPendingForOrder(int $orderId): array
    {
        return $this->db->select(
            "SELECT * FROM `commerce_mpesa_requests`
              WHERE `store_id` = ? AND `order_id` = ?
                AND `status` = 'pending'
              ORDER BY `id` DESC",
            [$this->storeId(), $orderId]
        );
    }

    /**
     * Mark a request completed. Called by
     * MpesaProvider::handleWebhook() when Safaricom
     * reports ResultCode 0.
     *
     * Stores the M-Pesa receipt number alongside the
     * result code so the order's payment_reference can
     * be written from the same row.
     */
    public function markCompleted(
        string $checkoutRequestId,
        int $resultCode,
        ?string $mpesaReceipt
    ): void {
        $this->db->execute(
            "UPDATE `commerce_mpesa_requests`
                SET `status` = 'completed',
                    `result_code` = ?,
                    `mpesa_receipt` = ?
              WHERE `store_id` = ? AND `checkout_request_id` = ?",
            [
                $resultCode,
                $mpesaReceipt,
                $this->storeId(),
                $checkoutRequestId,
            ]
        );
    }

    /**
     * Mark a request failed. Called by
     * MpesaProvider::handleWebhook() when Safaricom
     * reports a non-zero ResultCode (cancelled, wrong PIN,
     * timeout, insufficient funds).
     */
    public function markFailed(
        string $checkoutRequestId,
        int $resultCode,
        ?string $resultDescription
    ): void {
        $this->db->execute(
            "UPDATE `commerce_mpesa_requests`
                SET `status` = 'failed',
                    `result_code` = ?,
                    `result_description` = ?
              WHERE `store_id` = ? AND `checkout_request_id` = ?",
            [
                $resultCode,
                $resultDescription,
                $this->storeId(),
                $checkoutRequestId,
            ]
        );
    }

    /**
     * Delete every pending row older than the cutoff.
     * Called by bin/cleanup-mpesa-requests.php.
     *
     * Rows in 'completed' or 'failed' state are never
     * deleted by this method. They stay for audit. If the
     * table needs pruning, that is a separate concern and a
     * separate query.
     *
     * @return int  the number of rows deleted
     */
    public function deleteStale(int $hours = 24): int
    {
        return $this->db->execute(
            "DELETE FROM `commerce_mpesa_requests`
              WHERE `store_id` = ?
                AND `status` = 'pending'
                AND `created_at` < (NOW() - INTERVAL ? HOUR)",
            [$this->storeId(), $hours]
        );
    }
}
