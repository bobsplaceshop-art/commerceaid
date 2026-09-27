<?php
declare(strict_types=1);

namespace CorporateAid\Platform\SiteType;

/**
 * Registry of known site types.
 *
 * Populated at app boot. Core and Platform register nothing by
 * default - each product contributes its own site types. The
 * first registered type is the fallback used when a store's
 * site_type column is empty or unknown.
 *
 * Registered by:
 *   - CommerceServiceProvider registers EcommerceSiteType
 *   - Future products register their own (Property, Website, etc.)
 *
 * Access:
 *   $registry = $container->get(SiteTypeRegistry::class);
 *   $type = $registry->get('ecommerce');
 *
 * Contract:
 *   - Registration order determines display order in the
 *     onboarding dialog.
 *   - Duplicate keys throw immediately.
 *   - Lookups return null for unknown keys - callers decide
 *     whether that is an error or a fallback.
 */
final class SiteTypeRegistry
{
    /** @var array<string, SiteType> */
    private array $types = [];

    /**
     * Register a site type. Throws if the key is already taken.
     */
    public function register(SiteType $type): void
    {
        $key = $type->key();
        if ($key === '') {
            throw new \InvalidArgumentException(
                'Site type key must not be empty.'
            );
        }
        if (isset($this->types[$key])) {
            throw new \RuntimeException(
                "Site type '{$key}' is already registered by "
                . get_class($this->types[$key])
            );
        }
        $this->types[$key] = $type;
    }

    /**
     * Resolve a site type by its key, or null if unknown.
     */
    public function get(string $key): ?SiteType
    {
        return $this->types[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    /**
     * All registered site types in registration order.
     *
     * @return array<int, SiteType>
     */
    public function all(): array
    {
        return array_values($this->types);
    }

    /**
     * Just the keys, in registration order.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->types);
    }

    /**
     * The default site type for a store that has no explicit
     * selection. Returns the first registered type, or null if
     * nothing is registered.
     */
    public function default(): ?SiteType
    {
        foreach ($this->types as $type) {
            return $type;
        }
        return null;
    }
}
