<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support;

/**
 * Variant set definitions per media context.
 *
 * Each context (store-logo, product-image, hero-image, ...)
 * declares its own size variants. The generator reads the
 * MediaContextSpec returned by for(), produces the declared
 * derivatives, and does not need to know what a "store logo"
 * is - it just produces the declared sizes.
 *
 * Returns typed DTOs (MediaContextSpec, MediaVariantSpec),
 * not nested arrays. The DTOs give PHPStan real types
 * without a docblock shape that has to be maintained in
 * every factory method.
 *
 * Adding a new context is one branch in for(). Adding a new
 * variant to an existing context is one MediaVariantSpec row.
 *
 * See docs/stages/008g.4a_upload-core-plan.md, "Image Variant
 * Pipeline" section.
 */
final class MediaVariantSet
{
    // Contexts
    public const CONTEXT_STORE_LOGO     = 'store-logo';
    public const CONTEXT_STORE_FAVICON  = 'store-favicon';
    public const CONTEXT_PRODUCT_IMAGE  = 'product-image';
    public const CONTEXT_CATEGORY_IMAGE = 'category-image';
    public const CONTEXT_BRAND_LOGO     = 'brand-logo';
    public const CONTEXT_BANNER_IMAGE   = 'banner-image';
    public const CONTEXT_HERO_IMAGE     = 'hero-image';
    public const CONTEXT_GENERAL        = 'general';

    /**
     * Fit modes.
     *
     *   INSIDE - scale to fit within the bounding box,
     *            preserving aspect ratio. No crop. Padding
     *            with transparent or background color if the
     *            source aspect differs from the target.
     *
     *   COVER  - scale to fill the bounding box, preserving
     *            aspect ratio, cropping the overflow. Used
     *            for banners that must fill a fixed shape.
     */
    public const FIT_INSIDE = 'inside';
    public const FIT_COVER  = 'cover';

    /**
     * The declaration for a context. Unknown contexts fall
     * back to a generic single-variant set.
     */
    public static function for(string $context): MediaContextSpec
    {
        return match ($context) {
            self::CONTEXT_STORE_LOGO     => self::storeLogo(),
            self::CONTEXT_STORE_FAVICON  => self::storeFavicon(),
            self::CONTEXT_PRODUCT_IMAGE  => self::productImage(),
            self::CONTEXT_CATEGORY_IMAGE => self::categoryImage(),
            self::CONTEXT_BRAND_LOGO     => self::brandLogo(),
            self::CONTEXT_BANNER_IMAGE   => self::bannerImage(),
            self::CONTEXT_HERO_IMAGE     => self::heroImage(),
            self::CONTEXT_GENERAL        => self::general(),
            default                      => self::generic(),
        };
    }

    /**
     * All known contexts. Used by callers that need to
     * enumerate (media page filters, admin dropdowns).
     *
     * @return array<int, string>
     */
    public static function contexts(): array
    {
        return [
            self::CONTEXT_STORE_LOGO,
            self::CONTEXT_STORE_FAVICON,
            self::CONTEXT_PRODUCT_IMAGE,
            self::CONTEXT_CATEGORY_IMAGE,
            self::CONTEXT_BRAND_LOGO,
            self::CONTEXT_BANNER_IMAGE,
            self::CONTEXT_HERO_IMAGE,
            self::CONTEXT_GENERAL,
        ];
    }

    public static function isKnownContext(string $context): bool
    {
        return in_array($context, self::contexts(), true);
    }

    // =================================================================
    // Per-context declarations
    // =================================================================

    private static function storeLogo(): MediaContextSpec
    {
        return new MediaContextSpec(
            sourceMaxMb: 1,
            sharpen:     false,
            variants: [
                new MediaVariantSpec('lg', 512, 512, ['png', 'webp'], self::FIT_INSIDE),
                new MediaVariantSpec('md', 192, 192, ['png', 'webp'], self::FIT_INSIDE),
            ],
        );
    }

    private static function storeFavicon(): MediaContextSpec
    {
        return new MediaContextSpec(
            sourceMaxMb: 1,
            sharpen:     false,
            variants: [
                // PWA manifest icons. 512 and 192 are the two
                // sizes the W3C web app manifest requires for
                // installability. Both PNG, no WebP - the
                // manifest spec requires one of PNG, SVG,
                // JPEG, or WebP but PNG is the universal
                // default that every browser accepts.
                new MediaVariantSpec('lg',     512, 512, ['png'], self::FIT_INSIDE),
                new MediaVariantSpec('md',     192, 192, ['png'], self::FIT_INSIDE),
                // Classic favicon sizes for the browser tab.
                new MediaVariantSpec('sm',      64,  64, ['png'], self::FIT_INSIDE),
                new MediaVariantSpec('thumb',   32,  32, ['png'], self::FIT_INSIDE),
            ],
        );
    }

