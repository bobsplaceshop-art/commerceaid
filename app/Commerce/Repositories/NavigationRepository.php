<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\NavigationMenu;
use CorporateAid\Commerce\Models\NavigationColumn;
use CorporateAid\Commerce\Models\NavigationItem;
use CorporateAid\Commerce\Models\StoreContactDetail;

/**
 * Reads the navigation tree and resolves item destinations to hrefs.
 *
 * Three surfaces read from here:
 *   - header builder / storefront header -> getHeaderTree()
 *   - footer builder / storefront footer -> getFooterColumns()
 *   - menu builder                       -> listMenus()
 *
 * URL resolution is centralized in resolveUrl(). A resource item
 * (category, product, page, legal_page) is resolved against the
 * current store. A stale resource returns '#' so the renderer never
 * breaks on a deleted row.
 */
final class NavigationRepository extends CommerceRepository
{
    /** @return array<int, NavigationMenu> */
    public function listMenus(): array
    {
        $rows = $this->db->select(
            'SELECT * FROM `commerce_navigation_menus`
              WHERE `store_id` = ?
              ORDER BY `location` ASC, `name` ASC',
            [$this->storeId()]
        );
        return array_map(fn ($r) => NavigationMenu::fromRow($r), $rows);
    }

    public function findMenuBySlug(string $slug): ?NavigationMenu
    {
        $row = $this->db->selectOne(
            'SELECT * FROM `commerce_navigation_menus`
              WHERE `store_id` = ? AND `slug` = ? AND `is_active` = 1
              LIMIT 1',
            [$this->storeId(), $slug]
        );
        return $row ? NavigationMenu::fromRow($row) : null;
    }

    public function findMenuById(int $id): ?NavigationMenu
    {
        $row = $this->db->selectOne(
            'SELECT * FROM `commerce_navigation_menus`
              WHERE `store_id` = ? AND `id` = ? AND `is_active` = 1
              LIMIT 1',
            [$this->storeId(), $id]
        );
        return $row ? NavigationMenu::fromRow($row) : null;
    }

    public function findMenuByLocation(string $location): ?NavigationMenu
    {
        $row = $this->db->selectOne(
            'SELECT * FROM `commerce_navigation_menus`
              WHERE `store_id` = ? AND `location` = ? AND `is_active` = 1
              ORDER BY `id` ASC
              LIMIT 1',
            [$this->storeId(), $location]
        );
        return $row ? NavigationMenu::fromRow($row) : null;
    }

    /**
     * Header tree: root items (column_id NULL, parent_id NULL) with
     * children nested via $byId.
     *
     * @return array<int, NavigationItem>
     */
    public function getHeaderTree(string $slug = 'header'): array
    {
        $menu = $this->findMenuBySlug($slug);
        if ($menu === null) {
            return [];
        }

        $rows = $this->db->select(
            'SELECT * FROM `commerce_navigation_items`
              WHERE `menu_id` = ? AND `column_id` IS NULL
              ORDER BY `sort_order` ASC, `id` ASC',
            [$menu->id]
        );

        /** @var array<int, NavigationItem> $items */
        $items = [];
        foreach ($rows as $row) {
            $items[(int) $row['id']] = NavigationItem::fromRow($row);
        }

        $roots = [];
        foreach ($items as $id => $item) {
            if ($item->parentId !== null && isset($items[$item->parentId])) {
                $items[$item->parentId]->children[] = $item;
            } else {
                $roots[] = $item;
            }
        }

        return $roots;
    }

    /**
     * Footer columns: each column with its flat item list (parent_id NULL).
     *
     * @return array<int, NavigationColumn>
     */
    public function getFooterColumns(string $slug = 'footer'): array
    {
        $menu = $this->findMenuBySlug($slug);
        if ($menu === null) {
            return [];
        }

        $colRows = $this->db->select(
            'SELECT * FROM `commerce_navigation_columns`
              WHERE `menu_id` = ?
              ORDER BY `sort_order` ASC, `id` ASC',
            [$menu->id]
        );

        /** @var array<int, NavigationColumn> $columns */
        $columns = [];
        foreach ($colRows as $row) {
            $col = NavigationColumn::fromRow($row);
            $columns[$col->id] = $col;
        }

        if ($columns === []) {
            return [];
        }

        $itemRows = $this->db->select(
            'SELECT * FROM `commerce_navigation_items`
              WHERE `menu_id` = ? AND `column_id` IS NOT NULL
              ORDER BY `sort_order` ASC, `id` ASC',
            [$menu->id]
        );

        foreach ($itemRows as $row) {
            $item = NavigationItem::fromRow($row);
            $colId = $item->columnId;
            if ($colId !== null && isset($columns[$colId])) {
                $columns[$colId]->items[] = $item;
            }
        }

        return array_values($columns);
    }

