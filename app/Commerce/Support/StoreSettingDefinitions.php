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
 *   type     - boolean | string | text | number | select | file
 *   default  - fallback when the row is missing
 *   group    - which settings tab it renders on
 *   hint     - optional help text under the field
 *   options  - for type = select, [value => label]
 *
 * File fields (type = file) and any field that writes to a
 * column on commerce_stores instead of the key-value store
 * declare:
 *   target   - 'key_value' (default) | 'column'
 *   column   - the column name on commerce_stores, required
 *              when target = 'column'
 *   accept   - file input accept attribute, type = file only
 *   max_mb   - file size cap, type = file only
 *   media_context - MediaVariantSet context, type = file only
 *
 * The 'type' drives the input renderer in
 * resources/views/admin/settings/_field.php.
 *
 * See docs/stages/008e.1_german-compliance-plan.md.
 */
final class StoreSettingDefinitions
{
    public const GROUP_GENERAL    = 'general';
    public const GROUP_BRANDING   = 'branding';
    public const GROUP_FONTS      = 'fonts';
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
        // Branding (Stage 008G.4a)
        // -------------------------------------------------------------
        //
        // These fields write to columns on commerce_stores, not
        // to the key-value store. A logo is a relationship to a
        // file, not a string. The target/column pair tells the
        // SettingsController to route the save accordingly.
        'store.logo_file_id' => [
            'label'         => 'Store logo',
            'type'          => 'file',
            'default'       => null,
            'group'         => self::GROUP_BRANDING,
            'target'        => 'column',
            'column'        => 'logo_file_id',
            'accept'        => 'image/png,image/jpeg,image/webp,image/gif',
            'max_mb'        => 1,
            'media_context' => MediaVariantSet::CONTEXT_STORE_LOGO,
            'hint'          => 'Shown in the storefront header. ' .
                               'PNG or WebP recommended. Max 1 MB.',
        ],
        'store.favicon_file_id' => [
            'label'         => 'Favicon',
            'type'          => 'file',
            'default'       => null,
            'group'         => self::GROUP_BRANDING,
            'target'        => 'column',
            'column'        => 'favicon_file_id',
            'accept'        => 'image/png',
            'max_mb'        => 1,
            'media_context' => MediaVariantSet::CONTEXT_STORE_FAVICON,
            'hint'          => 'Browser tab icon. PNG only. ' .
                               '32x32 or 64x64 recommended.',
        ],
        'store.pwa_name' => [
            'label'   => 'PWA app name',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_BRANDING,
            'target'  => 'column',
            'column'  => 'pwa_name',
            'hint'    => 'Full name shown when the app is installed. ' .
                         'Falls back to the store name.',
        ],
        'store.pwa_short_name' => [
            'label'   => 'PWA short name',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_BRANDING,
            'target'  => 'column',
            'column'  => 'pwa_short_name',
            'hint'    => 'Home screen label. 12 characters max recommended.',
        ],
        'store.pwa_theme_color' => [
            'label'   => 'PWA theme color',
            'type'    => 'string',
            'default' => '#1a1a1a',
            'group'   => self::GROUP_BRANDING,
            'target'  => 'column',
            'column'  => 'pwa_theme_color',
            'hint'    => 'Hex color (#RRGGBB). Used for the browser ' .
                         'chrome and PWA title bar.',
        ],
        'store.pwa_background_color' => [
            'label'   => 'PWA background color',
            'type'    => 'string',
            'default' => '#ffffff',
            'group'   => self::GROUP_BRANDING,
            'target'  => 'column',
            'column'  => 'pwa_background_color',
            'hint'    => 'Hex color (#RRGGBB). Shown briefly while ' .
                         'the app is loading.',
        ],

        // -------------------------------------------------------------
        // Fonts
        // -------------------------------------------------------------
        'store.font_body_slug' => [
            'label'   => 'Body font',
            'type'    => 'select',
            'default' => '',
            'group'   => self::GROUP_FONTS,
            'hint'    => 'Used for body text across the storefront. Select a font to download it. Leave empty for the platform default.',
            'options' => [],
            'target'  => 'column',
            'column'  => 'font_body_slug',
        ],
        'store.font_display_slug' => [
            'label'   => 'Display font',
            'type'    => 'select',
            'default' => '',
            'group'   => self::GROUP_FONTS,
            'hint'    => 'Used for headings and titles. Serif fonts work well for a premium feel; bold sans-serifs for a modern one.',
            'options' => [],
            'target'  => 'column',
            'column'  => 'font_display_slug',
        ],
        'store.font_script_slug' => [
            'label'   => 'Script font',
            'type'    => 'select',
            'default' => '',
            'group'   => self::GROUP_FONTS,
            'hint'    => 'Used for decorative accents. Applied sparingly - for example on the storefront headline.',
            'options' => [],
            'target'  => 'column',
            'column'  => 'font_script_slug',
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
        // Returns & refunds (Stage 008H)
        'compliance.returns.window_days' => [
            'label'   => 'Return window (days)',
            'type'    => 'number',
            'default' => 14,
            'group'   => 'compliance',
            'hint'    => 'Number of days from delivery during which a customer '
                       . 'can return an item. German stores must offer at least '
                       . '14 days. Set higher to give a longer policy.',
        ],
        'compliance.returns.allow_before_delivery' => [
            'label'   => 'Allow return requests before delivery',
            'type'    => 'boolean',
            'default' => true,
            'group'   => 'compliance',
            'hint'    => 'When on, customers can start a return request any time '
                       . 'after ordering. The return window still opens on delivery.',
        ],
        'compliance.returns.auto_approve' => [
            'label'   => 'Auto-approve return requests',
            'type'    => 'boolean',
            'default' => false,
            'group'   => 'compliance',
            'hint'    => 'When on, return requests are approved as soon as the '
                       . 'customer submits them. When off, every request enters '
                       . 'the admin approval queue.',
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

        // The Fonts group has three selects whose option
        // lists come from FontCatalog at runtime. Constant
        // arrays cannot call functions, so populate them
        // here.
        if ($group === self::GROUP_FONTS) {
            $out = self::populateFontOptions($out);
        }

        return $out;
    }

    /**
     * Attach option lists to the three font select
     * definitions. Options come from
     * FontCatalog::forSlot(): 'body', 'display', 'script'.
     *
     * Each list is prepended with an empty option so the
     * merchant can clear the selection and fall back to
     * the platform default stack.
     *
     * @param array<string,array<string,mixed>> $defs
     * @return array<string,array<string,mixed>>
     */
    private static function populateFontOptions(array $defs): array
    {
        $slotMap = [
            'store.font_body_slug'    => 'body',
            'store.font_display_slug' => 'display',
            'store.font_script_slug'  => 'script',
        ];

        foreach ($slotMap as $key => $slot) {
            if (!isset($defs[$key])) {
                continue;
            }

            $options = ['' => '- Use platform default -'];
            foreach (\CorporateAid\Commerce\Support\Fonts\FontCatalog::forSlot($slot) as $font) {
                $options[$font['slug']] = $font['family'];
            }

            $defs[$key]['options'] = $options;
        }

        return $defs;
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
            self::GROUP_BRANDING   => 'Branding',
            self::GROUP_FONTS      => 'Fonts',
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
