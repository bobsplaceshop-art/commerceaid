<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\Customer;
use CorporateAid\Commerce\Models\CustomerApprovalHistory;

/**
 * Customer repository.
 *
 * Tenant-scoped. Every query filters by store_id.
 *
 * The approval methods (create, approve, reject, resetToPending)
 * write to commerce_customer_approval_history in the same
 * transaction as the state change. If the history write fails, the
 * state change rolls back.
 *
 * History is append-only. Nothing here updates or deletes it.
 *
 * See docs/baseline/08-repositories.md for the pattern this file
 * follows.
 */
final class CustomerRepository extends CommerceRepository
{
    // =================================================================
    // Reads
    // =================================================================

    /**
     * @param array{q?:string, approval_status?:string, status?:string, group_id?:int} $filters
     * @return array{items: array<int, Customer>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildFilters($filters);

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_customers` c WHERE {$where}",
            $params
        );

        $rows = $this->db->select(
            "SELECT * FROM `commerce_customers` c
              WHERE {$where}
              ORDER BY c.`created_at` DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items'    => array_map(fn ($r) => Customer::fromRow($r), $rows),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /** @return array<int, Customer> */
    public function listPending(int $limit = 100): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_customers`
              WHERE `store_id` = ? AND `approval_status` = 'pending'
              ORDER BY `created_at` ASC
              LIMIT {$limit}",
            [$this->storeId()]
        );
        return array_map(fn ($r) => Customer::fromRow($r), $rows);
    }

    public function findById(int $id): ?Customer
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_customers`
              WHERE `id` = ? AND `store_id` = ?
              LIMIT 1",
            [$id, $this->storeId()]
        );
        return $row ? Customer::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?Customer
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_customers`
              WHERE `email` = ? AND `store_id` = ?
              LIMIT 1",
            [strtolower(trim($email)), $this->storeId()]
        );
        return $row ? Customer::fromRow($row) : null;
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM `commerce_customers`
                 WHERE `store_id` = ? AND `email` = ?";
        $params = [$this->storeId(), strtolower(trim($email))];

        if ($excludeId !== null) {
            $sql .= " AND `id` <> ?";
            $params[] = $excludeId;
        }

        return (int) $this->db->scalar($sql, $params) > 0;
    }

    public function count(): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_customers`
              WHERE `store_id` = ? AND `status` = 'active'",
            [$this->storeId()]
        );
    }

    public function countPending(): int
    {
        return $this->countByApprovalStatus('pending');
    }

    /**
     * Count customers for a given approval status, or total if
     * $status is null. Used by the filter tab badges.
     */
    public function countByApprovalStatus(?string $status = null): int
    {
        if ($status === null) {
            return (int) $this->db->scalar(
                "SELECT COUNT(*) FROM `commerce_customers`
                  WHERE `store_id` = ?",
                [$this->storeId()]
            );
        }

        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_customers`
              WHERE `store_id` = ? AND `approval_status` = ?",
            [$this->storeId(), $status]
        );
    }

    // =================================================================
    // Approval history (reads)
    // =================================================================

    /**
     * Full timeline for a single customer, newest first.
     *
     * @return array<int, CustomerApprovalHistory>
     */
    public function approvalHistory(int $customerId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        $rows = $this->db->select(
            "SELECT h.*, u.`name` AS actor_name
               FROM `commerce_customer_approval_history` h
               LEFT JOIN `core_users` u ON u.`id` = h.`actor_id`
              WHERE h.`store_id` = ? AND h.`customer_id` = ?
              ORDER BY h.`created_at` DESC, h.`id` DESC
              LIMIT {$limit}",
            [$this->storeId(), $customerId]
        );

        return array_map(fn ($r) => CustomerApprovalHistory::fromRow($r), $rows);
    }

    /**
     * Store-wide recent history. Useful for a "Recent activity" widget.
     *
     * @return array<int, CustomerApprovalHistory>
     */
    public function recentApprovalHistory(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        $rows = $this->db->select(
            "SELECT h.*, u.`name` AS actor_name
               FROM `commerce_customer_approval_history` h
               LEFT JOIN `core_users` u ON u.`id` = h.`actor_id`
              WHERE h.`store_id` = ?
              ORDER BY h.`created_at` DESC, h.`id` DESC
              LIMIT {$limit}",
            [$this->storeId()]
        );

        return array_map(fn ($r) => CustomerApprovalHistory::fromRow($r), $rows);
    }

    // =================================================================
    // Writes
    // =================================================================

    /**
     * Create a new customer.
     *
     * Writes an initial history row with from_status = NULL.
     * If the history write fails, the customer insert rolls back.
     */
    public function create(Customer $customer): int
    {
        $this->db->beginTransaction();

        try {
            $id = $this->db->insert(
                "INSERT INTO `commerce_customers`
                    (`store_id`, `email`, `password_hash`, `first_name`, `last_name`,
                     `phone`, `company`, `tax_id`, `date_of_birth`,
                     `email_verified_at`, `accepts_marketing`, `customer_group_id`,
                     `status`, `approval_status`, `approval_reason`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $this->storeId(),
                    strtolower(trim($customer->email)),
                    $customer->passwordHash,
                    $customer->firstName,
                    $customer->lastName,
                    $customer->phone,
                    $customer->company,
                    $customer->taxId,
                    $customer->dateOfBirth,
                    $customer->emailVerifiedAt,
                    $customer->acceptsMarketing ? 1 : 0,
                    $customer->customerGroupId,
                    $customer->status,
                    $customer->approvalStatus,
                    $customer->approvalReason,
                ]
            );

            $this->writeHistory(
                customerId: $id,
                fromStatus: null,
                toStatus:   $customer->approvalStatus,
                reason:     'Account created',
                actorId:    null
            );

            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update(Customer $customer): void
    {
        $this->db->execute(
            "UPDATE `commerce_customers`
                SET `email` = ?, `first_name` = ?, `last_name` = ?,
                    `phone` = ?, `company` = ?, `tax_id` = ?, `date_of_birth` = ?,
                    `accepts_marketing` = ?, `customer_group_id` = ?,
                    `status` = ?
              WHERE `id` = ? AND `store_id` = ?",
            [
                strtolower(trim($customer->email)),
                $customer->firstName,
                $customer->lastName,
                $customer->phone,
                $customer->company,
                $customer->taxId,
                $customer->dateOfBirth,
                $customer->acceptsMarketing ? 1 : 0,
                $customer->customerGroupId,
                $customer->status,
                $customer->id,
                $this->storeId(),
            ]
        );
    }

    // =================================================================
    // Approval workflow (writes)
    // =================================================================

    /**
     * Approve a pending or rejected customer.
     *
     * Writes a history row from the current state to 'approved'.
     * If the customer is already approved, no change is made and no
     * history row is written.
     */
    public function approve(int $customerId, int $userId): bool
    {
        return $this->transition(
            customerId: $customerId,
            toStatus:   'approved',
            reason:     null,
            actorId:    $userId,
            applyUpdate: function () use ($customerId, $userId): int {
                return $this->db->execute(
                    "UPDATE `commerce_customers`
                        SET `approval_status` = 'approved',
                            `approval_reason` = NULL,
                            `approved_at` = NOW(),
                            `approved_by` = ?,
                            `rejected_at` = NULL
                      WHERE `id` = ? AND `store_id` = ?
                        AND `approval_status` <> 'approved'",
                    [$userId, $customerId, $this->storeId()]
                );
            }
        );
    }

    /**
     * Reject a customer with a reason.
     * Reason is admin-only; customers never see it.
     */
    public function reject(int $customerId, int $userId, string $reason): bool
    {
        return $this->transition(
            customerId: $customerId,
            toStatus:   'rejected',
            reason:     $reason,
            actorId:    $userId,
            applyUpdate: function () use ($customerId, $reason): int {
                return $this->db->execute(
                    "UPDATE `commerce_customers`
                        SET `approval_status` = 'rejected',
                            `approval_reason` = ?,
                            `rejected_at` = NOW(),
                            `approved_at` = NULL,
                            `approved_by` = NULL
                      WHERE `id` = ? AND `store_id` = ?
                        AND `approval_status` <> 'rejected'",
                    [$reason, $customerId, $this->storeId()]
                );
            }
        );
    }

    /**
     * Reset a customer to pending review.
     *
     * The optional $reason is stored on the history row but does not
     * overwrite the customer's approval_reason column (which is
     * reserved for rejections).
     */
    public function resetToPending(int $customerId, ?string $reason = null): bool
    {
        return $this->transition(
            customerId: $customerId,
            toStatus:   'pending',
            reason:     $reason,
            actorId:    $this->currentActorId(),
            applyUpdate: function () use ($customerId): int {
                return $this->db->execute(
                    "UPDATE `commerce_customers`
                        SET `approval_status` = 'pending',
                            `approval_reason` = NULL,
                            `approved_at` = NULL,
                            `approved_by` = NULL,
                            `rejected_at` = NULL
                      WHERE `id` = ? AND `store_id` = ?
                        AND `approval_status` <> 'pending'",
                    [$customerId, $this->storeId()]
                );
            }
        );
    }

    // =================================================================
    // Internal: state transition + history
    // =================================================================

    /**
     * Run an approval state transition inside a transaction.
     *
     * 1. Reads the current state (for from_status in the history row).
     * 2. Runs the caller-supplied update closure.
     * 3. If the update affected 1 row, writes a history row.
     * 4. Commits.
     *
     * Rolls back on any exception. Returns true if the transition
     * applied (1 row affected), false if the state was already the
     * target (idempotent no-op).
     *
     * @param callable():int $applyUpdate
     */
    private function transition(
        int $customerId,
        string $toStatus,
        ?string $reason,
        ?int $actorId,
        callable $applyUpdate
    ): bool {
        $this->db->beginTransaction();

        try {
            // Read current state inside the transaction so from_status
            // reflects what the update is actually transitioning FROM.
            $current = $this->findById($customerId);
            if ($current === null) {
                $this->db->rollBack();
                return false;
            }

            $affected = $applyUpdate();

            if ($affected > 0) {
                $this->writeHistory(
                    customerId: $customerId,
                    fromStatus: $current->approvalStatus,
                    toStatus:   $toStatus,
                    reason:     $reason,
                    actorId:    $actorId
                );
            }

            $this->db->commit();
            return $affected > 0;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Append one row to commerce_customer_approval_history.
     * Must be called inside an active transaction.
     */
    private function writeHistory(
        int $customerId,
        ?string $fromStatus,
        string $toStatus,
        ?string $reason,
        ?int $actorId
    ): void {
        $this->db->insert(
            "INSERT INTO `commerce_customer_approval_history`
                (`store_id`, `customer_id`, `from_status`, `to_status`,
                 `reason`, `actor_id`)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $this->storeId(),
                $customerId,
                $fromStatus,
                $toStatus,
                $reason,
                $actorId,
            ]
        );
    }

    /**
     * Resolve the acting user's ID from the session, if any.
     * Used by resetToPending so the history row records the actor
     * without requiring the caller to pass it.
     */
    private function currentActorId(): ?int
    {
        if (!class_exists(\CorporateAid\Core\App::class)) {
            return null;
        }
        try {
            $auth = \CorporateAid\Core\App::instance()
                ->container()
                ->get(\CorporateAid\Core\Auth::class);
            $user = $auth->user();
            return $user !== null ? (int) $user->id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    // =================================================================
    // Helpers
    // =================================================================

    /** @return array{0:string, 1:array} */
    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildFilters(array $filters): array
    {
        $where = ["c.`store_id` = ?"];
        $params = [$this->storeId()];

        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $where[] = "(c.`email` LIKE ? OR c.`first_name` LIKE ? OR c.`last_name` LIKE ? OR c.`company` LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['approval_status'])) {
            $where[] = "c.`approval_status` = ?";
            $params[] = (string) $filters['approval_status'];
        }

        if (!empty($filters['status'])) {
            $where[] = "c.`status` = ?";
            $params[] = (string) $filters['status'];
        }

        if (!empty($filters['group_id'])) {
            $where[] = "c.`customer_group_id` = ?";
            $params[] = (int) $filters['group_id'];
        }

        return [implode(' AND ', $where), $params];
    }
}
