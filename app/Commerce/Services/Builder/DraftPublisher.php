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
            foreach ($draftRows as $row) {
                if ($row['status'] === 'pending_delete') {
                    $this->blocks->delete((int) $row['block_id']);
                } elseif ($row['status'] === 'pending_update') {
                    $existing = $this->blocks->findById((int) $row['block_id']);
                    if ($existing) {
                        $draftConfig = json_decode($row['config_json'], true) ?: [];
                        // The draft carries the merchant's current
                        // config AND the current sort_order (written
                        // by PageDraftRepository::saveReorder() when
                        // the merchant drags a block). Both must land
                        // on the published row at publish time, or a
                        // reordered block publishes at its old
                        // position.
                        $this->blocks->update(
                            (int) $row['block_id'],
                            array_merge($existing->config, $draftConfig),
                            (int) $row['sort_order']
                        );
                    }
                } elseif ($row['status'] === 'pending_add') {
                    $draftConfig = json_decode($row['config_json'], true) ?: [];
                    $this->blocks->create($pageSlug, $row['block_type'], $draftConfig, (int) $row['sort_order']);
                }
            }

            $this->drafts->clearDrafts($pageSlug, $themeSlug);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
