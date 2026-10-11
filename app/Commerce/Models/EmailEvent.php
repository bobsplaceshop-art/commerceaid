<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * One entry in the email event log.
 *
 * event_data is a decoded associative array (or null). The
 * shape depends on event_type: the URL for a click, the
 * bounce reason text for a bounce, the user agent for an
 * open.
 *
 * See docs/stages/008i_newsletter-plan.md.
 */
final class EmailEvent
{
    public int $id = 0;
    public int $storeId = 0;
    public ?int $campaignId = null;
    public ?int $subscriberId = null;
    public string $eventType = '';
    /** @var array<string,mixed>|null */
    public ?array $eventData = null;
    public ?string $createdAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $e = new self();
        $e->id           = (int) $row['id'];
        $e->storeId      = (int) $row['store_id'];
        $e->campaignId   = isset($row['campaign_id']) ? (int) $row['campaign_id'] : null;
        $e->subscriberId = isset($row['subscriber_id']) ? (int) $row['subscriber_id'] : null;
        $e->eventType    = (string) $row['event_type'];
        $e->createdAt    = $row['created_at'] ?? null;

        $raw = $row['event_data'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $e->eventData = is_array($decoded) ? $decoded : null;
        } elseif (is_array($raw)) {
            $e->eventData = $raw;
        } else {
            $e->eventData = null;
        }

        return $e;
    }

    public function eventLabel(): string
    {
        return match ($this->eventType) {
            'sent'         => 'Sent',
            'open'         => 'Opened',
            'click'        => 'Clicked',
            'bounce_hard'  => 'Hard bounce',
            'bounce_soft'  => 'Soft bounce',
            'unsubscribe'  => 'Unsubscribed',
            'complaint'    => 'Complaint',
            default        => ucfirst($this->eventType),
        };
    }

    public function eventBadgeVariant(): string
    {
        return match ($this->eventType) {
            'sent'         => 'muted',
            'open'         => 'info',
            'click'        => 'success',
            'bounce_hard'  => 'danger',
            'bounce_soft'  => 'warning',
            'unsubscribe'  => 'muted',
            'complaint'    => 'danger',
            default        => 'muted',
        };
    }
}
