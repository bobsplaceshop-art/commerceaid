<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * A newsletter subscriber.
 *
 * The consent columns record the double opt-in evidence
 * required by DSGVO Art. 7(1) and German UWG § 7. Every
 * value is written at subscribe time and never changes
 * after confirmation.
 *
 * Status lifecycle:
 *   pending       - address captured, confirmation sent
 *   confirmed     - opt-in confirmed, receives campaigns
 *   unsubscribed  - user chose to stop
 *   suppressed    - system suppressed (hard bounce or
 *                   3 consecutive soft bounces)
 *
 * See docs/stages/008i_newsletter-plan.md.
 */
final class Subscriber
{
    public int $id = 0;
    public int $storeId = 0;
    public string $email = '';
    public ?string $name = null;
    public string $status = 'pending';

    public ?string $consentSource = null;
    public ?string $consentIp = null;
    public ?string $consentUserAgent = null;
    public ?string $consentAt = null;

    public ?string $confirmationToken = null;
    public ?string $confirmationSentAt = null;
    public ?string $confirmedAt = null;

    public ?string $unsubscribedAt = null;
    public ?string $unsubscribeReason = null;

    public int $softBounceCount = 0;

    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $s = new self();
        $s->id                 = (int) $row['id'];
        $s->storeId            = (int) $row['store_id'];
        $s->email              = (string) $row['email'];
        $s->name               = $row['name'] ?? null;
        $s->status             = (string) $row['status'];
        $s->consentSource      = $row['consent_source'] ?? null;
        $s->consentIp          = $row['consent_ip'] ?? null;
        $s->consentUserAgent   = $row['consent_user_agent'] ?? null;
        $s->consentAt          = $row['consent_at'] ?? null;
        $s->confirmationToken  = $row['confirmation_token'] ?? null;
        $s->confirmationSentAt = $row['confirmation_sent_at'] ?? null;
        $s->confirmedAt        = $row['confirmed_at'] ?? null;
        $s->unsubscribedAt     = $row['unsubscribed_at'] ?? null;
        $s->unsubscribeReason  = $row['unsubscribe_reason'] ?? null;
        $s->softBounceCount    = (int) ($row['soft_bounce_count'] ?? 0);
        $s->createdAt          = $row['created_at'] ?? null;
        $s->updatedAt          = $row['updated_at'] ?? null;
        return $s;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isUnsubscribed(): bool
    {
        return $this->status === 'unsubscribed';
    }

    public function isSuppressed(): bool
    {
        return $this->status === 'suppressed';
    }

    public function isActive(): bool
    {
        return $this->status === 'confirmed';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'      => 'Pending',
            'confirmed'    => 'Confirmed',
            'unsubscribed' => 'Unsubscribed',
            'suppressed'   => 'Suppressed',
            default        => ucfirst($this->status),
        };
    }

    public function statusBadgeVariant(): string
    {
        return match ($this->status) {
            'pending'      => 'warning',
            'confirmed'    => 'success',
            'unsubscribed' => 'muted',
            'suppressed'   => 'danger',
            default        => 'muted',
        };
    }

    /**
     * A short display name for the admin list. Falls back to
     * the email address when no name was captured.
     */
    public function displayName(): string
    {
        if ($this->name !== null && trim($this->name) !== '') {
            return trim($this->name);
        }
        return $this->email;
    }
}
