#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * One-shot CLI capture for builder-config-shaper.
 *
 * Drives AdminPageController::saveBlockConfig() against the
 * current implementation for every block type and every
 * field, and writes the resulting config_json to
 * bin/smoke-fixtures/builder-config-shaper.json.
 *
 * Run this BEFORE a refactor that changes the sanitiser.
 * After the refactor, `composer smoke` (which runs
 * builder-config-shaper) must pass against the captured
 * fixture byte-for-byte.
 *
 * Not part of composer check. Not part of composer smoke.
 * A developer tool for one specific moment: capturing a
 * baseline before a refactor.
 *
 * Usage:
 *   php bin/capture-builder-shaper.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$basePath = dirname(__DIR__);
$autoload = $basePath . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php. Run: composer install\n");
    exit(1);
}
require $autoload;

ob_start();
$app = \CorporateAid\Core\App::boot($basePath);
$container = $app->container();
ob_end_clean();

// Set the store context. CLI processes have no request-derived
// store, so StoreContext::id() throws until this runs. Same
// pattern the smoke harness uses (bin/smoke.php around line 243).
$pdo      = $container->get(\CorporateAid\Core\Database::class)->pdo();
$storeRow = $pdo->query(
    "SELECT `id`, `slug` FROM `commerce_stores` ORDER BY `id` ASC LIMIT 1"
)->fetch();
if ($storeRow === false) {
    fwrite(STDERR, "No store found. Run: php bin/seed.php\n");
    exit(1);
}
$storeCtx = $container->get(\CorporateAid\Commerce\Context\StoreContext::class);
$storeCtx->set((int) $storeRow['id'], (string) $storeRow['slug'], 'cli');
$storeId  = (int) $storeRow['id'];

use CorporateAid\Core\Request;
use CorporateAid\Commerce\Controllers\Admin\AdminPageController;
use CorporateAid\Commerce\Support\Blocks\BlockConfigRegistry;

$blockTypes = [
    'hero', 'product-grid', 'category-grid',
    'banner', 'rich-text', 'newsletter',
    'promo-panel', 'spacer',
];

$controller = $container->get(AdminPageController::class);
$fixture = [];

foreach ($blockTypes as $type) {
    $schema = BlockConfigRegistry::schema($type);
    $fixture[$type] = [];

    // Two posts per block type: one boolean set to true,
    // one boolean omitted. One file set numeric, one file
    // set non-numeric. To exercise both branches without
    // touching the DB more than twice per type.
    for ($pass = 1; $pass <= 2; $pass++) {
        $pdo->prepare(
            "INSERT INTO commerce_page_blocks
             (store_id, page_slug, block_type, sort_order, config_json, is_enabled)
             VALUES (?, 'shaper-capture', ?, 1, '{}', 1)"
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
            '/commerce/ca-admin/pages/shaper-capture/blocks/' . $blockId . '/config',
            '', [], ['config' => $posted], [], [], [], [], null
        );

        try {
            $controller->saveBlockConfig($request, [
                'slug' => 'shaper-capture',
                'id'   => (string) $blockId,
            ]);
        } catch (\Throwable $e) {
            fwrite(STDERR, "{$type} pass {$pass}: " . $e->getMessage() . "\n");
            fwrite(STDERR, $e->getTraceAsString() . "\n");
            $pdo->prepare("DELETE FROM commerce_page_blocks WHERE id = ?")
                ->execute([$blockId]);
            exit(1);
        }

        $stmt = $pdo->prepare(
            "SELECT config_json FROM commerce_page_drafts
             WHERE block_id = ? AND status = 'pending_update' LIMIT 1"
        );
        $stmt->execute([$blockId]);
        $written = $stmt->fetchColumn();
        $fixture[$type]['pass_' . $pass] = json_decode((string) $written, true);

        $pdo->prepare("DELETE FROM commerce_page_drafts WHERE block_id = ?")
            ->execute([$blockId]);
        $pdo->prepare("DELETE FROM commerce_page_blocks WHERE id = ?")
            ->execute([$blockId]);
    }
}

$fixturePath = $basePath . '/bin/smoke-fixtures/builder-config-shaper.json';
if (!is_dir(dirname($fixturePath))) {
    mkdir(dirname($fixturePath), 0755, true);
}
file_put_contents(
    $fixturePath,
    json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

echo "Captured fixture: {$fixturePath}\n";
echo "Run composer smoke to verify the shaper against this fixture.\n";
