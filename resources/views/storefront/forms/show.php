<?php
/**
 * Public form render. Stage 009 Session 3.
 *
 * Route: GET  /forms/{slug}
 *        POST /forms/{slug}  (on validation failure, re-renders
 *                             with $errors and $old populated)
 *
 * Context (from Storefront\FormController::renderForm):
 *   $form           CorporateAid\Commerce\Models\Form
 *   $fields         array<int, CorporateAid\Commerce\Models\FormField>
 *   $steps          array<int, int>  step numbers in order
 *   $old            array<string,mixed>  field_key => submitted value
 *   $errors         array<string,string> field_key => first error message
 *   $successMessage ?string  non-null on successful submission (or
 *                            a silent honeypot trip)
 *   $customer       ?Customer
 *
 * The view composes the InputType::render() output for each
 * field. The InputType classes emit their own labels, inputs,
 * help text, and per-field error markup. The view wraps each
 * in a .sf-form-field div, and adds the multi-step scaffolding.
 *
 * The CustomFields InputType classes emit inputs named
 * `cf[field_key]` (single) or `cf[field_key][]` (multi).
 * That is the convention the submission service reads. The
 * view does not intervene; it calls render() and trusts the
 * type.
 *
 * See docs/stages/009_forms-plan.md.
 */

use CorporateAid\Platform\CustomFields\InputTypeRegistry;

$form           = $form           ?? null;
$fields         = $fields         ?? [];
$steps          = $steps          ?? [1];
$old            = $old            ?? [];
$errors         = $errors         ?? [];
$successMessage = $successMessage ?? null;
$customer       = $customer       ?? null;

if ($form === null) {
    return;
}

$isMultiStep = count($steps) > 1;

/**
 * Resolve the current value for a field from the old input,
 * falling back to the field's default. Multi-value types
 * arrive as an array from $_POST; the CustomFields
 * convention is a comma-joined string, so join here.
 *
 * @param array<string,mixed> $old
 */
$currentValueFor = function (\CorporateAid\Commerce\Models\FormField $field) use ($old): string {
    $value = $old[$field->fieldKey] ?? $field->defaultValue ?? '';
    if (is_array($value)) {
        $value = implode(',', array_map('strval', $value));
    }
    return (string) $value;
};

/**
 * Is the field visible on first render? A conditional field
 * with a rule whose condition is false is hidden by the JS
 * on page load. The view still renders it (so the JS can
 * find it), but marks it hidden.
 *
 * The server-side conditional check that decides whether to
 * process a field's value is in FormSubmissionService - not
 * here. This function is for display only.
 *
 * @param array<string,mixed> $old
 */
$isFieldVisibleOnLoad = function (\CorporateAid\Commerce\Models\FormField $field) use ($old): bool {
    if ($field->conditional === null) return true;

    $rule = $field->conditional;
    $otherKey = (string) $rule['field'];
    $operator = (string) $rule['operator'];
    $expected = $rule['value'] ?? null;

    $actual = $old[$otherKey] ?? '';
    if (is_array($actual)) {
        $actual = implode(',', array_map('strval', $actual));
    }
    $actual = (string) $actual;

    return match ($operator) {
        'equals'       => $actual === (string) $expected,
        'not_equals'   => $actual !== (string) $expected,
        'contains'     => str_contains($actual, (string) $expected),
        'is_empty'     => $actual === '',
        'is_not_empty' => $actual !== '',
        'greater_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
        'less_than'    => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
        default        => true,
    };
};

// Group fields by step. In a single-step form, everything is
// step 1 and the loop below collapses to a plain list.
$fieldsByStep = [];
foreach ($fields as $field) {
    $fieldsByStep[$field->step][] = $field;
}
ksort($fieldsByStep);
?>

