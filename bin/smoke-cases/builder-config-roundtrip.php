<?php
declare(strict_types=1);

/**
 * Smoke case: builder config roundtrip.
 *
 * For every block type declared in BlockConfigRegistry,
 * this case:
 *
 *   1. Seeds a published block row in commerce_page_blocks.
 *   2. Builds a synthetic $posted config that exercises
 *      every field in the type's schema with a value
 *      matched to the field's type.
 *   3. Constructs a Request and calls
 *      AdminPageController::saveBlockConfig().
 *   4. Reads the resulting commerce_page_drafts row.
 *   5. Asserts every schema key is present in the saved
 *      config_json.
 *   6. Cleans up.
 *
 * The case does not assert exact values per field. Its job
 * is the flow: does every type reach the DB with the same
 * shape the schema declares. The exact values are pinned
 * in builder-config-shaper (added with the ConfigFieldRegistry
 * refactor).
 *
 * @param \CorporateAid\Core\Container $container
 * @param PDO $pdo  the test database connection
 */

use CorporateAid\Core\Request;
use CorporateAid\Commerce\Controllers\Admin\AdminPageController;
use CorporateAid\Commerce\Support\Blocks\BlockConfigRegistry;

return function ($container, PDO $pdo): bool|string {
    $storeCtx = $container->get(\CorporateAid\Commerce\Context\StoreContext::class);
    $storeId  = $storeCtx->id();

    $blockTypes = [
        'hero', 'product-grid', 'category-grid',
        'banner', 'rich-text', 'newsletter',
        'promo-panel', 'spacer',
    ];

    $failures = [];
    /** @var AdminPageController $controller */
    $controller = $container->get(AdminPageController::class);

    foreach ($blockTypes as $type) {
        $schema = BlockConfigRegistry::schema($type);
        if ($schema === []) {
            $failures[] = "{$type}: schema is empty";
            continue;
        }

        $pdo->prepare(
            "INSERT INTO commerce_page_blocks
             (store_id, page_slug, block_type, sort_order, config_json, is_enabled)
             VALUES (?, 'smoke-test', ?, 1, '{}', 1)"
        )->execute([$storeId, $type]);
        $blockId = (int) $pdo->lastInsertId();

        /** @var array<string,mixed> $posted */
        $posted = [];
        foreach ($schema as $fieldName => $meta) {
            $t = (string) ($meta['type'] ?? 'string');
            $posted[$fieldName] = match ($t) {
                'int'                   => '42',
                'boolean'               => '1',
                'select'                => (string) array_key_first($meta['options'] ?? ['x' => 'X']),
                'category_select'       => 'children',
                'category_multi_select' => ['1', '2'],
                'file'                  => '99',
                default                 => 'roundtrip-' . $fieldName,
            };
        }

        $request = new Request(
            'POST',
            '/commerce/ca-admin/pages/smoke-test/blocks/' . $blockId . '/config',
            '',
            [],
            ['config' => $posted],
            [],
            [],
            [],
            [],
            null
        );

        try {
            $controller->saveBlockConfig($request, [
                'slug' => 'smoke-test',
                'id'   => (string) $blockId,
            ]);
        } catch (\Throwable $e) {
            $failures[] = "{$type}: saveBlockConfig threw: " . $e->getMessage();
            $pdo->prepare("DELETE FROM commerce_page_blocks WHERE id = ?")
                ->execute([$blockId]);
            continue;
        }

        $stmt = $pdo->prepare(
            "SELECT config_json FROM commerce_page_drafts
             WHERE block_id = ? AND status = 'pending_update' LIMIT 1"
        );
        $stmt->execute([$blockId]);
        $written = $stmt->fetchColumn();

        if ($written === false) {
            $failures[] = "{$type}: no draft row written";
            $pdo->prepare("DELETE FROM commerce_page_blocks WHERE id = ?")
                ->execute([$blockId]);
            continue;
        }

        $decoded = json_decode((string) $written, true);
        if (!is_array($decoded)) {
            $failures[] = "{$type}: config_json did not decode to an array";
            $pdo->prepare("DELETE FROM commerce_page_blocks WHERE id = ?")
                ->execute([$blockId]);
            continue;
        }

        foreach ($schema as $fieldName => $meta) {
            if (!array_key_exists($fieldName, $decoded)) {
                $failures[] = "{$type}.{$fieldName}: missing from saved config";
            }
        }

        $pdo->prepare(
            "DELETE FROM commerce_page_drafts WHERE block_id = ?"
        )->execute([$blockId]);
        $pdo->prepare("DELETE FROM commerce_page_blocks WHERE id = ?")
            ->execute([$blockId]);
    }

    if ($failures !== []) {
        return implode(' | ', $failures);
    }
    return true;
};
