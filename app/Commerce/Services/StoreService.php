<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Models\Store;
use CorporateAid\Commerce\Repositories\StoreRepository;

/**
 * Business logic for store records.
 *
 * Wraps StoreRepository with validation, defaults, lifecycle rules,
 * and the mandatory-setup check that gates the admin dashboard.
 *
 * Read methods return Store models. Write methods accept plain
 * arrays (whitelisted by updateStore to prevent mass-assignment).
 */
final class StoreService
{
    /**
     * Mandatory fields - admin sees a persistent banner until these
     * are all set. Field names are database column names; the Store
     * model's isEmpty() method translates them to properties.
     */
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

    public function resolveByHostname(string $hostname): ?Store
    {
        return $this->stores->findByHostname($hostname);
    }

    public function resolveBySlug(string $slug): ?Store
    {
        return $this->stores->findBySlug($slug);
    }

    public function resolveById(int $id): ?Store
    {
        return $this->stores->findById($id);
    }

    public function defaultStore(): ?Store
    {
        return $this->stores->findDefault();
    }

    /** @return array<int, Store> */
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
            if ($store->isEmpty($field)) {
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
     * @param array<string,mixed> $input
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

        // Site type determines which admin navigation groups
        // are visible and which defaults are applied. Defaults to
        // 'ecommerce' when not supplied. See SiteTypeRegistry for
        // the list of valid keys.
        $siteType = (string) ($input['site_type'] ?? 'ecommerce');
        if (!preg_match('/^[a-z][a-z0-9-]{0,48}$/', $siteType)) {
            throw new \InvalidArgumentException(
                'Site type must be a lowercase kebab-case key.'
            );
        }

        $storeId = $this->stores->create([
            'slug'                  => $slug,
            'site_type'             => $siteType,
            'name'                  => $name,
            'legal_name'            => $input['legal_name']   ?? null,
            'default_currency_id'   => null,
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
     *
     * @param array<string,mixed> $data
     */
    public function updateStore(int $storeId, array $data): void
    {
        $allowed = [
            'name', 'legal_name', 'slug', 'site_type',
            'locale', 'timezone', 'country_code',
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
