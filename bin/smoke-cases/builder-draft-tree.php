<?php
declare(strict_types=1);

/**
 * Smoke case: draft tree.
 *
 * Seeds a Section (root), two published children, and one
 * pending_add child. Asserts three tree contracts:
 *
 *   1. getMergedBlocks() populates parentId on every block.
 *   2. getMergedTree() returns one root with three children.
 *   3. duplicateBlock() deep-copies: a new Section draft
 *      plus one child draft per descendant, each child's
 *      parent_id pointing at the new Section's negative
 *      draft id.
 *
 * @param \CorporateAid\Core\Container $container
 * @param PDO $pdo
 */

use CorporateAid\Commerce\Services\Builder\DraftManager;

return function ($container, PDO $pdo): bool|string {
    $failures = [];

    $storeCtx = $container->get(\CorporateAid\Commerce\Context\StoreContext::class);
    $storeId  = $storeCtx->id();

    /** @var DraftManager $dm */
    $dm = $container->get(DraftManager::class);

    // Seed: one root section, two published children.
    $pdo->prepare(
        "INSERT INTO commerce_page_blocks
         (store_id, page_slug, parent_id, block_type, sort_order, config_json, is_enabled)
         VALUES (?, 'smoke-tree', NULL, 'section', 1, '{}', 1)"
    )->execute([$storeId]);
    $sectionId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO commerce_page_blocks
         (store_id, page_slug, parent_id, block_type, sort_order, config_json, is_enabled)
         VALUES (?, 'smoke-tree', ?, 'rich-text', 1, '{}', 1)"
    )->execute([$storeId, $sectionId]);
    $child1Id = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO commerce_page_blocks
         (store_id, page_slug, parent_id, block_type, sort_order, config_json, is_enabled)
         VALUES (?, 'smoke-tree', ?, 'spacer', 2, '{}', 1)"
    )->execute([$storeId, $sectionId]);
    $child2Id = (int) $pdo->lastInsertId();

    // Seed: one pending_add child of the section.
    $pdo->prepare(
        "INSERT INTO commerce_page_drafts
         (store_id, theme_slug, page_slug, block_id, parent_id, block_type, sort_order, config_json, status)
         VALUES (?, 'modern', 'smoke-tree', NULL, ?, 'rich-text', 3, '{}', 'pending_add')"
    )->execute([$storeId, $sectionId]);
    $draftChildId = (int) $pdo->lastInsertId();

    // --- Assertion 1: flat merge carries parentId ---
    $flat = $dm->getMergedBlocks('smoke-tree', 'modern');
    $byId = [];
    foreach ($flat as $b) {
        $byId[$b->id] = $b;
    }

    if (!isset($byId[$sectionId]) || $byId[$sectionId]->parentId !== null) {
        $failures[] = 'flat: section parentId should be null';
    }
    if (!isset($byId[$child1Id]) || $byId[$child1Id]->parentId !== $sectionId) {
        $failures[] = "flat: child1 parentId should be {$sectionId}";
    }
    if (!isset($byId[$child2Id]) || $byId[$child2Id]->parentId !== $sectionId) {
        $failures[] = "flat: child2 parentId should be {$sectionId}";
    }
    $draftNegId = -1 * $draftChildId;
    if (!isset($byId[$draftNegId]) || $byId[$draftNegId]->parentId !== $sectionId) {
        $failures[] = "flat: draft child parentId should be {$sectionId}";
    }

    // --- Assertion 2: tree structure ---
    $tree = $dm->getMergedTree('smoke-tree', 'modern');
    if (count($tree) !== 1) {
        $failures[] = 'tree: expected 1 root, got ' . count($tree);
    } else {
        $root = $tree[0];
        if ($root->id !== $sectionId) {
            $failures[] = "tree: root id {$root->id}, expected {$sectionId}";
        }
        if (count($root->children) !== 3) {
            $failures[] = 'tree: expected 3 children, got ' . count($root->children);
        }
    }

    // --- Assertion 3: duplicateBlock deep-copies ---
    $dm->duplicateBlock($sectionId, 'smoke-tree', 'modern');

    $newDrafts = $pdo->prepare(
        "SELECT id, block_id, parent_id, block_type
           FROM commerce_page_drafts
          WHERE store_id = ? AND page_slug = 'smoke-tree'
            AND status = 'pending_add'
          ORDER BY id ASC"
    );
    $newDrafts->execute([$storeId]);
    $rows = $newDrafts->fetchAll(PDO::FETCH_ASSOC);

    $newSectionRow = null;
    $newChildRows  = [];
    foreach ($rows as $r) {
        if ((int) $r['id'] === $draftChildId) {
            continue;
        }
        if ($r['parent_id'] === null) {
            $newSectionRow = $r;
        } else {
            $newChildRows[] = $r;
        }
    }

    if ($newSectionRow === null) {
        $failures[] = 'duplicate: no new section draft found';
    } else {
        $newSectionNegId = -1 * (int) $newSectionRow['id'];
        if (count($newChildRows) !== 3) {
            $failures[] = 'duplicate: expected 3 child drafts, got ' . count($newChildRows);
        }
        foreach ($newChildRows as $child) {
            if ((int) $child['parent_id'] !== $newSectionNegId) {
                $failures[] = 'duplicate: child ' . $child['id']
                    . ' parent_id ' . $child['parent_id']
                    . ' expected ' . $newSectionNegId;
            }
        }
    }

    // Cleanup.
    $pdo->prepare("DELETE FROM commerce_page_drafts WHERE store_id = ? AND page_slug = 'smoke-tree'")
        ->execute([$storeId]);
    $pdo->prepare("DELETE FROM commerce_page_blocks WHERE store_id = ? AND page_slug = 'smoke-tree'")
        ->execute([$storeId]);

    if ($failures !== []) {
        return implode(' | ', $failures);
    }
    return true;
};
