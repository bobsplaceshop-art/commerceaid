<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Order status transition record.
 *
 * Mirrors commerce_order_status_history. Append-only: rows are
 * never updated or deleted. The full timeline of an order is the
 * ordered set of these rows.
 *
 * changed_by is null when the transition was customer-initiated
 * (order placed, order cancelled by the customer). It is set when
 * an admin performed the transition.
 */
final class OrderStatusHistory
{
    public int $id = 0;
    public int $storeId = 0;
    public int $orderId = 0;
    public ?string $fromStatus = null;
    public string $toStatus = '';
    public ?int $changedBy = null;
    public ?string $note = null;
    public ?string $createdAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $h = new self();
        $h->id         = (int) $row['id'];
        $h->storeId    = (int) $row['store_id'];
        $h->orderId    = (int) $row['order_id'];
        $h->fromStatus = $row['from_status'] ?? null;
        $h->toStatus   = (string) $row['to_status'];
        $h->changedBy  = isset($row['changed_by']) ? (int) $row['changed_by'] : null;
        $h->note       = $row['note'] ?? null;
        $h->createdAt  = $row['created_at'] ?? null;
        return $h;
    }

    /**
     * True if this is the first row in the order's timeline
     * (the order placement event, from_status = NULL).
     */
    public function isCreation(): bool
    {
        return $this->fromStatus === null;
    }

    /**
     * True if the transition was performed by an admin.
     */
    public function isByAdmin(): bool
    {
        return $this->changedBy !== null;
    }

    /**
     * True if the transition was performed by the customer
     * (or the system acting on their behalf).
     */
    public function isByCustomer(): bool
    {
        return $this->changedBy === null;
    }

    public function hasNote(): bool
    {
        return $this->note !== null && $this->note !== '';
    }

    /**
     * Human-readable label for the transition, e.g.
     * "pending_payment -> paid".
     */
    public function label(): string
    {
        $from = $this->fromStatus ?? 'created';
        return $from . ' -> ' . $this->toStatus;
    }
}
