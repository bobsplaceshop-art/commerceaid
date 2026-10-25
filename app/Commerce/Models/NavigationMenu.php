<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Navigation menu DTO.
 *
 * A menu is a named container. `location` decides where it renders:
 * 'header', 'footer', or 'custom' (a menu the merchant created for a
 * surface they will wire up later).
 */
final class NavigationMenu
{
    public int $id = 0;
    public int $storeId = 0;
    public string $name = '';
    public string $slug = '';
    public string $location = 'custom';
    public bool $isActive = true;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $m = new self();
        $m->id        = (int) $row['id'];
        $m->storeId   = (int) $row['store_id'];
        $m->name      = (string) $row['name'];
        $m->slug      = (string) $row['slug'];
        $m->location  = (string) ($row['location'] ?? 'custom');
        $m->isActive  = (bool) ($row['is_active'] ?? false);
        $m->createdAt = $row['created_at'] ?? null;
        $m->updatedAt = $row['updated_at'] ?? null;
        return $m;
    }

    public function isHeader(): bool { return $this->location === 'header'; }
    public function isFooter(): bool { return $this->location === 'footer'; }
}
