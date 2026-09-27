<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\SiteType;

use CorporateAid\Platform\SiteType\SiteType;

/**
 * E-commerce site type.
 *
 * A store that sells physical or digital products. Products,
 * orders, customers, cart, checkout, payments, shipping.
 *
 * This is the reference implementation. Every other site type
 * (property, restaurant, salon, website) follows this shape.
 *
 * Registered by CommerceServiceProvider during app boot.
 */
final class EcommerceSiteType implements SiteType
{
    public function key(): string
    {
        return 'ecommerce';
    }

    public function label(): string
    {
        return 'E-commerce Store';
    }

    public function description(): string
    {
        return 'Sell products online with cart, checkout, and order management';
    }

    public function icon(): string
    {
        return 'bi-shop';
    }

    public function defaultThemeSlug(): string
    {
        // Matches the DEFAULT value on commerce_stores.theme_slug.
        // The theme engine (Stage 012) will use this when a store
        // has no explicit theme selected.
        return 'modern';
    }

    /**
     * Which sidebar navigation groups are visible.
     *
     * These IDs match the group IDs declared in the sidebar
     * partial (partials/sidebar.php). Any product-specific
     * groups beyond this list would also be declared here.
     */
    public function adminNavigationGroups(): array
    {
        return [
            'main',
            'business',
            'catalog',
            'sales',
            'settings',
        ];
    }

    /**
     * Default store settings applied when a store selects this
     * site type during onboarding.
     *
     * Keys are dotted setting paths. Values are initial values.
     * Existing store settings are not overwritten unless the
     * admin explicitly resets the store to this site type.
     */
    public function defaultSettings(): array
    {
        return [
            // Checkout defaults
            'commerce.checkout.allow_guest'  => true,
            'commerce.checkout.require_phone' => false,
            'commerce.checkout.require_company' => false,

            // Catalog defaults
            'commerce.catalog.products_per_page' => 24,
            'commerce.catalog.low_stock_threshold' => 5,
            'commerce.catalog.enable_reviews' => false,

            // Order defaults
            'commerce.orders.prefix' => 'ORD-',
            'commerce.orders.invoice_prefix' => 'INV-',

            // Storefront defaults
            'commerce.storefront.show_categories_on_home' => true,
            'commerce.storefront.show_new_arrivals' => true,
            'commerce.storefront.show_best_sellers' => true,
        ];
    }
}
