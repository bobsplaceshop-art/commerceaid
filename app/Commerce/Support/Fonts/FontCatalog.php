<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support\Fonts;

/**
 * Curated catalog of open-source writing fonts.
 *
 * Every font in this list is licensed under OFL 1.1 or
 * Apache 2.0. Every WOFF2 file is fetched from Fontsource,
 * a pure redistribution project that serves the fonts from
 * cdn.jsdelivr.net. Fontsource does no tracking. The
 * download happens server-side once per font per weight.
 * After that the files live on the store's own disk. No
 * visitor request ever reaches Google or a CDN.
 *
 * Top 50 curated by use case. Not the full 1,500-family
 * Google Fonts catalog - a merchant cannot meaningfully
 * browse that, and the compliance goal is met either way.
 * A custom-upload path for arbitrary fonts is a future
 * sub-stage.
 *
 * Slots:
 *   body    - sans-serif for running text
 *   display - serif or bold sans for headings
 *   script  - decorative for accents
 *
 * Every font here has been chosen for legibility at small
 * sizes, wide platform support, and reliable Fontsource
 * availability. Adding or removing a font is one entry.
 *
 * See docs/stages/008g.4c_font-manager-plan.md (to be
 * written at stage close).
 */
final class FontCatalog
{
    public const SLOT_BODY    = 'body';
    public const SLOT_DISPLAY = 'display';
    public const SLOT_SCRIPT  = 'script';

    /**
     * Weights available for every font, unless overridden.
     * Most OFL fonts ship 400 and 700. Some ship 500 and 600.
     * The downloader fetches exactly what is declared.
     *
     * @var array<int,string>
     */
    private const DEFAULT_WEIGHTS = ['400', '500', '600', '700'];

