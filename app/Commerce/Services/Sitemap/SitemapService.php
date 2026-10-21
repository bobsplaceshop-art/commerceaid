<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Sitemap;

use CorporateAid\Commerce\Models\Category;
use CorporateAid\Commerce\Models\Product;
use CorporateAid\Commerce\Models\Store;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Repositories\PageBlockRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;

/**
 * Builds the sitemap.xml for the storefront.
 *
 * A sitemap lists every public URL and its last-modified
 * date. Google Search Console and Bing Webmaster Tools
 * accept the sitemap URL and use it to discover every page.
 *
 * The sitemap includes:
 *   - The storefront home, catalog, category index.
 *   - One entry per active category.
 *   - One entry per published product.
 *   - One entry per custom page (PageBlockRepository::pageSlugs()).
 *
 * It does NOT include:
 *   - Admin routes (/commerce/ca-admin/*).
 *   - Customer routes (/account/*).
 *   - Cart, checkout.
 *   - Offline, feed.
 *
 * Those are excluded by robots.txt and are not crawlable
 * anyway. Listing them here would be noise.
 */
final class SitemapService
{
    public function __construct(
        private ProductRepository $products,
        private CategoryRepository $categories,
        private PageBlockRepository $pages,
    ) {
    }

    public function buildXml(Store $store, string $baseUrl): string
    {
        $urls = [];

        // Static pages
        $urls[] = ['loc' => $baseUrl . '/',          'priority' => '1.0'];
        $urls[] = ['loc' => $baseUrl . '/products',  'priority' => '0.9'];
        $urls[] = ['loc' => $baseUrl . '/categories','priority' => '0.8'];

        // Categories
        $categoryList = $this->categories->listAll(false);
        foreach ($categoryList as $category) {
            $urls[] = [
                'loc'      => $baseUrl . '/categories/' . rawurlencode($category->slug),
                'lastmod'  => $this->isoDate($category->updatedAt ?? null),
                'priority' => '0.7',
            ];
        }

        // Products (paginated loop)
        $page = 1;
        $pages = 1;
        do {
            $result = $this->products->paginatePublished([], $page, 48);
            $pages  = (int) $result['pages'];

            foreach ($result['items'] as $product) {
                $urls[] = [
                    'loc'      => $baseUrl . '/products/' . rawurlencode($product->slug),
                    'lastmod'  => $this->isoDate($product->updatedAt ?? null),
                    'priority' => '0.6',
                ];
            }

            $page++;
        } while ($page <= $pages);

        // Custom pages are not yet reachable on the storefront
        // (the page builder ships in a later stage). Skip them
        // until the /pages/{slug} route exists.
        //
        // foreach ($this->pages->pageSlugs() as $slug) { ... }

        return $this->renderXml($urls);
    }

    // -----------------------------------------------------------------

    /**
     * @param array<int, array{loc:string, lastmod?:?string, priority?:?string}> $urls
     */
    private function renderXml(array $urls): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        foreach ($urls as $entry) {
            $xml .= '  <url>' . PHP_EOL;
            $xml .= '    <loc>' . $this->esc($entry['loc']) . '</loc>' . PHP_EOL;

            if (!empty($entry['lastmod'])) {
                $xml .= '    <lastmod>' . $this->esc($entry['lastmod']) . '</lastmod>' . PHP_EOL;
            }

            if (!empty($entry['priority'])) {
                $xml .= '    <priority>' . $this->esc($entry['priority']) . '</priority>' . PHP_EOL;
            }

            $xml .= '  </url>' . PHP_EOL;
        }

        $xml .= '</urlset>' . PHP_EOL;

        return $xml;
    }

    private function isoDate(?string $datetime): ?string
    {
        if ($datetime === null || $datetime === '') {
            return null;
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d\TH:i:sP', $ts);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
