<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Store DTO.
 *
 * A store is the tenant anchor for every commerce_* table. Every
 * tenant-scoped row carries store_id. One install can host
 * multiple stores (multi-tenant from day one), though the current
 * deployment has exactly one.
 *
 * Business rules:
 *   - Exactly one store per install is the "default" (resolved
 *     when no other signal is available)
 *   - slug is globally unique across all installs (composite key)
 *   - status drives visibility: active / suspended / archived
 *   - Setup wizard writes the branding columns (logo, colors,
 *     pwa fields, seo fields) during initial configuration
 *
 * Every property has a default value. This is non-negotiable -
 * PHP 8 throws on access to an uninitialized typed property.
 * See Pattern 2 in docs/REMEMBER.md.
 */
final class Store
{
    public int $id = 0;
    public string $slug = '';
    public string $siteType = 'ecommerce';
    public string $name = '';
    public ?string $legalName = null;
    public ?int $defaultCurrencyId = null;
    public string $countryCode = '';
    public string $locale = 'en_KE';
    public string $timezone = 'Africa/Nairobi';
    public string $dateFormat = 'd/m/Y';
    public string $numberFormat = '1,234.56';
    public string $weightUnit = 'kg';
    public string $dimensionUnit = 'cm';
    public ?int $logoFileId = null;
    public ?int $faviconFileId = null;
    public ?string $primaryColor = null;
    public ?string $secondaryColor = null;
    public ?string $tagline = null;
    public string $themeSlug = 'modern';
    public string $orderPrefix = 'ORD-';
    public string $invoicePrefix = 'INV-';
    public bool $allowGuestCheckout = true;
    public bool $requirePhone = false;
    public bool $requireCompany = false;
    public bool $abandonedCartEnabled = true;
    public int $abandonedCartThresholdMinutes = 60;
    public int $lowStockThresholdDefault = 5;
    public ?string $pwaName = null;
    public ?string $pwaShortName = null;
    public ?string $pwaThemeColor = null;
    public ?string $pwaBackgroundColor = null;

    // Font selections. Null = use the platform default.
    // Non-null = a slug from FontCatalog. The downloader
    // fetches the WOFF2 files the first time a slug is
    // selected.
    public ?string $fontBodySlug    = null;
    public ?string $fontDisplaySlug = null;
    public ?string $fontScriptSlug  = null;
    public ?string $seoDefaultTitle = null;
    public ?string $seoDefaultDescription = null;
    public ?int $seoDefaultOgFileId = null;
    public string $status = 'active';
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
    public ?int $createdBy = null;
    public ?int $updatedBy = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $s = new self();
        $s->id                           = (int) $row['id'];
        $s->slug                         = (string) $row['slug'];
        $s->siteType                     = (string) ($row['site_type'] ?? 'ecommerce');
        $s->name                         = (string) $row['name'];
        $s->legalName                    = $row['legal_name'] ?? null;
        $s->defaultCurrencyId            = isset($row['default_currency_id']) ? (int) $row['default_currency_id'] : null;
        $s->countryCode                  = (string) ($row['country_code'] ?? '');
        $s->locale                       = (string) ($row['locale'] ?? 'en_KE');
        $s->timezone                     = (string) ($row['timezone'] ?? 'Africa/Nairobi');
        $s->dateFormat                   = (string) ($row['date_format'] ?? 'd/m/Y');
        $s->numberFormat                 = (string) ($row['number_format'] ?? '1,234.56');
        $s->weightUnit                   = (string) ($row['weight_unit'] ?? 'kg');
        $s->dimensionUnit                = (string) ($row['dimension_unit'] ?? 'cm');
        $s->logoFileId                   = isset($row['logo_file_id']) ? (int) $row['logo_file_id'] : null;
        $s->faviconFileId                = isset($row['favicon_file_id']) ? (int) $row['favicon_file_id'] : null;
        $s->primaryColor                 = $row['primary_color'] ?? null;
        $s->secondaryColor               = $row['secondary_color'] ?? null;
        $s->tagline                      = $row['tagline'] ?? null;
        $s->themeSlug                    = (string) ($row['theme_slug'] ?? 'modern');
        $s->orderPrefix                  = (string) ($row['order_prefix'] ?? 'ORD-');
        $s->invoicePrefix                = (string) ($row['invoice_prefix'] ?? 'INV-');
        $s->allowGuestCheckout           = (bool) ($row['allow_guest_checkout'] ?? true);
        $s->requirePhone                 = (bool) ($row['require_phone'] ?? false);
        $s->requireCompany               = (bool) ($row['require_company'] ?? false);
        $s->abandonedCartEnabled         = (bool) ($row['abandoned_cart_enabled'] ?? true);
        $s->abandonedCartThresholdMinutes = (int) ($row['abandoned_cart_threshold_minutes'] ?? 60);
        $s->lowStockThresholdDefault     = (int) ($row['low_stock_threshold_default'] ?? 5);
        $s->pwaName                      = $row['pwa_name'] ?? null;
        $s->pwaShortName                 = $row['pwa_short_name'] ?? null;
        $s->pwaThemeColor                = $row['pwa_theme_color'] ?? null;
        $s->pwaBackgroundColor           = $row['pwa_background_color'] ?? null;
        $s->seoDefaultTitle              = $row['seo_default_title'] ?? null;
        $s->seoDefaultDescription        = $row['seo_default_description'] ?? null;
        $s->seoDefaultOgFileId           = isset($row['seo_default_og_file_id']) ? (int) $row['seo_default_og_file_id'] : null;
        $s->status                       = (string) ($row['status'] ?? 'active');
        $s->createdAt                    = $row['created_at'] ?? null;
        $s->updatedAt                    = $row['updated_at'] ?? null;
        $s->createdBy                    = isset($row['created_by']) ? (int) $row['created_by'] : null;
        $s->updatedBy                    = isset($row['updated_by']) ? (int) $row['updated_by'] : null;
        
