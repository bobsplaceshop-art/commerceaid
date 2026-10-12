<?php
/**
 * Field add/edit modal. Stage 009 Session 2b.
 *
 * Uses the shared admin modal system (.ra-modal*).
 * Visibility is controlled by inline style.display from
 * forms-field-editor.js, matching every other admin modal.
 *
 * Context:
 *   $form     Form
 *   $fields   array<int, FormField>  existing fields on the form
 *   $allTypes array<string, InputType>  from InputTypeRegistry::all()
 */
$form     = $form     ?? null;
$fields   = $fields   ?? [];
$allTypes = $allTypes ?? [];
if ($form === null) return;

$typeOptions = [];
foreach ($allTypes as $key => $type) {
    $typeOptions[$key] = [
        'label'            => $type->label(),
        'supports_options' => $type->supportsOptions(),
    ];
}
?>
<div id="ra-field-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" data-ra-field-close></div>

    <div class="ra-modal-panel" role="dialog" aria-labelledby="ra-field-modal-title" style="max-width:720px;">

        <form method="POST" id="ra-field-form"
              action="/commerce/ca-admin/forms/<?= (int) $form->id ?>/fields">
            <?= csrf_field() ?>

            <div class="ra-modal-header">
                <h3 id="ra-field-modal-title" class="h6 mb-0">Add field</h3>
                <button type="button" class="ra-icon-btn" data-ra-field-close aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="ra-modal-body">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="muted small" for="ra-field-type">
                            Type <span aria-hidden="true">*</span>
                        </label>
                        <select id="ra-field-type" name="input_type" class="form-control" required data-ra-field-type>
                            <?php foreach ($typeOptions as $key => $info): ?>
                                <option value="<?= e($key) ?>"
                                        data-supports-options="<?= $info['supports_options'] ? '1' : '0' ?>">
                                    <?= e($info['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="muted small" for="ra-field-label">
                            Label <span aria-hidden="true">*</span>
                        </label>
                        <input type="text" id="ra-field-label" name="label"
                               class="form-control" maxlength="190" required
                               placeholder="Email address">
                    </div>

                    <div class="col-md-6">
                        <label class="muted small" for="ra-field-key">
                            Key <span aria-hidden="true">*</span>
                        </label>
                        <input type="text" id="ra-field-key" name="field_key"
                               class="form-control" maxlength="60" required
                               pattern="[a-z][a-z0-9_]*"
                               placeholder="email">
                        <div class="muted small mt-1">
                            Lowercase letters, digits, underscores. Starts with a letter. Used as the payload key.
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="muted small" for="ra-field-placeholder">Placeholder</label>
                        <input type="text" id="ra-field-placeholder" name="placeholder"
                               class="form-control" maxlength="190">
                    </div>

                    <div class="col-12">
                        <label class="muted small" for="ra-field-help">Help text</label>
                        <input type="text" id="ra-field-help" name="help_text"
                               class="form-control" maxlength="500"
                               placeholder="Shown below the field on the public form">
                    </div>

                    <div class="col-md-6">
                        <label class="muted small" for="ra-field-step">Step</label>
                        <input type="number" id="ra-field-step" name="step"
                               class="form-control" min="1" max="20" value="1">
                        <div class="muted small mt-1">
                            A form is multi-step when any field has step &gt; 1.
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="muted small" for="ra-field-default">Default value</label>
                        <input type="text" id="ra-field-default" name="default_value"
                               class="form-control" maxlength="500">
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   id="ra-field-required" name="is_required" value="1">
                            <label class="form-check-label" for="ra-field-required">
                                Required field
                            </label>
                        </div>
                    </div>
                </div>

                <div data-ra-field-options-section hidden>
                    <hr class="my-4">
                    <h6 class="mb-3">Options</h6>
                    <p class="muted small mb-3">
                        Shown to the customer as a list. Each option has a submitted value and a display label.
                    </p>
                    <div data-ra-field-options-list></div>
                    <button type="button" class="ra-btn ra-btn-outline ra-btn-sm" data-ra-field-option-add>
                        <i class="bi bi-plus-lg me-1"></i> Add option
                    </button>
                </div>

                <?php if ($fields !== []): ?>
                <div data-ra-field-conditional-section>
                    <hr class="my-4">
                    <h6 class="mb-3">Conditional visibility</h6>
                    <p class="muted small mb-3">
                        Optionally show this field only when another field's value matches a condition.
                        Leave blank to always show it.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="muted small" for="ra-field-cond-field">When field</label>
                            <select id="ra-field-cond-field" name="conditional[field]" class="form-control" data-ra-field-cond-field>
                                <option value="">&mdash; always &mdash;</option>
                                <?php foreach ($fields as $f): ?>
                                    <option value="<?= e($f->fieldKey) ?>"
                                            data-field-id="<?= (int) $f->id ?>">
                                        <?= e($f->label) ?> (<?= e($f->fieldKey) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="muted small" for="ra-field-cond-op">Is</label>
                            <select id="ra-field-cond-op" name="conditional[operator]" class="form-control">
                                <option value="">— never —</option>
                                <option value="equals">equal to</option>
                                <option value="not_equals">not equal to</option>
                                <option value="contains">contains</option>
                                <option value="is_empty">empty</option>
                                <option value="is_not_empty">not empty</option>
                                <option value="greater_than">greater than</option>
                                <option value="less_than">less than</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="muted small" for="ra-field-cond-value">Value</label>
                            <input type="text" id="ra-field-cond-value"
                                   name="conditional[value]"
                                   class="form-control" maxlength="190">
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div data-ra-field-type-configs hidden>
                    <hr class="my-4">
                    <h6 class="mb-3">Type settings</h6>
                    <?php foreach ($allTypes as $key => $type): ?>
                        <?php $configFields = $type->configFields(); ?>
                        <?php if ($configFields === []): continue; endif; ?>
                        <div data-ra-field-type-section="<?= e($key) ?>" hidden>
                            <div class="row g-3">
                                <?php foreach ($configFields as $cf): ?>
                                    <div class="col-md-6">
                                        <label class="muted small"
                                               for="ra-field-cf-<?= e($key) ?>-<?= e($cf['key']) ?>">
                                            <?= e($cf['label']) ?>
                                        </label>
                                        <input type="<?= e($cf['type'] ?? 'text') ?>"
                                               id="ra-field-cf-<?= e($key) ?>-<?= e($cf['key']) ?>"
                                               name="constraints[<?= e($cf['key']) ?>]"
                                               class="form-control"
                                               <?= isset($cf['help']) ? 'placeholder="' . e($cf['help']) . '"' : '' ?>>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>

            <div class="ra-modal-footer">
                <button type="button" class="ra-btn ra-btn-outline" data-ra-field-close>Cancel</button>
                <button type="submit" class="ra-btn ra-btn-primary" data-ra-field-submit>Save field</button>
            </div>

        </form>

    </div>
</div>

<template id="ra-field-option-template">
    <div class="row g-2 align-items-center mb-2" data-ra-field-option-row>
        <div class="col">
            <input type="text" name="option_values[]" class="form-control"
                   placeholder="Value (submitted)" maxlength="120">
        </div>
        <div class="col">
            <input type="text" name="option_labels[]" class="form-control"
                   placeholder="Label (displayed)" maxlength="190">
        </div>
        <div class="col-auto">
            <button type="button" class="ra-icon-btn" data-ra-field-option-remove title="Remove">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>
</template>
