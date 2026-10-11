<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * A newsletter campaign.
 *
 * Status lifecycle:
 *   draft      - composed, not sent
 *   scheduled  - reserved for a future cron scheduler
 *   sending    - the queue is being drained
 *   sent       - the queue drained completely
 *   cancelled  - abandoned before send
 *
 * See docs/stages/008i_newsletter-plan.md.
 */
final class EmailCampaign
{
    public int $id = 0;
    public int $storeId = 0;
    public int $listId = 0;
    public string $subject = '';
    public ?string $preheader = null;
    public string $bodyHtml = '';
    public ?string $bodyText = null;
    public string $status = 'draft';
    public ?string $scheduledAt = null;
    public ?string $sentAt = null;
    public int $sentCount = 0;
    public int $failedCount = 0;
    public ?int $createdBy = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $c = new self();
        $c->id          = (int) $row['id'];
        $c->storeId     = (int) $row['store_id'];
        $c->listId      = (int) $row['list_id'];
        $c->subject     = (string) $row['subject'];
        $c->preheader   = $row['preheader'] ?? null;
        $c->bodyHtml    = (string) $row['body_html'];
        $c->bodyText    = $row['body_text'] ?? null;
        $c->status      = (string) $row['status'];
        $c->scheduledAt = $row['scheduled_at'] ?? null;
        $c->sentAt      = $row['sent_at'] ?? null;
        $c->sentCount   = (int) ($row['sent_count'] ?? 0);
        $c->failedCount = (int) ($row['failed_count'] ?? 0);
        $c->createdBy   = isset($row['created_by']) ? (int) $row['created_by'] : null;
        $c->createdAt   = $row['created_at'] ?? null;
        $c->updatedAt   = $row['updated_at'] ?? null;
        return $c;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSending(): bool
    {
        return $this->status === 'sending';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function canEdit(): bool
    {
        return in_array($this->status, ['draft', 'scheduled', 'cancelled'], true);
    }

    public function canSend(): bool
    {
        return in_array($this->status, ['draft', 'cancelled'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft'     => 'Draft',
            'scheduled' => 'Scheduled',
            'sending'   => 'Sending',
            'sent'      => 'Sent',
            'cancelled' => 'Cancelled',
            default     => ucfirst($this->status),
        };
    }

    public function statusBadgeVariant(): string
    {
        return match ($this->status) {
            'draft'     => 'muted',
            'scheduled' => 'info',
            'sending'   => 'warning',
            'sent'      => 'success',
            'cancelled' => 'danger',
            default     => 'muted',
        };
    }
}