        $s->fontBodySlug                 = $row['font_body_slug']    ?? null;
        $s->fontDisplaySlug              = $row['font_display_slug'] ?? null;
        $s->fontScriptSlug               = $row['font_script_slug']  ?? null;
        return $s;
    }

    // ---------- Status helpers ----------

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Is this store an e-commerce site type?
     *
     * Convenience for code that needs to branch on site type.
     * Additional helpers (isProperty(), isWebsite(), etc.) are
     * added as new site types ship.
     */
    public function isEcommerce(): bool
    {
        return $this->siteType === 'ecommerce';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    // ---------- Display helpers ----------

    public function displayName(): string
    {
        if ($this->name !== '') {
            return $this->name;
        }
        if ($this->legalName !== null && $this->legalName !== '') {
            return $this->legalName;
        }
        return 'Store';
    }

    public function hasLogo(): bool
    {
        return $this->logoFileId !== null && $this->logoFileId > 0;
    }

    public function hasFavicon(): bool
    {
        return $this->faviconFileId !== null && $this->faviconFileId > 0;
    }

    public function hasTagline(): bool
    {
        return $this->tagline !== null && $this->tagline !== '';
    }

    // ---------- Branding helpers ----------

    public function primaryColorOr(string $fallback = '#b45309'): string
    {
        if ($this->primaryColor !== null && $this->primaryColor !== '') {
            return $this->primaryColor;
        }
        return $fallback;
    }

    public function secondaryColorOr(string $fallback = '#92400e'): string
    {
        if ($this->secondaryColor !== null && $this->secondaryColor !== '') {
            return $this->secondaryColor;
        }
        return $fallback;
    }

    // ---------- PWA helpers ----------

    public function hasPwaBranding(): bool
    {
        return ($this->pwaName !== null && $this->pwaName !== '')
            || ($this->pwaShortName !== null && $this->pwaShortName !== '');
    }

    public function pwaDisplayName(): string
    {
        if ($this->pwaName !== null && $this->pwaName !== '') {
            return $this->pwaName;
        }
        return $this->displayName();
    }

    // ---------- Checkout helpers ----------

    public function canGuestCheckout(): bool
    {
        return $this->allowGuestCheckout && $this->isActive();
    }

    public function requiresPhoneOnCheckout(): bool
    {
        return $this->requirePhone;
    }

    public function requiresCompanyOnCheckout(): bool
    {
        return $this->requireCompany;
    }

    // ---------- Column introspection ----------

    /**
     * Is the given database column value empty on this store?
     *
     * Maps a snake_case column name to its camelCase property and
     * returns true if that property is null, empty string, or zero.
     *
     * Used by StoreService::mandatoryFieldsMissing() to check which
     * required setup fields are still unset. Keeps the column-name
     * to property-name mapping in one place instead of duplicating
     * the string munging at every call site.
     *
     * Returns true for unknown columns - treat missing mappings as
     * "empty" so the caller can flag them.
     */
    public function isEmpty(string $column): bool
    {
        $value = match ($column) {
            'id'                             => $this->id,
            'slug'                           => $this->slug,
            'name'                           => $this->name,
            'legal_name'                     => $this->legalName,
            'default_currency_id'            => $this->defaultCurrencyId,
            'country_code'                   => $this->countryCode,
            'locale'                         => $this->locale,
            'timezone'                       => $this->timezone,
            'date_format'                    => $this->dateFormat,
            'number_format'                  => $this->numberFormat,
            'weight_unit'                    => $this->weightUnit,
            'dimension_unit'                 => $this->dimensionUnit,
            'logo_file_id'                   => $this->logoFileId,
            'favicon_file_id'                => $this->faviconFileId,
            'primary_color'                  => $this->primaryColor,
            'secondary_color'                => $this->secondaryColor,
            'tagline'                        => $this->tagline,
            'theme_slug'                     => $this->themeSlug,
            'order_prefix'                   => $this->orderPrefix,
            'invoice_prefix'                 => $this->invoicePrefix,
            'allow_guest_checkout'           => $this->allowGuestCheckout,
            'require_phone'                  => $this->requirePhone,
            'require_company'                => $this->requireCompany,
            'abandoned_cart_enabled'         => $this->abandonedCartEnabled,
            'abandoned_cart_threshold_minutes' => $this->abandonedCartThresholdMinutes,
            'low_stock_threshold_default'    => $this->lowStockThresholdDefault,
            'pwa_name'                       => $this->pwaName,
            'pwa_short_name'                 => $this->pwaShortName,
            'pwa_theme_color'                => $this->pwaThemeColor,
            'pwa_background_color'           => $this->pwaBackgroundColor,
            'seo_default_title'              => $this->seoDefaultTitle,
            'seo_default_description'        => $this->seoDefaultDescription,
            'seo_default_og_file_id'         => $this->seoDefaultOgFileId,
            'status'                         => $this->status,
            'created_at'                     => $this->createdAt,
            'updated_at'                     => $this->updatedAt,
            'created_by'                     => $this->createdBy,
            'updated_by'                     => $this->updatedBy,
            default                          => null,
        };

        return empty($value);
    }
}
