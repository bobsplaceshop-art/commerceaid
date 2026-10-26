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
     * Options for the text_font select field, built from the
     * FontCatalog. Called by the config field renderer when
     * the field declares font_options: true.
     *
     * @return array<string,string>  slug => family
     */
    public static function fontOptions(): array
    {
        $opts = ['inherit' => 'Inherit (store default)'];
        foreach (\CorporateAid\Commerce\Support\Fonts\FontCatalog::all() as $font) {
            $opts[$font['slug']] = $font['family'];
        }
        return $opts;
    }

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
            'trust-row'     => self::trustRow(),
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
            'layout_direction' => [
                'label'   => 'Layout direction',
                'type'    => 'select',
                'default' => 'column',
                'options' => [
                    'column' => 'Column (stack vertically)',
                    'row'    => 'Row (side by side)',
                    'wrap'   => 'Wrap (side by side, wraps to next line)',
                ],
                'section' => 'layout',
            ],
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
                'section' => 'layout',
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
                'section' => 'layout',
            ],
            'background_color' => [
                'label'   => 'Background color',
                'type'    => 'color',
                'default' => null,
                'hint'    => 'Leave empty for the storefront default surface color.',
                'section' => 'style',
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
                'section' => 'layout',
            ],
            'align_items' => [
                'label'   => 'Cross-axis alignment',
                'type'    => 'select',
                'default' => 'stretch',
                'options' => [
                    'stretch' => 'Stretch',
                    'left'    => 'Start',
                    'center'  => 'Center',
                    'right'   => 'End',
                ],
                'hint'    => 'In column layout, this is horizontal. In row layout, this is vertical.',
                'section' => 'layout',
            ],
            'justify_content' => [
                'label'   => 'Main-axis alignment',
                'type'    => 'select',
                'default' => 'start',
                'options' => [
                    'start'         => 'Start',
                    'center'        => 'Center',
                    'end'           => 'End',
                    'space-between' => 'Space between',
                    'space-around'  => 'Space around',
                    'space-evenly'  => 'Space evenly',
                ],
                'hint'    => 'In column layout, this is vertical. In row layout, this is horizontal.',
                'section' => 'layout',
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
            'layout_direction' => [
                'label'   => 'Layout direction',
                'type'    => 'select',
                'default' => 'column',
                'options' => [
                    'column' => 'Column (stack vertically)',
                    'row'    => 'Row (side by side)',
                    'wrap'   => 'Wrap (side by side, wraps to next line)',
                ],
                'section' => 'layout',
            ],
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
                'section' => 'layout',
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
                'section' => 'layout',
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
                'section' => 'layout',
            ],
            'align_items' => [
                'label'   => 'Cross-axis alignment',
                'type'    => 'select',
                'default' => 'stretch',
                'options' => [
                    'stretch' => 'Stretch',
                    'left'    => 'Start',
                    'center'  => 'Center',
                    'right'   => 'End',
                ],
                'hint'    => 'In column layout, this is horizontal. In row layout, this is vertical.',
                'section' => 'layout',
            ],
            'justify_content' => [
                'label'   => 'Main-axis alignment',
                'type'    => 'select',
                'default' => 'start',
                'options' => [
                    'start'         => 'Start',
                    'center'        => 'Center',
                    'end'           => 'End',
                    'space-between' => 'Space between',
                    'space-around'  => 'Space around',
                    'space-evenly'  => 'Space evenly',
                ],
                'hint'    => 'In column layout, this is vertical. In row layout, this is horizontal.',
                'section' => 'layout',
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
                'section' => 'content',
            ],
            'subheadline' => [
                'label'   => 'Subheadline',
                'type'    => 'string',
                'default' => '',
                'hint'    => 'Optional line under the headline.',
                'section' => 'content',
            ],
            'cta_text' => [
                'label'   => 'CTA text',
                'type'    => 'string',
                'default' => 'Explore Collection',
                'section' => 'content',
            ],
            'cta_url' => [
                'label'   => 'CTA URL',
                'type'    => 'string',
                'default' => '/products',
                'hint'    => 'Where the call-to-action button points.',
                'section' => 'content',
            ],
            'image_file_id' => [
                'label'   => 'Background image',
                'type'    => 'file',
                'default' => null,
                'hint'    => 'Optional. Shown behind the headline.',
                'section' => 'style',
            ],
            'heading_typography' => [
                'label'   => 'Heading typography',
                'type'    => 'typography',
                'default' => ['font' => 'inherit', 'size' => 'inherit', 'weight' => 'inherit', 'alignment' => 'inherit'],
                'section' => 'style',
            ],
            'subheading_typography' => [
                'label'   => 'Subheading typography',
                'type'    => 'typography',
                'default' => ['font' => 'inherit', 'size' => 'inherit', 'weight' => 'inherit', 'alignment' => 'inherit'],
                'section' => 'style',
            ],
            'text_color' => [
                'label'   => 'Text color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Leave empty for the storefront default color.',
                'section' => 'style',
                'pair' => 'colors',
            ],
            'secondary_text_color' => [
                'label'   => 'Secondary text color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Optional. Used for the subheading, subtitle, or caption. Leave empty for the storefront muted default.',
                'section' => 'style',
                'pair' => 'colors',
            ],
            'background_type' => [
                'label'   => 'Background type',
                'type'    => 'select',
                'default' => 'none',
                'options' => [
                    'none'     => 'None (inherit)',
                    'solid'    => 'Solid color',
                    'gradient' => 'CSS gradient',
                    'image'    => 'Image',
                ],
                'section' => 'style',
            ],
            'background_color' => [
                'label'   => 'Background color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Used when background type is "Solid color".',
                'section' => 'style',
            ],
            'background_gradient' => [
                'label'   => 'Background gradient',
                'type'    => 'string',
                'default' => '',
                'hint'    => 'CSS gradient, e.g. linear-gradient(135deg, #1a2b23, #0f0f0f). Used when type is "CSS gradient".',
                'section' => 'style',
            ],
            'background_image_file_id' => [
                'label'   => 'Background image',
                'type'    => 'file',
                'default' => null,
                'hint'    => 'Used when type is "Image".',
                'section' => 'style',
            ],
            'background_overlay' => [
                'label'   => 'Background image overlay',
                'type'    => 'select',
                'default' => 'none',
                'options' => [
                    'none'  => 'None',
                    'dark'  => 'Dark (improves text on photos)',
                    'light' => 'Light',
                ],
                'hint'    => 'Applies a semi-transparent tint over the image.',
                'section' => 'style',
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
                'section' => 'content',
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
                'section' => 'content',
            ],
            'category_slug' => [
                'label'   => 'Category',
                'type'    => 'category_select',
                'default' => '',
                'hint'    => 'Used when "Products" is set to "Specific category".',
                'section' => 'content',
            ],
            'limit' => [
                'label'   => 'Number of products',
                'type'    => 'int',
                'default' => 4,
                'section' => 'content',
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
                'section' => 'layout',
            ],
            'columns_mobile' => [
                'label'   => 'Mobile columns',
                'type'    => 'select',
                'default' => '2',
                'options' => [
                    '1' => '1 column',
                    '2' => '2 columns',
                ],
                'section' => 'layout',
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
                'section' => 'layout',
            ],
            'show_view_all' => [
                'label'   => 'Show "View all" link',
                'type'    => 'boolean',
                'default' => true,
                'section' => 'content',
            ],
            'padding_top' => [
                'label'   => 'Top padding',
                'type'    => 'select',
                'default' => 'lg',
                'options' => [
                    'none' => 'None',
                    'sm'   => 'Small',
                    'md'   => 'Medium',
                    'lg'   => 'Large',
                    'xl'   => 'Extra large',
                ],
                'section' => 'layout',
            ],
            'padding_bottom' => [
                'label'   => 'Bottom padding',
                'type'    => 'select',
                'default' => 'lg',
                'options' => [
                    'none' => 'None',
                    'sm'   => 'Small',
                    'md'   => 'Medium',
                    'lg'   => 'Large',
                    'xl'   => 'Extra large',
                ],
                'section' => 'layout',
            ],
            'text_color' => [
                'label'   => 'Text color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Leave empty for the storefront default color.',
                'section' => 'style',
                'pair' => 'colors',
            ],
            'secondary_text_color' => [
                'label'   => 'Secondary text color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Optional. Used for the subheading, subtitle, or caption. Leave empty for the storefront muted default.',
                'section' => 'style',
                'pair' => 'colors',
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
                'section' => 'content',
            ],
            'subheadline' => [
                'label'   => 'Subheadline',
                'type'    => 'string',
                'default' => 'Up to 50% off selected styles.',
                'section' => 'content',
            ],
            'cta_text' => [
                'label'   => 'CTA text',
                'type'    => 'string',
                'default' => 'Shop Sale',
                'section' => 'content',
            ],
            'cta_url' => [
                'label'   => 'CTA URL',
                'type'    => 'string',
                'default' => '/products',
                'section' => 'content',
            ],
            'image_file_id' => [
                'label'   => 'Background image',
                'type'    => 'file',
                'default' => null,
                'section' => 'style',
            ],
            'heading_typography' => [
                'label'   => 'Heading typography',
                'type'    => 'typography',
                'default' => ['font' => 'inherit', 'size' => 'inherit', 'weight' => 'inherit', 'alignment' => 'inherit'],
                'section' => 'style',
            ],
            'subheading_typography' => [
                'label'   => 'Subheading typography',
                'type'    => 'typography',
                'default' => ['font' => 'inherit', 'size' => 'inherit', 'weight' => 'inherit', 'alignment' => 'inherit'],
                'section' => 'style',
            ],
            'text_color' => [
                'label'   => 'Text color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Leave empty for the storefront default color.',
                'section' => 'style',
                'pair' => 'colors',
            ],
            'secondary_text_color' => [
                'label'   => 'Secondary text color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Optional. Used for the subheading, subtitle, or caption. Leave empty for the storefront muted default.',
                'section' => 'style',
                'pair' => 'colors',
            ],
            'background_type' => [
                'label'   => 'Background type',
                'type'    => 'select',
                'default' => 'none',
                'options' => [
                    'none'     => 'None (inherit)',
                    'solid'    => 'Solid color',
                    'gradient' => 'CSS gradient',
                    'image'    => 'Image',
                ],
                'section' => 'style',
            ],
            'background_color' => [
                'label'   => 'Background color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Used when background type is "Solid color".',
                'section' => 'style',
            ],
            'background_gradient' => [
                'label'   => 'Background gradient',
                'type'    => 'string',
                'default' => '',
                'hint'    => 'CSS gradient, e.g. linear-gradient(135deg, #1a2b23, #0f0f0f). Used when type is "CSS gradient".',
                'section' => 'style',
            ],
            'background_image_file_id' => [
                'label'   => 'Background image',
                'type'    => 'file',
                'default' => null,
                'hint'    => 'Used when type is "Image".',
                'section' => 'style',
            ],
            'background_overlay' => [
                'label'   => 'Background image overlay',
                'type'    => 'select',
                'default' => 'none',
                'options' => [
                    'none'  => 'None',
                    'dark'  => 'Dark (improves text on photos)',
                    'light' => 'Light',
                ],
                'hint'    => 'Applies a semi-transparent tint over the image.',
                'section' => 'style',
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
                'section' => 'content',
            ],
            'body_typography' => [
                'label'   => 'Body typography',
                'type'    => 'typography',
                'default' => ['font' => 'inherit', 'size' => 'inherit', 'weight' => 'inherit', 'alignment' => 'inherit'],
                'section' => 'style',
            ],
            'text_color' => [
                'label'   => 'Text color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Leave empty for the storefront default color.',
                'section' => 'style',
                'pair' => 'colors',
            ],
            'secondary_text_color' => [
                'label'   => 'Secondary text color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Optional. Used for the subheading, subtitle, or caption. Leave empty for the storefront muted default.',
                'section' => 'style',
                'pair' => 'colors',
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
                'section' => 'content',
            ],
            'subheadline' => [
                'label'   => 'Subheadline',
                'type'    => 'string',
                'default' => 'Join our newsletter for 10% off your first order.',
                'section' => 'content',
            ],
            'button_text' => [
                'label'   => 'Button text',
                'type'    => 'string',
                'default' => 'Subscribe',
                'section' => 'content',
            ],
            'padding_top' => [
                'label'   => 'Top padding',
                'type'    => 'select',
                'default' => 'lg',
                'options' => [
                    'none' => 'None',
                    'sm'   => 'Small',
                    'md'   => 'Medium',
                    'lg'   => 'Large',
                    'xl'   => 'Extra large',
                ],
                'section' => 'layout',
            ],
            'padding_bottom' => [
                'label'   => 'Bottom padding',
                'type'    => 'select',
                'default' => 'lg',
                'options' => [
                    'none' => 'None',
                    'sm'   => 'Small',
                    'md'   => 'Medium',
                    'lg'   => 'Large',
                    'xl'   => 'Extra large',
                ],
                'section' => 'layout',
            ],
            'text_color' => [
                'label'   => 'Text color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Leave empty for the storefront default color.',
                'section' => 'style',
                'pair' => 'colors',
            ],
            'secondary_text_color' => [
                'label'   => 'Secondary text color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Optional. Used for the subheading, subtitle, or caption. Leave empty for the storefront muted default.',
                'section' => 'style',
                'pair' => 'colors',
            ],
            'background_type' => [
                'label'   => 'Background type',
                'type'    => 'select',
                'default' => 'none',
                'options' => [
                    'none'     => 'None (inherit)',
                    'solid'    => 'Solid color',
                    'gradient' => 'CSS gradient',
                    'image'    => 'Image',
                ],
                'section' => 'style',
            ],
            'background_color' => [
                'label'   => 'Background color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Used when background type is "Solid color".',
                'section' => 'style',
            ],
            'background_gradient' => [
                'label'   => 'Background gradient',
                'type'    => 'string',
                'default' => '',
                'hint'    => 'CSS gradient, e.g. linear-gradient(135deg, #1a2b23, #0f0f0f). Used when type is "CSS gradient".',
                'section' => 'style',
            ],
            'background_image_file_id' => [
                'label'   => 'Background image',
                'type'    => 'file',
                'default' => null,
                'hint'    => 'Used when type is "Image".',
                'section' => 'style',
            ],
            'background_overlay' => [
                'label'   => 'Background image overlay',
                'type'    => 'select',
                'default' => 'none',
                'options' => [
                    'none'  => 'None',
                    'dark'  => 'Dark (improves text on photos)',
                    'light' => 'Light',
                ],
                'hint'    => 'Applies a semi-transparent tint over the image.',
                'section' => 'style',
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
                'section' => 'layout',
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
                'section' => 'content',
            ],
            'cta_text' => [
                'label'   => 'CTA text',
                'type'    => 'string',
                'default' => 'Explore',
                'section' => 'content',
            ],
            'cta_url' => [
                'label'   => 'CTA URL',
                'type'    => 'string',
                'default' => '/categories/new',
                'section' => 'content',
            ],
            'image_file_id' => [
                'label'   => 'Image',
                'type'    => 'file',
                'default' => null,
                'section' => 'style',
            ],
            'eyebrow' => [
                'label'   => 'Eyebrow (small uppercase label)',
                'type'    => 'string',
                'default' => 'Why Choose Us',
                'section' => 'content',
            ],
            'right_headline' => [
                'label'   => 'Right caption headline',
                'type'    => 'string',
                'default' => 'Style Without Borders',
                'section' => 'content',
            ],
            'right_subheadline' => [
                'label'   => 'Right caption subheadline',
                'type'    => 'string',
                'default' => 'From Nairobi to the world.',
                'section' => 'content',
            ],
            'heading_typography' => [
                'label'   => 'Heading typography',
                'type'    => 'typography',
                'default' => ['font' => 'inherit', 'size' => 'inherit', 'weight' => 'inherit', 'alignment' => 'inherit'],
                'section' => 'style',
            ],
            'padding_top' => [
                'label'   => 'Top padding',
                'type'    => 'select',
                'default' => 'lg',
                'options' => [
                    'none' => 'None',
                    'sm'   => 'Small',
                    'md'   => 'Medium',
                    'lg'   => 'Large',
                    'xl'   => 'Extra large',
                ],
                'section' => 'layout',
            ],
            'padding_bottom' => [
                'label'   => 'Bottom padding',
                'type'    => 'select',
                'default' => 'lg',
                'options' => [
                    'none' => 'None',
                    'sm'   => 'Small',
                    'md'   => 'Medium',
                    'lg'   => 'Large',
                    'xl'   => 'Extra large',
                ],
                'section' => 'layout',
            ],
            'text_color' => [
                'label'   => 'Text color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Leave empty for the storefront default color.',
                'section' => 'style',
                'pair' => 'colors',
            ],
            'secondary_text_color' => [
                'label'   => 'Secondary text color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Optional. Used for the subheading, subtitle, or caption. Leave empty for the storefront muted default.',
                'section' => 'style',
                'pair' => 'colors',
            ],
            'background_type' => [
                'label'   => 'Background type',
                'type'    => 'select',
                'default' => 'none',
                'options' => [
                    'none'     => 'None (inherit)',
                    'solid'    => 'Solid color',
                    'gradient' => 'CSS gradient',
                    'image'    => 'Image',
                ],
                'section' => 'style',
            ],
            'background_color' => [
                'label'   => 'Background color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Used when background type is "Solid color".',
                'section' => 'style',
            ],
            'background_gradient' => [
                'label'   => 'Background gradient',
                'type'    => 'string',
                'default' => '',
                'hint'    => 'CSS gradient, e.g. linear-gradient(135deg, #1a2b23, #0f0f0f). Used when type is "CSS gradient".',
                'section' => 'style',
            ],
            'background_image_file_id' => [
                'label'   => 'Background image',
                'type'    => 'file',
                'default' => null,
                'hint'    => 'Used when type is "Image".',
                'section' => 'style',
            ],
            'background_overlay' => [
                'label'   => 'Background image overlay',
                'type'    => 'select',
                'default' => 'none',
                'options' => [
                    'none'  => 'None',
                    'dark'  => 'Dark (improves text on photos)',
                    'light' => 'Light',
                ],
                'hint'    => 'Applies a semi-transparent tint over the image.',
                'section' => 'style',
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
                'section' => 'content',
            ],
            'category_ids' => [
                'label'   => 'Categories',
                'type'    => 'category_multi_select',
                'default' => [],
                'hint'    => 'Leave empty to show the top categories by product count.',
                'section' => 'content',
            ],
            'limit' => [
                'label'   => 'Maximum categories',
                'type'    => 'int',
                'default' => 5,
                'hint'    => 'Between 1 and 12.',
                'section' => 'content',
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
                'section' => 'layout',
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
                'section' => 'layout',
            ],
            'columns_mobile' => [
                'label'   => 'Mobile columns',
                'type'    => 'select',
                'default' => '2',
                'options' => [
                    '1' => '1 column',
                    '2' => '2 columns',
                ],
                'section' => 'layout',
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
                'section' => 'layout',
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
                'section' => 'layout',
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
                'section' => 'layout',
            ],
            'divider' => [
                'label'   => 'Show divider',
                'type'    => 'boolean',
                'default' => false,
                'hint'    => 'A thin horizontal line above the section.',
                'section' => 'layout',
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
                'section' => 'layout',
            ],
            'text_color' => [
                'label'   => 'Text color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Leave empty for the storefront default color.',
                'section' => 'style',
                'pair' => 'colors',
            ],
            'secondary_text_color' => [
                'label'   => 'Secondary text color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Optional. Used for the subheading, subtitle, or caption. Leave empty for the storefront muted default.',
                'section' => 'style',
                'pair' => 'colors',
            ],
        ];
    }

    /** @return array<string, array<string,mixed>> */
    private static function trustRow(): array
    {
        return [
            'heading' => [
                'label'   => 'Visible heading (optional)',
                'type'    => 'string',
                'default' => '',
                'hint'    => 'A short line above the trust icons. Leave empty for icons only.',
                'section' => 'content',
            ],
            'background_color' => [
                'label'   => 'Background color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Leave empty for the storefront cream default. Use a hex color like #F8F1E8 to override.',
                'section' => 'style',
            ],
            'padding_top' => [
                'label'   => 'Top padding',
                'type'    => 'select',
                'default' => 'md',
                'options' => [
                    'none' => 'None',
                    'sm'   => 'Small',
                    'md'   => 'Medium',
                    'lg'   => 'Large',
                    'xl'   => 'Extra large',
                ],
                'section' => 'layout',
            ],
            'padding_bottom' => [
                'label'   => 'Bottom padding',
                'type'    => 'select',
                'default' => 'md',
                'options' => [
                    'none' => 'None',
                    'sm'   => 'Small',
                    'md'   => 'Medium',
                    'lg'   => 'Large',
                    'xl'   => 'Extra large',
                ],
                'section' => 'layout',
            ],
            'text_color' => [
                'label'   => 'Text color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Leave empty for the storefront default color.',
                'section' => 'style',
                'pair' => 'colors',
            ],
            'secondary_text_color' => [
                'label'   => 'Secondary text color',
                'type'    => 'color',
                'default' => '',
                'hint'    => 'Optional. Used for the subheading, subtitle, or caption. Leave empty for the storefront muted default.',
                'section' => 'style',
                'pair' => 'colors',
            ],
        ];
    }
}
