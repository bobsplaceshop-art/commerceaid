<?php
declare(strict_types=1);

/**
 * Smoke case: builder config shaper.
 *
 * Companion to builder-config-roundtrip. The roundtrip case
 * proves the flow works; this case pins the exact value the
 * sanitiser produces per field per type.
 *
 * Reads bin/smoke-fixtures/builder-config-shaper.json
 * (produced by bin/capture-builder-shaper.php) and re-runs
 * the same capture, comparing byte-for-byte.
 *
 * Two passes per block type:
 *   pass 1  booleans set to true, file = '99'
 *   pass 2  booleans omitted, file = 'abc', multi-select = []
 *
 * Exercises both branches of every sanitiser that has one.
 *
 * The fixture is captured against the current
 * saveBlockConfig(). After the ConfigFieldRegistry refactor,
 * this case must pass unchanged. Any failure names the block
 * type, pass, and field whose value drifted.
 *
 * @param \CorporateAid\Core\Container $container
 * @param PDO $pdo
 */

use CorporateAid\Core\Request;
use CorporateAid\Commerce\Controllers\Admin\AdminPageController;
use CorporateAid\Commerce\Support\Blocks\BlockConfigRegistry;

return function ($container, PDO $pdo): bool|string {
    $fixturePath = dirname(__DIR__) . '/smoke-fixtures/builder-config-shaper.json';
    if (!is_file($fixturePath)) {
        return 'shaper fixture missing. Run: php bin/capture-builder-shaper.php';
    }

    $fixture = json_decode((string) file_get_contents($fixturePath), true);
    if (!is_array($fixture)) {
        return 'shaper fixture is not valid JSON';
    }

    $storeCtx = $container->get(\CorporateAid\Commerce\Context\StoreContext::class);
    $storeId  = $storeCtx->id();
    $controller = $container->get(AdminPageController::class);

    $failures = [];

    foreach ($fixture as $type => $passes) {
        $schema = BlockConfigRegistry::schema($type);
        if ($schema === []) {
            $failures[] = "{$type}: schema is empty";
            continue;
        }

        for ($pass = 1; $pass <= 2; $pass++) {
            $pdo->prepare(
                "INSERT INTO commerce_page_blocks
                 (store_id, page_slug, block_type, sort_order, config_json, is_enabled)
                 VALUES (?, 'shaper-verify', ?, 1, '{}', 1)"
            )->execute([$storeId, $type]);
            $blockId = (int) $pdo->lastInsertId();

            $posted = [];
            foreach ($schema as $fieldName => $meta) {
                $t = (string) ($meta['type'] ?? 'string');
                $posted[$fieldName] = match ($t) {
                    'int'                   => '42',
                    'boolean'               => $pass === 1 ? '1' : null,
                    'select'                => (string) array_key_first($meta['options'] ?? ['x' => 'X']),
                    'category_select'       => 'children',
                    'category_multi_select' => $pass === 1 ? ['1', '2'] : [],
                    'file'                  => $pass === 1 ? '99' : 'abc',
                    'color'                 => '#ff0000',
                    default                 => 'shaper-' . $fieldName,
                };
                if ($posted[$fieldName] === null) {
                    unset($posted[$fieldName]);
                }
            }

            $request = new Request(
                'POST',
                '/commerce/ca-admin/pages/shaper-verify/blocks/' . $blockId . '/config',
                '', [], ['config' => $posted], [], [], [], [], null
            );

            try {
                $controller->saveBlockConfig($request, [
                    'slug' => 'shaper-verify',
                    'id'   => (string) $blockId,
                ]);
            } catch (\Throwable $e) {
                $failures[] = "{$type} pass {$pass}: threw " . $e->getMessage();
                $pdo->prepare("DELETE FROM commerce_page_blocks WHERE id = ?")
                    ->execute([$blockId]);
                continue;
            }

            $stmt = $pdo->prepare(
                "SELECT config_json FROM commerce_page_drafts
                 WHERE block_id = ? AND status = 'pending_update' LIMIT 1"
            );
            $stmt->execute([$blockId]);
            $actual = json_decode((string) $stmt->fetchColumn(), true);

            $expected = $passes['pass_' . $pass] ?? null;
            if (!is_array($actual) || !is_array($expected)) {
                $failures[] = "{$type} pass {$pass}: could not decode";
                $pdo->prepare("DELETE FROM commerce_page_drafts WHERE block_id = ?")->execute([$blockId]);
                $pdo->prepare("DELETE FROM commerce_page_blocks WHERE id = ?")->execute([$blockId]);
                continue;
            }

            foreach ($expected as $fieldName => $expectedValue) {
                if (!array_key_exists($fieldName, $actual)) {
                    $failures[] = "{$type} pass {$pass}.{$fieldName}: missing";
                    continue;
                }
                if ($actual[$fieldName] !== $expectedValue) {
                    $failures[] = "{$type} pass {$pass}.{$fieldName}: " .
                        'expected ' . json_encode($expectedValue) . ', got ' . json_encode($actual[$fieldName]);
                }
            }

            $pdo->prepare("DELETE FROM commerce_page_drafts WHERE block_id = ?")
                ->execute([$blockId]);
            $pdo->prepare("DELETE FROM commerce_page_blocks WHERE id = ?")
                ->execute([$blockId]);
        }
    }

    if ($failures !== []) {
        return implode(' | ', $failures);
    }
    return true;
};
