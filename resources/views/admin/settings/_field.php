<?php
/**
 * One settings field. Called from index.php for each definition.
 *
 * Vars:
 *   @var string              $key          dotted setting key
 *   @var array<string,mixed> $def          the definition
 *   @var string              $value        current value as string
 *   @var string              $fieldName    form field name (dots -> __)
 */
$key       = (string) ($key       ?? '');
$def       = $def                 ?? [];
$value     = (string) ($value     ?? '');
$fieldName = (string) ($fieldName ?? '');

$type  = (string) ($def['type']  ?? 'string');
$label = (string) ($def['label'] ?? $key);
$hint  = (string) ($def['hint']  ?? '');

$boolOn = in_array(strtolower(trim($value)), ['1','true','on','yes'], true);
?>
<div class="ra-settings-field">
    <label class="ra-settings-label" for="set-<?= e($fieldName) ?>">
        <?= e($label) ?>
    </label>

    <?php if ($type === 'boolean'): ?>
        <label class="ra-settings-toggle">
            <input type="checkbox"
                   id="set-<?= e($fieldName) ?>"
                   name="<?= e($fieldName) ?>"
                   value="1"
                   <?= $boolOn ? 'checked' : '' ?>>
            <span class="ra-settings-toggle-track"><span class="ra-settings-toggle-knob"></span></span>
            <span class="ra-settings-toggle-state"><?= $boolOn ? 'On' : 'Off' ?></span>
        </label>

    <?php elseif ($type === 'text'): ?>
        <textarea id="set-<?= e($fieldName) ?>"
                  name="<?= e($fieldName) ?>"
                  rows="4"
                  class="ra-settings-textarea"><?= e($value) ?></textarea>

    <?php elseif ($type === 'number'): ?>
        <input type="number"
               id="set-<?= e($fieldName) ?>"
               name="<?= e($fieldName) ?>"
               value="<?= e($value) ?>"
               class="ra-settings-input">

    <?php elseif ($type === 'file'): ?>
        <?php
        // File fields use their own partial. The partial
        // resolves the current file ID to a preview URL and
        // renders the upload + remove controls.
        require __DIR__ . '/_field-file.php';
        ?>

    <?php elseif ($type === 'select'): ?>
        <?php $options = is_array($def['options'] ?? null) ? $def['options'] : []; ?>
        <select id="set-<?= e($fieldName) ?>"
                name="<?= e($fieldName) ?>"
                class="ra-settings-select">
            <?php foreach ($options as $optVal => $optLabel): ?>
                <option value="<?= e((string) $optVal) ?>"
                        <?= $value === (string) $optVal ? 'selected' : '' ?>>
                    <?= e((string) $optLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>

    <?php else: /* string */ ?>
        <input type="text"
               id="set-<?= e($fieldName) ?>"
               name="<?= e($fieldName) ?>"
               value="<?= e($value) ?>"
               class="ra-settings-input">
    <?php endif; ?>

    <?php if ($hint !== ''): ?>
        <div class="ra-settings-hint"><?= e($hint) ?></div>
    <?php endif; ?>
</div>
