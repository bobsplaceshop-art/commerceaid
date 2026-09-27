<?php
declare(strict_types=1);

namespace CorporateAid\Platform\SiteType;

/**
 * A site type is a first-class concept describing what kind of
 * website a store is. Different site types declare different
 * admin surfaces, different default themes, and different
 * initial settings.
 *
 * Examples:
 *   - ecommerce    - products, orders, customers
 *   - property     - listings, inquiries, viewings
 *   - website      - pages, blog, contact forms
 *   - restaurant   - menus, tables, reservations
 *   - salon        - services, stylists, appointments
 *
 * The interface lives in Platform because the concept is shared
 * across every product. Concrete implementations live in each
 * product's namespace.
 *
 * Registration:
 *   Each site type is a class implementing this interface. The
 *   SiteTypeRegistry is populated at app boot. See
 *   docs/SITE-TYPES.md for the full model.
 *
 * Impact when a store changes type:
 *   - The store's site_type column updates
 *   - The default theme slug is applied (if not overridden)
 *   - The admin sidebar reflects the new adminNavigationGroups
 *   - Existing data is preserved (a store that started as
 *     Ecommerce and switches to a hybrid type keeps its
 *     products and adds the new modules)
 */
interface SiteType
{
    /**
     * Stable identifier written to commerce_stores.site_type.
     * Lowercase, kebab-case, no spaces. Used as the primary key
     * for registry lookup.
     */
    public function key(): string;

    /**
     * Human-readable label for the onboarding dialog and any
     * admin surface that lists available site types.
     */
    public function label(): string;

    /**
     * Short description shown under the label in the onboarding
     * dialog. One sentence, no period.
     */
    public function description(): string;

    /**
     * Bootstrap Icon class for the onboarding tile.
     */
    public function icon(): string;

    /**
     * The default theme slug applied when a store selects this
     * site type during onboarding. The admin can override it
     * later via the theme builder. If the theme is not
     * installed, the store falls back to the platform default.
     */
    public function defaultThemeSlug(): string;

    /**
     * Which admin navigation groups are visible for this site
     * type. Keys match the group IDs used in the sidebar
     * (main, business, catalog, sales, settings). Additional
     * product-specific groups can be declared as strings and
     * resolved by the product's sidebar partial.
     *
     * Returning an empty array means "show every group the
     * sidebar declares" - useful for hybrid types.
     *
     * @return string[]
     */
    public function adminNavigationGroups(): array;

    /**
     * Default settings written to the store when this type is
     * selected during onboarding. Keys are dotted setting paths
     * (matching SystemSettingService), values are initial values.
     * Existing values are not overwritten unless the admin
     * explicitly resets the store to this site type.
     *
     * @return array<string,mixed>
     */
    public function defaultSettings(): array;
}
