<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Commerce\Repositories\BrandRepository;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Repositories\InventoryRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Services\CustomerAuthService;
use CorporateAid\Commerce\Services\ProductReviewService;
use CorporateAid\Commerce\Repositories\ProductImageRepository;
use CorporateAid\Commerce\Repositories\MediaRepository;
use CorporateAid\Commerce\Repositories\ProductVariantRepository;
use CorporateAid\Commerce\Services\RecentViewService;
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

        // ----- Product gallery -----
        // Read the product's images (all rows, ordered by
        // sort_order) and pair each with its MediaItem. The
        // view renders the first as the hero and every image
        // as a thumbnail. Products with no images pass an
        // empty array; the view falls back to the placeholder.
        $gallery = [];
        $imageRepo = $this->container->get(ProductImageRepository::class);
        $mediaRepo = $this->container->get(MediaRepository::class);

        $rows = $imageRepo->listAllForProduct($product->id);
        if ($rows !== []) {
            $fileIds = [];
            foreach ($rows as $row) {
                $fileIds[] = $row->fileId;
            }
            $mediaById = [];
            foreach ($mediaRepo->findByIds($fileIds) as $item) {
                $mediaById[$item->file->id] = $item;
            }
            foreach ($rows as $row) {
                $media = $mediaById[$row->fileId] ?? null;
                if ($media === null) {
                    continue;
                }
                $gallery[] = [
                    'image' => $row,
                    'media' => $media,
                ];
            }
        }

        // ----- Swatch selector data (variable products) -----
        // The storefront swatch row reads:
        //   - swatchAxes:         variant axes with values + is_color_axis
        //   - combinations:       "value_id:value_id" -> variant_id
        //   - swatchImages:       attribute_value_id -> assigned image
        //   - sellableVariantIds: variants with available > 0
        $swatchAxes         = [];
        $combinations       = [];
        $swatchImages       = [];
        $sellableVariantIds = [];

        if ($product->isVariable()) {
            $swatchAxes   = $variants->swatchAxesFor($product->id);
            $combinations = $variants->combinationsFor($product->id);
            $swatchImages = $this->resolveSwatchImages($product->id);

            // A variant is sellable when its inventory row has
            // available > 0. Products that do not manage stock
            // return every active variant id as sellable.
            if ($product->manageStock) {
                foreach ($inventory->stockForProduct($product->id) as $row) {
                    $vid = (int) ($row['variant_id'] ?? 0);
                    $available = (int) ($row['available'] ?? 0);
                    if ($vid > 0 && $available > 0) {
                        $sellableVariantIds[] = $vid;
                    }
                }
            } else {
                foreach ($variantList as $v) {
                    $sellableVariantIds[] = (int) $v->id;
                }
            }
        }

        // ----- Record the view (best-effort, never throws) -----
        // Side effect of viewing. The service swallows all
        // failures so a broken recorder cannot break the page.
        // See docs/stages/008g.2_recently-viewed-plan.md.
        $this->container->get(RecentViewService::class)->record($product->id);

        // ----- Product reviews -----
        // The Reviews tab renders the aggregate (average,
        // count, distribution), the submission form (or a
        // sign-in CTA), and the list of approved reviews.
        //
        // Data comes from ProductReviewService. The customer's
        // own review (any status) is resolved separately so
        // the form loads in edit mode when one exists.
        $reviewService = $this->container->get(ProductReviewService::class);
        $reviewAggregate = $reviewService->forProduct($product->id, 10, 0);

        $customerId = null;
        try {
            $customerId = $this->container->get(CustomerAuthService::class)->id();
        } catch (\Throwable) {
            $customerId = null;
        }
        $customerReview = $reviewService->customerReview($product->id, $customerId);

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
            'variants'          => $variantList,
            'related'           => $related,
            'gallery'           => $gallery,
            'swatchAxes'        => $swatchAxes,
            'combinations'      => $combinations,
            'swatchImages'      => $swatchImages,
            'sellableVariantIds'=> $sellableVariantIds,
            'reviewAggregate'   => $reviewAggregate,
            'customerReview'    => $customerReview,
            'customerId'        => $customerId,
        ]);
    }

    /**
     * Resolve the product's variant swatch images to
     * MediaItem pairs, keyed by attribute_value_id.
     *
     * Reads ProductImageRepository::swatchPreviewsFor()
     * (returns attribute_value_id => ProductImage), bulk
     * loads the underlying MediaItem for each, and pairs
     * them.
     *
     * @return array<int, array{image: \CorporateAid\Commerce\Models\ProductImage,
     *                          media: \CorporateAid\Commerce\Models\MediaItem}>
     */
    private function resolveSwatchImages(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $imageRepo = $this->container->get(ProductImageRepository::class);
        $mediaRepo = $this->container->get(MediaRepository::class);

        $rows = $imageRepo->swatchPreviewsFor($productId);
        if ($rows === []) {
            return [];
        }

        $fileIds = [];
        foreach ($rows as $row) {
            $fileIds[] = $row->fileId;
        }

        $mediaById = [];
        foreach ($mediaRepo->findByIds($fileIds) as $item) {
            $mediaById[$item->file->id] = $item;
        }

        $out = [];
        foreach ($rows as $attributeValueId => $row) {
            $media = $mediaById[$row->fileId] ?? null;
            if ($media === null) {
                continue;
            }
            $out[(int) $attributeValueId] = [
                'image' => $row,
                'media' => $media,
            ];
        }
        return $out;
    }
}
