<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support;

/**
 * Country-driven compliance rules.
 *
 * The first deployment destination is Germany. A future
 * deployment in France or Kenya has different rules. Rather
 * than hardcode German slugs into the checkout gate, this map
 * declares what each country requires and the gate reads it.
 *
 * The store's country_code (commerce_stores.country_code,
 * set during Stage 005 setup) selects the rule set. If the
 * country is not in the map, a safe default applies with all
 * compliance elements off.
 *
 * See docs/stages/008e.1_german-compliance-plan.md.
 */
final class ComplianceMap
{
    /**
     * Country code to compliance rules.
     *
     * Keys:
     *   cookie_banner      - show the TTDSG consent banner
     *   legal_pages        - which legal page slugs must exist
     *   widerruf_required  - is the withdrawal instruction required
     *   verpackg_notice    - show the VerpackG registration notice
     *   agb_required       - is the terms-and-conditions checkbox required
     *
     * @var array<string, array{
     *     cookie_banner: bool,
     *     legal_pages: array<int, string>,
     *     widerruf_required: bool,
     *     verpackg_notice: bool,
     *     agb_required: bool
     * }>
     */
    private const MAP = [
        'DE' => [
            'cookie_banner'      => true,
            'legal_pages'        => ['impressum', 'agb', 'widerruf', 'datenschutz'],
            'widerruf_required'  => true,
            'verpackg_notice'    => true,
            'agb_required'       => true,
        ],
        // Placeholders for future deployments. Not wired yet.
        // 'FR' => [ ... ],
        // 'KE' => [ ... ],
    ];

    /**
     * The safe default for a country not in the map. All
     * compliance elements off. The admin sees a warning
     * suggesting they configure the country.
     *
     * @var array<string, mixed>
     */
    private const DEFAULT_RULES = [
        'cookie_banner'     => false,
        'legal_pages'       => [],
        'widerruf_required' => false,
        'verpackg_notice'   => false,
        'agb_required'      => false,
    ];

    /**
     * Compliance rules for a country code. Falls back to the
     * safe default when the country is unknown.
     *
     * @return array<string, mixed>
     */
    public static function for(string $countryCode): array
    {
        $code = strtoupper(trim($countryCode));
        return self::MAP[$code] ?? self::DEFAULT_RULES;
    }

    /**
     * Which legal page slugs must exist for this country.
     *
     * @return array<int, string>
     */
    public static function requiredLegalSlugs(string $countryCode): array
    {
        $rules = self::for($countryCode);
        return is_array($rules['legal_pages'] ?? null)
            ? $rules['legal_pages']
            : [];
    }

    /**
     * Is the cookie consent banner required?
     */
    public static function cookieBannerRequired(string $countryCode): bool
    {
        return (bool) (self::for($countryCode)['cookie_banner'] ?? false);
    }

    /**
     * Is the Widerrufsbelehrung required?
     */
    public static function widerrufRequired(string $countryCode): bool
    {
        return (bool) (self::for($countryCode)['widerruf_required'] ?? false);
    }

    /**
     * Is the VerpackG notice relevant?
     */
    public static function verpackgNoticeRelevant(string $countryCode): bool
    {
        return (bool) (self::for($countryCode)['verpackg_notice'] ?? false);
    }

    /**
     * Is the AGB checkbox required?
     */
    public static function agbRequired(string $countryCode): bool
    {
        return (bool) (self::for($countryCode)['agb_required'] ?? false);
    }

    /**
     * Which countries are explicitly configured. For the admin
     * dropdown and future "add country" workflows.
     *
     * @return array<int, string>
     */
    public static function configuredCountries(): array
    {
        return array_keys(self::MAP);
    }
}
