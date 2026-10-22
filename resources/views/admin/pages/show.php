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
        <div class="ra-customizer-brand fw-bold text-light d-flex align-items-center">
            <i class="bi bi-layout-text-window me-2"></i> <?= e($label) ?>
        </div>

        <div class="ra-customizer-devices d-flex gap-2 bg-dark p-1 rounded border border-secondary">
            <button type="button" class="device-btn active" data-device="desktop" title="Desktop" style="background:none;border:none;color:#d4af37;"><i class="bi bi-display fs-5"></i></button>
            <button type="button" class="device-btn" data-device="tablet" title="Tablet" style="background:none;border:none;color:#64748b;"><i class="bi bi-tablet fs-5"></i></button>
            <button type="button" class="device-btn" data-device="mobile" title="Mobile" style="background:none;border:none;color:#64748b;"><i class="bi bi-phone fs-5"></i></button>
        </div>

        <div class="ra-customizer-actions d-flex gap-2 align-items-center">
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
                <div class="ra-builder-blocks p-2" id="ra-blocks-list" data-reorder-url="/commerce/ca-admin/pages/<?= e($slug) ?>/blocks/reorder" data-csrf="<?= e(csrf_token()) ?>">
                    <?php if (empty($blocks)): ?>
                        <p class="text-muted small text-center py-3">No blocks yet. Click + to add one.</p>
                    <?php else: ?>
                        <?php foreach ($blocks as $block): ?>
                            <div class="ra-customizer-block" data-block-id="<?= (int) $block->id ?>">
                                <i class="bi bi-grip-vertical drag-handle"></i>
                                <span class="fw-bold ms-2"><?= e($block->typeLabel()) ?></span>
                                <div class="ms-auto d-flex gap-1">
                                    <button type="button" class="ra-icon-btn ra-open-settings" data-block-id="<?= (int) $block->id ?>"><i class="bi bi-pencil"></i></button>
                                    <form method="POST" action="/commerce/ca-admin/pages/<?= e($slug) ?>/blocks/<?= (int) $block->id ?>/duplicate" class="m-0" onsubmit="return confirm('Duplicate this block?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="ra-icon-btn" title="Duplicate"><i class="bi bi-files"></i></button>
                                    </form>
                                    <form method="POST" action="/commerce/ca-admin/pages/<?= e($slug) ?>/blocks/<?= (int) $block->id ?>/delete" class="m-0" onsubmit="return confirm('Delete this block?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="ra-icon-btn text-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                $previewUrl = '/?customize_preview=1';
                ?>
                <iframe src="<?= e($previewUrl) ?>" id="preview-iframe"></iframe>
            </div>
        </div>
    </div>
</div>

<?php foreach ($blocks as $block): ?>
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
<?php endforeach; ?>

<?= view_partial('admin/pages/_block-picker', ['slug' => $slug]) ?>
<?= view_partial('admin/pages/_block-media-picker', ['mediaLibrary' => $mediaLibrary]) ?>
<script src="<?= versioned_asset('js/page-builder-customizer.js') ?>"></script>
