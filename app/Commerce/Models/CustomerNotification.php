<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Customer notification DTO.
 *
 * One row per notification delivered to a storefront customer.
 * Distinct from the Platform Notification model
 * (CorporateAid\Platform\Models\Notification), which targets
 * core_users (admin identity). The two live in separate tables
 * and serve separate audiences.
 *
 * The category enum drives the message center tabs:
 *
 *   'system'    - welcome, password change, policy updates,
 *                 admin announcements
 *   'order'     - order placed, and (once producers exist)
 *                 shipped, delivered, refunded
 *   'promotion' - marketing, opt-in only
 *
 * read_at NULL means unread. Setting it marks the notification
 * read. There is no "unmark" path.
 *
 * See docs/stages/008g.3_message-center-plan.md.
 */
final class CustomerNotification
{
    public int $id = 0;
    public int $storeId = 0;
    public int $customerId = 0;
    public string $category = 'system';
    public string $title = '';
    public ?string $body = null;
    public ?string $link = null;
    public ?string $readAt = null;
    public ?string $createdAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $n = new self();
        $n->id         = (int) $row['id'];
        $n->storeId    = (int) $row['store_id'];
        $n->customerId = (int) $row['customer_id'];
        $n->category   = (string) ($row['category'] ?? 'system');
        $n->title      = (string) $row['title'];
        $n->body       = $row['body'] !== null ? (string) $row['body'] : null;
        $n->link       = $row['link'] !== null ? (string) $row['link'] : null;
        $n->readAt     = $row['read_at'] !== null ? (string) $row['read_at'] : null;
        $n->createdAt  = $row['created_at'] !== null ? (string) $row['created_at'] : null;
        return $n;
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    /**
     * Human-readable label for the category. Used as the tab
     * heading and as a small badge on the row.
     */
    public function categoryLabel(): string
    {
        return match ($this->category) {
            'system'    => 'System Message',
            'order'     => 'Order Update',
            'promotion' => 'Promotion',
            default     => 'Notification',
        };
    }

    /**
     * Bootstrap icon class for the category. Mirrors the
     * Platform Notification::icon() pattern for visual
     * consistency between the admin bell and the customer
     * message center.
     */
    public function icon(): string
    {
        return match ($this->category) {
            'system'    => 'bi-info-circle',
            'order'     => 'bi-receipt',
            'promotion' => 'bi-megaphone',
            default     => 'bi-bell',
        };
    }

    /**
     * Relative time string. Same shape as the Platform
     * Notification model so the two surfaces feel consistent.
     */
    public function relativeTime(): string
    {
        if ($this->createdAt === null) {
            return '';
        }
        $ts = strtotime($this->createdAt);
        if ($ts === false) {
            return '';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            return (string) ((int) floor($diff / 60)) . 'm ago';
        }
        if ($diff < 86400) {
            return (string) ((int) floor($diff / 3600)) . 'h ago';
        }
        if ($diff < 604800) {
            return (string) ((int) floor($diff / 86400)) . 'd ago';
        }
        return date('M j', $ts);
    }

    /**
     * Absolute date in a compact form, used as a title
     * attribute on the relative time string.
     */
    public function absoluteTime(): string
    {
        if ($this->createdAt === null) {
            return '';
        }
        $ts = strtotime($this->createdAt);
        if ($ts === false) {
            return '';
        }
        return date('M j, Y g:i a', $ts);
    }
}
