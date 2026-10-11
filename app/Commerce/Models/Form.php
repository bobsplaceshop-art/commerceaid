<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * A form. Stage 009.
 *
 * A form is a named collection of fields that a
 * merchant publishes at /forms/{slug}. Submissions are
 * stored in commerce_form_submissions, keyed by form id.
 *
 * Status lifecycle:
 *   draft      - visible in the admin only
 *   published  - reachable at /forms/{slug}
 *   archived   - hidden everywhere; any public URL 404s
 *
 * Soft delete via deleted_at. A soft-deleted form is
 * hidden from every list and every public route. The
 * row is preserved for audit.
 *
 * See docs/stages/009_forms-plan.md.
 */
final class Form
{
    public int $id = 0;
    public int $storeId = 0;
    public string $name = '';
    public string $slug = '';
    public ?string $description = null;
    public string $status = 'draft';
    public ?string $successMessage = null;
    public ?string $notifyEmail = null;
    public bool $honeypotEnabled = true;
    public bool $requiresAuth = false;
    public ?int $createdBy = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
    public ?string $deletedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $f = new self();
        $f->id              = (int) $row['id'];
        $f->storeId         = (int) $row['store_id'];
        $f->name            = (string) $row['name'];
        $f->slug            = (string) $row['slug'];
        $f->description     = $row['description']     !== null ? (string) $row['description']     : null;
        $f->status          = (string) $row['status'];
        $f->successMessage  = $row['success_message'] !== null ? (string) $row['success_message'] : null;
        $f->notifyEmail     = $row['notify_email']    !== null ? (string) $row['notify_email']    : null;
        $f->honeypotEnabled = ((int) ($row['honeypot_enabled'] ?? 1)) === 1;
        $f->requiresAuth    = ((int) ($row['requires_auth']    ?? 0)) === 1;
        $f->createdBy       = $row['created_by']      !== null ? (int) $row['created_by']         : null;
        $f->createdAt       = $row['created_at']      !== null ? (string) $row['created_at']      : null;
        $f->updatedAt       = $row['updated_at']      !== null ? (string) $row['updated_at']      : null;
        $f->deletedAt       = $row['deleted_at']      !== null ? (string) $row['deleted_at']      : null;
        return $f;
    }

    public function isDraft(): bool     { return $this->status === 'draft'; }
    public function isPublished(): bool { return $this->status === 'published'; }
    public function isArchived(): bool  { return $this->status === 'archived'; }
    public function isDeleted(): bool   { return $this->deletedAt !== null; }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft'     => 'Draft',
            'published' => 'Published',
            'archived'  => 'Archived',
            default     => ucfirst($this->status),
        };
    }

    public function statusBadgeVariant(): string
    {
        return match ($this->status) {
            'draft'     => 'muted',
            'published' => 'success',
            'archived'  => 'warning',
            default     => 'muted',
        };
    }
}
