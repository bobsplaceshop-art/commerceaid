<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support;

/**
 * Shared helpers for admin edit forms.
 *
 * Provides value resolvers that safely translate between snake_case
 * field keys (matching DB columns and form names) and camelCase
 * model properties (matching PHP conventions).
 *
 * Usage in a view:
 *
 *     $val = EditView::valClosure($old ?? [], $product ?? null);
 *     $valBool = EditView::valBoolClosure($old ?? [], $product ?? null);
 *
 * Then in the template:
 *     value="<?= e($val('product_type', 'simple')) ?>"
 *     <?= $valBool('manage_stock') ? 'checked' : '' ?>
 *
 * The helper:
 *   1. Checks old input first (by snake_case key)
 *   2. Falls back to model property (translating to camelCase)
 *   3. Falls back to the default
 *
 * This eliminates the recurring bug where views called
 * $val('manage_stock') and the model had $manageStock.
 */
final class EditView
{
    /**
     * Returns a closure suitable for reading string values.
     *
     * @param array<string,mixed> $old
     */
    public static function valClosure(array $old, mixed $item): \Closure
    {
        return function (string $key, $default = '') use ($old, $item) {
            if (array_key_exists($key, $old)) {
                return (string) $old[$key];
            }
            if ($item === null) {
                return (string) $default;
            }
            $camel = self::toCamel($key);
            $value = $item->$camel ?? $default;
            return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        };
    }

    /**
     * Returns a closure suitable for reading boolean values.
     *
     * @param array<string,mixed> $old
     */
    public static function valBoolClosure(array $old, mixed $item): \Closure
    {
        return function (string $key, bool $default = false) use ($old, $item) {
            if (array_key_exists($key, $old)) {
                return !empty($old[$key]);
            }
            if ($item === null) {
                return $default;
            }
            $camel = self::toCamel($key);
            $value = $item->$camel ?? $default;
            return (bool) $value;
        };
    }

    /**
     * Translate snake_case to camelCase.
     *   product_type       → productType
     *   is_default         → isDefault
     *   requires_approval  → requiresApproval
     */
    public static function toCamel(string $snake): string
    {
        if (strpos($snake, '_') === false) {
            return $snake;
        }
        return lcfirst(str_replace('_', '', ucwords($snake, '_')));
    }
}
