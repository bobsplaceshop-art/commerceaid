<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Feed;

use CorporateAid\Commerce\Models\Category;
use CorporateAid\Commerce\Models\Product;
use CorporateAid\Commerce\Models\ProductVariant;
use CorporateAid\Commerce\Models\Store;
use CorporateAid\Commerce\Repositories\BrandRepository;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Repositories\MediaRepository;
use CorporateAid\Commerce\Repositories\ProductImageRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Repositories\ProductVariantRepository;
use CorporateAid\Commerce\Repositories\StoreRepository;

/**
 * Builds a Google Merchant Center XML feed for the store.
 *
 * Spec: https://support.google.com/merchants/answer/7052112
 *
 * One <item> per product (simple) or per variant (variable).
 * Variable products use <g:item_group_id> to group variants
 * under one parent entry and carry <g:size> / <g:color> so
 * Google renders "3 sizes available" in Shopping results.
 *
 * Every URL in the feed is absolute. Images use the "md"
 * variant (800x1000), which clears Google's 100x100 floor
 * with margin.
 *
 * Products without a GTIN emit <g:identifier_exists>no</g:identifier_exists>.
 * Fashion products rarely have GTINs; this tells Google not
 * to warn about the missing field.
 */
final class MerchantFeedService
{
    /** @var array<int, Category> */
    private array $categoryCache = [];
    /** @var array<int, string> */
    private array $brandCache = [];

    public function __construct(
        private ProductRepository $products,
        private ProductVariantRepository $variants,
        private ProductImageRepository $images,
        private MediaRepository $media,
        private CategoryRepository $categories,
        private BrandRepository $brands,
        private StoreRepository $stores,
    ) {
    }

    public function buildXml(Store $store, string $baseUrl): string
    {
        $storeId   = (int) $store->id;
        $storeName = $store->name !== '' ? $store->name : 'Store';
        $currency  = $this->stores->currencyCodeForStore($storeId);

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . PHP_EOL;
        $xml .= '  <channel>' . PHP_EOL;
        $xml .= '    <title>' . $this->esc($storeName) . '</title>' . PHP_EOL;
        $xml .= '    <link>' . $this->esc($baseUrl) . '</link>' . PHP_EOL;
        $xml .= '    <description>' . $this->esc($storeName . ' product feed') . '</description>' . PHP_EOL;

        $page = 1;
        $pages = 1;
        do {
            $result = $this->products->paginatePublished([], $page, 48);
            $pages  = (int) $result['pages'];

            foreach ($result['items'] as $product) {
                $xml .= $this->buildProductItems($product, $baseUrl, $currency);
            }

            $page++;
        } while ($page <= $pages);

        $xml .= '  </channel>' . PHP_EOL;
        $xml .= '</rss>' . PHP_EOL;

        return $xml;
    }

    // -----------------------------------------------------------------

    private function buildProductItems(Product $product, string $baseUrl, string $currency): string
    {
        $context = [
            'category_path' => $this->categoryPath($product->primaryCategoryId),
            'brand_name'    => $this->brandName($product->brandId),
            'product_url'   => $baseUrl . '/products/' . rawurlencode($product->slug),
            'image_url'     => $this->primaryImageUrl($product, $baseUrl),
            'description'   => $this->cleanDescription(
                $product->description ?? $product->shortDescription ?? $product->name
            ),
            'currency'      => $currency,
        ];

        if ($product->productType === 'variable') {
            $variants = $this->variants->listForProduct((int) $product->id);
            $xml = '';
            foreach ($variants as $variant) {
                $xml .= $this->buildItem($product, $variant, $context, (string) $product->id);
            }
            return $xml;
        }

        return $this->buildItem($product, null, $context, null);
    }

    /**
     * @param array{category_path:string, brand_name:string, product_url:string,
     *              image_url:string, description:string, currency:string} $context
     */
    private function buildItem(
        Product $product,
        ?ProductVariant $variant,
        array $context,
        ?string $itemGroupId
    ): string {
        $id = $variant !== null
            ? ($variant->sku ?: 'VAR-' . $variant->id)
            : ($product->sku ?: 'PROD-' . $product->id);

        $title = $this->buildTitle($product, $variant);

        $effectivePrice = $product->price;
        $compareAt      = $product->compareAtPrice;
        if ($variant !== null) {
            if ($variant->price > 0) {
                $effectivePrice = $variant->price;
            }
            if ($variant->compareAtPrice !== null && $variant->compareAtPrice > 0) {
                $compareAt = $variant->compareAtPrice;
            }
        }

        $stockStatus = $variant !== null ? $variant->stockStatus : $product->stockStatus;
        $availability = $this->availabilityLabel($stockStatus);

        $xml  = '    <item>' . PHP_EOL;
        $xml .= '      <g:id>' . $this->esc($id) . '</g:id>' . PHP_EOL;
        $xml .= '      <g:title>' . $this->esc($title) . '</g:title>' . PHP_EOL;
        $xml .= '      <g:description>' . $this->esc($context['description']) . '</g:description>' . PHP_EOL;
        $xml .= '      <g:link>' . $this->esc($context['product_url']) . '</g:link>' . PHP_EOL;

        if ($context['image_url'] !== '') {
            $xml .= '      <g:image_link>' . $this->esc($context['image_url']) . '</g:image_link>' . PHP_EOL;
        }

        $xml .= '      <g:availability>' . $this->esc($availability) . '</g:availability>' . PHP_EOL;
        $xml .= $this->priceLines($effectivePrice, $compareAt, $context['currency']);

        $xml .= '      <g:condition>new</g:condition>' . PHP_EOL;

        if ($context['brand_name'] !== '') {
            $xml .= '      <g:brand>' . $this->esc($context['brand_name']) . '</g:brand>' . PHP_EOL;
        }

        if ($context['category_path'] !== '') {
            $xml .= '      <g:product_type>' . $this->esc($context['category_path']) . '</g:product_type>' . PHP_EOL;
        }

        if ($itemGroupId !== null) {
            $xml .= '      <g:item_group_id>' . $this->esc($itemGroupId) . '</g:item_group_id>' . PHP_EOL;
        }

        if ($variant !== null) {
            $size  = $variant->attributeValues['size']  ?? null;
            $color = $variant->attributeValues['color'] ?? null;
            if ($size !== null && $size !== '') {
                $xml .= '      <g:size>' . $this->esc((string) $size) . '</g:size>' . PHP_EOL;
            }
            if ($color !== null && $color !== '') {
                $xml .= '      <g:color>' . $this->esc((string) $color) . '</g:color>' . PHP_EOL;
            }
        }

        $xml .= '      <g:identifier_exists>no</g:identifier_exists>' . PHP_EOL;
        $xml .= '    </item>' . PHP_EOL;

        return $xml;
    }

