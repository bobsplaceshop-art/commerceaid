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
        private \CorporateAid\Commerce\Repositories\StoreRepository $stores,
    ) {
    }

    /** @return array<string,string> */
    public function product(Product $product, Store $store): array
    {
        $storeName = $store->name !== '' ? $store->name : 'Store';

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
            'product:price:currency' => strtoupper($this->stores->currencyCodeForStore((int) $store->id)),
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
        $storeName = $store->name !== '' ? $store->name : 'Store';

        // Title fallback chain:
        //   1. Explicit page title (e.g. a category name)
        //   2. Store SEO default title
        //   3. Store name
        $effectiveTitle = trim($title);
        if ($effectiveTitle === '' && $store->seoDefaultTitle !== null) {
            $effectiveTitle = trim((string) $store->seoDefaultTitle);
        }
        if ($effectiveTitle === '') {
            $effectiveTitle = $storeName;
        }

        // Description fallback chain:
        //   1. Explicit description passed by the caller
        //   2. Store SEO default description
        //   3. Store tagline
        //   4. Generic store description
        $description = trim($description);
        if ($description === '' && $store->seoDefaultDescription !== null) {
            $description = trim((string) $store->seoDefaultDescription);
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
            'og:title'            => $effectiveTitle,
            'og:description'      => $descText,
            'og:site_name'        => $storeName,
            'twitter:card'        => 'summary_large_image',
            'twitter:title'       => $effectiveTitle,
            'twitter:description' => $descText,
        ];

        // The share image. Prefer the merchant's uploaded OG
        // image; fall back to the shipped brand card. Both
        // are converted to absolute URLs because the OG spec
        // requires them.
        $ogImageUrl = $this->resolveOgImage($store)
            ?? $this->absoluteUrl('/assets/seo/og-image.png');

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
            return $this->absoluteUrl($media[0]->variantUrl('lg', 'png'));
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

        return $media[0]->variantUrl('lg', 'jpg');
    }

    private function ogAvailability(string $stockStatus): string
    {
        return match ($stockStatus) {
            'out_of_stock' => 'out of stock',
            'backorder'    => 'available for order',
            default        => 'in stock',
        };
    }

    /**
     * Convert a root-relative path to an absolute URL.
     *
     * OG and Twitter Cards require absolute URLs. Relative
     * paths are silently ignored by every scraper.
     */
    /**
     * Convert a root-relative path to an absolute URL.
     *
     * The scheme is determined by the request, not by a
     * hardcoded assumption. In production the storefront
     * sits behind a reverse proxy (Cloudflare Tunnel) that
     * terminates TLS and forwards to Apache over plain HTTP.
     * Reading \$_SERVER['HTTPS'] alone would return 'http'
     * on the tunnel even though the customer used HTTPS.
     *
     * The fix is to prefer X-Forwarded-Proto, which every
     * standard reverse proxy sets. If that header is absent
     * (direct Apache, no proxy), fall back to Apache's own
     * HTTPS flag. If neither is present, the request is on
     * plain HTTP and 'http' is correct.
     */
    private function absoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($proto !== 'http' && $proto !== 'https') {
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        }

        return $proto . '://' . $host . '/' . ltrim($path, '/');
    }

}
