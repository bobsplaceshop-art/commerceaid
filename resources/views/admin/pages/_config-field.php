<?php
/**
 * One config field. Rendered from BlockConfigRegistry::schema()
 * via ConfigFieldRegistry::get().
 *
 * Vars:
 *   @var string              $name       field name (e.g. 'headline')
 *   @var array<string,mixed> $meta       schema entry
 *   @var mixed               $value      current value from config
 *   @var array<int, mixed>   $categories resolved categories, for
 *                                        category_select and
 *                                        category_multi_select
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

$definition = \CorporateAid\Commerce\Support\Blocks\ConfigFieldRegistry::get($type);
?>
<div class="mb-3">
    <label class="form-label" for="<?= e($fieldId) ?>">
        <?= e($label) ?>
        <?php if ($required): ?><span style="color:var(--ra-danger);">*</span><?php endif; ?>
    </label>

    <?php if ($definition !== null): ?>
        <?= ($definition['render'])([
            'name'       => $name,
            'meta'       => $meta,
            'value'      => $value,
            'fieldId'    => $fieldId,
            'categories' => $categories,
            'required'   => $required,
        ]) ?>
    <?php else: ?>
        <div class="form-text" style="color:var(--ra-danger);">
            Unknown field type: <?= e($type) ?>
        </div>
    <?php endif; ?>

    <?php if ($hint !== ''): ?>
        <div class="form-text"><?= e($hint) ?></div>
    <?php endif; ?>
</div>
