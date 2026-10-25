<?php
/**
 * Page Body Editor (Customizer Shell).
 *
 * @var string $slug
 * @var string $label
 * @var array $blocks
 * @var array $categories
 * @var array $mediaLibrary
 */
?>
<div class="ra-customizer" id="ra-customizer" data-slug="<?= e($slug) ?>" data-csrf="<?= e(csrf_token()) ?>">

    <div class="ra-customizer-topbar d-flex justify-content-between align-items-center px-3 w-100">
        <div class="d-flex align-items-center gap-2">
            <button type="button"
                    class="ra-customizer-mobile-toggle"
                    data-customizer-sidebar-toggle
                    aria-label="Show blocks"
                    aria-expanded="false">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div class="ra-customizer-brand fw-bold text-light d-flex align-items-center">
                <i class="bi bi-layout-text-window me-2"></i> <?= e($label) ?>
            </div>
        </div>

        <div class="ra-customizer-devices d-flex gap-2 bg-dark p-1 rounded border border-secondary">
            <button type="button" class="device-btn active" data-device="desktop" title="Desktop"><i class="bi bi-display fs-5"></i></button>
            <button type="button" class="device-btn" data-device="tablet" title="Tablet"><i class="bi bi-tablet fs-5"></i></button>
            <button type="button" class="device-btn" data-device="mobile" title="Mobile"><i class="bi bi-phone fs-5"></i></button>
        </div>

        <div class="ra-customizer-actions d-flex gap-2 align-items-center">
            <button type="button" class="ra-btn ra-btn-outline ra-btn-sm text-light border-secondary" data-page-settings-open>
                <i class="bi bi-gear me-1"></i> Page Settings
            </button>
            <a href="/commerce/ca-admin/pages" class="ra-btn ra-btn-outline ra-btn-sm text-light border-secondary">Close</a>
            <form method="POST" action="/commerce/ca-admin/pages/<?= e($slug) ?>/save-draft" class="m-0">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-outline ra-btn-sm text-light border-secondary">Save Draft</button>
            </form>
            <?php if (!empty($hasDraft)): ?>
                <form method="POST" action="/commerce/ca-admin/pages/<?= e($slug) ?>/discard-draft" class="m-0" onsubmit="return confirm('Discard the draft? The live site was never changed.');">
                    <?= csrf_field() ?>
                    <button type="submit" class="ra-btn ra-btn-outline ra-btn-sm text-light border-danger" style="border-color:#dc2626;color:#dc2626;">Discard</button>
                </form>
            <?php endif; ?>
            <form method="POST" action="/commerce/ca-admin/pages/<?= e($slug) ?>/publish" class="m-0">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-primary ra-btn-sm">Publish</button>
            </form>
        </div>
    </div>

    <div class="ra-customizer-body">
        <div class="ra-customizer-sidebar">
            <div class="ra-customizer-panel" id="panel-blocks">
                <div class="p-3 border-bottom border-dark d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Page Blocks</h6>
                    <button type="button" class="ra-btn ra-btn-primary ra-btn-sm" data-bs-toggle="modal" data-bs-target="#ra-block-picker-modal">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
                <div class="ra-builder-blocks p-2" id="ra-blocks-list" data-reorder-url="/commerce/ca-admin/pages/<?= e($slug) ?>/blocks/reorder" data-csrf="<?= e(csrf_token()) ?>" data-page-slug="<?= e($slug) ?>">
                    <?php if (empty($blocks)): ?>
                        <p class="text-muted small text-center py-3">No blocks yet. Click + to add one.</p>
                    <?php else: ?>
                        <?php
                        // Emit one <template class="row-data"> per block
                        // with the block's metadata. The row markup itself
                        // comes from the single <template id="tpl-block-row">
                        // below, so PHP and JS render identical rows.
                        $emitRowData = function ($block, int $depth = 0) use (&$emitRowData): void {
                            $isStructural = \CorporateAid\Commerce\Support\Blocks\BlockTypeRegistry::acceptsChildren($block->blockType);
                            ?>
                            <template class="row-data"
                                      data-block-id="<?= (int) $block->id ?>"
                                      data-parent-id="<?= (int) ($block->parentId ?? 0) ?>"
                                      data-block-type="<?= e($block->blockType) ?>"
                                      data-type-label="<?= e($block->typeLabel()) ?>"
                                      data-is-structural="<?= $isStructural ? '1' : '0' ?>"
                                      data-depth="<?= $depth ?>"></template>
                            <?php
                            foreach ($block->children as $child) {
                                $emitRowData($child, $depth + 1);
                            }
                        };
                        foreach ($blocks as $block) {
                            $emitRowData($block, 0);
                        }
                        ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ra-customizer-panel" id="panel-settings" style="display: none;">
                <div class="p-3 border-bottom border-dark d-flex align-items-center">
                    <button type="button" class="ra-icon-btn me-2" id="back-to-blocks"><i class="bi bi-arrow-left"></i></button>
                    <h6 class="mb-0 fw-bold" id="settings-title">Block Settings</h6>
                </div>
                <div class="p-3" id="settings-content"></div>
            </div>
        </div>

        <div class="ra-customizer-preview">
            <div class="ra-preview-frame-wrapper" id="preview-wrapper">
                <?php
                // Point the iframe at the real storefront with the
                // preview token. The storefront reads the token and
                // merges the session draft. Non-home pages (about-us,
                // contact) fall back to the home page until custom
                // page routes exist (Session 5).
                //
                // The cache-buster is keyed on the mtime of the
                // storefront CSS, so the iframe picks up new CSS
                // on the next customizer load (Chrome caches
                // iframe HTTP separately from the parent page).
                $cssFile = dirname(__DIR__, 4) . '/public/assets/css/storefront-catalog.css';
                $cssHash = is_file($cssFile) ? substr(md5_file($cssFile), 0, 8) : '0';
                $previewUrl = '/?customize_preview=1&v=' . $cssHash;
                ?>
                <iframe src="<?= e($previewUrl) ?>"
                        id="preview-iframe"
                        title="Storefront preview"></iframe>
            </div>
        </div>
    </div>
