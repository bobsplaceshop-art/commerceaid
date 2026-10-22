<?php
/**
 * File-type settings field using the Media Library picker.
 */
$value     = (string) ($value     ?? '');
$def       = $def                 ?? [];
$fieldName = (string) ($fieldName ?? '');
$label     = (string) ($def['label'] ?? '');
$fileId    = (int) $value;

$previewUrl = null;
if ($fileId > 0) {
    try {
        $container = \CorporateAid\Core\App::instance()->container();
        $media = $container->get(\CorporateAid\Commerce\Repositories\MediaRepository::class);
        $item = $media->findById($fileId);
        if ($item !== null) $previewUrl = $item->url();
    } catch (\Throwable) {}
}
$fieldId = 'set-' . e($fieldName);
?>
<div class="ra-file-field" data-reload-on-pick="true">
    <div class="ra-file-preview" id="<?= $fieldId ?>-preview-wrap">
        <?php if ($previewUrl !== null): ?>
            <img src="<?= e($previewUrl) ?>" alt="Preview" style="width:80px; height:80px; object-fit:cover; border-radius:4px;">
        <?php else: ?>
            <div style="width:80px; height:80px; background:#0f172a; border:1px dashed #2d3748; border-radius:4px; display:flex; align-items:center; justify-content:center; color:#64748b;">
                <i class="bi bi-image"></i>
            </div>
        <?php endif; ?>
    </div>
    <div class="ra-file-controls" style="margin-top: 1rem;">
        <input type="hidden" id="<?= $fieldId ?>" name="<?= e($fieldName) ?>" value="<?= $fileId > 0 ? $fileId : '' ?>">
        <button type="button" class="ra-btn ra-btn-outline ra-btn-sm" data-settings-media-open="<?= $fieldId ?>">
            <i class="bi bi-images me-1"></i> Choose image
        </button>
        <button type="button" class="ra-btn ra-btn-outline ra-btn-sm text-danger ms-2" style="border-color:#dc2626; color:#dc2626; <?= $fileId > 0 ? '' : 'display:none;' ?>" data-settings-media-clear="<?= $fieldId ?>">
            <i class="bi bi-x-circle me-1"></i> Remove
        </button>
    </div>
</div>
