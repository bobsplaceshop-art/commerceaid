<?php
/**
 * Block config modal. Opened by the pencil icon on each
 * block tile. Renders a form with one field per schema entry.
 *
 * Vars:
 *   @var \CorporateAid\Commerce\Models\PageBlock $block
 *   @var string                                  $slug         page slug
 *   @var array<int, \CorporateAid\Commerce\Models\Category> $categories
 */
$block = $block ?? null;
$slug  = (string) ($slug ?? '');
if ($block === null) {
    return;
}

$schema = \CorporateAid\Commerce\Support\Blocks\BlockConfigRegistry::schema($block->blockType);
$config = $block->config;
?>
<div class="modal fade"
     id="ra-block-config-modal-<?= (int) $block->id ?>"
     tabindex="-1"
     aria-labelledby="ra-block-config-label-<?= (int) $block->id ?>"
     aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background:#1a2235; color:#e5e7eb; border:1px solid #2d3748;">
            <div class="modal-header" style="border-bottom:1px solid #2d3748;">
                <h5 class="modal-title" id="ra-block-config-label-<?= (int) $block->id ?>">
                    Configure <?= e($block->typeLabel()) ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST"
                  action="/commerce/ca-admin/pages/<?= e($slug) ?>/blocks/<?= (int) $block->id ?>/config">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <?php if (empty($schema)): ?>
                        <p class="text-muted mb-0">
                            This block type has no configuration fields yet.
                        </p>
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
                <div class="modal-footer" style="border-top:1px solid #2d3748;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save config</button>
                </div>
            </form>
        </div>
    </div>
</div>
