<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Builder;

use CorporateAid\Commerce\Repositories\PageBlockRepository;
use CorporateAid\Commerce\Repositories\PageDraftRepository;

final class DraftManager
{
    public function __construct(
        private PageBlockRepository $blocks,
        private PageDraftRepository $drafts
    ) {}

    /**
     * Merge published blocks with the current draft state.
     * Returns an array of PageBlock models (some with temporary IDs).
     *
     * @return array<int, \CorporateAid\Commerce\Models\PageBlock>
     */
    public function getMergedBlocks(string $pageSlug, string $themeSlug): array
    {
        $published = $this->blocks->listForPage($pageSlug, false);
        $draftRows = $this->drafts->getDraftsForPage($pageSlug, $themeSlug);

        $merged = [];
        $deletedIds = [];

        foreach ($draftRows as $row) {
            if ($row['status'] === 'pending_delete') {
                $deletedIds[] = (int) $row['block_id'];
            }
        }

        // 1. Start with published blocks, applying updates and skipping deletes
        foreach ($published as $block) {
            if (in_array($block->id, $deletedIds, true)) {
                continue;
            }

            foreach ($draftRows as $row) {
                if ($row['status'] === 'pending_update' && (int) $row['block_id'] === $block->id) {
                    $draftConfig = json_decode($row['config_json'], true) ?: [];
                    $block->config = array_merge($block->config, $draftConfig);
                    if (!empty($row['sort_order'])) {
                        $block->sortOrder = (int) $row['sort_order'];
                    }
                }
            }
            $merged[] = $block;
        }

        // 2. Add pending additions
        foreach ($draftRows as $row) {
            if ($row['status'] === 'pending_add') {
                $block = new \CorporateAid\Commerce\Models\PageBlock();
                $block->id = (int) $row['id'] * -1; // Negative ID for temp additions
                $block->pageSlug = $pageSlug;
                $block->blockType = $row['block_type'];
                $block->sortOrder = (int) $row['sort_order'];
                $block->config = json_decode($row['config_json'], true) ?: [];
                $block->isEnabled = true;
                $merged[] = $block;
            }
        }

        // 3. Sort everything
        usort($merged, fn($a, $b) => $a->sortOrder <=> $b->sortOrder);

        return $merged;
    }

    /**
     * Duplicate a block. Works for both published blocks (positive
     * ID) and pending_add drafts (negative ID). The copy is always
     * created as a new pending_add so it shows up immediately in
     * the sidebar and only lands in the published table on Publish.
     */
    public function duplicateBlock(int $blockId, string $pageSlug, string $themeSlug): void
    {
        // Resolve the source block's type and config, whether it
        // lives in commerce_page_blocks or commerce_page_drafts.
        $sourceType   = '';
        $sourceConfig = [];

        if ($blockId < 0) {
            // Pending add.
            $drafts = $this->drafts->getDraftsForPage($pageSlug, $themeSlug);
            foreach ($drafts as $d) {
                if ((int) $d['id'] === abs($blockId) && $d['status'] === 'pending_add') {
                    $sourceType   = (string) $d['block_type'];
                    $sourceConfig = json_decode((string) $d['config_json'], true) ?: [];
                    break;
                }
            }
        } else {
            // Published block.
            $published = $this->blocks->findById($blockId);
            if ($published === null) {
                return;
            }
            $sourceType   = $published->blockType;
            $sourceConfig = $published->config;
        }

        if ($sourceType === '') {
            return;
        }

        // Compute the next sort_order. Use the merged block count
        // so a duplicate lands at the bottom regardless of which
        // table the source lives in.
        $merged = $this->getMergedBlocks($pageSlug, $themeSlug);
        $nextSort = count($merged) + 1;

        $this->drafts->addDraftBlockWithConfig(
            $pageSlug,
            $themeSlug,
            $sourceType,
            $nextSort,
            $sourceConfig
        );
    }

    public function hasDrafts(string $pageSlug, string $themeSlug): bool
    {
        return count($this->drafts->getDraftsForPage($pageSlug, $themeSlug)) > 0;
    }
}
