<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * A form field. Stage 009.
 *
 * fieldKey is the submitted name. It is the key the
 * payload JSON uses, and it is what the renderer emits
 * as the input's `name` attribute. It is stable across
 * edits — once saved, it must not change, or the
 * historical submissions lose their ability to map the
 * value back to a label.
 *
 * inputType is one of the twelve keys from the
 * CustomFields InputTypeRegistry: text, email, phone,
 * number, date, url, longtext, select, multiselect,
 * checkbox, yesno, file.
 *
 * options is the list of choices for the option-bearing
 * types (select, multiselect, checkbox). Each entry is
 * a {value, label} pair; the model exposes them as an
 * array of ['value' => ..., 'label' => ...].
 *
 * constraints is the type-specific settings (min/max
 * length, regex, min/max number, date range). The shape
 * is defined by each InputType's configFields() method.
 *
 * conditional is either null (always visible) or a rule
 * of the shape:
 *   ['field' => 'other_key', 'operator' => 'equals', 'value' => 'yes']
 * A field with a conditional rule is only shown (and
 * only validated) when the rule matches the current
 * payload.
 *
 * See docs/stages/009_forms-plan.md.
 */
final class FormField
{
    public int $id = 0;
    public int $storeId = 0;
    public int $formId = 0;
    public string $fieldKey = '';
    public string $label = '';
    public string $inputType = 'text';
    public ?string $placeholder = null;
    public ?string $helpText = null;
    public bool $isRequired = false;

    /** @var array<int,array{value:string,label:string}> */
    public array $options = [];

    /** @var array<string,mixed> */
    public array $constraints = [];

    public ?string $defaultValue = null;
    public int $step = 1;
    public int $sortOrder = 0;

    /** @var array{field:string,operator:string,value:mixed}|null */
    public ?array $conditional = null;

    public bool $isActive = true;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $f = new self();
        $f->id          = (int) $row['id'];
        $f->storeId     = (int) $row['store_id'];
        $f->formId      = (int) $row['form_id'];
        $f->fieldKey    = (string) $row['field_key'];
        $f->label       = (string) $row['label'];
        $f->inputType   = (string) $row['input_type'];
        $f->placeholder = $row['placeholder'] !== null ? (string) $row['placeholder'] : null;
        $f->helpText    = $row['help_text']   !== null ? (string) $row['help_text']   : null;
        $f->isRequired  = ((int) ($row['is_required'] ?? 0)) === 1;

        // options_json shape: [{"value":"x","label":"X"}, ...]
        $f->options = [];
        if (!empty($row['options_json'])) {
            $decoded = is_array($row['options_json'])
                ? $row['options_json']
                : json_decode((string) $row['options_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $opt) {
                    if (!is_array($opt)) continue;
                    $value = (string) ($opt['value'] ?? '');
                    $label = (string) ($opt['label'] ?? $value);
                    if ($value === '') continue;
                    $f->options[] = ['value' => $value, 'label' => $label];
                }
            }
        }

        // constraints_json shape: {"min":1,"max":255,"regex":"..."}
        $f->constraints = [];
        if (!empty($row['constraints_json'])) {
            $decoded = is_array($row['constraints_json'])
                ? $row['constraints_json']
                : json_decode((string) $row['constraints_json'], true);
            if (is_array($decoded)) {
                $f->constraints = $decoded;
            }
        }

        $f->defaultValue = $row['default_value'] !== null ? (string) $row['default_value'] : null;
        $f->step         = (int) ($row['step']       ?? 1);
        $f->sortOrder    = (int) ($row['sort_order'] ?? 0);

        // conditional_json shape:
        //   {"field":"x","operator":"equals","value":"yes"}
        $f->conditional = null;
        if (!empty($row['conditional_json'])) {
            $decoded = is_array($row['conditional_json'])
                ? $row['conditional_json']
                : json_decode((string) $row['conditional_json'], true);
            if (is_array($decoded)
                && isset($decoded['field'], $decoded['operator'])
                && is_string($decoded['field'])
                && is_string($decoded['operator'])
            ) {
                $f->conditional = [
                    'field'    => $decoded['field'],
                    'operator' => $decoded['operator'],
                    'value'    => $decoded['value'] ?? null,
                ];
            }
        }

        $f->isActive  = ((int) ($row['is_active'] ?? 1)) === 1;
        $f->createdAt = $row['created_at'] !== null ? (string) $row['created_at'] : null;
        $f->updatedAt = $row['updated_at'] !== null ? (string) $row['updated_at'] : null;

        return $f;
    }

    public function hasOptions(): bool
    {
        return in_array($this->inputType, ['select', 'multiselect', 'checkbox'], true);
    }

    public function isConditional(): bool
    {
        return $this->conditional !== null;
    }

    /**
     * The field_key as a valid HTML name attribute. The
     * stored key is already lowercase + underscores; this
     * is a defensive normaliser for edge cases.
     */
    public function htmlName(): string
    {
        return preg_replace('/[^a-z0-9_]/', '_', strtolower($this->fieldKey)) ?? $this->fieldKey;
    }
}
