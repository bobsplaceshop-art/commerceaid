<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support;

use CorporateAid\Commerce\Models\Store;

/**
 * Read a field from a Store model by its snake_case key.
 *
 * The setup wizard views were written against an array-shaped
 * $store. When the Store model shipped in Stage 008A, the
 * array-access reads broke silently. This helper bridges the
 * two conventions: callers pass the snake_case key that
 * matches the form field name; the helper reads the
 * corresponding camelCase property.
 *
 * Converts 'legal_name' -> 'legalName', 'allow_guest_checkout'
 * -> 'allowGuestCheckout'. Returns the default when the store
 * is null or the property does not exist.
 */
final class StoreFieldReader
{
    public static function read(?Store $store, string $snakeKey, mixed $default = ''): mixed
    {
        if ($store === null || $snakeKey === '') {
            return $default;
        }

        // Direct property first (in case the property is
        // already snake_case).
        if (property_exists($store, $snakeKey)) {
            $value = $store->{$snakeKey};
            return $value ?? $default;
        }

        // Camel-case translation.
        $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $snakeKey))));
        if (property_exists($store, $camel)) {
            $value = $store->{$camel};
            return $value ?? $default;
        }

        return $default;
    }
}
