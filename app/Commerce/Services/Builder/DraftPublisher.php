<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Builder;

use CorporateAid\Commerce\Repositories\PageBlockRepository;
use CorporateAid\Commerce\Repositories\PageDraftRepository;

final class DraftPublisher
{
    public function __construct(
        private PageBlockRepository $blocks,
        private PageDraftRepository $drafts,
        private \CorporateAid\Core\Database $db
    ) {}

    public function publish(string $pageSlug, string $themeSlug): void
    {
        $draftRows = $this->drafts->getDraftsForPage($pageSlug, $themeSlug);
        if (empty($draftRows)) {
            return;
        }

        $this->db->beginTransaction();

        try {
            // ---- Pass 1: deletes, updates, and root additions ----
            //
            // Roots first because a child whose parent_id names
            // a pending_add draft id needs the parent to have a
            // positive published id before the child inserts.
            //
            // draftIdToPublishedId records the mapping so pass 2
            // can resolve negative parent_id references.
            /** @var array<int,int> $draftIdToPublishedId */
            $draftIdToPublishedId = [];

            foreach ($draftRows as $row) {
                if ($row['status'] === 'pending_delete') {
                    $this->blocks->delete((int) $row['block_id']);
                    continue;
                }

                if ($row['status'] === 'pending_update') {
                    $existing = $this->blocks->findById((int) $row['block_id']);
                    if ($existing) {
                        $draftConfig = json_decode($row['config_json'], true) ?: [];
                        $this->blocks->update(
                            (int) $row['block_id'],
                            array_merge($existing->config, $draftConfig),
                            (int) $row['sort_order']
                        );
                    }
                    continue;
                }

                if ($row['status'] === 'pending_add') {
                    $parentId = isset($row['parent_id'])
                        ? (int) $row['parent_id']
                        : null;

                    // Only root drafts publish in pass 1.
                    // Children wait until their parent has a
                    // positive published id.
                    if ($parentId !== null) {
                        continue;
                    }

                    $draftConfig = json_decode($row['config_json'], true) ?: [];
                    $newId = $this->blocks->create(
                        $pageSlug,
                        $row['block_type'],
                        $draftConfig,
                        (int) $row['sort_order']
                    );
                    $draftIdToPublishedId[(int) $row['id']] = (int) $newId;
                }
            }

            // ---- Pass 2: child additions ----
            //
            // Every pending_add whose parent_id is a negative
            // draft id resolves to the parent's new positive
            // id from $draftIdToPublishedId. A child whose
            // parent was not a pending_add in this batch (its
            // parent is already published, positive id) is
            // also handled here because pass 1 only skipped
            // parent_id null — this pass catches everything
            // parent_id !== null.
            foreach ($draftRows as $row) {
                if ($row['status'] !== 'pending_add') {
                    continue;
                }

                if (!isset($row['parent_id'])) {
                    continue; // already published in pass 1 (parent_id null)
                }

                $parentId = (int) $row['parent_id'];

                // Resolve a negative draft parent id to the
                // published id created in pass 1.
                if ($parentId < 0) {
                    $draftParentId = abs($parentId);
                    if (!isset($draftIdToPublishedId[$draftParentId])) {
                        throw new \RuntimeException(
                            "Publish: child draft #{$row['id']} references "
                            . "missing parent draft #{$draftParentId}"
                        );
                    }
                    $parentId = $draftIdToPublishedId[$draftParentId];
                }

                $draftConfig = json_decode($row['config_json'], true) ?: [];
                $this->blocks->create(
                    $pageSlug,
                    $row['block_type'],
                    $draftConfig,
                    (int) $row['sort_order'],
                    $parentId
                );
            }

            $this->drafts->clearDrafts($pageSlug, $themeSlug);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
