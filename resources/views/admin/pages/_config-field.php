<?php
/**
 * One config field. Rendered from BlockConfigRegistry::schema().
 *
 * Vars:
 *   @var string              $name       field name (e.g. 'headline')
 *   @var array<string,mixed> $meta       schema entry (label, type, default, hint, options)
 *   @var mixed               $value      current value from the block's config
 *   @var array<int, mixed>   $categories resolved categories, for category_select
 */
$name = (string) ($name ?? '');
$meta = $meta ?? [];
$value = $value ?? ($meta['default'] ?? '');
$categories = $categories ?? [];

$type    = (string) ($meta['type'] ?? 'string');
$label   = (string) ($meta['label'] ?? $name);
$hint    = (string) ($meta['hint'] ?? '');
$required = !empty($meta['required']);
$fieldId = 'bc-' . preg_replace('/[^a-z0-9_-]+/i', '-', $name);
?>
<div class="mb-3">
    <label class="form-label" for="<?= e($fieldId) ?>">
        <?= e($label) ?>
        <?php if ($required): ?><span style="color:var(--ra-danger);">*</span><?php endif; ?>
    </label>

    <?php if ($type === 'string'): ?>
        <input type="text"
               id="<?= e($fieldId) ?>"
               name="<?= e($name) ?>"
               class="form-control"
               value="<?= e((string) $value) ?>"
               <?= $required ? 'required' : '' ?>>

    <?php elseif ($type === 'text'): ?>
        <textarea id="<?= e($fieldId) ?>"
                  name="<?= e($name) ?>"
                  class="form-control"
                  rows="4"
                  <?= $required ? 'required' : '' ?>><?= e((string) $value) ?></textarea>

    <?php elseif ($type === 'int'): ?>
        <input type="number"
               id="<?= e($fieldId) ?>"
               name="<?= e($name) ?>"
               class="form-control"
               value="<?= e((string) $value) ?>"
               <?= $required ? 'required' : '' ?>>

    <?php elseif ($type === 'boolean'): ?>
        <?php $checked = in_array(strtolower((string) $value), ['1','true','on','yes'], true); ?>
        <div class="form-check">
            <input type="checkbox"
                   id="<?= e($fieldId) ?>"
                   name="<?= e($name) ?>"
                   value="1"
                   class="form-check-input"
                   <?= $checked ? 'checked' : '' ?>>
            <label for="<?= e($fieldId) ?>" class="form-check-label">Enable</label>
        </div>

    <?php elseif ($type === 'select'): ?>
        <?php $options = is_array($meta['options'] ?? null) ? $meta['options'] : []; ?>
        <select id="<?= e($fieldId) ?>"
                name="<?= e($name) ?>"
                class="form-select">
            <?php foreach ($options as $optVal => $optLabel): ?>
                <option value="<?= e((string) $optVal) ?>" <?= (string) $value === (string) $optVal ? 'selected' : '' ?>>
                    <?= e((string) $optLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>

    <?php elseif ($type === 'category_select'): ?>
        <select id="<?= e($fieldId) ?>"
                name="<?= e($name) ?>"
                class="form-select">
            <option value="">— Select a category —</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= e((string) $cat->slug) ?>" <?= (string) $value === (string) $cat->slug ? 'selected' : '' ?>>
                    <?= e((string) $cat->name) ?>
                </option>
            <?php endforeach; ?>
        </select>

    <?php elseif ($type === 'file'): ?>
        <?php
        // Hidden file ID + preview + "Choose" button. The
        // picker modal (rendered alongside the config modal
        // in show.php) is opened by the "Choose" button.
        $fileId   = is_numeric($value) ? (int) $value : 0;
        $previewUrl = $fileId > 0
            ? '/media/' . $fileId . '/md.jpg'
            : '';
        ?>
        <div style="display:flex; align-items:center; gap:.75rem;">
            <?php if ($previewUrl !== ''): ?>
                <img src="<?= e($previewUrl) ?>"
                     alt="Selected image"
                     style="width:64px; height:64px; object-fit:cover; border-radius:4px; border:1px solid var(--ra-border);">
            <?php else: ?>
                <div style="width:64px; height:64px; border:1px dashed var(--ra-border); border-radius:4px; display:flex; align-items:center; justify-content:center; color:var(--ra-muted);">
                    <i class="bi bi-image"></i>
                </div>
            <?php endif; ?>
            <input type="hidden"
                   id="<?= e($fieldId) ?>"
                   name="<?= e($name) ?>"
                   value="<?= $fileId > 0 ? (int) $fileId : '' ?>">
            <button type="button"
                    class="ra-btn ra-btn-outline ra-btn-sm"
                    data-block-media-open="<?= e($fieldId) ?>">
                <i class="bi bi-images me-1"></i> Choose image
            </button>
            <?php if ($fileId > 0): ?>
                <button type="button"
                        class="ra-btn ra-btn-sm"
                        style="background:transparent;border:1px solid var(--ra-danger);color:var(--ra-danger);"
                        data-block-media-clear="<?= e($fieldId) ?>">
                    Remove
                </button>
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <?php if ($hint !== ''): ?>
        <div class="form-text"><?= e($hint) ?></div>
    <?php endif; ?>
</div>
