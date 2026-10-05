<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * A customer-initiated return request.
 *
 * One request may cover one or more order lines. A request is open
 * while its status is requested, approved, or received. It is
 * resolved when it becomes rejected, refunded, or cancelled.
 */
final class ReturnRequest
{
    public int $id = 0;
    public int $storeId = 0;
    public int $orderId = 0;
    public int $customerId = 0;
    public string $requestNumber = '';
    public string $status = 'requested';
    public string $reasonCode = '';
    public ?string $reasonNote = null;
    public ?string $adminNote = null;
    public float $refundTotal = 0.0;
    public ?string $requestedAt = null;
    public ?string $reviewedAt = null;
    public ?int $reviewedBy = null;
    public ?string $resolvedAt = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $r = new self();
        $r->id            = (int) $row['id'];
        $r->storeId       = (int) $row['store_id'];
        $r->orderId       = (int) $row['order_id'];
        $r->customerId    = (int) $row['customer_id'];
        $r->requestNumber = (string) $row['request_number'];
        $r->status        = (string) $row['status'];
        $r->reasonCode    = (string) $row['reason_code'];
        $r->reasonNote    = $row['reason_note'] ?? null;
        $r->adminNote     = $row['admin_note'] ?? null;
        $r->refundTotal   = (float) $row['refund_total'];
        $r->requestedAt   = $row['requested_at'] ?? null;
        $r->reviewedAt    = $row['reviewed_at'] ?? null;
        $r->reviewedBy    = isset($row['reviewed_by']) ? (int) $row['reviewed_by'] : null;
        $r->resolvedAt    = $row['resolved_at'] ?? null;
        $r->createdAt     = $row['created_at'] ?? null;
        $r->updatedAt     = $row['updated_at'] ?? null;
        return $r;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['requested', 'approved', 'received'], true);
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['rejected', 'refunded', 'cancelled'], true);
    }

    public function isRefundable(): bool
    {
        return $this->status === 'received';
    }

    public function isWiderruf(): bool
    {
        return $this->reasonCode === 'changed_mind';
    }

    public function reasonLabel(): string
    {
        return match ($this->reasonCode) {
            'damaged'          => 'Item arrived damaged',
            'wrong_item'       => 'Wrong item received',
            'not_as_described' => 'Not as described',
            'changed_mind'     => 'Changed my mind',
            default            => 'Other',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'requested' => 'Requested',
            'approved'  => 'Approved',
            'rejected'  => 'Rejected',
            'received'  => 'Received',
            'refunded'  => 'Refunded',
            'cancelled' => 'Cancelled',
            default     => ucfirst($this->status),
        };
    }

    public function statusBadgeVariant(): string
    {
        return match ($this->status) {
            'requested' => 'warning',
            'approved'  => 'info',
            'rejected'  => 'danger',
            'received'  => 'info',
            'refunded'  => 'success',
            'cancelled' => 'muted',
            default     => 'muted',
        };
    }
}
