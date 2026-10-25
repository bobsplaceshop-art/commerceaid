<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support\Blocks;

use Closure;

/**
 * Property type vocabulary for block config fields.
 *
 * Every block type declares its fields in
 * BlockConfigRegistry::schema() with a `type` key. This
 * class provides the two behaviours that key drives:
 *
 *   render(ctx)         -> HTML control string
 *   sanitize(raw, meta) -> normalised value for config_json
 *
 * Before this registry, _config-field.php had one branch
 * per type and saveBlockConfig() had a match per type. A
 * ninth type required editing both files in lockstep. The
 * registry collapses the vocabulary into one place; adding
 * a type edits this file only.
 *
 * The boolean and category_multi_select short-circuits in
 * saveBlockConfig() stay in saveBlockConfig(). Those two
 * types must run before the required/empty check (a boolean
 * checkbox posted unchecked is null, but the field must
 * resolve to false, not the default; an empty multi-select
 * must resolve to [] not the default). The registry
 * reproduces the sanitisation operation; the call order in
 * saveBlockConfig() reproduces the short-circuit.
 *
 * Static. No container binding. Same shape as
 * BlockConfigRegistry.
 */
final class ConfigFieldRegistry
{
    /**
     * Look up one type's definition.
     *
     * @return array{render: Closure, sanitize: Closure}|null
     */
    public static function get(string $type): ?array
    {
        return self::types()[$type] ?? null;
    }

    /**
     * Every registered type, keyed by type name.
     *
     * @return array<string, array{render: Closure, sanitize: Closure}>
     */
    public static function all(): array
    {
        return self::types();
    }