</div>

<?php
// Recursive template emitter. Every node in the tree (root
// or child at any depth) gets a tpl-settings-<id> template
// so the sidebar's edit button can find its config form.
$emitSettingsTemplate = function ($block, $slug, $categories) use (&$emitSettingsTemplate): void {
    ?>
    <template id="tpl-settings-<?= (int) $block->id ?>">
        <form class="ra-live-config-form"
              data-block-id="<?= (int) $block->id ?>"
              data-preview-url="/commerce/ca-admin/pages/<?= e($slug) ?>/blocks/<?= (int) $block->id ?>/preview"
              data-save-url="/commerce/ca-admin/pages/<?= e($slug) ?>/blocks/<?= (int) $block->id ?>/config">
            <?= csrf_field() ?>
            <?php
            $schema = \CorporateAid\Commerce\Support\Blocks\BlockConfigRegistry::schema($block->blockType);
            $config = $block->config;
            $draft  = $_SESSION['builder_draft'][$slug][$block->id] ?? [];
            $config = array_merge($config, $draft);
            foreach ($schema as $name => $meta):
                echo view_partial('admin/pages/_config-field', [
                    'name'       => 'config[' . $name . ']',
                    'meta'       => $meta,
                    'value'      => $config[$name] ?? ($meta['default'] ?? ''),
                    'categories' => $categories,
                ]);
            endforeach;
            ?>
        </form>
    </template>
    <?php
    foreach ($block->children as $child) {
        $emitSettingsTemplate($child, $slug, $categories);
    }
};
foreach ($blocks as $block) {
    $emitSettingsTemplate($block, $slug, $categories);
}
?>

<div class="modal fade" id="ra-page-settings-modal" tabindex="-1" aria-labelledby="ra-page-settings-label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="/commerce/ca-admin/pages/<?= e($slug) ?>/settings" class="modal-content" style="background:#1a2235; color:#e5e7eb; border:1px solid #2d3748;">
            <?= csrf_field() ?>
            <div class="modal-header" style="border-bottom:1px solid #2d3748;">
                <h5 class="modal-title" id="ra-page-settings-label">Page Settings</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label text-light">Page title</label>
                    <input type="text" name="title" class="form-control" required
                           value="<?= e((string) ($page['title'] ?? $label)) ?>">
                    <div class="form-text" style="color:#94a3b8;">Shown in the browser tab and as the heading on search results.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-light">Meta description</label>
                    <textarea name="meta_description" class="form-control" rows="3" maxlength="320"><?= e((string) ($page['meta_description'] ?? '')) ?></textarea>
                    <div class="form-text" style="color:#94a3b8;">155–160 characters recommended. Shown under the title in search results.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-light">Status</label>
                    <select name="status" class="form-select">
                        <?php $__status = (string) ($page['status'] ?? 'draft'); ?>
                        <option value="draft" <?= $__status === 'draft' ? 'selected' : '' ?>>Draft (not visible to customers)</option>
                        <option value="published" <?= $__status === 'published' ? 'selected' : '' ?>>Published (visible at /pages/<?= e($slug) ?>)</option>
                        <option value="archived" <?= $__status === 'archived' ? 'selected' : '' ?>>Archived (hidden, redirects to 404)</option>
                    </select>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="noindex" id="ra-page-noindex" value="1" <?= !empty($page['noindex']) ? 'checked' : '' ?>>
                    <label class="form-check-label text-light" for="ra-page-noindex">
                        Hide from search engines (noindex)
                    </label>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #2d3748;">
                <button type="button" class="ra-btn ra-btn-outline text-light border-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="ra-btn ra-btn-primary">Save page settings</button>
            </div>
        </form>
    </div>
</div>

