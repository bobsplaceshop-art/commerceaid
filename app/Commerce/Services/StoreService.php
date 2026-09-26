<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Repositories\StoreRepository;

/**
 * Business logic for store records.
 *
 * Wraps StoreRepository with validation, defaults, lifecycle rules,
 * and the mandatory-setup check that gates the admin dashboard.
 */
final class StoreService
{
    /** Mandatory fields - admin sees a persistent banner until these are all set. */
    public const MANDATORY_FIELDS = [
        'name',
        'slug',
        'default_currency_id',
        'timezone',
        'country_code',
    ];

    public function __construct(private StoreRepository $stores)
    {
    }

    // ---------- Resolution ----------

    /** @return array<string,mixed>|null */
    public function resolveByHostname(string $hostname): ?array
    {
        return $this->stores->findByHostname($hostname);
    }

    /** @return array<string,mixed>|null */
    public function resolveBySlug(string $slug): ?array
    {
        return $this->stores->findBySlug($slug);
    }

    /** @return array<string,mixed>|null */
    public function resolveById(int $id): ?array
    {
        return $this->stores->findById($id);
    }

    /** @return array<string,mixed>|null */
    public function defaultStore(): ?array
    {
        return $this->stores->findDefault();
    }

    /** @return array<int, array<string,mixed>> */
    public function allActive(): array
    {
        return $this->stores->allActive();
    }

    // ---------- Setup completeness ----------

    /**
     * Returns the list of mandatory fields still missing on this store.
     * Empty array = setup is complete.
     *
     * @return string[]
     */
    public function mandatoryFieldsMissing(int $storeId): array
    {
        $store = $this->stores->findById($storeId);
        if ($store === null) {
            return ['store_missing'];
        }

        $missing = [];
        foreach (self::MANDATORY_FIELDS as $field) {
            if (empty($store[$field])) {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    public function isSetupComplete(int $storeId): bool
    {
        return $this->mandatoryFieldsMissing($storeId) === [];
    }

    // ---------- Create / update ----------

    /**
     * Create a store. Returns the new id.
     *
     * @throws \InvalidArgumentException on validation failure
     */
    public function createStore(array $input): int
    {
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));

        if ($slug === '') {
            throw new \InvalidArgumentException('Store slug is required.');
        }
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,78}[a-z0-9]$/', $slug)) {
            throw new \InvalidArgumentException(
                'Store slug must be 3-80 characters, lowercase letters, digits, and hyphens only.'
            );
        }
        if ($name === '') {
            throw new \InvalidArgumentException('Store name is required.');
        }
        if ($this->stores->findBySlug($slug) !== null) {
            throw new \InvalidArgumentException("A store with slug '{$slug}' already exists.");
        }

        $country = strtoupper((string) ($input['country_code'] ?? 'KE'));
        if (strlen($country) !== 2) {
            throw new \InvalidArgumentException('Country code must be ISO 3166-1 alpha-2 (2 letters).');
        }

        $storeId = $this->stores->create([
            'slug'                  => $slug,
            'name'                  => $name,
            'legal_name'            => $input['legal_name']   ?? null,
            'default_currency_id'   => null, // backfilled once a currency row exists
            'locale'                => $input['locale']       ?? 'en_KE',
            'timezone'              => $input['timezone']     ?? 'Africa/Nairobi',
            'country_code'          => $country,
            'date_format'           => 'd/m/Y',
            'number_format'         => '1,234.56',
            'weight_unit'           => 'kg',
            'dimension_unit'        => 'cm',
            'theme_slug'            => $input['theme_slug']   ?? 'modern',
            'order_prefix'          => 'ORD-',
            'invoice_prefix'        => 'INV-',
            'allow_guest_checkout'  => 1,
            'status'                => 'active',
        ]);

        if (!empty($input['domain'])) {
            $this->stores->addDomain($storeId, (string) $input['domain'], true);
        }

        return $storeId;
    }

    /**
     * Update mutable store fields.
     * Whitelist prevents mass-assignment.
     */
    public function updateStore(int $storeId, array $data): void
    {
        $allowed = [
            'name', 'legal_name', 'slug', 'locale', 'timezone', 'country_code',
            'date_format', 'number_format',
            'weight_unit', 'dimension_unit',
            'theme_slug', 'order_prefix', 'invoice_prefix',
            'allow_guest_checkout', 'require_phone', 'require_company',
            'abandoned_cart_enabled', 'abandoned_cart_threshold_minutes',
            'low_stock_threshold_default',
            'primary_color', 'secondary_color', 'tagline',
            'pwa_name', 'pwa_short_name', 'pwa_theme_color', 'pwa_background_color',
            'seo_default_title', 'seo_default_description',
            'default_currency_id',
            'status',
        ];

        $filtered = array_intersect_key($data, array_flip($allowed));
        if ($filtered === []) {
            return;
        }

        $this->stores->update($storeId, $filtered);
    }

    /** Attach a hostname to a store. */
    public function addDomain(int $storeId, string $hostname, bool $isPrimary = false): int
    {
        $hostname = strtolower(trim($hostname));
        if ($hostname === '' || !preg_match('/^[a-z0-9.-]+$/', $hostname)) {
            throw new \InvalidArgumentException('Invalid hostname.');
        }
        return $this->stores->addDomain($storeId, $hostname, $isPrimary);
    }

    /** @return array<int, array<string,mixed>> */
    public function domainsFor(int $storeId): array
    {
        return $this->stores->domainsFor($storeId);
    }

    public function isMultiStore(): bool
    {
        return count($this->stores->allActive()) > 1;
    }
}
