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
            'section'       => self::section(),
            'container'     => self::container(),
            default         => [],
        };
    }

    /**
     * Section has no per-instance config yet. It is a
     * structural wrapper; its only state is parent/child
     * relationships, which live in the row, not the config.
     * Config fields (padding, background, container width)
     * ship in a later sub-stage once the layout engine
     * needs them.
     *
     * @return array<string, array<string,mixed>>
     */
    /**
     * Section: a full-width structural block. Config covers
     * vertical rhythm (padding top/bottom), color (background
     * for the full-bleed band), and content width (constrains
     * the inner container).
     *
     * @return array<string, array<string,mixed>>
     */
    private static function section(): array
    {
        return [
            'padding_top' => [
                'label'   => 'Padding top',
                'type'    => 'select',
                'default' => 'md',
                'options' => [
                    'none' => 'None',
                    'sm'   => 'Small',
                    'md'   => 'Medium',
                    'lg'   => 'Large',
                    'xl'   => 'Extra large',
                ],
            ],
            'padding_bottom' => [
                'label'   => 'Padding bottom',
                'type'    => 'select',
                'default' => 'md',
                'options' => [
                    'none' => 'None',
                    'sm'   => 'Small',
                    'md'   => 'Medium',
                    'lg'   => 'Large',
                    'xl'   => 'Extra large',
                ],
            ],
            'background_color' => [
                'label'   => 'Background color',
                'type'    => 'color',
                'default' => null,
                'hint'    => 'Leave empty for the storefront default surface color.',
            ],
            'max_width' => [
                'label'   => 'Content width',
                'type'    => 'select',
                'default' => 'wide',
                'options' => [
                    'full'   => 'Full width',
                    'wide'   => 'Wide (1200px)',
                    'narrow' => 'Narrow (800px)',
                ],
            ],
        ];
    }

    /**
     * Container: a nested wrapper inside a Section. Config
     * covers horizontal padding (inner breathing room), content
     * width, and gap between children.
     *
     * @return array<string, array<string,mixed>>
     */
    private static function container(): array
    {
        return [
            'padding_x' => [
                'label'   => 'Horizontal padding',
                'type'    => 'select',
                'default' => 'md',
                'options' => [
                    'none' => 'None',
                    'sm'   => 'Small',
                    'md'   => 'Medium',
                    'lg'   => 'Large',
                ],
            ],
            'max_width' => [
                'label'   => 'Content width',
                'type'    => 'select',
                'default' => 'wide',
                'options' => [
                    'full'   => 'Full width',
                    'wide'   => 'Wide (1200px)',
                    'narrow' => 'Narrow (800px)',
                ],
            ],
            'gap' => [
                'label'   => 'Gap between children',
                'type'    => 'select',
                'default' => 'md',
                'options' => [
                    'none' => 'None',
                    'sm'   => 'Small',
                    'md'   => 'Medium',
                    'lg'   => 'Large',
                ],
            ],
        ];
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
            'text_font' => [
                'label'   => 'Font',
                'type'    => 'select',
                'default' => 'inherit',
                'options' => [
                    'inherit' => 'Inherit (store default)',
                    'display' => 'Display (headings)',
                    'body'    => 'Body (text)',
                    'script'  => 'Script (accent)',
                ],
            ],
            'text_size' => [
                'label'   => 'Font size',
                'type'    => 'select',
                'default' => 'inherit',
                'options' => [
                    'inherit' => 'Inherit (default)',
                    'sm'      => 'Small',
                    'md'      => 'Medium',
                    'lg'      => 'Large',
                    'xl'      => 'Extra large',
                    '2xl'     => 'Huge',
                ],
            ],
            'text_weight' => [
                'label'   => 'Font weight',
                'type'    => 'select',
                'default' => 'inherit',
                'options' => [
                    'inherit' => 'Inherit',
                    'normal'  => 'Normal',
                    'medium'  => 'Medium',
                    'bold'    => 'Bold',
                ],
            ],
            'text_alignment' => [
                'label'   => 'Text alignment',
                'type'    => 'select',
                'default' => 'inherit',
                'options' => [
                    'inherit' => 'Inherit',
                    'left'    => 'Left',
                    'center'  => 'Center',
                    'right'   => 'Right',
                ],
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
            'columns_desktop' => [
                'label'   => 'Desktop columns',
                'type'    => 'select',
                'default' => '4',
                'options' => [
                    '3' => '3 columns',
                    '4' => '4 columns',
                    '5' => '5 columns',
                    '6' => '6 columns',
                    '7' => '7 columns',
                    '8' => '8 columns',
                ],
            ],
            'columns_mobile' => [
                'label'   => 'Mobile columns',
                'type'    => 'select',
                'default' => '2',
                'options' => [
                    '1' => '1 column',
                    '2' => '2 columns',
                ],
            ],
            'grid_alignment' => [
                'label'   => 'Grid item alignment',
                'type'    => 'select',
                'default' => 'justify',
                'options' => [
                    'left'    => 'Left',
                    'center'  => 'Center',
                    'right'   => 'Right',
                    'justify' => 'Justified (spread)',
                ],
                'hint'    => 'Left / Center / Right cluster the cards when there are fewer items than columns. Justified spreads them to fill the row.',
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
            'text_font' => [
                'label'   => 'Font',
                'type'    => 'select',
                'default' => 'inherit',
                'options' => [
                    'inherit' => 'Inherit (store default)',
                    'display' => 'Display (headings)',
                    'body'    => 'Body (text)',
                    'script'  => 'Script (accent)',
                ],
            ],
            'text_size' => [
                'label'   => 'Font size',
                'type'    => 'select',
                'default' => 'inherit',
                'options' => [
                    'inherit' => 'Inherit (default)',
                    'sm'      => 'Small',
                    'md'      => 'Medium',
                    'lg'      => 'Large',
                    'xl'      => 'Extra large',
                    '2xl'     => 'Huge',
                ],
            ],
            'text_weight' => [
                'label'   => 'Font weight',
                'type'    => 'select',
                'default' => 'inherit',
                'options' => [
                    'inherit' => 'Inherit',
                    'normal'  => 'Normal',
                    'medium'  => 'Medium',
                    'bold'    => 'Bold',
                ],
            ],
            'text_alignment' => [
                'label'   => 'Text alignment',
                'type'    => 'select',
                'default' => 'inherit',
                'options' => [
                    'inherit' => 'Inherit',
                    'left'    => 'Left',
                    'center'  => 'Center',
                    'right'   => 'Right',
                ],
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
            'text_font' => [
                'label'   => 'Font',
                'type'    => 'select',
                'default' => 'inherit',
                'options' => [
                    'inherit' => 'Inherit (store default)',
                    'display' => 'Display (headings)',
                    'body'    => 'Body (text)',
                    'script'  => 'Script (accent)',
                ],
            ],
            'text_size' => [
                'label'   => 'Font size',
                'type'    => 'select',
                'default' => 'inherit',
                'options' => [
                    'inherit' => 'Inherit (default)',
                    'sm'      => 'Small',
                    'md'      => 'Medium',
                    'lg'      => 'Large',
                    'xl'      => 'Extra large',
                    '2xl'     => 'Huge',
                ],
            ],
            'text_weight' => [
                'label'   => 'Font weight',
                'type'    => 'select',
                'default' => 'inherit',
                'options' => [
                    'inherit' => 'Inherit',
                    'normal'  => 'Normal',
                    'medium'  => 'Medium',
                    'bold'    => 'Bold',
                ],
            ],
            'text_alignment' => [
                'label'   => 'Text alignment',
                'type'    => 'select',
                'default' => 'inherit',
                'options' => [
                    'inherit' => 'Inherit',
                    'left'    => 'Left',
                    'center'  => 'Center',
                    'right'   => 'Right',
                ],
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
            'text_font' => [
                'label'   => 'Font',
                'type'    => 'select',
                'default' => 'inherit',
                'options' => [
                    'inherit' => 'Inherit (store default)',
                    'display' => 'Display (headings)',
                    'body'    => 'Body (text)',
                    'script'  => 'Script (accent)',
                ],
            ],
            'text_size' => [
                'label'   => 'Font size',
                'type'    => 'select',
                'default' => 'inherit',
                'options' => [
                    'inherit' => 'Inherit (default)',
                    'sm'      => 'Small',
                    'md'      => 'Medium',
                    'lg'      => 'Large',
                    'xl'      => 'Extra large',
                    '2xl'     => 'Huge',
                ],
            ],
            'text_weight' => [
                'label'   => 'Font weight',
                'type'    => 'select',
                'default' => 'inherit',
                'options' => [
                    'inherit' => 'Inherit',
                    'normal'  => 'Normal',
                    'medium'  => 'Medium',
                    'bold'    => 'Bold',
                ],
            ],
            'text_alignment' => [
                'label'   => 'Text alignment',
                'type'    => 'select',
                'default' => 'inherit',
                'options' => [
                    'inherit' => 'Inherit',
                    'left'    => 'Left',
                    'center'  => 'Center',
                    'right'   => 'Right',
                ],
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
            'category_ids' => [
                'label'   => 'Categories',
                'type'    => 'category_multi_select',
                'default' => [],
                'hint'    => 'Leave empty to show the top categories by product count.',
            ],
            'limit' => [
                'label'   => 'Maximum categories',
                'type'    => 'int',
                'default' => 5,
                'hint'    => 'Between 1 and 12.',
            ],
            'columns_desktop' => [
                'label'   => 'Desktop columns',
                'type'    => 'select',
                'default' => '5',
                'options' => [
                    '3' => '3 columns',
                    '4' => '4 columns',
                    '5' => '5 columns',
                    '6' => '6 columns',
                    '7' => '7 columns',
                    '8' => '8 columns',
                ],
            ],
            'grid_alignment' => [
                'label'   => 'Grid item alignment',
                'type'    => 'select',
                'default' => 'justify',
                'options' => [
                    'left'    => 'Left',
                    'center'  => 'Center',
                    'right'   => 'Right',
                    'justify' => 'Justified (spread)',
                ],
                'hint'    => 'Left / Center / Right cluster the cards when there are fewer items than columns. Justified spreads them to fill the row.',
            ],
            'columns_mobile' => [
                'label'   => 'Mobile columns',
                'type'    => 'select',
                'default' => '2',
                'options' => [
                    '1' => '1 column',
                    '2' => '2 columns',
                ],
            ],
            'alignment' => [
                'label'   => 'Heading alignment',
                'type'    => 'select',
                'default' => 'center',
                'options' => [
                    'left'   => 'Left',
                    'center' => 'Center',
                    'right'  => 'Right',
                ],
            ],
            'padding_top' => [
                'label'   => 'Top padding',
                'type'    => 'select',
                'default' => 'md',
                'options' => [
                    'none' => 'None',
                    'sm'   => 'Small',
                    'md'   => 'Medium (default)',
                    'lg'   => 'Large',
                    'xl'   => 'Extra large',
                ],
            ],
            'padding_bottom' => [
                'label'   => 'Bottom padding',
                'type'    => 'select',
                'default' => 'md',
                'options' => [
                    'none' => 'None',
                    'sm'   => 'Small',
                    'md'   => 'Medium (default)',
                    'lg'   => 'Large',
                    'xl'   => 'Extra large',
                ],
            ],
            'divider' => [
                'label'   => 'Show divider',
                'type'    => 'boolean',
                'default' => false,
                'hint'    => 'A thin horizontal line above the section.',
            ],
            'spacer_above' => [
                'label'   => 'Spacer above',
                'type'    => 'select',
                'default' => 'none',
                'options' => [
                    'none' => 'None',
                    'sm'   => 'Small (1rem)',
                    'md'   => 'Medium (2rem)',
                    'lg'   => 'Large (4rem)',
                ],
            ],
        ];
    }
}
