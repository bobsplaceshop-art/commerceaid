<?php
declare(strict_types=1);

/**
 * Smoke case: block renderer.
 *
 * Asserts BlockRenderer::render(PageBlock) dispatches every
 * registered block type to its partial. Asserts the safe-
 * fail contract for unknown type.
 *
 * Root class per type comes from the actual markup each
 * partial emits (see reads of resources/views/storefront/
 * blocks/*.php). Several types share the generic .sf-section
 * wrapper; hero, banner, promo-panel, category-grid have
 * their own specific class on the root. spacer emits a div
 * with an inline height and no class.
 *
 * Category-grid early-returns on empty($categories). The
 * test seeds one category in the smoke DB so the partial
 * renders (empty categories is a legitimate production
 * state, but the test's purpose here is to prove dispatch,
 * so it needs the partial to actually produce output).
 *
 * @param \CorporateAid\Core\Container $container
 * @param PDO $pdo
 */

use CorporateAid\Commerce\Models\PageBlock;
use CorporateAid\Commerce\Services\Builder\BlockRenderer;
use CorporateAid\Commerce\Support\Blocks\BlockTypeRegistry;

return function ($container, PDO $pdo): bool|string {
    $failures = [];

    /** @var BlockRenderer $renderer */
    $renderer = $container->get(BlockRenderer::class);

    $storeCtx = $container->get(\CorporateAid\Commerce\Context\StoreContext::class);
    $storeId  = $storeCtx->id();

    // Seed one category so category-grid.php does not early-
    // return on empty($categories). Schema: name, slug,
    // sort_order, status. Cleaned up at the end.
    $pdo->prepare(
        "INSERT INTO commerce_categories
         (store_id, name, slug, sort_order, status)
         VALUES (?, 'Renderer Smoke Category', 'renderer-smoke-cat', 1, 'active')"
    )->execute([$storeId]);
    $seededCategoryId = (int) $pdo->lastInsertId();

    $rootClassByType = [
        'hero'          => 'sf-hero',
        'product-grid'  => 'sf-section',              // generic wrapper (line 10 of product-grid.php)
        'category-grid' => 'sf-category-grid-section', // from $sectionClasses (line 52)
        'promo-panel'   => 'sf-promo',
        'banner'        => 'sf-banner',
        'rich-text'     => 'sf-section',
        'newsletter'    => 'sf-section',
        'spacer'        => 'height:',                  // inline style, no class
        'section'       => 'sf-section-block',
        'container'     => 'sf-container-block',
        'columns'       => 'sf-columns',
    ];

    foreach (BlockTypeRegistry::all() as $type => $meta) {
        $block = new PageBlock();
        $block->id        = 99999;
        $block->storeId   = $storeId;
        $block->pageSlug  = 'smoke-test';
        $block->blockType = $type;
        $block->config    = [];

        try {
            $html = $renderer->render($block);
        } catch (\Throwable $e) {
            $failures[] = "{$type}: render threw: " . $e->getMessage();
            continue;
        }

        if ($html === '') {
            $failures[] = "{$type}: render returned empty string";
            continue;
        }

        $needle = $rootClassByType[$type] ?? null;
        if ($needle !== null && !str_contains($html, $needle)) {
            $failures[] = "{$type}: expected '{$needle}' in output, got "
                . substr($html, 0, 100);
        }
    }

    // Cleanup.
    $pdo->prepare("DELETE FROM commerce_categories WHERE id = ?")
        ->execute([$seededCategoryId]);

    // Safe-fail: unknown type throws with a specific message.
    $bogus = new PageBlock();
    $bogus->id        = 99998;
    $bogus->blockType = 'does-not-exist';

    try {
        $renderer->render($bogus);
        $failures[] = 'unknown type: render did not throw';
    } catch (\RuntimeException $e) {
        $msg = $e->getMessage();
        if (!str_contains($msg, 'does-not-exist')) {
            $failures[] = "unknown type: message missing type name: {$msg}";
        }
        if (!str_contains($msg, '99998')) {
            $failures[] = "unknown type: message missing block id: {$msg}";
        }
    } catch (\Throwable $e) {
        $failures[] = 'unknown type: wrong exception class '
            . get_class($e) . ', expected RuntimeException';
    }

    if ($failures !== []) {
        return implode(' | ', $failures);
    }
    return true;
};
