<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Footer column heading DTO.
 *
 * A column is a heading attached to a string key. Items reference the
 * column by `column_id`. The column is not a tree node: it has no
 * parent, no children, and no resource link. It exists so the footer
 * builder can rename a heading without touching every item.
 */
final class NavigationColumn
{
    public int $id = 0;
    public int $menuId = 0;
    public string $columnKey = '';
    public string $heading = '';
    public int $sortOrder = 0;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @var array<int, NavigationItem> */
    public array $items = [];

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $c = new self();
        $c->id        = (int) $row['id'];
        $c->menuId    = (int) $row['menu_id'];
        $c->columnKey = (string) $row['column_key'];
        $c->heading   = (string) $row['heading'];
        $c->sortOrder = (int) ($row['sort_order'] ?? 0);
        $c->createdAt = $row['created_at'] ?? null;
        $c->updatedAt = $row['updated_at'] ?? null;
        return $c;
    }
}
