<?php
/**
 * Block type picker. Opens from the "Add block" button on the
 * page editor canvas. Clicking a tile submits a POST to the
 * addBlock route with the chosen type.
 *
 * @var string $slug
 */
$types = [
    'hero'          => ['label' => 'Hero',          'icon' => 'bi-image',        'hint' => 'A big banner with headline and CTA.'],
    'product-grid'  => ['label' => 'Product Grid',  'icon' => 'bi-grid-3x3-gap', 'hint' => 'A row of products (new arrivals, best sellers, category).'],
    'category-grid' => ['label' => 'Category Grid', 'icon' => 'bi-grid',         'hint' => 'The "Shop by Category" row.'],
    'promo-panel'   => ['label' => 'Promo Panel',   'icon' => 'bi-tag',          'hint' => 'A single promotional tile with image and CTA.'],
    'banner'        => ['label' => 'Banner',        'icon' => 'bi-rectangle-wide','hint' => 'A full-width banner with text overlay.'],
    'rich-text'     => ['label' => 'Rich Text',     'icon' => 'bi-text-paragraph','hint' => 'A block of copy (headings, paragraphs, links).'],
    'newsletter'    => ['label' => 'Newsletter',    'icon' => 'bi-envelope',     'hint' => 'A subscribe form.'],
];
?>
<div class="modal fade" id="ra-block-picker-modal" tabindex="-1" aria-labelledby="ra-block-picker-label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background:#1a2235; color:#e5e7eb; border:1px solid #2d3748;">
            <div class="modal-header" style="border-bottom:1px solid #2d3748;">
                <h5 class="modal-title" id="ra-block-picker-label">Add a block</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div style="display:grid; grid-template-columns:repeat(2, 1fr); gap:.75rem;">
                    <?php foreach ($types as $key => $meta): ?>
                        <form method="POST" action="/commerce/ca-admin/pages/<?= e($slug) ?>/blocks" style="margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="block_type" value="<?= e($key) ?>">
                            <button type="submit"
                                    style="width:100%; text-align:left; padding:1rem; background:var(--ra-surface-2,#0f172a); border:1px solid var(--ra-border,#2d3748); border-radius:6px; color:var(--ra-fg,#e2e8f0); cursor:pointer;"
                                    onmouseover="this.style.borderColor='#d4af37';"
                                    onmouseout="this.style.borderColor='var(--ra-border,#2d3748)';">
                                <div style="display:flex; align-items:center; gap:.75rem; margin-bottom:.4rem;">
                                    <i class="bi <?= e($meta['icon']) ?>" style="font-size:1.4rem; color:#d4af37;"></i>
                                    <span style="font-weight:600;"><?= e($meta['label']) ?></span>
                                </div>
                                <div style="font-size:.75rem; color:var(--ra-muted,#94a3b8); line-height:1.4;">
                                    <?= e($meta['hint']) ?>
                                </div>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
