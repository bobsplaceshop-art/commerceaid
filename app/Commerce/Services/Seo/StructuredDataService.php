<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Seo;

use CorporateAid\Commerce\Models\Product;
use CorporateAid\Commerce\Models\Store;
use CorporateAid\Commerce\Repositories\BrandRepository;
use CorporateAid\Commerce\Repositories\ProductImageRepository;
use CorporateAid\Commerce\Repositories\ProductReviewRepository;
use CorporateAid\Commerce\Repositories\ProductVariantRepository;
use CorporateAid\Commerce\Repositories\MediaRepository;

/**
 * Generates JSON-LD structured data for the storefront.
 *
 * The Product schema is the highest-impact SEO signal for
 * a fashion store. Google uses it for:
 *   - Rich Results (price, availability, rating in search)
 *   - Google Shopping carousels
 *   - AI overviews and visual search
 *
 * For variable products, each variant becomes an Offer
 * inside an AggregateOffer. Google uses size and color
 * from attributeMapFor() to surface "3 sizes available".
 *
 * See docs/stages/024_seo-de-eu-plan.md.
 */
final class StructuredDataService
{
    public function __construct(
        private BrandRepository $brands,
        private ProductImageRepository $images,
        private ProductVariantRepository $variants,
        private ProductReviewRepository $reviews,
        private MediaRepository $media,
        private \CorporateAid\Commerce\Repositories\StoreRepository $stores,
    ) {
    }

    /**
     * The full Product schema for one product.
     *
     * @return array<string,mixed>
     */
    public function product(Product $product, Store $store): array
    {
        $currency = $this->currencyCode($store);

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $product->name,
            'sku'         => $product->sku ?? '',
            'description' => $this->cleanText(
                $product->description ?? $product->shortDescription ?? ''
            ),
            'url'         => '/products/' . $product->slug,
        ];

        if ($product->brandId !== null && $product->brandId > 0) {
            $brand = $this->brands->findById($product->brandId);
            if ($brand !== null) {
                $schema['brand'] = [
                    '@type' => 'Brand',
                    'name'  => $brand->name,
                ];
            }
        }

        $imageUrls = $this->imageUrls($product);
        if (!empty($imageUrls)) {
            $schema['image'] = $imageUrls;
        }

        $avg = $this->reviews->averageForProduct($product->id);
        $count = $this->reviews->countApprovedForProduct($product->id);
        if ($avg !== null && $count > 0) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => (string) round($avg, 1),
                'reviewCount' => (string) $count,
                'bestRating'  => '5',
                'worstRating' => '1',
            ];
        }

        if ($product->productType === 'simple') {
            $schema['offers'] = $this->simpleOffer($product, $currency);
        } else {
            $schema['offers'] = $this->variantOffers($product, $currency);
        }

        return $schema;
    }

    /** @return array<string,mixed> */
    private function simpleOffer(Product $product, string $currency): array
    {
        return [
            '@type'         => 'Offer',
            'url'           => '/products/' . $product->slug,
            'priceCurrency' => $currency,
            'price'         => number_format($product->price, 2, '.', ''),
            'availability'  => $this->schemaAvailability($product->stockStatus),
            'itemCondition' => 'https://schema.org/NewCondition',
        ];
    }

    /** @return array<string,mixed> */
    private function variantOffers(Product $product, string $currency): array
    {
        $variantList = $this->variants->listForProduct($product->id);

        $offers = [];
        $prices = [];
        foreach ($variantList as $variant) {
            $price = $variant->price > 0 ? $variant->price : $product->price;
            $prices[] = $price;

            $attrMap = $this->variants->attributeMapFor($variant->id);
            $size    = $attrMap['size'] ?? null;
            $color   = $attrMap['color'] ?? null;

            $offer = [
                '@type'         => 'Offer',
                'sku'           => $variant->sku ?? $product->sku ?? '',
                'priceCurrency' => $currency,
                'price'         => number_format($price, 2, '.', ''),
                'availability'  => $this->schemaAvailability($variant->stockStatus),
                'itemCondition' => 'https://schema.org/NewCondition',
            ];

            if ($size !== null || $color !== null) {
                $offer['itemOffered'] = [
                    '@type' => 'Product',
                    'name'  => $product->name
                        . ($size !== null ? ' — ' . $size : '')
                        . ($color !== null ? ' / ' . $color : ''),
                ];
            }

            $offers[] = $offer;
        }

        $fallback = number_format($product->price, 2, '.', '');
        return [
            '@type'         => 'AggregateOffer',
            'priceCurrency' => $currency,
            'lowPrice'      => $prices !== [] ? number_format(min($prices), 2, '.', '') : $fallback,
            'highPrice'     => $prices !== [] ? number_format(max($prices), 2, '.', '') : $fallback,
            'offerCount'    => count($offers),
            'offers'        => $offers,
        ];
    }

    /** @return array<int, string> */
    private function imageUrls(Product $product): array
    {
        $rows = $this->images->listAllForProduct($product->id);
        if (empty($rows)) {
            return [];
        }

        $fileIds = array_map(fn($r) => $r->fileId, $rows);
        $media = $this->media->findByIds($fileIds);
        $byFile = [];
        foreach ($media as $item) {
            $byFile[$item->file->id] = $item;
        }

        $urls = [];
        foreach ($rows as $row) {
            $item = $byFile[$row->fileId] ?? null;
            if ($item !== null) {
                $urls[] = $item->variantUrl('lg', 'jpg');
            }
        }
        return $urls;
    }

    private function schemaAvailability(string $stockStatus): string
    {
        return match ($stockStatus) {
            'out_of_stock' => 'https://schema.org/OutOfStock',
            'backorder'    => 'https://schema.org/BackOrder',
            default        => 'https://schema.org/InStock',
        };
    }

    private function currencyCode(Store $store): string
    {
        try {
            return strtoupper($this->stores->currencyCodeForStore((int) $store->id));
        } catch (\Throwable) {
            return 'EUR';
        }
    }

    private function cleanText(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        return trim($text);
    }
}
