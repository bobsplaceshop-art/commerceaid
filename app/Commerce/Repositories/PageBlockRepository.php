<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\PageBlock;

/**
 * Page block repository.
 *
 * Every query is scoped to (store_id, page_slug). The store
 * comes from StoreContext; the page slug is a parameter.
 *
 * Reads:
 *   listForPage()   every block on one page, in sort order
 *   findById()      one block by primary key
 *
 * Writes:
 *   create()        insert a new block
 *   update()        change block_type / config / enabled
 *   delete()        hard delete one block
 *   reorder()       rewrite sort_order for every block on
 *                   a page, given an ordered array of ids
 *   enable() / disable()   toggle is_enabled
 *   nextSortOrder() the next free sort_order on a page
 *
 * The repository does not validate config_json shape. The
 * builder UI is responsible for producing valid configs;
 * the renderer is responsible for defensive reads.
 *
 * See docs/stages/023_page-builder-plan.md.
 */
final class PageBlockRepository extends CommerceRepository
{
    /**
     * Every block on one page, sorted by sort_order ASC.
     *
     * @return array<int, PageBlock>
     */
    public function listForPage(string $pageSlug, bool $enabledOnly = false): array
    {
        $sql = "SELECT * FROM `commerce_page_blocks`
                WHERE `store_id` = ? AND `page_slug` = ?";
        $params = [$this->storeId(), $pageSlug];

        if ($enabledOnly) {
            $sql .= " AND `is_enabled` = 1";
        }

        $sql .= " ORDER BY `sort_order` ASC, `id` ASC";

        $rows = $this->db->select($sql, $params);
        return array_map([PageBlock::class, 'fromRow'], $rows);
    }

    /**
     * Distinct page slugs in use at this store, for the admin
     * page-list view.
     *
     * @return array<int, string>
     */
    public function pageSlugs(): array
    {
        $rows = $this->db->select(
            "SELECT DISTINCT `page_slug`
             FROM `commerce_page_blocks`
             WHERE `store_id` = ?
             ORDER BY `page_slug` ASC",
            [$this->storeId()]
        );
        return array_map(fn($r) => (string) $r['page_slug'], $rows);
    }

    /**
     * One block by primary key, scoped to this store.
     */
    public function findById(int $id): ?PageBlock
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_page_blocks`
             WHERE `store_id` = ? AND `id` = ?",
            [$this->storeId(), $id]
        );
        return $row === null ? null : PageBlock::fromRow($row);
    }

    /**
     * Insert a new block. Returns the new id.
     *
     * If sortOrder is 0 (default), the block is appended
     * to the end of the page.
     *
     * @param array<string,mixed> $config
     */
    public function create(
        string $pageSlug,
        string $blockType,
        array $config,
        int $sortOrder = 0
    ): int {
        if ($sortOrder <= 0) {
            $sortOrder = $this->nextSortOrder($pageSlug);
        }

        return $this->db->insert(
            "INSERT INTO `commerce_page_blocks`
                (`store_id`, `page_slug`, `block_type`,
                 `sort_order`, `config_json`, `is_enabled`)
             VALUES (?, ?, ?, ?, ?, 1)",
            [
                $this->storeId(),
                $pageSlug,
                $blockType,
                $sortOrder,
                json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    /**
     * Update a block's config (and optionally its type).
     * Does not touch sort_order; use reorder() for that.
     *
     * @param array<string,mixed> $config
     */
    public function update(int $id, array $config): void
    {
        $this->db->execute(
            "UPDATE `commerce_page_blocks`
             SET `config_json` = ?
             WHERE `store_id` = ? AND `id` = ?",
            [
                json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $this->storeId(),
                $id,
            ]
        );
    }

    /**
     * Hard delete one block. The store cascade already covers
     * the whole-page case; this is for individual removal.
     */
    public function delete(int $id): void
    {
        $this->db->execute(
            "DELETE FROM `commerce_page_blocks`
             WHERE `store_id` = ? AND `id` = ?",
            [$this->storeId(), $id]
        );
    }

    /**
     * Rewrite sort_order for every block on a page.
     *
     * @param array<int, int> $orderedIds block ids in the new order
     */
    public function reorder(string $pageSlug, array $orderedIds): void
    {
        $this->requireStore();
        $position = 1;

        foreach ($orderedIds as $id) {
            $this->db->execute(
                "UPDATE `commerce_page_blocks`
                 SET `sort_order` = ?
                 WHERE `store_id` = ? AND `page_slug` = ? AND `id` = ?",
                [$position, $this->storeId(), $pageSlug, (int) $id]
            );
            $position++;
        }
    }

    /**
     * Toggle a block's enabled state.
     */
    public function setEnabled(int $id, bool $enabled): void
    {
        $this->db->execute(
            "UPDATE `commerce_page_blocks`
             SET `is_enabled` = ?
             WHERE `store_id` = ? AND `id` = ?",
            [$enabled ? 1 : 0, $this->storeId(), $id]
        );
    }

    /**
     * The next free sort_order on a page. Used by create()
     * to append a block to the end.
     */
    public function nextSortOrder(string $pageSlug): int
    {
        $max = $this->db->scalar(
            "SELECT COALESCE(MAX(`sort_order`), 0) + 1
             FROM `commerce_page_blocks`
             WHERE `store_id` = ? AND `page_slug` = ?",
            [$this->storeId(), $pageSlug]
        );
        return $max !== false && $max !== null ? (int) $max : 1;
    }

    /**
     * How many blocks are on a page. Used by the admin list.
     */
    public function countForPage(string $pageSlug): int
    {
        $count = $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_page_blocks`
             WHERE `store_id` = ? AND `page_slug` = ?",
            [$this->storeId(), $pageSlug]
        );
        return $count !== false && $count !== null ? (int) $count : 0;
    }
}
