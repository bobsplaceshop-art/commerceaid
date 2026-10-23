<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support\Blocks;

/**
 * Config schema for every block type.
 *
 * Each block type declares the fields the merchant can
 * configure. The config form renders from this schema; the
 * storefront renderer reads the same schema for its defaults.
 *
 * Field shape (matches StoreSettingDefinitions):
 *   label    string              Display name
 *   type     string              Renderer key ('string','text','int','boolean','select','file','category_select')
 *   default  mixed               Fallback when config_json is empty
 *   hint     string|null         Optional helper text under the field
 *   options  array|null          For 'select': value => label pairs
 *   required bool                Validation gate
 */
final class BlockConfigRegistry
{
    /**
     * @return array<string, array<string,mixed>>
     */
    public static function schema(string $blockType): array
    {
        return match ($blockType) {
            'hero'          => self::hero(),
            'product-grid'  => self::productGrid(),
            'category-grid' => self::categoryGrid(),
            'banner'        => self::banner(),
            'rich-text'     => self::richText(),
            'newsletter'    => self::newsletter(),
            'promo-panel'   => self::promoPanel(),
            'spacer'        => self::spacer(),
            default         => [],
        };
    }

    /**
     * Every schema-declared field's default value, keyed by
     * field name. Used to seed config_json on block creation
     * so the renderer never has to fall back to defaults.
     *
     * @return array<string,mixed>
     */
    public static function defaults(string $blockType): array
    {
        $out = [];
        foreach (self::schema($blockType) as $field => $meta) {
            $out[$field] = $meta['default'] ?? null;
        }
        return $out;
    }

    // -----------------------------------------------------------------

    /** @return array<string, array<string,mixed>> */
    private static function hero(): array
    {
        return [
            'headline' => [
                'label'    => 'Headline',
                'type'     => 'string',
                'default'  => 'Shop the World, Love Your Style.',
                'hint'     => 'The big text at the top of the hero.',
                'required' => true,
            ],
            'subheadline' => [
                'label'   => 'Subheadline',
                'type'    => 'string',
                'default' => '',
                'hint'    => 'Optional line under the headline.',
            ],
            'cta_text' => [
                'label'   => 'CTA text',
                'type'    => 'string',
                'default' => 'Explore Collection',
            ],
            'cta_url' => [
                'label'   => 'CTA URL',
                'type'    => 'string',
                'default' => '/products',
                'hint'    => 'Where the call-to-action button points.',
            ],
            'image_file_id' => [
                'label'   => 'Background image',
                'type'    => 'file',
                'default' => null,
                'hint'    => 'Optional. Shown behind the headline.',
            ],
        ];
    }

    /** @return array<string, array<string,mixed>> */
    private static function productGrid(): array
    {
        return [
            'title' => [
                'label'   => 'Section title',
                'type'    => 'string',
                'default' => 'New Arrivals',
            ],
            'source' => [
                'label'   => 'Products',
                'type'    => 'select',
                'default' => 'newest',
                'options' => [
                    'newest'       => 'Newest arrivals',
                    'best_sellers' => 'Best sellers',
                    'category'     => 'Specific category',
                ],
            ],
            'category_slug' => [
                'label'   => 'Category',
                'type'    => 'category_select',
                'default' => '',
                'hint'    => 'Used when "Products" is set to "Specific category".',
            ],
            'limit' => [
                'label'   => 'Number of products',
                'type'    => 'int',
                'default' => 4,
            ],
            'columns' => [
                'label'   => 'Columns',
                'type'    => 'int',
                'default' => 4,
                'hint'    => 'How many product cards fit in one row.',
            ],
            'show_view_all' => [
                'label'   => 'Show "View all" link',
                'type'    => 'boolean',
                'default' => true,
            ],
        ];
    }

    /** @return array<string, array<string,mixed>> */
    private static function banner(): array
    {
        return [
            'headline' => [
                'label'   => 'Headline',
                'type'    => 'string',
                'default' => 'Mid-Season Sale',
            ],
            'subheadline' => [
                'label'   => 'Subheadline',
                'type'    => 'string',
                'default' => 'Up to 50% off selected styles.',
            ],
            'cta_text' => [
                'label'   => 'CTA text',
                'type'    => 'string',
                'default' => 'Shop Sale',
            ],
            'cta_url' => [
                'label'   => 'CTA URL',
                'type'    => 'string',
                'default' => '/products',
            ],
            'image_file_id' => [
                'label'   => 'Background image',
                'type'    => 'file',
                'default' => null,
            ],
        ];
    }

    /** @return array<string, array<string,mixed>> */
    private static function richText(): array
    {
        return [
            'content' => [
                'label'   => 'Content',
                'type'    => 'text',
                'default' => '<h2>About Us</h2><p>We are a fashion brand...</p>',
                'hint'    => 'Basic HTML is allowed (headings, paragraphs, links).',
            ],
        ];
    }

    /** @return array<string, array<string,mixed>> */
    private static function newsletter(): array
    {
        return [
            'headline' => [
                'label'   => 'Headline',
                'type'    => 'string',
                'default' => 'Stay in Style',
            ],
            'subheadline' => [
                'label'   => 'Subheadline',
                'type'    => 'string',
                'default' => 'Join our newsletter for 10% off your first order.',
            ],
            'button_text' => [
                'label'   => 'Button text',
                'type'    => 'string',
                'default' => 'Subscribe',
            ],
        ];
    }

    /** @return array<string, array<string,mixed>> */
    private static function spacer(): array
    {
        return [
            'height' => [
                'label'   => 'Height (px)',
                'type'    => 'int',
                'default' => 48,
                'hint'    => 'Vertical space in pixels. Try 24 for tight, 48 for standard, 96 for a big break.',
            ],
        ];
    }

    /** @return array<string, array<string,mixed>> */
    private static function promoPanel(): array
    {
        return [
            'title' => [
                'label'   => 'Title',
                'type'    => 'string',
                'default' => 'New Collection',
            ],
            'cta_text' => [
                'label'   => 'CTA text',
                'type'    => 'string',
                'default' => 'Explore',
            ],
            'cta_url' => [
                'label'   => 'CTA URL',
                'type'    => 'string',
                'default' => '/categories/new',
            ],
            'image_file_id' => [
                'label'   => 'Image',
                'type'    => 'file',
                'default' => null,
            ],
        ];
    }

    /** @return array<string, array<string,mixed>> */
    private static function categoryGrid(): array
    {
        return [
            'title' => [
                'label'   => 'Section title',
                'type'    => 'string',
                'default' => 'Shop by Category',
            ],
        ];
    }
}
