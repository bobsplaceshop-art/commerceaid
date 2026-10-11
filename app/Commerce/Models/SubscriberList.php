<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * A named subscriber list.
 *
 * Exactly one list per store carries is_default = 1. The
 * footer signup form joins that list. The admin can create
 * more (Newsletter, Product updates, Promotions).
 *
 * See docs/stages/008i_newsletter-plan.md.
 */
final class SubscriberList
{
    public int $id = 0;
    public int $storeId = 0;
    public string $name = '';
    public string $slug = '';
    public ?string $description = null;
    public bool $isDefault = false;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $l = new self();
        $l->id          = (int) $row['id'];
        $l->storeId     = (int) $row['store_id'];
        $l->name        = (string) $row['name'];
        $l->slug        = (string) $row['slug'];
        $l->description = $row['description'] ?? null;
        $l->isDefault   = ((int) ($row['is_default'] ?? 0)) === 1;
        $l->createdAt   = $row['created_at'] ?? null;
        $l->updatedAt   = $row['updated_at'] ?? null;
        return $l;
    }
}
