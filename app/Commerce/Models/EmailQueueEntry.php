<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * One pending send in commerce_email_queue.
 *
 * See docs/stages/008i_newsletter-plan.md for the queue
 * lifecycle and the retry policy.
 */
final class EmailQueueEntry
{
    public int $id = 0;
    public int $storeId = 0;
    public int $campaignId = 0;
    public int $subscriberId = 0;
    public string $toEmail = '';
    public ?string $toName = null;
    public string $unsubscribeToken = '';
    public int $attempts = 0;
    public ?string $lastError = null;
    public string $status = 'pending';
    public ?string $scheduledAt = null;
    public ?string $sentAt = null;
    public ?string $createdAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $e = new self();
        $e->id               = (int) $row['id'];
        $e->storeId          = (int) $row['store_id'];
        $e->campaignId       = (int) $row['campaign_id'];
        $e->subscriberId     = (int) $row['subscriber_id'];
        $e->toEmail          = (string) $row['to_email'];
        $e->toName           = $row['to_name'] ?? null;
        $e->unsubscribeToken = (string) $row['unsubscribe_token'];
        $e->attempts         = (int) ($row['attempts'] ?? 0);
        $e->lastError        = $row['last_error'] ?? null;
        $e->status           = (string) $row['status'];
        $e->scheduledAt      = $row['scheduled_at'] ?? null;
        $e->sentAt           = $row['sent_at'] ?? null;
        $e->createdAt        = $row['created_at'] ?? null;
        return $e;
    }
}
