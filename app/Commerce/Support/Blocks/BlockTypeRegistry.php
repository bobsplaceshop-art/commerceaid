<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support\Blocks;

/**
 * The single source of block types.
 *
 * Every place that currently hardcodes the set of block types
 * (the picker, addBlock validation, PageBlock::typeLabel)
 * reads from this class instead. Adding a new block type is
 * one entry in types(), not a multi-file edit.
 *
 * Each entry carries:
 *   - label:            Human-readable name for the sidebar.
 *   - icon:             Bootstrap Icon class.
 *   - hint:             Short description for the picker.
 *   - group:            'structural' (Sections) vs 'element' (widgets).
 *   - accepts_children: True if the block can contain other blocks.
 *   - partial:          The view path for the renderer.
 */
final class BlockTypeRegistry
{
    /**
     * @return array<string, array{label:string,icon:string,hint:string,group:string,accepts_children:bool,partial:string}>
     */
    public static function all(): array
    {
        return self::types();
    }

    /**
     * @return array{label:string,icon:string,hint:string,group:string,accepts_children:bool,partial:string}|null
     */
    public static function get(string $type): ?array
    {
        return self::types()[$type] ?? null;
    }

    public static function has(string $type): bool
    {
        return isset(self::types()[$type]);
    }

    public static function acceptsChildren(string $type): bool
    {
        return self::types()[$type]['accepts_children'] ?? false;
    }

    public static function group(string $type): string
    {
        return self::types()[$type]['group'] ?? 'element';
    }

    public static function label(string $type): string
    {
        return self::types()[$type]['label'] ?? ucfirst(str_replace('-', ' ', $type));
    }

    public static function partial(string $type): string
    {
        return self::types()[$type]['partial'] ?? 'storefront/blocks/' . $type;
    }

    /**
     * @return array<string, array{label:string,icon:string,hint:string,group:string,accepts_children:bool,partial:string}>
     */
    private static function types(): array
    {
        static $types = null;
        if ($types !== null) {
            return $types;
        }

        $types = [
            'hero' => [
                'label'            => 'Hero',
                'icon'             => 'bi-image',
                'hint'             => 'A big banner with headline and CTA.',
                'group'            => 'structural',
                'accepts_children' => false,
                'partial'          => 'storefront/blocks/hero',
            ],
            'product-grid' => [
                'label'            => 'Product Grid',
                'icon'             => 'bi-grid-3x3-gap',
                'hint'             => 'A row of products (new arrivals, best sellers, category).',
                'group'            => 'element',
                'accepts_children' => false,
                'partial'          => 'storefront/blocks/product-grid',
            ],
            'category-grid' => [
                'label'            => 'Category Grid',
                'icon'             => 'bi-grid',
                'hint'             => 'The "Shop by Category" row.',
                'group'            => 'element',
                'accepts_children' => false,
                'partial'          => 'storefront/blocks/category-grid',
            ],
            'promo-panel' => [
                'label'            => 'Promo Panel',
                'icon'             => 'bi-tag',
                'hint'             => 'A single promotional tile with image and CTA.',
                'group'            => 'element',
                'accepts_children' => false,
                'partial'          => 'storefront/blocks/promo-panel',
            ],
            'banner' => [
                'label'            => 'Banner',
                'icon'             => 'bi-rectangle-wide',
                'hint'             => 'A full-width banner with text overlay.',
                'group'            => 'element',
                'accepts_children' => false,
                'partial'          => 'storefront/blocks/banner',
            ],
            'rich-text' => [
                'label'            => 'Rich Text',
                'icon'             => 'bi-text-paragraph',
                'hint'             => 'A block of copy (headings, paragraphs, links).',
                'group'            => 'element',
                'accepts_children' => false,
                'partial'          => 'storefront/blocks/rich-text',
            ],
            'newsletter' => [
                'label'            => 'Newsletter',
                'icon'             => 'bi-envelope',
                'hint'             => 'A subscribe form.',
                'group'            => 'element',
                'accepts_children' => false,
                'partial'          => 'storefront/blocks/newsletter',
            ],
            'trust-row' => [
                'label'            => 'Trust Bar',
                'icon'             => 'bi-patch-check',
                'hint'             => 'The trust bar (shipping, payment, delivery, support). Items are edited from Design > Header Builder.',
                'group'            => 'element',
                'accepts_children' => false,
                'partial'          => 'storefront/blocks/trust-row',
            ],
            'spacer' => [
                'label'            => 'Spacer',
                'icon'             => 'bi-arrows-expand',
                'hint'             => 'Empty vertical space. Useful between two similar blocks.',
                'group'            => 'element',
                'accepts_children' => false,
                'partial'          => 'storefront/blocks/spacer',
            ],
            'section' => [
                'label'            => 'Section',
                'icon'             => 'bi-layout-three-columns',
                'hint'             => 'A full-width container. Drop elements inside it to build a layout.',
                'group'            => 'structural',
                'accepts_children' => true,
                'partial'          => 'storefront/blocks/section',
            ],
            'container' => [
                'label'            => 'Container',
                'icon'             => 'bi-box',
                'hint'             => 'A nested wrapper. Use inside a Section to group related elements.',
                'group'            => 'structural',
                'accepts_children' => true,
                'partial'          => 'storefront/blocks/container',
            ],
        ];

        return $types;
    }
}
