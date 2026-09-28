<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Commerce\Repositories\BrandRepository;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Repositories\InventoryRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Repositories\ProductVariantRepository;
use CorporateAid\Commerce\Support\StorefrontController;

/**
 * Public product detail page at /products/{slug}.
 *
 * Shows:
 *   - name, brand, category breadcrumb
 *   - price with compare-at if on sale
 *   - stock status
 *   - short + long descriptions
 *   - variant list for variable products (interactive in a later
 *     sub-stage when cart exists)
 *   - up to 4 related products from the same primary category
 *
 * Not published products return 404 (well - redirect to /products).
 * The storefront should never serve a draft or archived product.
 */
final class ProductController extends StorefrontController
{
    private const RELATED_LIMIT = 4;

    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $products = $this->container->get(ProductRepository::class);
        $categories = $this->container->get(CategoryRepository::class);
        $brands = $this->container->get(BrandRepository::class);
        $inventory = $this->container->get(InventoryRepository::class);
        $variants = $this->container->get(ProductVariantRepository::class);

        $slug = (string) ($params['slug'] ?? '');
        if ($slug === '') {
            return Response::redirect('/products');
        }

        $product = $products->findBySlug($slug);

        // Only serve published products to the storefront. Anything
        // else (draft, archived, soft-deleted, missing) redirects to
        // the catalog. A 404 would also be valid; the redirect is
        // friendlier and matches the pattern used elsewhere.
        if ($product === null || !$product->isPublished()) {
            return Response::redirect('/products');
        }

        // ----- Brand -----
        $brand = null;
        if ($product->brandId !== null && $product->brandId > 0) {
            $brand = $brands->findById($product->brandId);
        }

        // ----- Primary category + breadcrumb -----
        $primaryCategory = null;
        $parentCategory = null;
        if ($product->primaryCategoryId !== null) {
            $primaryCategory = $categories->findById($product->primaryCategoryId);
            if ($primaryCategory !== null && $primaryCategory->parentId !== null) {
                $parentCategory = $categories->findById($primaryCategory->parentId);
            }
        }

        // ----- Inventory for simple products -----
        // Variable products resolve stock from their variants; the
        // detail page still shows a summary badge.
        $stockQty = null;
        if ($product->isSimple()) {
            $inv = $inventory->forUnit($product->id, 0);
            if ($inv !== null) {
                $stockQty = $inv->available;
            }
        }

        // ----- Variant list for variable products -----
        $variantList = [];
        if ($product->isVariable()) {
            $variantList = $variants->listForProduct($product->id);
        }

        // ----- Live sold-out state -----
        // The product's cached stock_status can drift from the
        // live inventory. This boolean reflects truth:
        //
        //   simple:  available <= 0
        //   variable: no variant with available > 0
        //   no inventory row: treated as sold out for simple
        //     products, treated as sold out for variable products
        //     with no active variants
        //
        // Products that do not track stock (manage_stock = 0)
        // are never sold out - digital goods have no inventory.
        $isLiveSoldOut = false;

        if ($product->manageStock) {
            $units = $inventory->stockForProduct($product->id);

            if ($units === []) {
                // No inventory rows for a stock-managed product.
                // Data integrity issue post-008D.1; treat as
                // sold out rather than advertising availability.
                $isLiveSoldOut = true;
            } else {
                $anyAvailable = false;
                foreach ($units as $u) {
                    if ((int) ($u['available'] ?? 0) > 0) {
                        $anyAvailable = true;
                        break;
                    }
                }
                $isLiveSoldOut = !$anyAvailable;
            }
        }

        // ----- Related products -----
        $related = [];
        if ($primaryCategory !== null) {
            $relatedResult = $products->paginatePublished(
                array_merge(
                    ['category_id' => $primaryCategory->id, 'sort' => 'newest'],
                    $this->hideOutOfStockFilter()
                ),
                1,
                self::RELATED_LIMIT + 1
            );
            foreach ($relatedResult['items'] as $p) {
                if ($p->id === $product->id) {
                    continue;
                }
                $related[] = $p;
                if (count($related) >= self::RELATED_LIMIT) {
                    break;
                }
            }
        }

        // ----- SEO title fallback -----
        $title = $product->seoTitle ?: $product->name;

        return $this->render('storefront.product.show', [
            'title'           => $title,
            'product'         => $product,
            'brand'           => $brand,
            'primaryCategory' => $primaryCategory,
            'parentCategory'  => $parentCategory,
            'stockQty'        => $stockQty,
            'isLiveSoldOut'   => $isLiveSoldOut,
            'variants'        => $variantList,
            'related'         => $related,
        ]);
    }
}
