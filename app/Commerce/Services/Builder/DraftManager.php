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
                    // Overlay parent_id when the draft row has a
                    // non-null value. Null means "keep the
                    // published parent." A non-null value means
                    // "reparent" — the current UI does not
                    // trigger reparenting but the merge is
                    // prepared for it.
                    if (array_key_exists('parent_id', $row) && $row['parent_id'] !== null) {
                        $block->parentId = (int) $row['parent_id'];
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
                // parent_id: positive for a published parent,
                // negative for a pending_add parent. Stored
                // as-is; getMergedTree() resolves against the
                // merged id space.
                if (array_key_exists('parent_id', $row) && $row['parent_id'] !== null) {
                    $block->parentId = (int) $row['parent_id'];
                }
                $merged[] = $block;
            }
        }

        // 3. Sort everything
        usort($merged, fn($a, $b) => $a->sortOrder <=> $b->sortOrder);

        return $merged;
    }

    /**
     * Return the page as a tree: roots (parentId null) with
     * their direct children in $block->children.
     *
     * Children whose parentId names an id not in the merged
     * set (a parent that was deleted while its children
     * survived, or a stale draft) attach to a synthetic
     * '__orphaned__' root so the tree never loses content
     * silently. The caller can inspect the child's parentId
     * to see the missing id.
     *
     * @return array<int, \CorporateAid\Commerce\Models\PageBlock>
     */
    public function getMergedTree(string $pageSlug, string $themeSlug): array
    {
        $flat = $this->getMergedBlocks($pageSlug, $themeSlug);

        $byId = [];
        foreach ($flat as $block) {
            $byId[$block->id] = $block;
        }

        $roots = [];
        $orphans = [];

        foreach ($flat as $block) {
            if ($block->parentId === null) {
                $roots[] = $block;
                continue;
            }

            if (isset($byId[$block->parentId])) {
                $byId[$block->parentId]->children[] = $block;
            } else {
                $orphans[] = $block;
            }
        }

        if ($orphans !== []) {
            $synthetic = new \CorporateAid\Commerce\Models\PageBlock();
            $synthetic->id        = 0;
            $synthetic->pageSlug  = $pageSlug;
            $synthetic->blockType = '__orphaned__';
            $synthetic->config    = ['reason' => 'parent block missing'];
            $synthetic->children  = $orphans;
            $roots[] = $synthetic;
        }

        return $roots;
    }

    /**
     * Duplicate a block. Works for both published blocks (positive
     * ID) and pending_add drafts (negative ID). The copy is always
     * created as a new pending_add so it shows up immediately in
     * the sidebar and only lands in the published table on Publish.
     */
    public function duplicateBlock(int $blockId, string $pageSlug, string $themeSlug): void
    {
        $this->duplicateSubtree($blockId, $pageSlug, $themeSlug, null);
    }

    /**
     * Duplicate one block and, if it has children, recurse.
     *
     * The new draft row carries $newParentId as its parent_id
     * — null when the source is a root, the new draft parent
     * id (negative) when the source is a child. Children of
     * the source are found by scanning the merged flat list
     * for blocks whose parentId matches the source's id.
     *
     * Sort order within the destination parent: count the
     * siblings already at that parent, add one.
     */
    private function duplicateSubtree(
        int $blockId,
        string $pageSlug,
        string $themeSlug,
        ?int $newParentId
    ): void {
        $sourceType   = '';
        $sourceConfig = '';

        if ($blockId < 0) {
            $drafts = $this->drafts->getDraftsForPage($pageSlug, $themeSlug);
            foreach ($drafts as $d) {
                if ((int) $d['id'] === abs($blockId) && $d['status'] === 'pending_add') {
                    $sourceType   = (string) $d['block_type'];
                    $sourceConfig = json_decode((string) $d['config_json'], true) ?: [];
                    break;
                }
            }
        } else {
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

        // Sibling count under the destination parent.
        $merged = $this->getMergedBlocks($pageSlug, $themeSlug);
        $siblingCount = 0;
        foreach ($merged as $m) {
            if ($m->parentId === $newParentId) {
                $siblingCount++;
            }
        }
        $nextSort = $siblingCount + 1;

        $newDraftId = $this->drafts->addDraftBlockWithConfig(
            $pageSlug,
            $themeSlug,
            $sourceType,
            $nextSort,
            $sourceConfig,
            $newParentId
        );

        // Recurse into children. A child is a block whose
        // parentId matches the source's id (positive for
        // published, negative for pending_add).
        foreach ($merged as $candidate) {
            if ($candidate->parentId === $blockId) {
                $this->duplicateSubtree(
                    $candidate->id,
                    $pageSlug,
                    $themeSlug,
                    $newDraftId
                );
            }
        }
    }

    public function hasDrafts(string $pageSlug, string $themeSlug): bool
    {
        return count($this->drafts->getDraftsForPage($pageSlug, $themeSlug)) > 0;
    }
}
