<?php
/**
 * File-type settings field.
 *
 * Renders an upload widget for a column-target settings
 * field. Called from _field.php when $type === 'file'.
 *
 * Vars:
 *   @var string              $key          dotted setting key
 *   @var array<string,mixed> $def          the definition
 *   @var string              $value        current file ID as string, or ''
 *   @var string              $fieldName    form field name (dots -> __)
 *
 * The upload input and the remove flag use the field name
 * with suffixes. The controller's saveFileColumn() reads
 * them by the same name.
 *
 * No JavaScript yet. This is the progressive-enhancement
 * version: a plain multipart upload. The AJAX widget lands
 * in a follow-up commit that adds media-upload.js.
 */

$value     = (string) ($value     ?? '');
$def       = $def                 ?? [];
$fieldName = (string) ($fieldName ?? '');
$label     = (string) ($def['label'] ?? '');
$hint      = (string) ($def['hint']  ?? '');
$accept    = (string) ($def['accept'] ?? 'image/*');
$maxMb     = (int)    ($def['max_mb'] ?? 5);
$context   = (string) ($def['media_context'] ?? '');

$fileId = (int) $value;
$removeKey = $fieldName . '__remove';
$uploadKey = $fieldName . '__upload';

// Resolve the current file to a preview URL when set.
$previewUrl = null;
$previewName = null;
if ($fileId > 0) {
    try {
        $container = \CorporateAid\Core\App::instance()->container();
        $media = $container->get(
            \CorporateAid\Commerce\Repositories\MediaRepository::class
        );
        $item = $media->findById($fileId);
        if ($item !== null) {
            $previewUrl  = $item->url();
            $previewName = $item->file->originalName;
        }
    } catch (\Throwable) {
        // Silently fall through with no preview.
    }
}
?>

<div class="ra-file-field">
        <div class="ra-file-preview">
            <?php if ($previewUrl !== null): ?>
                <img src="<?= e($previewUrl) ?>"
                     alt="<?= e($label) ?> preview"
                     class="ra-file-preview-img"
                     loading="lazy">
            <?php else: ?>
                <div class="ra-file-preview-empty" aria-hidden="true">
                    <i class="bi bi-image"></i>
                </div>
            <?php endif; ?>
        </div>

        <div class="ra-file-controls">
            <input type="file"
                   id="set-<?= e($fieldName) ?>"
                   name="<?= e($uploadKey) ?>"
                   accept="<?= e($accept) ?>"
                   class="ra-file-input">

            <div class="ra-file-meta">
                <span class="ra-file-hint">
                    Accepted: <?= e($accept) ?>. Max <?= (int) $maxMb ?> MB.
                </span>
                <?php if ($previewName !== null): ?>
                    <span class="ra-file-current">
                        Current: <strong><?= e($previewName) ?></strong>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($fileId > 0): ?>
                <label class="ra-file-remove">
                    <input type="checkbox"
                           name="<?= e($removeKey) ?>"
                           value="1">
                    <span>Remove this file</span>
                </label>
            <?php endif; ?>
        </div>
    </div>
</div>
