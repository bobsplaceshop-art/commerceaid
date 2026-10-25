<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Navigation item DTO.
 *
 * One link. Belongs to a menu, optionally to a column (footer) and
 * optionally to a parent item (header submenu).
 *
 * Destination is either a resource (`resource_type` + `resource_id`)
 * or a literal URL (`url`). Exactly one is set.
 */
final class NavigationItem
{
    public int $id = 0;
    public int $menuId = 0;
    public ?int $columnId = null;
    public ?int $parentId = null;
    public string $label = '';
    public ?string $url = null;
    public ?string $resourceType = null;
    public ?int $resourceId = null;
    public int $sortOrder = 0;
    public bool $isHighlight = false;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @var array<int, NavigationItem> */
    public array $children = [];

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $i = new self();
        $i->id           = (int) $row['id'];
        $i->menuId       = (int) $row['menu_id'];
        $i->columnId     = isset($row['column_id']) ? (int) $row['column_id'] : null;
        $i->parentId     = isset($row['parent_id']) ? (int) $row['parent_id'] : null;
        $i->label        = (string) $row['label'];
        $i->url          = $row['url'] ?? null;
        $i->resourceType = $row['resource_type'] ?? null;
        $i->resourceId   = isset($row['resource_id']) ? (int) $row['resource_id'] : null;
        $i->sortOrder    = (int) ($row['sort_order'] ?? 0);
        $i->isHighlight  = (bool) ($row['is_highlight'] ?? false);
        $i->createdAt    = $row['created_at'] ?? null;
        $i->updatedAt    = $row['updated_at'] ?? null;
        return $i;
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    public function isResource(): bool
    {
        return $this->resourceType !== null && $this->resourceId !== null;
    }
}