    private static function productImage(): MediaContextSpec
    {
        return new MediaContextSpec(
            sourceMaxMb: 5,
            sharpen:     true,
            variants: [
                new MediaVariantSpec('lg',   1200, 1500, ['webp', 'jpeg'], self::FIT_INSIDE),
                new MediaVariantSpec('md',    800, 1000, ['webp', 'jpeg'], self::FIT_INSIDE),
                new MediaVariantSpec('card',  400,  500, ['webp', 'jpeg'], self::FIT_INSIDE),
            ],
        );
    }

    private static function categoryImage(): MediaContextSpec
    {
        return new MediaContextSpec(
            sourceMaxMb: 5,
            sharpen:     true,
            variants: [
                new MediaVariantSpec('lg',    1600, 1600, ['webp', 'jpeg'], self::FIT_INSIDE),
                new MediaVariantSpec('md',     800,  800, ['webp', 'jpeg'], self::FIT_INSIDE),
                new MediaVariantSpec('thumb',  400,  400, ['webp', 'jpeg'], self::FIT_INSIDE),
            ],
        );
    }

    private static function brandLogo(): MediaContextSpec
    {
        return new MediaContextSpec(
            sourceMaxMb: 1,
            sharpen:     false,
            variants: [
                new MediaVariantSpec('lg', 512, 512, ['png', 'webp'], self::FIT_INSIDE),
                new MediaVariantSpec('md', 256, 256, ['png', 'webp'], self::FIT_INSIDE),
                new MediaVariantSpec('sm', 128, 128, ['png', 'webp'], self::FIT_INSIDE),
            ],
        );
    }

    private static function bannerImage(): MediaContextSpec
    {
        return new MediaContextSpec(
            sourceMaxMb: 5,
            sharpen:     true,
            variants: [
                new MediaVariantSpec('lg', 1920,  960, ['webp', 'jpeg'], self::FIT_COVER),
                new MediaVariantSpec('md', 1600,  800, ['webp', 'jpeg'], self::FIT_COVER),
                new MediaVariantSpec('sm',  800,  400, ['webp', 'jpeg'], self::FIT_COVER),
            ],
        );
    }

    private static function heroImage(): MediaContextSpec
    {
        return new MediaContextSpec(
            sourceMaxMb: 5,
            sharpen:     true,
            variants: [
                new MediaVariantSpec('lg', 1920, 960, ['webp', 'jpeg'], self::FIT_COVER),
                new MediaVariantSpec('md', 1200, 600, ['webp', 'jpeg'], self::FIT_COVER),
            ],
        );
    }

    /**
     * The general-purpose media context.
     *
     * This is the context any feature that does not have a
     * specialized need (popup image, settings image, page
     * block image, etc.) should use. It produces the same
     * full size list as productImage(), so any caller that
     * requests lg / md / card gets a real file.
     *
     * Before this existed, general fell through to generic(),
     * which only produced a single 'default' variant. That
     * broke every feature that expected the standard sizes.
     */
    private static function general(): MediaContextSpec
    {
        return new MediaContextSpec(
            sourceMaxMb: 5,
            sharpen:     true,
            variants: [
                new MediaVariantSpec('lg',   1200, 1500, ['webp', 'jpeg'], self::FIT_INSIDE),
                new MediaVariantSpec('md',    800, 1000, ['webp', 'jpeg'], self::FIT_INSIDE),
                new MediaVariantSpec('card',  400,  500, ['webp', 'jpeg'], self::FIT_INSIDE),
                new MediaVariantSpec('thumb', 200,  250, ['webp', 'jpeg'], self::FIT_INSIDE),
            ],
        );
    }

    /**
     * Fallback for unknown contexts. Produces one general-
     * purpose variant so a caller never fails on an
     * unexpected context string.
     */
    private static function generic(): MediaContextSpec
    {
        return new MediaContextSpec(
            sourceMaxMb: 5,
            sharpen:     true,
            variants: [
                new MediaVariantSpec('default', 1200, 1200, ['webp', 'jpeg'], self::FIT_INSIDE),
            ],
        );
    }
}