    /** @return array<int, StoreContactDetail> */
    public function getContactDetails(): array
    {
        $rows = $this->db->select(
            'SELECT * FROM `commerce_store_contact_details`
              WHERE `store_id` = ?
              ORDER BY `sort_order` ASC, `id` ASC',
            [$this->storeId()]
        );
        return array_map(fn ($r) => StoreContactDetail::fromRow($r), $rows);
    }

    public function createItem(NavigationItem $item): int
    {
        return $this->db->insert(
            'INSERT INTO `commerce_navigation_items`
                (`menu_id`, `column_id`, `parent_id`, `label`, `icon`, `subtitle`,
                 `url`, `resource_type`, `resource_id`, `sort_order`, `is_highlight`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $item->menuId,
                $item->columnId,
                $item->parentId,
                $item->label,
                $item->icon,
                $item->subtitle,
                $item->url,
                $item->resourceType,
                $item->resourceId,
                $item->sortOrder,
                $item->isHighlight ? 1 : 0,
            ]
        );
    }

    public function updateItem(NavigationItem $item): void
    {
        $this->db->execute(
            'UPDATE `commerce_navigation_items`
                SET `label` = ?, `icon` = ?, `subtitle` = ?, `url` = ?,
                    `resource_type` = ?, `resource_id` = ?,
                    `sort_order` = ?, `is_highlight` = ?
              WHERE `id` = ? AND `menu_id` = ?',
            [
                $item->label,
                $item->icon,
                $item->subtitle,
                $item->url,
                $item->resourceType,
                $item->resourceId,
                $item->sortOrder,
                $item->isHighlight ? 1 : 0,
                $item->id,
                $item->menuId,
            ]
        );
    }

    /**
     * Delete a single navigation item.
     *
     * Child items are removed by the self-referencing
     * commerce_navigation_items.parent_id foreign key
     * (ON DELETE CASCADE, migration 0356 lines 82-83). Do
     * NOT add manual recursion here; the database already
     * deletes the subtree. Manual recursion would double-delete.
     */
    public function deleteItem(int $itemId, int $menuId): void
    {
        $this->db->execute(
            'DELETE FROM `commerce_navigation_items` WHERE `id` = ? AND `menu_id` = ?',
            [$itemId, $menuId]
        );
    }

    public function createContact(StoreContactDetail $detail): int
    {
        return $this->db->insert(
            'INSERT INTO `commerce_store_contact_details`
                (`store_id`, `type`, `label`, `value`, `sort_order`)
             VALUES (?, ?, ?, ?, ?)',
            [
                $this->storeId(),
                $detail->type,
                $detail->label,
                $detail->value,
                $detail->sortOrder,
            ]
        );
    }

    public function updateContact(StoreContactDetail $detail): void
    {
        $this->db->execute(
            'UPDATE `commerce_store_contact_details`
                SET `type` = ?, `label` = ?, `value` = ?, `sort_order` = ?
              WHERE `id` = ? AND `store_id` = ?',
            [
                $detail->type,
                $detail->label,
                $detail->value,
                $detail->sortOrder,
                $detail->id,
                $this->storeId(),
            ]
        );
    }

    public function deleteContact(int $id): void
    {
        $this->db->execute(
            'DELETE FROM `commerce_store_contact_details` WHERE `id` = ? AND `store_id` = ?',
            [$id, $this->storeId()]
        );
    }
    /**
     * Resolve an item's destination to an href for rendering.
     *
     * Resource items are looked up against the current store. If the
     * row is missing or the store does not own it, the item returns
     * '#'. This keeps a stale link from taking down the page.
     */
    /**
     * Fetches the trust bar items (icon + subtitle) from the header menu.
     *
     * @return array<int, NavigationItem>
     */
    public function getTrustBarItems(string $slug = 'header'): array
    {
        $menu = $this->findMenuBySlug($slug);
        if ($menu === null) {
            return [];
        }

        $rows = $this->db->select(
            'SELECT i.* FROM `commerce_navigation_items` i
             JOIN `commerce_navigation_columns` c ON c.id = i.column_id
             WHERE i.menu_id = ? AND c.column_key = \'trust_bar\'
             ORDER BY i.sort_order ASC',
            [$menu->id]
        );

        return array_map(fn ($r) => NavigationItem::fromRow($r), $rows);
    }

