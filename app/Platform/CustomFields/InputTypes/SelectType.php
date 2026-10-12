<?php
declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields\InputTypes;

use CorporateAid\Platform\CustomFields\InputType;

final class SelectType implements InputType
{
    public function key(): string { return 'select'; }
    public function label(): string { return 'Dropdown (single choice)'; }
    public function supportsOptions(): bool { return true; }
    public function configFields(): array { return []; }

    /** @param array<string,mixed> $constraints */
    public function validate(mixed $value, array $constraints, bool $isRequired): array
    {
        $v = is_string($value) ? trim($value) : '';
        $errors = [];
        if ($isRequired && $v === '') { $errors[] = 'This field is required.'; return $errors; }
        if ($v === '') return $errors;

        // Options come from $constraints['options'] when validating - the validator
        // passes the field's options via constraints so we don't need a 4th parameter.
        $options = $constraints['_options'] ?? [];
        if (!is_array($options) || !in_array($v, array_map('strval', $options), true)) {
            $errors[] = 'Must be one of the allowed options.';
        }
        return $errors;
    }

    public function cast(mixed $value): ?string
    {
        $v = is_string($value) ? trim($value) : '';
        return $v === '' ? null : $v;
    }

    public function humanize(?string $value): string { return $value ?? ''; }

    /** @param array<string,mixed> $constraints */
    public function render(
        string $fieldKey,
        string $fieldLabel,
        ?string $currentValue,
        array $constraints,
        array $options,
        ?string $helpText,
        bool $isRequired,
        array $errors = []
    ): string {
        $html  = '<div class="ra-field">';
        $html .= '<label class="ra-label" for="cf_' . e($fieldKey) . '">' . e($fieldLabel);
        if ($isRequired) $html .= ' <span style="color:var(--ra-error-fg)">*</span>';
        $html .= '</label>';
        $html .= '<select id="cf_' . e($fieldKey) . '"'
               . ' name="cf[' . e($fieldKey) . ']"'
               . ' class="ra-input"' . ($isRequired ? ' required' : '') . '>';
        $html .= '<option value="">&mdash; Select &mdash;</option>';
        foreach ($options as $opt) {
            $optValue    = is_array($opt) ? (string) ($opt['value'] ?? '') : (string) $opt;
            $optLabel    = is_array($opt) ? (string) ($opt['label'] ?? $optValue) : $optValue;
            if ($optValue === '') continue;
            $selected    = ($optValue === (string) $currentValue) ? ' selected' : '';
            $html .= '<option value="' . e($optValue) . '"' . $selected . '>' . e($optLabel) . '</option>';
        }
        $html .= '</select>';
        if ($helpText) $html .= '<div class="muted small mt-1">' . e($helpText) . '</div>';
        foreach ($errors as $err) $html .= '<div class="small" style="color:var(--ra-error-fg);margin-top:.25rem;">' . e($err) . '</div>';
        $html .= '</div>';
        return $html;
    }
}
