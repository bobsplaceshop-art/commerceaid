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
    public const GROUP_NEWSLETTER = 'newsletter';
    public const GROUP_PAYMENTS   = 'payments';

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

        'general.orders.abandoned_after_minutes' => [
            'label'   => 'Cancel unpaid orders after',
            'type'    => 'int',
            'default' => 60,
            'group'   => self::GROUP_GENERAL,
            'hint'    => 'Minutes. An order that is not paid within this window is cancelled and its reserved stock released. The bin/cancel-abandoned-orders.php cron does the work; it must be scheduled on the host.',
        ],

        'general.store_notice' => [
            'label'   => 'Store notice',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_GENERAL,
            'hint'    => 'Optional. Displayed at the top of every storefront page as a banner.',
        ],

        'system.update.scheduled_at' => [
            'label'   => 'Scheduled update time',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_GENERAL,
            'hint'    => 'YYYY-MM-DD HH:MM:SS. A banner appears on the storefront 24 hours before this time.',
        ],

        'system.store_status' => [
            'label'   => 'Storefront Status',
            'type'    => 'select',
            'options' => [
                'live' => 'Live (Accepting Orders)',
                'coming_soon' => 'Coming Soon (Pre-launch holding page)',
                'maintenance' => 'Maintenance (503 Service Unavailable)',
            ],
            'default' => 'live',
            'group'   => self::GROUP_GENERAL,
            'hint'    => 'Use "Coming Soon" for pre-launch. Admins can always bypass this and view the live site.',
        ],

        'system.store_status_message' => [
            'label'   => 'Status Message',
            'type'    => 'string',
            'default' => 'We are working hard to launch our new store. Please check back soon!',
            'group'   => self::GROUP_GENERAL,
            'hint'    => 'Shown on the Coming Soon and Maintenance pages.',
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

        // -------------------------------------------------------------
        // Newsletter (Stage 008I)
        // -------------------------------------------------------------
        'newsletter.double_opt_in' => [
            'label'   => 'Require double opt-in',
            'type'    => 'boolean',
            'default' => true,
            'group'   => self::GROUP_NEWSLETTER,
            'hint'    => 'Strongly recommended for German stores. '
                       . 'The subscriber must click a confirmation link '
                       . 'in an email before receiving campaigns.',
        ],
        'newsletter.tracking_enabled' => [
            'label'   => 'Track opens and clicks',
            'type'    => 'boolean',
            'default' => false,
            'group'   => self::GROUP_NEWSLETTER,
            'hint'    => 'When on, each campaign email embeds a tracking '
                       . 'pixel and rewrites links. Under TTDSG § 25 this '
                       . 'requires the subscriber\'s consent. Leave off '
                       . 'for German deployments unless you have a legal '
                       . 'basis.',
        ],
        'newsletter.rate_limit_per_minute' => [
            'label'   => 'Send rate per minute',
            'type'    => 'number',
            'default' => 60,
            'group'   => self::GROUP_NEWSLETTER,
            'hint'    => 'The worker sends at most this many emails per '
                       . 'minute. Lower for shared SMTP, higher for a '
                       . 'dedicated relay.',
        ],
        'newsletter.unsubscribe_token_days' => [
            'label'   => 'Unsubscribe link validity (days)',
            'type'    => 'number',
            'default' => 90,
            'group'   => self::GROUP_NEWSLETTER,
            'hint'    => 'How long an unsubscribe link in a sent campaign '
                       . 'stays valid. Longer is friendlier. 90 days is '
                       . 'standard.',
        ],
        'newsletter.footer_address' => [
            'label'   => 'Physical address in campaign footer',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_NEWSLETTER,
            'hint'    => 'Required by German UWG § 7 in every marketing '
                       . 'email. The operator\'s postal address. Falls '
                       . 'back to the store legal name if empty, but a '
                       . 'proper address is recommended.',
        ],

        // -------------------------------------------------------------
        // Payments (Stage 010)
        // -------------------------------------------------------------
        // The credentials for each payment provider. Enable/
        // disable state lives in commerce_store_payment_methods;
        // these are the API keys and mode toggles the providers
        // read at charge/refund time.

        'payments.stripe.mode' => [
            'label'   => 'Stripe mode',
            'type'    => 'select',
            'default' => 'sandbox',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Sandbox uses Stripe test keys. Live charges real cards.',
            'options' => ['sandbox' => 'Sandbox (test)', 'live' => 'Live (production)'],
        ],
        'payments.stripe.publishable_key' => [
            'label'   => 'Stripe publishable key',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Begins with pk_test_ (sandbox) or pk_live_ (live).',
        ],
        'payments.stripe.secret_key' => [
            'label'   => 'Stripe secret key',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Begins with sk_test_ (sandbox) or sk_live_ (live). Stored in commerce_store_settings.',
        ],
        'payments.stripe.webhook_secret' => [
            'label'   => 'Stripe webhook signing secret',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Begins with whsec_. Set in the Stripe dashboard when you add the webhook endpoint.',
        ],
        'payments.paypal.mode' => [
            'label'   => 'PayPal mode',
            'type'    => 'select',
            'default' => 'sandbox',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Sandbox uses PayPal sandbox credentials. Live accepts real payments.',
            'options' => ['sandbox' => 'Sandbox (test)', 'live' => 'Live (production)'],
        ],
        'payments.paypal.client_id' => [
            'label'   => 'PayPal client ID',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'From the PayPal developer dashboard, Apps & Credentials.',
        ],
        'payments.paypal.client_secret' => [
            'label'   => 'PayPal client secret',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Paired with the client ID. Stored in commerce_store_settings.',
        ],
        'payments.paypal.webhook_id' => [
            'label'   => 'PayPal webhook ID',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Returned by PayPal when you register the webhook endpoint.',
        ],

        'payments.mpesa.mode' => [
            'label'   => 'M-Pesa mode',
            'type'    => 'select',
            'default' => 'sandbox',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Sandbox uses the Daraja test environment. Live charges real phones.',
            'options' => ['sandbox' => 'Sandbox (test)', 'live' => 'Live (production)'],
        ],
        'payments.mpesa.consumer_key' => [
            'label'   => 'M-Pesa consumer key',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'From the Daraja developer portal, My Apps.',
        ],
        'payments.mpesa.consumer_secret' => [
            'label'   => 'M-Pesa consumer secret',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Paired with the consumer key. Stored in commerce_store_settings.',
        ],
        'payments.mpesa.shortcode' => [
            'label'   => 'M-Pesa shortcode',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Paybill or Till number. Six digits typically.',
        ],
        'payments.mpesa.passkey' => [
            'label'   => 'M-Pesa passkey',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Lipa na M-Pesa online passkey from the Daraja portal.',
        ],
        'payments.mpesa.callback_url' => [
            'label'   => 'M-Pesa callback URL',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Copy this URL into the Daraja dashboard. It must be https and publicly reachable.',
        ],
        'payments.mpesa.transaction_type' => [
            'label'   => 'M-Pesa transaction type',
            'type'    => 'select',
            'default' => 'CustomerPayBillOnline',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Paybill for a paybill shortcode. Buy Goods for a Till number.',
            'options' => [
                'CustomerPayBillOnline' => 'Paybill',
                'CustomerBuyGoodsOnline' => 'Buy Goods (Till)',
            ],
        ],
        'payments.mpesa.refund_enabled' => [
            'label'   => 'M-Pesa refunds enabled',
            'type'    => 'bool',
            'default' => false,
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Turn on only if your Daraja plan exposes the refund endpoint. Otherwise refunds are recorded offline.',
        ],

        'payments.pesapal.mode' => [
            'label'   => 'Pesapal mode',
            'type'    => 'select',
            'default' => 'sandbox',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Sandbox uses the Pesapal test environment. Live charges real cards and mobile money.',
            'options' => ['sandbox' => 'Sandbox (test)', 'live' => 'Live (production)'],
        ],
        'payments.pesapal.consumer_key' => [
            'label'   => 'Pesapal consumer key',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'From the Pesapal merchant dashboard, API credentials.',
        ],
        'payments.pesapal.consumer_secret' => [
            'label'   => 'Pesapal consumer secret',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Paired with the consumer key. Stored in commerce_store_settings.',
        ],
        'payments.pesapal.ipn_id' => [
            'label'   => 'Pesapal IPN ID',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Returned by Pesapal when you register the IPN URL. Copy it here.',
        ],
        'payments.pesapal.callback_url' => [
            'label'   => 'Pesapal callback URL',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'The URL the customer returns to after paying. It must be https and publicly reachable.',
        ],
        'payments.pesapal.refund_enabled' => [
            'label'   => 'Pesapal refunds enabled',
            'type'    => 'bool',
            'default' => false,
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Turn on only if your Pesapal plan exposes the refund endpoint. Otherwise refunds are recorded offline.',
        ],

        'payments.flutterwave.mode' => [
            'label'   => 'Flutterwave mode',
            'type'    => 'select',
            'default' => 'sandbox',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Sandbox uses the Flutterwave test environment. Live charges real cards and mobile money.',
            'options' => ['sandbox' => 'Sandbox (test)', 'live' => 'Live (production)'],
        ],
        'payments.flutterwave.public_key' => [
            'label'   => 'Flutterwave public key',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Begins with FLWPUBK_TEST- (sandbox) or FLWPUBK- (live).',
        ],
        'payments.flutterwave.secret_key' => [
            'label'   => 'Flutterwave secret key',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Begins with FLWSECK_TEST- (sandbox) or FLWSECK- (live). Stored in commerce_store_settings.',
        ],
        'payments.flutterwave.secret_hash' => [
            'label'   => 'Flutterwave secret hash',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'The value you set in the Flutterwave dashboard under Webhook Settings. Flutterwave sends it in the verif-hash header on every webhook. It is a plain shared secret, not an HMAC.',
        ],
        'payments.flutterwave.redirect_url' => [
            'label'   => 'Flutterwave redirect URL',
            'type'    => 'string',
            'default' => '',
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'The URL the customer returns to after paying. It must be https and publicly reachable.',
        ],
        'payments.flutterwave.refund_enabled' => [
            'label'   => 'Flutterwave refunds enabled',
            'type'    => 'bool',
            'default' => false,
            'group'   => self::GROUP_PAYMENTS,
            'hint'    => 'Turn on only if your Flutterwave plan exposes the refund endpoint. Otherwise refunds are recorded offline.',
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
            self::GROUP_NEWSLETTER => 'Newsletter',
            self::GROUP_PAYMENTS   => 'Payments',
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
