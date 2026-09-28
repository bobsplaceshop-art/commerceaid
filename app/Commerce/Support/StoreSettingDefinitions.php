<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support;

/**
 * The single source of truth for every known store setting.
 *
 * Adding a new toggle is one line in DEFINITIONS plus the code
 * that reads it. No migration. The admin settings page reads
 * this array and renders the form dynamically.
 *
 * Each definition declares:
 *   label    - human-readable name for the admin form
 *   type     - boolean | string | text | number | select
 *   default  - fallback when the row is missing
 *   group    - which settings tab it renders on
 *   hint     - optional help text under the field
 *   options  - for type = select, [value => label]
 *
 * The 'type' drives the input renderer in
 * resources/views/admin/settings/_field.php.
 *
 * See docs/stages/008e.1_german-compliance-plan.md.
 */
final class StoreSettingDefinitions
{
    public const GROUP_GENERAL    = 'general';
    public const GROUP_COMPLIANCE = 'compliance';

    /**
     * Every known setting, keyed by dotted key name.
     *
     * @var array<string, array{
     *     label: string,
     *     type: string,
     *     default: mixed,
     *     group: string,
     *     hint?: string,
     *     options?: array<string, string>
     * }>
     */
    public const DEFINITIONS = [
        // -------------------------------------------------------------
        // General
        // -------------------------------------------------------------
        'general.contact_email' => [
            'label'   => 'Contact email',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_GENERAL,
            'hint'    => 'Displayed on the storefront contact page and in the footer.',
        ],
        'general.contact_phone' => [
            'label'   => 'Contact phone',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_GENERAL,
            'hint'    => 'Optional. Shown alongside the contact email.',
        ],
        'general.slogan' => [
            'label'   => 'Store slogan',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_GENERAL,
            'hint'    => 'Short tagline shown in the storefront footer.',
        ],

        // -------------------------------------------------------------
        // Compliance
        // -------------------------------------------------------------
        'catalog.hide_out_of_stock' => [
            'label'   => 'Hide out-of-stock products from listings',
            'type'    => 'boolean',
            'default' => false,
            'group'   => self::GROUP_GENERAL,
            'hint'    => 'When enabled, products whose every sellable '
                       . 'variant is unavailable are hidden from '
                       . 'catalog pages, category pages, home sections, '
                       . 'search results, and related products. Direct '
                       . 'product URLs still work and show a Sold out '
                       . 'state so bookmarks and search engine results '
                       . 'continue to resolve.',
        ],

        'compliance.cookie_banner.enabled' => [
            'label'   => 'Cookie consent banner',
            'type'    => 'boolean',
            'default' => true,
            'group'   => self::GROUP_COMPLIANCE,
            'hint'    => 'Show a TTDSG-compliant consent banner on the '
                       . 'storefront. Required in Germany.',
        ],
        'compliance.legal_pages.required' => [
            'label'   => 'Require legal pages before checkout',
            'type'    => 'boolean',
            'default' => true,
            'group'   => self::GROUP_COMPLIANCE,
            'hint'    => 'Block checkout until the required legal pages '
                       . 'are published. Required in Germany.',
        ],
        'compliance.verpackg.lucid' => [
            'label'   => 'LUCID registration number',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_COMPLIANCE,
            'hint'    => 'Your VerpackG registration number from the '
                       . 'Zentrale Stelle Verpackungsregister. Required '
                       . 'if you ship packaged goods to Germany.',
        ],
        'compliance.verpackg.notice_dismissed' => [
            'label'   => 'VerpackG notice dismissed',
            'type'    => 'boolean',
            'default' => false,
            'group'   => self::GROUP_COMPLIANCE,
            'hint'    => 'Set automatically when the admin acknowledges '
                       . 'the VerpackG registration notice.',
        ],

        'compliance.guest_claim.enabled' => [
            'label'   => 'Reconcile guest orders with existing accounts',
            'type'    => 'boolean',
            'default' => false,
            'group'   => self::GROUP_COMPLIANCE,
            'hint'    => 'When enabled, guest orders placed with an email '
                       . 'that matches an existing customer account will be '
                       . 'marked as claimable by that customer. The customer '
                       . 'will be offered a link to claim the order on their '
                       . 'next login. '
                       . 'Legal note (DSGVO): the claimable link asserts '
                       . 'that the entered email matched an account email, '
                       . 'not that the customer owns the order. Ownership '
                       . 'is established only when the customer explicitly '
                       . 'claims. '
                       . 'Operational note: if a guest mistypes an existing '
                       . 'customer email, the wrong customer may be '
                       . 'offered the claim. Consider whether the '
                       . 'reconciliation benefit outweighs this risk for '
                       . 'your business.',
        ],
    ];

    /**
     * All definitions for a given group.
     *
     * @return array<string, array<string,mixed>>
     */
    public static function forGroup(string $group): array
    {
        $out = [];
        foreach (self::DEFINITIONS as $key => $def) {
            if (($def['group'] ?? '') === $group) {
                $out[$key] = $def;
            }
        }
        return $out;
    }

    /**
     * All group identifiers in definition order. Used to build
     * the tab navigation.
     *
     * @return array<int, string>
     */
    public static function groups(): array
    {
        $seen = [];
        foreach (self::DEFINITIONS as $def) {
            $g = $def['group'];
            if (!in_array($g, $seen, true)) {
                $seen[] = $g;
            }
        }
        return $seen;
    }

    /**
     * Human-readable label for a group.
     */
    public static function groupLabel(string $group): string
    {
        return match ($group) {
            self::GROUP_GENERAL    => 'General',
            self::GROUP_COMPLIANCE => 'Compliance',
            default                => ucfirst($group),
        };
    }

    /**
     * Does the definition exist for this key?
     */
    public static function has(string $key): bool
    {
        return isset(self::DEFINITIONS[$key]);
    }

    /**
     * The default value for a key, or null if the key is unknown.
     */
    public static function defaultFor(string $key): mixed
    {
        return self::DEFINITIONS[$key]['default'] ?? null;
    }
}
