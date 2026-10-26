<?php
/**
 * Standalone config page for a single block.
 *
 * @var string $slug
 * @var \CorporateAid\Commerce\Models\PageBlock $block
 * @var array<int, \CorporateAid\Commerce\Models\Category> $categories
 * @var array<int, \CorporateAid\Commerce\Models\MediaItem> $mediaLibrary
 */
$schema     = \CorporateAid\Commerce\Support\Blocks\BlockConfigRegistry::schema($block->blockType);
$config     = $block->config;
$sections   = \CorporateAid\Commerce\Support\Blocks\ConfigGroup::group($schema, $config, $categories);
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
                <?php foreach ($sections as $section): ?>
                    <?php
                    // Build the body HTML for this section.
                    $bodyHtml = '';
                    foreach ($section['fields'] as $item) {
                        if (isset($item['pair'])) {
                            $pairHtml = '<div class="ra-config-pair">';
                            foreach ($item['fields'] as $pf) {
                                $pairHtml .= '<div class="ra-config-pair-cell">'
                                    . view_partial('admin/pages/_config-field', [
                                        'name'       => 'config[' . $pf['name'] . ']',
                                        'meta'       => $pf['meta'],
                                        'value'      => $pf['value'],
                                        'categories' => $categories,
                                    ])
                                    . '</div>';
                            }
                            $pairHtml .= '</div>';
                            $bodyHtml .= $pairHtml;
                        } else {
                            $bodyHtml .= view_partial('admin/pages/_config-field', [
                                'name'       => 'config[' . $item['name'] . ']',
                                'meta'       => $item['meta'],
                                'value'      => $item['value'],
                                'categories' => $categories,
                            ]);
                        }
                    }
                    ?>
                    <?= view_partial('admin/pages/_config-section', [
                        'sectionKey' => $section['section'],
                        'title'      => $section['title'],
                        'icon'       => $section['icon'],
                        'bodyHtml'   => $bodyHtml,
                        'count'      => $section['count'],
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
