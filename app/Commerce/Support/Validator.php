<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support;

/**
 * Small, composable field validator.
 *
 * Each static method returns a human-readable error string when the
 * value is invalid, or null when it passes. Controllers collect the
 * nulls away and merge the errors into a single array.
 *
 * Usage:
 *
 *   $errors = [];
 *   if ($e = Validator::required($data['name'], 'Name')) $errors['name'] = $e;
 *   if ($e = Validator::maxLength($data['name'], 200, 'Name')) $errors['name'] = $e;
 *   if ($e = Validator::url($data['website'], 'Website')) $errors['website'] = $e;
 *
 * No dependencies. No config. No fluent API. Just functions.
 */
final class Validator
{
    /**
     * Value must be a non-empty string after trimming.
     */
    public static function required(mixed $value, string $label): ?string
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if ($value === null || $value === '' || $value === []) {
            return "{$label} is required.";
        }
        return null;
    }

    public static function maxLength(mixed $value, int $max, string $label): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $len = mb_strlen($value);
        if ($len > $max) {
            return "{$label} must be {$max} characters or fewer (currently {$len}).";
        }
        return null;
    }

    public static function minLength(mixed $value, int $min, string $label): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $len = mb_strlen($value);
        if ($len < $min) {
            return "{$label} must be at least {$min} characters.";
        }
        return null;
    }

    /**
     * Value must match the given PCRE pattern.
     * Caller supplies the hint shown to the user.
     */
    public static function pattern(mixed $value, string $regex, string $label, string $hint): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        if (!preg_match($regex, $value)) {
            return "{$label} must be {$hint}.";
        }
        return null;
    }

    /**
     * Strict URL validation: must be http:// or https:// with a
     * dotted host. Rejects htp://, ftp://, and other typos.
     */
    public static function url(mixed $value, string $label): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        // 1. Scheme check — must be http or https
        $scheme = parse_url($value, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return "{$label} must start with http:// or https://.";
        }

        // 2. Host check — must have a dot and at least 2 chars TLD
        $host = parse_url($value, PHP_URL_HOST);
        if (!is_string($host) || !preg_match('/\.[a-z]{2,}$/i', $host)) {
            return "{$label} must include a valid domain (e.g. https://example.com).";
        }

        // 3. PHP's built-in check — catches remaining edge cases
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return "{$label} is not a valid URL.";
        }

        return null;
    }

    public static function email(mixed $value, string $label): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return "{$label} is not a valid email address.";
        }
        return null;
    }

    /**
     * Value must be one of the allowed list.
     * @param array<int, string> $allowed
     */
    public static function inArray(mixed $value, array $allowed, string $label): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!in_array($value, $allowed, true)) {
            return "{$label} has an invalid value.";
        }
        return null;
    }

    /**
     * Value must be a valid IANA timezone identifier.
     * Examples: Africa/Nairobi, Europe/Berlin, America/New_York.
     */
    public static function timezone(mixed $value, string $label = 'Timezone'): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        if (!in_array($value, timezone_identifiers_list(), true)) {
            return "{$label} is not a recognised IANA timezone (e.g. Africa/Nairobi).";
        }
        return null;
    }

    /**
     * Positive integer > 0.
     */
    public static function positiveInt(mixed $value, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value) || (int) $value < 1) {
            return "{$label} must be a positive number.";
        }
        return null;
    }

    /**
     * Numeric value >= 0.
     */
    public static function nonNegativeNumber(mixed $value, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value) || (float) $value < 0) {
            return "{$label} must be zero or greater.";
        }
        return null;
    }

    /**
     * Hex color: #RRGGBB.
     */
    public static function hexColor(mixed $value, string $label): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return "{$label} must be a hex color (e.g. #0f172a).";
        }
        return null;
    }

    /**
     * ISO 3166-1 alpha-2 country code (two letters).
     */
    public static function countryCode(mixed $value, string $label = 'Country'): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        if (!preg_match('/^[A-Za-z]{2}$/', $value)) {
            return "{$label} must be a 2-letter ISO country code.";
        }
        return null;
    }

    /**
     * Slug format: lowercase, digits, hyphens, starts and ends with
     * an alphanumeric. Enforces both format and length.
     */
    public static function slug(mixed $value, int $max = 120, string $label = 'Slug'): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        if (mb_strlen($value) > $max) {
            return "{$label} must be {$max} characters or fewer.";
        }
        if (!preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', $value) && !preg_match('/^[a-z0-9]$/', $value)) {
            return "{$label} must be lowercase letters, digits, and hyphens only.";
        }
        return null;
    }

    /**
     * Run multiple rule closures against a value. Returns the first
     * error encountered, or null.
     *
     * @param callable[] $rules
     */
    public static function firstError(mixed $value, array $rules): ?string
    {
        foreach ($rules as $rule) {
            $error = $rule($value);
            if ($error !== null) {
                return $error;
            }
        }
        return null;
    }
}
