<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Seo;

use CorporateAid\Commerce\Models\Product;
use CorporateAid\Commerce\Models\Store;
use CorporateAid\Commerce\Repositories\ProductImageRepository;
use CorporateAid\Commerce\Repositories\MediaRepository;

/**
 * Generates Open Graph and Twitter Card meta tags.
 *
 * These are what a link preview shows when someone shares
 * a product URL on WhatsApp, Facebook, Pinterest, LinkedIn,
 * or X. The German/EU market expects product:price:amount
 * so the price appears in the preview.
 *
 * See docs/stages/024_seo-de-eu-plan.md.
 */
final class SocialMetaService
{
    public function __construct(
        private ProductImageRepository $images,
        private MediaRepository $media,
    ) {
    }

    /** @return array<string,string> */
    public function product(Product $product, Store $store): array
    {
        $storeName = $store->name ?? 'Store';

        $description = strip_tags(
            $product->shortDescription ?? $product->description ?? ''
        );
        $description = preg_replace('/\s+/', ' ', $description) ?? '';
        $description = mb_substr(trim($description), 0, 200);

        $imageUrl = $this->primaryImageUrl($product);

        $meta = [
            'og:type'                => 'product',
            'og:title'               => $product->name . ' — ' . $storeName,
            'og:description'         => $description,
            'og:url'                 => '/products/' . $product->slug,
            'og:site_name'           => $storeName,
            'product:price:amount'   => number_format($product->price, 2, '.', ''),
            'product:price:currency' => strtoupper((string) ($store->defaultCurrency ?? 'EUR')),
            'product:availability'   => $this->ogAvailability($product->stockStatus),
            'twitter:card'           => 'summary_large_image',
            'twitter:title'          => $product->name,
            'twitter:description'    => $description,
        ];

        if ($imageUrl !== null) {
            $meta['og:image']        = $imageUrl;
            $meta['og:image:width']  = '1200';
            $meta['og:image:height'] = '630';
            $meta['og:image:alt']    = $product->name;
            $meta['twitter:image']   = $imageUrl;
        }

        return $meta;
    }

    /** @return array<string,string> */
    public function page(string $title, string $description, Store $store): array
    {
        $storeName = $store->name ?? 'Store';
        $fullTitle = $title !== '' ? $title . ' — ' . $storeName : $storeName;

        // Description fallback chain:
        //   1. Explicit description passed by the caller
        //   2. Store SEO default description
        //   3. Store tagline
        //   4. Generic store description
        $description = trim($description);
        if ($description === '' && $store->seoDefaultDescription !== null) {
            $description = $store->seoDefaultDescription;
        }
        if ($description === '' && $store->tagline !== null) {
            $description = $store->tagline;
        }
        if ($description === '') {
            $description = 'Shop online at ' . $storeName . '.';
        }
        $descText = mb_substr(strip_tags($description), 0, 200);

        $meta = [
            'og:type'             => 'website',
            'og:title'            => $fullTitle,
            'og:description'      => $descText,
            'og:site_name'        => $storeName,
            'twitter:card'        => 'summary_large_image',
            'twitter:title'       => $fullTitle,
            'twitter:description' => $descText,
        ];

        // The share image. Prefer the merchant's uploaded OG
        // image; fall back to the shipped brand card.
        $ogImageUrl = $this->resolveOgImage($store) ?? '/assets/seo/og-image.png';

        $meta['og:image']        = $ogImageUrl;
        $meta['og:image:type']   = 'image/png';
        $meta['og:image:width']  = '1200';
        $meta['og:image:height'] = '630';
        $meta['og:image:alt']    = $storeName;
        $meta['twitter:image']   = $ogImageUrl;

        return $meta;
    }

    /**
     * The merchant's uploaded OG image, or null if none set.
     */
    private function resolveOgImage(Store $store): ?string
    {
        $fileId = $store->seoDefaultOgFileId ?? null;
        if ($fileId === null || $fileId <= 0) {
            return null;
        }

        try {
            $media = $this->media->findByIds([$fileId]);
            if (empty($media)) {
                return null;
            }
            return '/media/' . $media[0]->file->uuid . '/lg.png';
        } catch (\Throwable) {
            return null;
        }
    }

    private function primaryImageUrl(Product $product): ?string
    {
        $rows = $this->images->listAllForProduct($product->id);
        if (empty($rows)) {
            return null;
        }

        $media = $this->media->findByIds([$rows[0]->fileId]);
        if (empty($media)) {
            return null;
        }

        return '/media/' . $media[0]->file->uuid . '/lg.jpg';
    }

    private function ogAvailability(string $stockStatus): string
    {
        return match ($stockStatus) {
            'out_of_stock' => 'out of stock',
            'backorder'    => 'available for order',
            default        => 'in stock',
        };
    }
}
