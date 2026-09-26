<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Customer approval history entry (DTO).
 *
 * One row per state transition on commerce_customers.approval_status.
 * Append-only: history rows are never edited or deleted.
 *
 * A row with from_status = NULL is the initial creation event.
 * Every other row records a transition (approved, rejected, reset).
 *
 * Business logic methods here answer "what does this row mean",
 * not "how do I get it" (that is the repository's job).
 */
final class CustomerApprovalHistory
{
    public int $id = 0;
    public int $storeId = 0;
    public int $customerId = 0;
    public ?string $fromStatus = null;
    public string $toStatus = '';
    public ?string $reason = null;
    public ?int $actorId = null;
    public ?string $createdAt = null;

    /**
     * Optional display-only joins. Populated by the repository when
     * the query joins core_users to resolve the actor name.
     */
    public ?string $actorName = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $h = new self();
        $h->id         = (int) $row['id'];
        $h->storeId    = (int) $row['store_id'];
        $h->customerId = (int) $row['customer_id'];
        $h->fromStatus = $row['from_status'] ?? null;
        $h->toStatus   = (string) $row['to_status'];
        $h->reason     = $row['reason'] ?? null;
        $h->actorId    = isset($row['actor_id']) ? (int) $row['actor_id'] : null;
        $h->createdAt  = $row['created_at'] ?? null;
        $h->actorName  = $row['actor_name'] ?? null;
        return $h;
    }

    // ---------- Classification ----------

    public function isCreation(): bool
    {
        return $this->fromStatus === null;
    }

    public function isApproval(): bool
    {
        return $this->toStatus === 'approved' && $this->fromStatus !== null;
    }

    public function isRejection(): bool
    {
        return $this->toStatus === 'rejected';
    }

    public function isReset(): bool
    {
        return $this->toStatus === 'pending' && $this->fromStatus !== null;
    }

    public function hasReason(): bool
    {
        return $this->reason !== null && $this->reason !== '';
    }

    public function hasActor(): bool
    {
        return $this->actorId !== null;
    }

    // ---------- Display helpers ----------

    /**
     * Human-readable action for the timeline heading.
     */
    public function actionLabel(): string
    {
        if ($this->isCreation()) {
            return 'Account created';
        }

        return match ($this->toStatus) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'pending'  => 'Reset to pending',
            default    => 'Changed to ' . $this->toStatus,
        };
    }

    /**
     * Badge variant for the timeline UI.
     * Returns one of: success | danger | warning | muted
     */
    public function badgeVariant(): string
    {
        if ($this->isCreation()) {
            return 'muted';
        }

        return match ($this->toStatus) {
            'approved' => 'success',
            'rejected' => 'danger',
            'pending'  => 'warning',
            default    => 'muted',
        };
    }

    /**
     * Actor display: name if known, "System" if actor is NULL.
     */
    public function actorDisplay(): string
    {
        if ($this->actorName !== null && $this->actorName !== '') {
            return $this->actorName;
        }
        if ($this->actorId === null) {
            return 'System';
        }
        return 'User #' . $this->actorId;
    }
}