    /**
     * Every curated font.
     *
     * @var array<int,array{
     *     family: string,
     *     slug: string,
     *     slot: string,
     *     weights: array<int,string>,
     *     category: string,
     *     license: string
     * }>
     */
    private const FONTS = [
        // ----- Body / sans-serif -----
        ['family' => 'Inter',              'slug' => 'inter',              'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'sans-serif', 'license' => 'OFL-1.1'],
        ['family' => 'Roboto',             'slug' => 'roboto',             'slot' => 'body',    'weights' => ['400','500','700'],       'category' => 'sans-serif', 'license' => 'Apache-2.0'],
        ['family' => 'Open Sans',          'slug' => 'open-sans',          'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'sans-serif', 'license' => 'OFL-1.1'],
        ['family' => 'Lato',               'slug' => 'lato',               'slot' => 'body',    'weights' => ['400','700'],             'category' => 'sans-serif', 'license' => 'OFL-1.1'],
        ['family' => 'Source Sans 3',      'slug' => 'source-sans-3',      'slot' => 'body',    'weights' => ['400','600','700'],       'category' => 'sans-serif', 'license' => 'OFL-1.1'],
        ['family' => 'Work Sans',          'slug' => 'work-sans',          'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'sans-serif', 'license' => 'OFL-1.1'],
        ['family' => 'IBM Plex Sans',      'slug' => 'ibm-plex-sans',      'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'sans-serif', 'license' => 'OFL-1.1'],
        ['family' => 'Manrope',            'slug' => 'manrope',            'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'sans-serif', 'license' => 'OFL-1.1'],
        ['family' => 'Public Sans',        'slug' => 'public-sans',        'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'sans-serif', 'license' => 'OFL-1.1'],
        ['family' => 'DM Sans',            'slug' => 'dm-sans',            'slot' => 'body',    'weights' => ['400','500','700'],       'category' => 'sans-serif', 'license' => 'OFL-1.1'],
        ['family' => 'Figtree',            'slug' => 'figtree',            'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'sans-serif', 'license' => 'OFL-1.1'],
        ['family' => 'Karla',              'slug' => 'karla',              'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'sans-serif', 'license' => 'OFL-1.1'],
        ['family' => 'Rubik',              'slug' => 'rubik',              'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'sans-serif', 'license' => 'OFL-1.1'],
        ['family' => 'Nunito Sans',        'slug' => 'nunito-sans',        'slot' => 'body',    'weights' => ['400','600','700'],       'category' => 'sans-serif', 'license' => 'OFL-1.1'],
        ['family' => 'Mulish',             'slug' => 'mulish',             'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'sans-serif', 'license' => 'OFL-1.1'],
        ['family' => 'Noto Sans',          'slug' => 'noto-sans',          'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'sans-serif', 'license' => 'OFL-1.1'],
        ['family' => 'Barlow',             'slug' => 'barlow',             'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'sans-serif', 'license' => 'OFL-1.1'],
        ['family' => 'Outfit',             'slug' => 'outfit',             'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'sans-serif', 'license' => 'OFL-1.1'],
        ['family' => 'Plus Jakarta Sans',  'slug' => 'plus-jakarta-sans',  'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'sans-serif', 'license' => 'OFL-1.1'],
        ['family' => 'Sora',               'slug' => 'sora',               'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'sans-serif', 'license' => 'OFL-1.1'],

        // ----- Display / serif -----
        ['family' => 'Playfair Display',   'slug' => 'playfair-display',   'slot' => 'display', 'weights' => ['400','500','600','700'], 'category' => 'serif', 'license' => 'OFL-1.1'],
        ['family' => 'Lora',               'slug' => 'lora',               'slot' => 'display', 'weights' => ['400','500','600','700'], 'category' => 'serif', 'license' => 'OFL-1.1'],
        ['family' => 'Merriweather',       'slug' => 'merriweather',       'slot' => 'display', 'weights' => ['400','700'],             'category' => 'serif', 'license' => 'OFL-1.1'],
        ['family' => 'Source Serif 4',     'slug' => 'source-serif-4',     'slot' => 'display', 'weights' => ['400','600','700'],       'category' => 'serif', 'license' => 'OFL-1.1'],
        ['family' => 'Libre Baskerville',  'slug' => 'libre-baskerville',  'slot' => 'display', 'weights' => ['400','700'],             'category' => 'serif', 'license' => 'OFL-1.1'],
        ['family' => 'Crimson Pro',        'slug' => 'crimson-pro',        'slot' => 'display', 'weights' => ['400','600','700'],       'category' => 'serif', 'license' => 'OFL-1.1'],
        ['family' => 'EB Garamond',        'slug' => 'eb-garamond',        'slot' => 'display', 'weights' => ['400','500','600','700'], 'category' => 'serif', 'license' => 'OFL-1.1'],
        ['family' => 'Cormorant Garamond', 'slug' => 'cormorant-garamond', 'slot' => 'display', 'weights' => ['400','500','600','700'], 'category' => 'serif', 'license' => 'OFL-1.1'],
        ['family' => 'Spectral',           'slug' => 'spectral',           'slot' => 'display', 'weights' => ['400','600','700'],       'category' => 'serif', 'license' => 'OFL-1.1'],
        ['family' => 'PT Serif',           'slug' => 'pt-serif',           'slot' => 'display', 'weights' => ['400','700'],             'category' => 'serif', 'license' => 'OFL-1.1'],
        ['family' => 'Noto Serif',         'slug' => 'noto-serif',         'slot' => 'display', 'weights' => ['400','600','700'],       'category' => 'serif', 'license' => 'OFL-1.1'],
        ['family' => 'Bitter',             'slug' => 'bitter',             'slot' => 'display', 'weights' => ['400','500','600','700'], 'category' => 'serif', 'license' => 'OFL-1.1'],
        ['family' => 'Fraunces',           'slug' => 'fraunces',           'slot' => 'display', 'weights' => ['400','500','600','700'], 'category' => 'serif', 'license' => 'OFL-1.1'],
        ['family' => 'DM Serif Display',   'slug' => 'dm-serif-display',   'slot' => 'display', 'weights' => ['400'],                   'category' => 'serif', 'license' => 'OFL-1.1'],
        ['family' => 'Bodoni Moda',        'slug' => 'bodoni-moda',        'slot' => 'display', 'weights' => ['400','500','600','700'], 'category' => 'serif', 'license' => 'OFL-1.1'],

        // ----- Script / decorative -----
        ['family' => 'Dancing Script',     'slug' => 'dancing-script',     'slot' => 'script',  'weights' => ['400','600','700'],       'category' => 'script', 'license' => 'OFL-1.1'],
        ['family' => 'Great Vibes',        'slug' => 'great-vibes',        'slot' => 'script',  'weights' => ['400'],                   'category' => 'script', 'license' => 'OFL-1.1'],
        ['family' => 'Allura',             'slug' => 'allura',             'slot' => 'script',  'weights' => ['400'],                   'category' => 'script', 'license' => 'OFL-1.1'],
        ['family' => 'Pacifico',           'slug' => 'pacifico',           'slot' => 'script',  'weights' => ['400'],                   'category' => 'script', 'license' => 'OFL-1.1'],
        ['family' => 'Caveat',             'slug' => 'caveat',             'slot' => 'script',  'weights' => ['400','500','600','700'], 'category' => 'script', 'license' => 'OFL-1.1'],
        ['family' => 'Kalam',              'slug' => 'kalam',              'slot' => 'script',  'weights' => ['400','700'],             'category' => 'script', 'license' => 'OFL-1.1'],
        ['family' => 'Satisfy',            'slug' => 'satisfy',            'slot' => 'script',  'weights' => ['400'],                   'category' => 'script', 'license' => 'OFL-1.1'],
        ['family' => 'Parisienne',         'slug' => 'parisienne',         'slot' => 'script',  'weights' => ['400'],                   'category' => 'script', 'license' => 'OFL-1.1'],
        ['family' => 'Alex Brush',         'slug' => 'alex-brush',         'slot' => 'script',  'weights' => ['400'],                   'category' => 'script', 'license' => 'OFL-1.1'],
        ['family' => 'Sacramento',         'slug' => 'sacramento',         'slot' => 'script',  'weights' => ['400'],                   'category' => 'script', 'license' => 'OFL-1.1'],

        // ----- Mono / technical -----
        ['family' => 'JetBrains Mono',     'slug' => 'jetbrains-mono',     'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'monospace', 'license' => 'OFL-1.1'],
        ['family' => 'Fira Code',          'slug' => 'fira-code',          'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'monospace', 'license' => 'OFL-1.1'],
        ['family' => 'IBM Plex Mono',      'slug' => 'ibm-plex-mono',      'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'monospace', 'license' => 'OFL-1.1'],
        ['family' => 'Source Code Pro',    'slug' => 'source-code-pro',    'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'monospace', 'license' => 'OFL-1.1'],
        ['family' => 'Roboto Mono',        'slug' => 'roboto-mono',        'slot' => 'body',    'weights' => ['400','500','600','700'], 'category' => 'monospace', 'license' => 'Apache-2.0'],
    ];

    /**
     * Every font as a flat array, sorted by family name.
     *
     * @return array<int,array{family:string,slug:string,slot:string,weights:array<int,string>,category:string,license:string}>
     */
    public static function all(): array
    {
        $out = self::FONTS;
        usort($out, fn ($a, $b) => strcmp($a['family'], $b['family']));
        return $out;
    }

    /**
     * Fonts filtered by slot: body, display, or script.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forSlot(string $slot): array
    {
        return array_values(array_filter(
            self::all(),
            fn ($f) => $f['slot'] === $slot
        ));
    }

    /**
     * Look up one font by slug. Null when the slug is not
     * in the catalog.
     *
     * @return array<string,mixed>|null
     */
    public static function find(string $slug): ?array
    {
        foreach (self::FONTS as $font) {
            if ($font['slug'] === $slug) {
                return $font;
            }
        }
        return null;
    }

    /**
     * Weights declared for a slug, or the default set when
     * the slug is not in the catalog.
     *
     * @return array<int,string>
     */
    public static function weightsFor(string $slug): array
    {
        $font = self::find($slug);
        return $font['weights'] ?? self::DEFAULT_WEIGHTS;
    }

    /**
     * Build the Fontsource CDN URL for a WOFF2 file.
     *
     * Fontsource serves WOFF2 files at:
     *   https://cdn.jsdelivr.net/npm/@fontsource/{slug}/files/
     *     {slug}-latin-{weight}-normal.woff2
     *
     * The latin subset is what we want - it covers all common
     * Western European characters. Cyrillic, Greek, and other
     * subsets add bytes without benefit for a storefront that
     * serves Germany, Kenya, and the UK.
     */
    public static function woff2Url(string $slug, string $weight): string
    {
        return 'https://cdn.jsdelivr.net/npm/@fontsource/'
            . rawurlencode($slug)
            . '/files/'
            . rawurlencode($slug) . '-latin-' . rawurlencode($weight) . '-normal.woff2';
    }
}
