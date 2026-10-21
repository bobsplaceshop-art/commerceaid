<?php
/**
 * Standalone config page for a single block.
 *
 * The normal path is the inline modal on the page editor.
 * This full-page route exists as a fallback: if the modal
 * fails to open, the merchant can navigate here directly.
 *
 * @var string $slug
 * @var \CorporateAid\Commerce\Models\PageBlock $block
 * @var array<int, \CorporateAid\Commerce\Models\Category> $categories
 * @var array<int, \CorporateAid\Commerce\Models\MediaItem> $mediaLibrary
 */
$schema = \CorporateAid\Commerce\Support\Blocks\BlockConfigRegistry::schema($block->blockType);
$config = $block->config;
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1">Configure <?= e($block->typeLabel()) ?></h1>
        <p class="muted mb-0">Page: <?= e($slug) ?></p>
    </div>
    <a href="/commerce/ca-admin/pages/<?= e($slug) ?>" class="ra-btn ra-btn-outline">
        <i class="bi bi-arrow-left me-1"></i> Back to page
    </a>
</div>

<div class="ra-card">
    <form method="POST" action="/commerce/ca-admin/pages/<?= e($slug) ?>/blocks/<?= (int) $block->id ?>/config">
        <?= csrf_field() ?>
        <div class="ra-card-body">
            <?php if (empty($schema)): ?>
                <p class="text-muted mb-0">This block type has no configuration fields yet.</p>
            <?php else: ?>
                <?php foreach ($schema as $name => $meta): ?>
                    <?= view_partial('admin/pages/_config-field', [
                        'name'       => $name,
                        'meta'       => $meta,
                        'value'      => $config[$name] ?? ($meta['default'] ?? ''),
                        'categories' => $categories,
                    ]) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="ra-sticky-actions">
            <div class="ra-sticky-actions-inner">
                <a href="/commerce/ca-admin/pages/<?= e($slug) ?>" class="ra-btn ra-btn-outline">Cancel</a>
                <button type="submit" class="ra-btn ra-btn-primary">Save config</button>
            </div>
        </div>
        <div class="ra-sticky-spacer"></div>
    </form>
</div>