    /**
     * Price lines. If the compare-at price is strictly higher
     * than the effective price, Google's spec is:
     *
     *   <g:price>       regular price
     *   <g:sale_price>  discounted price
     *
     * If there is no sale, only <g:price> is emitted.
     *
     * Both prices carry the currency code. Google expects
     * "<number> <ISO code>" with a single space.
     */
    private function priceLines(float $price, ?float $compareAt, string $currency): string
    {
        $hasSale = $compareAt !== null && $compareAt > $price;

        $regularStr = number_format($hasSale ? (float) $compareAt : $price, 2, '.', '') . ' ' . $currency;

        $xml = '      <g:price>' . $this->esc($regularStr) . '</g:price>' . PHP_EOL;

        if ($hasSale) {
            $saleStr = number_format($price, 2, '.', '') . ' ' . $currency;
            $xml .= '      <g:sale_price>' . $this->esc($saleStr) . '</g:sale_price>' . PHP_EOL;
        }

        return $xml;
    }

    private function buildTitle(Product $product, ?ProductVariant $variant): string
    {
        // Reserve space for the variant suffix. Truncating the
        // full title (name + suffix) would drop the suffix for
        // long product names, leaving every variant with the
        // same truncated title. Google uses the title to
        // distinguish variants; the suffix must survive.
        $suffix = '';
        if ($variant !== null) {
            $size  = $variant->attributeValues['size']  ?? null;
            $color = $variant->attributeValues['color'] ?? null;
            $parts = array_filter(
                [$color, $size],
                static fn ($v) => $v !== null && $v !== ''
            );
            if ($parts !== []) {
                $suffix = ' — ' . implode(' / ', $parts);
            }
        }

        $suffixLen = mb_strlen($suffix);
        $nameLimit = 150 - $suffixLen;

        if (mb_strlen($product->name) <= $nameLimit) {
            return $product->name . $suffix;
        }

        $truncated = mb_substr($product->name, 0, $nameLimit - 1) . '…';
        return $truncated . $suffix;
    }

    // -----------------------------------------------------------------

    private function categoryPath(?int $categoryId): string
    {
        if ($categoryId === null || $categoryId <= 0) {
            return '';
        }

        $parts = [];
        $currentId = $categoryId;
        $guard = 0;
        while ($currentId !== null && $currentId > 0 && $guard < 20) {
            $category = $this->categoryById($currentId);
            if ($category === null) {
                break;
            }
            array_unshift($parts, $category->name);
            $currentId = $category->parentId;
            $guard++;
        }
        return implode(' > ', $parts);
    }

    private function categoryById(int $id): ?Category
    {
        if (isset($this->categoryCache[$id])) {
            return $this->categoryCache[$id];
        }
        $category = $this->categories->findById($id);
        if ($category !== null) {
            $this->categoryCache[$id] = $category;
        }
        return $category;
    }

    private function brandName(?int $brandId): string
    {
        if ($brandId === null || $brandId <= 0) {
            return '';
        }
        if (isset($this->brandCache[$brandId])) {
            return $this->brandCache[$brandId];
        }
        $brand = $this->brands->findById($brandId);
        $name = $brand !== null ? (string) $brand->name : '';
        $this->brandCache[$brandId] = $name;
        return $name;
    }

    private function primaryImageUrl(Product $product, string $baseUrl): string
    {
        $rows = $this->images->listAllForProduct((int) $product->id);
        if ($rows === []) {
            return '';
        }
        $media = $this->media->findByIds([$rows[0]->fileId]);
        if ($media === []) {
            return '';
        }
        // MediaItem::variantUrl() produces the root-relative
        // path. Prefix with $baseUrl for the merchant feed,
        // which requires absolute image URLs.
        return $baseUrl . $media[0]->variantUrl('md', 'jpg');
    }

    private function availabilityLabel(string $stockStatus): string
    {
        return match ($stockStatus) {
            'out_of_stock' => 'out of stock',
            'backorder'    => 'preorder',
            default        => 'in stock',
        };
    }

    private function cleanDescription(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        return $this->truncate(trim($text), 5000);
    }

    private function truncate(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        return mb_substr($text, 0, $limit - 1) . '…';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
