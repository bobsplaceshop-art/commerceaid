<?php
declare(strict_types=1);

/**
 * Deployment branding - Layer 2 defaults.
 *
 * These values apply to this install's brand (Awalo Global).
 * Individual tenants (stores) may override any of these from the
 * admin UI - those overrides are stored in `commerce_stores`.
 *
 * Vendor name (Corporate Aid Solutions) never appears here.
 */

return [
    // ----- Identity -----
    'product_name'      => 'Awalo Global',
    'product_short'     => 'Awalo',
    'tagline'           => 'Global Commerce Platform',
    'company_name'      => 'Awalo Global',
    'company_legal'     => 'Awalo Global Ltd',

    // ----- Contact -----
    'support_email'     => 'support@anayaglobal.test',
    'support_phone'     => null,
    'support_url'       => null,

    // ----- Email defaults -----
    'email_from_name'   => 'Awalo Global',
    'email_from_email'  => 'no-reply@anayaglobal.test',

    // ----- Visual identity -----
    // Placeholders until Awalo provides real assets.
    'logo_path'         => null,   // e.g. '/uploads/branding/logo.svg'
    'favicon_path'      => null,   // e.g. '/uploads/branding/favicon.ico'
    'primary_color'     => '#0f172a',
    'secondary_color'   => '#475569',
    'accent_color'      => '#3b82f6',

    // ----- PWA (deployment default, tenants can override) -----
    'pwa_name'          => 'Awalo Global',
    'pwa_short_name'    => 'Awalo',
    'pwa_theme_color'   => '#0f172a',
    'pwa_bg_color'      => '#ffffff',

    // ----- Footer -----
    'footer_text'       => '© ' . date('Y') . ' Awalo Global. All rights reserved.',
    'show_powered_by'   => false, // true would reveal "Powered by <vendor>"
];