    public function resolveUrl(NavigationItem $item): string
    {
        if (!$item->isResource()) {
            return $item->url ?? '#';
        }

        $table  = match ($item->resourceType) {
            'category'   => 'commerce_categories',
            'product'    => 'commerce_products',
            'page'       => 'commerce_store_pages',
            'legal_page' => 'commerce_legal_pages',
            default      => null,
        };
        if ($table === null) {
            return $item->url ?? '#';
        }

        // legal_pages and store_pages use different prefix maps.
        $slug = $this->db->scalar(
            "SELECT `slug` FROM `{$table}` WHERE `id` = ? AND `store_id` = ? LIMIT 1",
            [$item->resourceId, $this->storeId()]
        );
        if (!is_string($slug) || $slug === '') {
            return $item->url ?? '#';
        }

        return match ($item->resourceType) {
            'category'   => '/categories/' . $slug,
            'product'    => '/products/' . $slug,
            'page'       => '/pages/' . $slug,
            'legal_page' => '/legal/' . $slug,
        };
    }

    public function createColumn(int $menuId, string $columnKey, string $heading, int $sortOrder = 0): int
    {
        return $this->db->insert(
            'INSERT INTO `commerce_navigation_columns` (`menu_id`, `column_key`, `heading`, `sort_order`) VALUES (?, ?, ?, ?)',
            [$menuId, $columnKey, $heading, $sortOrder]
        );
    }

    public function updateColumn(int $columnId, int $menuId, string $heading, int $sortOrder): void
    {
        $this->db->execute(
            'UPDATE `commerce_navigation_columns` SET `heading` = ?, `sort_order` = ? WHERE `id` = ? AND `menu_id` = ?',
            [$heading, $sortOrder, $columnId, $menuId]
        );
    }

    /**
     * Delete a footer column.
     *
     * Items in the column are removed by the
     * commerce_navigation_items.column_id foreign key
     * (ON DELETE CASCADE, migration 0356 lines 79-80).
     * No manual recursion needed.
     */
    public function deleteColumn(int $columnId, int $menuId): void
    {
        $this->db->execute(
            'DELETE FROM `commerce_navigation_columns` WHERE `id` = ? AND `menu_id` = ?',
            [$columnId, $menuId]
        );
    }

    public function createMenu(string $name, string $slug, string $location = 'custom'): int
    {
        return $this->db->insert(
            'INSERT INTO `commerce_navigation_menus` (`store_id`, `name`, `slug`, `location`, `is_active`) VALUES (?, ?, ?, ?, 1)',
            [$this->storeId(), $name, $slug, $location]
        );
    }

    /**
     * Returns the tree of items for any menu, ordered by sort_order.
     * Items with column_id are excluded (those belong to footer columns).
     *
     * @return array<int, NavigationItem>
     */
    public function getMenuTree(string $slug): array
    {
        $menu = $this->findMenuBySlug($slug);
        if ($menu === null) {
            return [];
        }

        $rows = $this->db->select(
            'SELECT * FROM `commerce_navigation_items`
              WHERE `menu_id` = ? AND `column_id` IS NULL
              ORDER BY `sort_order` ASC, `id` ASC',
            [$menu->id]
        );

        /** @var array<int, NavigationItem> $items */
        $items = [];
        foreach ($rows as $row) {
            $items[(int) $row['id']] = NavigationItem::fromRow($row);
        }

        $roots = [];
        foreach ($items as $id => $item) {
            if ($item->parentId !== null && isset($items[$item->parentId])) {
                $items[$item->parentId]->children[] = $item;
            } else {
                $roots[] = $item;
            }
        }

        return $roots;
    }

    /**
     * Delete a navigation menu.
     *
     * Columns and items are removed by the
     * commerce_navigation_columns.menu_id and
     * commerce_navigation_items.menu_id foreign keys
     * (both ON DELETE CASCADE, migration 0356 lines
     * 52-53 and 76-77). No manual cascade needed.
     */
    public function deleteMenu(int $menuId): void
    {
        $this->db->execute(
            'DELETE FROM `commerce_navigation_menus` WHERE `id` = ? AND `store_id` = ?',
            [$menuId, $this->storeId()]
        );
    }
}