    /**
     * The nine types.
     *
     * Render closures receive:
     *   name      string
     *   meta      array<string,mixed>
     *   value     mixed
     *   fieldId   string
     *   categories array<int,mixed>
     *   required  bool
     *
     * Sanitize closures receive:
     *   raw       mixed
     *   meta      array<string,mixed>
     *
     * @return array<string, array{render: Closure, sanitize: Closure}>
     */
    private static function types(): array
    {
        static $types = null;
        if ($types !== null) {
            return $types;
        }

        $types = [
            'string' => [
                'render' => static function (array $c): string {
                    return '<input type="text"'
                        . ' id="' . e($c['fieldId']) . '"'
                        . ' name="' . e($c['name']) . '"'
                        . ' class="form-control"'
                        . ' value="' . e((string) $c['value']) . '"'
                        . ($c['required'] ? ' required' : '') . '>';
                },
                'sanitize' => static function (mixed $raw, array $meta): mixed {
                    return trim((string) $raw);
                },
            ],

            'text' => [
                'render' => static function (array $c): string {
                    return '<textarea'
                        . ' id="' . e($c['fieldId']) . '"'
                        . ' name="' . e($c['name']) . '"'
                        . ' class="form-control"'
                        . ' rows="4"'
                        . ($c['required'] ? ' required' : '') . '>'
                        . e((string) $c['value'])
                        . '</textarea>';
                },
                'sanitize' => static function (mixed $raw, array $meta): mixed {
                    return trim((string) $raw);
                },
            ],

            'int' => [
                'render' => static function (array $c): string {
                    return '<input type="number"'
                        . ' id="' . e($c['fieldId']) . '"'
                        . ' name="' . e($c['name']) . '"'
                        . ' class="form-control"'
                        . ' value="' . e((string) $c['value']) . '"'
                        . ($c['required'] ? ' required' : '') . '>';
                },
                'sanitize' => static function (mixed $raw, array $meta): mixed {
                    return (int) $raw;
                },
            ],

            'boolean' => [
                'render' => static function (array $c): string {
                    $checked = in_array(strtolower((string) $c['value']), ['1', 'true', 'on', 'yes'], true);
                    return '<div class="form-check">'
                        . '<input type="checkbox"'
                        . ' id="' . e($c['fieldId']) . '"'
                        . ' name="' . e($c['name']) . '"'
                        . ' value="1"'
                        . ' class="form-check-input"'
                        . ($checked ? ' checked' : '') . '>'
                        . '<label for="' . e($c['fieldId']) . '" class="form-check-label">Enable</label>'
                        . '</div>';
                },
                'sanitize' => static function (mixed $raw, array $meta): mixed {
                    return $raw === '1' || $raw === 'on' || $raw === true;
                },
            ],

            'select' => [
                'render' => static function (array $c): string {
                    $options = is_array($c['meta']['options'] ?? null) ? $c['meta']['options'] : [];
                    $html = '<select id="' . e($c['fieldId']) . '"'
                        . ' name="' . e($c['name']) . '"'
                        . ' class="form-select">';
                    foreach ($options as $optVal => $optLabel) {
                        $sel = (string) $c['value'] === (string) $optVal ? ' selected' : '';
                        $html .= '<option value="' . e((string) $optVal) . '"' . $sel . '>'
                            . e((string) $optLabel) . '</option>';
                    }
                    return $html . '</select>';
                },
                'sanitize' => static function (mixed $raw, array $meta): mixed {
                    return trim((string) $raw);
                },
            ],

            'category_select' => [
                'render' => static function (array $c): string {
                    $html = '<select id="' . e($c['fieldId']) . '"'
                        . ' name="' . e($c['name']) . '"'
                        . ' class="form-select">'
                        . '<option value="">— Select a category —</option>';
                    foreach ($c['categories'] as $cat) {
                        $sel = (string) $c['value'] === (string) $cat->slug ? ' selected' : '';
                        $html .= '<option value="' . e((string) $cat->slug) . '"' . $sel . '>'
                            . e((string) $cat->name) . '</option>';
                    }
                    return $html . '</select>';
                },
                'sanitize' => static function (mixed $raw, array $meta): mixed {
                    return trim((string) $raw);
                },
            ],

            'category_multi_select' => [
                'render' => static function (array $c): string {
                    $selectedIds = is_array($c['value'])
                        ? array_map('intval', $c['value'])
                        : [];

                    // Checkbox list rather than native <select multiple>.
                    // Easier to use, and consistent with how the
                    // product editor's category picker works. Name
                    // attribute is name[] so POST delivers an array
                    // of checked ids — the sanitize closure below
                    // expects exactly that shape.
                    $html = '<div id="' . e($c['fieldId']) . '"'
                        . ' style="max-height:240px; overflow-y:auto;'
                        . ' border:1px solid var(--ra-border,#2d3748);'
                        . ' border-radius:6px; padding:.5rem .75rem;'
                        . ' background:var(--ra-surface-1,#0f172a);">';
                    if ($c['categories'] === []) {
                        $html .= '<div class="text-muted small">'
                            . 'No categories yet.</div>';
                    }
                    foreach ($c['categories'] as $cat) {
                        $id = (int) $cat->id;
                        $checked = in_array($id, $selectedIds, true) ? ' checked' : '';
                        $html .= '<div class="form-check" style="margin-bottom:.15rem;">'
                            . '<input type="checkbox"'
                            . ' class="form-check-input"'
                            . ' id="' . e($c['fieldId']) . '-' . $id . '"'
                            . ' name="' . e($c['name']) . '[]"'
                            . ' value="' . $id . '"' . $checked . '>'
                            . '<label class="form-check-label"'
                            . ' for="' . e($c['fieldId']) . '-' . $id . '"'
                            . ' style="cursor:pointer;">'
                            . e((string) $cat->name)
                            . '</label>'
                            . '</div>';
                    }
                    return $html . '</div>';
                },
                'sanitize' => static function (mixed $raw, array $meta): mixed {
                    $values = is_array($raw) ? $raw : [];
                    return array_values(array_map('intval', $values));
                },
            ],

            'file' => [
                'render' => static function (array $c): string {
                    $fileId = is_numeric($c['value']) ? (int) $c['value'] : 0;
                    $previewUrl = (string) ($c['previewUrl'] ?? '');
                    $html = '<div class="ra-file-field" style="display:flex; align-items:center; gap:.75rem;">';
                    if ($previewUrl !== '') {
                        $html .= '<img src="' . e($previewUrl) . '"'
                            . ' alt="Selected image"'
                            . ' style="width:64px; height:64px; object-fit:cover;'
                            . ' border-radius:4px; border:1px solid var(--ra-border);">';
                    } else {
                        $html .= '<div style="width:64px; height:64px; border:1px dashed'
                            . ' var(--ra-border); border-radius:4px; display:flex;'
                            . ' align-items:center; justify-content:center;'
                            . ' color:var(--ra-muted);"><i class="bi bi-image"></i></div>';
                    }
                    $html .= '<input type="hidden"'
                        . ' id="' . e($c['fieldId']) . '"'
                        . ' name="' . e($c['name']) . '"'
                        . ' value="' . ($fileId > 0 ? (int) $fileId : '') . '">';
                    $html .= '<button type="button"'
                        . ' class="ra-btn ra-btn-outline ra-btn-sm"'
                        . ' data-block-media-open="' . e($c['fieldId']) . '">'
                        . '<i class="bi bi-images me-1"></i> Choose image</button>';
                    if ($fileId > 0) {
                        $html .= '<button type="button"'
                            . ' class="ra-btn ra-btn-sm"'
                            . ' style="background:transparent;border:1px solid'
                            . ' var(--ra-danger);color:var(--ra-danger);"'
                            . ' data-block-media-clear="' . e($c['fieldId']) . '"'
                            . ' data-settings-media-clear="' . e($c['fieldId']) . '">'
                            . 'Remove</button>';
                    }
                    return $html . '</div>';
                },
                'sanitize' => static function (mixed $raw, array $meta): mixed {
                    return is_numeric($raw) ? (int) $raw : null;
                },
            ],

            'typography' => [
                'render' => static function (array $c): string {
                    $value = is_array($c['value']) ? $c['value'] : [];
                    $font      = (string) ($value['font']      ?? 'inherit');
                    $size      = (string) ($value['size']      ?? 'inherit');
                    $weight    = (string) ($value['weight']    ?? 'inherit');
                    $alignment = (string) ($value['alignment'] ?? 'inherit');

                    $fontOptions   = \CorporateAid\Commerce\Support\Blocks\BlockConfigRegistry::fontOptions();
                    $sizeOptions   = ['inherit' => 'Inherit', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large', 'xl' => 'Extra large', '2xl' => 'Huge'];
                    $weightOptions = ['inherit' => 'Inherit', 'normal' => 'Normal', 'medium' => 'Medium', 'bold' => 'Bold'];
                    $alignOptions  = ['inherit' => 'Inherit', 'left' => 'Left', 'center' => 'Center', 'right' => 'Right'];

                    $select = function (string $sub, array $options, string $current) use ($c): string {
                        $html = '<select name="' . e($c['name']) . '[' . $sub . ']" class="form-select form-select-sm">';
                        foreach ($options as $val => $label) {
                            $sel = (string) $val === $current ? ' selected' : '';
                            $html .= '<option value="' . e((string) $val) . '"' . $sel . '>'
                                . e((string) $label) . '</option>';
                        }
                        return $html . '</select>';
                    };

                    $html  = '<div class="ra-typography-panel" style="border:1px solid var(--ra-border); border-radius:6px; padding:.75rem;">';
                    $html .= '<div class="row g-2">';
                    $html .= '<div class="col-6"><label class="form-label small mb-1">Font</label>'      . $select('font', $fontOptions, $font)           . '</div>';
                    $html .= '<div class="col-6"><label class="form-label small mb-1">Size</label>'      . $select('size', $sizeOptions, $size)           . '</div>';
                    $html .= '<div class="col-6"><label class="form-label small mb-1">Weight</label>'    . $select('weight', $weightOptions, $weight)     . '</div>';
                    $html .= '<div class="col-6"><label class="form-label small mb-1">Alignment</label>' . $select('alignment', $alignOptions, $alignment) . '</div>';
                    $html .= '</div></div>';
                    return $html;
                },
                'sanitize' => static function (mixed $raw, array $meta): mixed {
                    $in = is_array($raw) ? $raw : [];

                    $fontKeys   = array_keys(\CorporateAid\Commerce\Support\Blocks\BlockConfigRegistry::fontOptions());
                    $sizeKeys   = ['inherit', 'sm', 'md', 'lg', 'xl', '2xl'];
                    $weightKeys = ['inherit', 'normal', 'medium', 'bold'];
                    $alignKeys  = ['inherit', 'left', 'center', 'right'];

                    $font      = (string) ($in['font']      ?? 'inherit');
                    $size      = (string) ($in['size']      ?? 'inherit');
                    $weight    = (string) ($in['weight']    ?? 'inherit');
                    $alignment = (string) ($in['alignment'] ?? 'inherit');

                    return [
                        'font'      => in_array($font, $fontKeys, true)        ? $font      : 'inherit',
                        'size'      => in_array($size, $sizeKeys, true)        ? $size      : 'inherit',
                        'weight'    => in_array($weight, $weightKeys, true)    ? $weight    : 'inherit',
                        'alignment' => in_array($alignment, $alignKeys, true)  ? $alignment : 'inherit',
                    ];
                },
            ],

            'color' => [
                'render' => static function (array $c): string {
                    $hex = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $c['value'])
                        ? (string) $c['value']
                        : '#000000';
                    return '<div class="d-flex gap-2">'
                        . '<input type="color"'
                        . ' id="' . e($c['fieldId']) . '"'
                        . ' name="' . e($c['name']) . '"'
                        . ' class="form-control form-control-color"'
                        . ' value="' . e($hex) . '">'
                        . '<input type="text"'
                        . ' class="form-control"'
                        . ' value="' . e($hex) . '"'
                        . ' oninput="document.getElementById(\'' . e($c['fieldId']) . '\').value = this.value">'
                        . '</div>';
                },
                'sanitize' => static function (mixed $raw, array $meta): mixed {
                    $s = (string) $raw;
                    return preg_match('/^#[0-9a-fA-F]{6}$/', $s) ? $s : null;
                },
            ],
        ];

        return $types;
    }
}
