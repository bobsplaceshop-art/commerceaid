<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Customer group DTO.
 *
 * A group categorizes buyers (Retail, Wholesale, VIP, Staff).
 * Each group can carry its own price list.
 *
 * Design rules:
 *   - One group per customer
 *   - Exactly one group per store has is_default = 1
 *   - is_b2b groups can require approval for new signups
 *   - B2B groups can have default net terms (payment due in N days)
 */
final class CustomerGroup
{
    public int $id = 0;
    public int $storeId = 0;
    public string $slug = '';
    public string $name = '';
    public ?string $description = null;
    public bool $isDefault = false;
    public bool $isB2b = false;
    public bool $requiresApproval = false;
    public int $defaultNetDays = 0;
    public int $sortOrder = 0;
    public string $status = 'active';
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $g = new self();
        $g->id                = (int) $row['id'];
        $g->storeId           = (int) $row['store_id'];
        $g->slug              = (string) $row['slug'];
        $g->name              = (string) $row['name'];
        $g->description       = $row['description'] ?? null;
        $g->isDefault         = (bool) ($row['is_default'] ?? false);
        $g->isB2b             = (bool) ($row['is_b2b'] ?? false);
        $g->requiresApproval  = (bool) ($row['requires_approval'] ?? false);
        $g->defaultNetDays    = (int) ($row['default_net_days'] ?? 0);
        $g->sortOrder         = (int) ($row['sort_order'] ?? 0);
        $g->status            = (string) ($row['status'] ?? 'active');
        $g->createdAt         = $row['created_at'] ?? null;
        $g->updatedAt         = $row['updated_at'] ?? null;
        return $g;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    /**
     * Does this group offer net terms (pay-later) instead of prepay?
     * Groups with defaultNetDays = 0 are prepay groups.
     */
    public function hasNetTerms(): bool
    {
        return $this->defaultNetDays > 0;
    }
}