<?= view_partial('admin/pages/_block-picker', ['slug' => $slug, 'addUnder' => $addUnder ?? null]) ?>
<?= view_partial('admin/pages/_block-media-picker', ['mediaLibrary' => $mediaLibrary]) ?>
<template id="tpl-block-row">
    <div class="ra-customizer-block" data-block-id="" data-parent-id="" data-block-type="" data-type-label="" data-is-structural="" data-depth="" draggable="true">
        <i class="bi bi-grip-vertical drag-handle"></i>
        <span class="fw-bold ms-2" data-field="type-label"></span>
        <div class="ms-auto d-flex gap-1" data-field="actions">
            <a href="#" class="ra-icon-btn" title="Add a child inside this block" data-field="add-child" draggable="false"><i class="bi bi-plus-lg"></i></a>
            <button type="button" class="ra-icon-btn ra-open-settings" data-block-id="" draggable="false"><i class="bi bi-pencil"></i></button>
            <form method="POST" action="" class="m-0" onsubmit="return confirm('Duplicate this block?');" data-field="duplicate-form">
                <input type="hidden" name="_csrf" value="">
                <button type="submit" class="ra-icon-btn" title="Duplicate"><i class="bi bi-files"></i></button>
            </form>
            <form method="POST" action="" class="m-0" onsubmit="return confirm('Delete this block?');" data-field="delete-form">
                <input type="hidden" name="_csrf" value="">
                <button type="submit" class="ra-icon-btn text-danger"><i class="bi bi-trash"></i></button>
            </form>
        </div>
    </div>
</template>

<script>
(function () {
    'use strict';
    var list = document.getElementById('ra-blocks-list');
    if (!list) return;

    var slug    = list.getAttribute('data-page-slug') || '';
    var csrf    = list.getAttribute('data-csrf') || '';
    var tpl     = document.getElementById('tpl-block-row');
    var markers = list.querySelectorAll('.row-data');

    if (!tpl || markers.length === 0) return;

    var fillRow = function (marker) {
        var node = tpl.content.cloneNode(true);
        var row = node.querySelector('.ra-customizer-block');
        var id       = marker.getAttribute('data-block-id');
        var parentId = marker.getAttribute('data-parent-id');
        var isStruct = marker.getAttribute('data-is-structural') === '1';
        var depth    = parseInt(marker.getAttribute('data-depth'), 10) || 0;

        row.setAttribute('data-block-id', id);
        row.setAttribute('data-parent-id', parentId);
        row.setAttribute('data-block-type', marker.getAttribute('data-block-type'));
        row.setAttribute('data-type-label', marker.getAttribute('data-type-label'));
        row.setAttribute('data-is-structural', isStruct ? '1' : '0');
        row.setAttribute('data-depth', String(depth));
        if (depth > 0) row.classList.add('ra-customizer-block--child');
        if (marker.getAttribute('data-block-type') === '__orphaned__') {
            row.classList.add('ra-customizer-block--synthetic');
        }

        row.querySelector('[data-field="type-label"]').textContent =
            marker.getAttribute('data-type-label');

        if (!isStruct) {
            row.querySelector('[data-field="add-child"]').remove();
        } else {
            // + opens the shared block type dropdown. It uses
            // our own click handler (not Bootstrap's data-api),
            // because the shared menu is a single element at the
            // bottom of the page, not a sibling of the button.
            // Bootstrap's data-api assumes sibling markup and
            // would throw on _isShown(null).
            var addBtn = row.querySelector('[data-field="add-child"]');
            addBtn.removeAttribute('href');
            addBtn.setAttribute('role', 'button');
            addBtn.setAttribute('tabindex', '0');
            addBtn.setAttribute('draggable', 'false');
            addBtn.setAttribute('data-block-add-trigger', '1');
            addBtn.setAttribute('data-add-under', id);
        }

        row.querySelector('.ra-open-settings').setAttribute('data-block-id', id);

        var dup = row.querySelector('[data-field="duplicate-form"]');
        dup.action = '/commerce/ca-admin/pages/' + slug + '/blocks/' + id + '/duplicate';
        dup.querySelector('input[name="_csrf"]').value = csrf;

        var del = row.querySelector('[data-field="delete-form"]');
        del.action = '/commerce/ca-admin/pages/' + slug + '/blocks/' + id + '/delete';
        del.querySelector('input[name="_csrf"]').value = csrf;

        return row;
    };

    // Remove the empty-state paragraph if it exists and build rows.
    var empty = list.querySelector('p');
    markers.forEach(function (marker) {
        list.appendChild(fillRow(marker));
    });
    if (empty && markers.length > 0) empty.remove();

    // Expose the fill function for the JS to reuse on re-render.
    window.__builderFillRow = fillRow;
})();
</script>
<?php
// Block types for the + dropdown. The menu itself is built
// dynamically in page-builder-customizer-v3.js and appended
// to document.body so it escapes the .ra-customizer stacking
// context (which has position: fixed and clips everything
// inside it).
$pickableTypes = \CorporateAid\Commerce\Support\Blocks\BlockTypeRegistry::all();
?>
<script>
window.__builderBlockTypes = <?= json_encode(
    array_map(
        fn($key, $meta) => [
            'slug'  => $key,
            'label' => $meta['label'],
            'icon'  => $meta['icon'],
        ],
        array_keys($pickableTypes),
        $pickableTypes
    )
) ?>;
</script>
<script src="<?= versioned_asset('js/page-builder-customizer-v3.js') ?>"></script>