<div class="sf-forms sf-form-single" data-sf-page="form" data-sf-form="<?= e($form->slug) ?>">

    <div class="sf-container">

        <header class="sf-form-heading">
            <h1 class="sf-form-title"><?= e($form->name) ?></h1>
            <?php if ($form->description !== null && trim($form->description) !== ''): ?>
                <p class="sf-form-description"><?= nl2br(e($form->description)) ?></p>
            <?php endif; ?>
        </header>

        <?php if ($successMessage !== null): ?>

            <div class="sf-form-success" role="status" aria-live="polite">
                <div class="sf-form-success-icon" aria-hidden="true">
                    <i class="bi bi-check-circle"></i>
                </div>
                <p class="sf-form-success-message"><?= e($successMessage) ?></p>
            </div>

        <?php else: ?>

            <form method="POST"
                  action="/forms/<?= e($form->slug) ?>"
                  novalidate
                  class="sf-form"
                  data-sf-form-form>

                <?= csrf_field() ?>

                <?php if ($form->honeypotEnabled): ?>
                    <?php /* The honeypot field. Hidden from humans via
                             CSS; bots fill every input they see. The
                             field name is `website`, matching the
                             newsletter convention. The submission
                             service reads $_POST['website'] and treats
                             non-empty as a bot signal. */ ?>
                    <div class="sf-form-honeypot" aria-hidden="true">
                        <label for="cf_website">Website</label>
                        <input type="text"
                               id="cf_website"
                               name="website"
                               value=""
                               tabindex="-1"
                               autocomplete="off">
                    </div>
                <?php endif; ?>

                <?php if ($isMultiStep): ?>
                    <div class="sf-form-step-indicator" data-sf-step-indicator>
                        Step <span data-sf-step-current>1</span> of <?= (int) count($steps) ?>
                    </div>
                <?php endif; ?>

                <?php foreach ($fieldsByStep as $stepNum => $stepFields): ?>

                    <div class="sf-form-step"
                         data-sf-step="<?= (int) $stepNum ?>"
                         <?= $stepNum === (int) $steps[0] ? '' : 'hidden' ?>>

                        <?php foreach ($stepFields as $field): ?>

                            <?php
                                $type = InputTypeRegistry::get($field->inputType);
                                $current = $currentValueFor($field);
                                $fieldError = isset($errors[$field->fieldKey])
                                    ? [$errors[$field->fieldKey]]
                                    : [];
                                $visible = $isFieldVisibleOnLoad($field);

                                $fieldHtml = $type->render(
                                    $field->fieldKey,
                                    $field->label,
                                    $current === '' ? null : $current,
                                    $field->constraints,
                                    $field->options,
                                    $field->helpText,
                                    $field->isRequired,
                                    $fieldError
                                );

                                $conditionalJson = $field->conditional !== null
                                    ? (string) json_encode(
                                        $field->conditional,
                                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                                    )
                                    : '';
                            ?>

                            <div class="sf-form-field"
                                 data-sf-field="<?= e($field->fieldKey) ?>"
                                 <?php if ($conditionalJson !== ''): ?>
                                     data-sf-conditional="<?= e($conditionalJson) ?>"
                                 <?php endif; ?>
                                 <?php if (!$visible): ?>
                                     hidden
                                 <?php endif; ?>>
                                <?= $fieldHtml ?>
                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endforeach; ?>

                <div class="sf-form-actions">

                    <?php if ($isMultiStep): ?>
                        <button type="button"
                                class="sf-btn sf-btn-outline"
                                data-sf-step-prev
                                hidden>
                            <i class="bi bi-arrow-left" aria-hidden="true"></i>
                            <span>Back</span>
                        </button>

                        <button type="button"
                                class="sf-btn sf-btn-primary"
                                data-sf-step-next
                                <?= count($steps) === 1 ? 'hidden' : '' ?>>
                            <span>Next</span>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </button>
                    <?php endif; ?>

                    <button type="submit"
                            class="sf-btn sf-btn-primary sf-form-submit"
                            data-sf-step-submit
                            <?= $isMultiStep && count($steps) > 1 ? 'hidden' : '' ?>>
                        <i class="bi bi-send" aria-hidden="true"></i>
                        <span>Send</span>
                    </button>

                </div>

            </form>

        <?php endif; ?>

    </div>

</div>
