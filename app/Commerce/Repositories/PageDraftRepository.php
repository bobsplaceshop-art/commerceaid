<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\PageBlock;

final class PageDraftRepository extends CommerceRepository
{
    /**
     * Fetch all drafts for a specific page and theme.
     *
     * @return array<int, array<string,mixed>> Raw rows
     */
    public function getDraftsForPage(string $pageSlug, string $themeSlug): array
    {
        return $this->db->select(
            "SELECT * FROM `commerce_page_drafts`
             WHERE `store_id` = ? AND `page_slug` = ? AND `theme_slug` = ?",
            [$this->storeId(), $pageSlug, $themeSlug]
        );
    }

    /**
     * Upsert a pending update for an existing block.
     */
    /** @param array<string,mixed> $config */
    public function saveUpdate(int $blockId, string $pageSlug, string $themeSlug, array $config): void
    {
        $this->db->execute(
            "INSERT INTO `commerce_page_drafts`
                (`store_id`, `theme_slug`, `page_slug`, `block_id`, `block_type`, `config_json`, `status`)
             VALUES (?, ?, ?, ?, 'update', ?, 'pending_update')
             ON DUPLICATE KEY UPDATE `config_json` = VALUES(`config_json`)",
            [$this->storeId(), $themeSlug, $pageSlug, $blockId, json_encode($config)]
        );
    }

    /**
     * Insert a pending addition with a pre-populated config.
     * Used by duplicateBlock() to clone a source block's config.
     *
     * @param array<string,mixed> $config
     */
    public function addDraftBlockWithConfig(
        string $pageSlug,
        string $themeSlug,
        string $blockType,
        int $sortOrder,
        array $config,
        ?int $parentId = null
    ): int {
        // The parentId may be a published id (positive) or a
        // pending_add draft id (negative). Both are stored as-
        // is; DraftPublisher::publish() resolves negative ids
        // to their new published id in two passes.
        $draftId = $this->db->insert(
            "INSERT INTO `commerce_page_drafts`
                (`store_id`, `theme_slug`, `page_slug`, `block_id`, `parent_id`, `block_type`, `sort_order`, `config_json`, `status`)
             VALUES (?, ?, ?, NULL, ?, ?, ?, ?, 'pending_add')",
            [
                $this->storeId(),
                $themeSlug,
                $pageSlug,
                $parentId,
                $blockType,
                $sortOrder,
                json_encode($config),
            ]
        );
        return (int) $draftId * -1;
    }

    /**
     * Update the config of an existing pending_add row.
     * The ID here is the raw draft table row ID (positive),
     * passed in as negative from the UI.
     */
    /** @param array<string,mixed> $config */
    public function updatePendingAdd(int $draftId, array $config): void
    {
        $this->db->execute(
            "UPDATE `commerce_page_drafts` 
             SET `config_json` = ? 
             WHERE `id` = ? AND `store_id` = ? AND `status` = 'pending_add'",
            [json_encode($config), $draftId, $this->storeId()]
        );
    }

    /**
     * Insert a pending addition. Returns the negative temporary ID.
     */
    public function addDraftBlock(string $pageSlug, string $themeSlug, string $blockType, int $sortOrder): int
    {
        // Database::insert() returns the last insert ID directly.
        $draftId = $this->db->insert(
            "INSERT INTO `commerce_page_drafts`
                (`store_id`, `theme_slug`, `page_slug`, `block_id`, `block_type`, `sort_order`, `config_json`, `status`)
             VALUES (?, ?, ?, NULL, ?, ?, '{}', 'pending_add')",
            [$this->storeId(), $themeSlug, $pageSlug, $blockType, $sortOrder]
        );
        // Return a negative ID based on the new draft row's ID to avoid collisions
        return (int) $draftId * -1;
    }

    /**
     * Mark an existing block for deletion.
     */
    public function markDelete(int $blockId, string $pageSlug, string $themeSlug): void
    {
        $this->db->execute(
            "INSERT INTO `commerce_page_drafts`
                (`store_id`, `theme_slug`, `page_slug`, `block_id`, `block_type`, `config_json`, `status`)
             VALUES (?, ?, ?, ?, 'unknown', '{}', 'pending_delete')
             ON DUPLICATE KEY UPDATE `status` = 'pending_delete'",
            [$this->storeId(), $themeSlug, $pageSlug, $blockId]
        );
    }

    /**
     * Save a reorder (this updates the sort_order on both updates and adds).
     */
    /** @param array<int,int> $orderedIds */
    public function saveReorder(string $pageSlug, string $themeSlug, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            // If ID is negative, it's a pending add. If positive, it's an existing block.
            // We update the corresponding draft row.
            if ($id < 0) {
                $draftId = abs($id);
                $this->db->execute(
                    "UPDATE `commerce_page_drafts` SET `sort_order` = ? WHERE `id` = ?",
                    [$index + 1, $draftId]
                );
            } else {
                $this->db->execute(
                    "UPDATE `commerce_page_drafts` SET `sort_order` = ? WHERE `block_id` = ? AND `status` = 'pending_update'",
                    [$index + 1, $id]
                );
                // If there was no update draft, we need to create one just to hold the new order
                $exists = $this->db->scalar("SELECT COUNT(*) FROM `commerce_page_drafts` WHERE `block_id` = ? AND `status` = 'pending_update'", [$id]);
                if (!$exists) {
                    $this->db->execute(
                        "INSERT INTO `commerce_page_drafts` (`store_id`, `theme_slug`, `page_slug`, `block_id`, `block_type`, `sort_order`, `config_json`, `status`) VALUES (?, ?, ?, ?, 'unknown', ?, '{}', 'pending_update')",
                        [$this->storeId(), $themeSlug, $pageSlug, $id, $index + 1]
                    );
                }
            }
        }
    }

    /**
     * Remove a pending addition draft row (for when the merchant
     * deletes a block that was only just added).
     */
    public function removePendingAdd(int $draftId): void
    {
        $this->db->execute(
            "DELETE FROM `commerce_page_drafts` WHERE `id` = ? AND `store_id` = ? AND `status` = 'pending_add'",
            [$draftId, $this->storeId()]
        );
    }

    /**
     * Clear all drafts for a page + theme.
     */
    public function clearDrafts(string $pageSlug, string $themeSlug): void
    {
        $this->db->execute(
            "DELETE FROM `commerce_page_drafts` WHERE `store_id` = ? AND `page_slug` = ? AND `theme_slug` = ?",
            [$this->storeId(), $pageSlug, $themeSlug]
        );
    }
}
