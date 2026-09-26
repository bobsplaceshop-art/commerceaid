<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Repositories\BrandRepository;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Repositories\InventoryRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;

/**
 * Catalog service.
 *
 * Orchestrates the catalog repositories for cross-entity operations.
 * Controllers use this when a single request needs data or mutations
 * from more than one repository.
 *
 * Single-repository reads may still go directly to the repository
 * (e.g. ProductRepository::findById in a show controller). This
 * service exists for the multi-entity cases.
 *
 * As the module grows, add methods here for:
 *   - creating products with categories, tags, attributes
 *   - duplicating a product (with all relations)
 *   - bulk operations (bulk publish, bulk archive)
 *   - computing inventory summaries across products
 */
final class CatalogService
{
    public function __construct(
        private ProductRepository   $products,
        private CategoryRepository  $categories,
        private BrandRepository     $brands,
        private InventoryRepository $inventory
    ) {
    }

    // ---------- Dashboard summaries ----------

    /**
     * Aggregated counts for the dashboard tiles.
     * @return array<string,int>
     */
    public function dashboardCounts(): array
    {
        return [
            'products'         => $this->products->count(),
            'products_published'=> $this->products->countPublished(),
            'products_draft'   => $this->products->countDraft(),
            'categories'       => $this->categories->count(),
            'brands'           => $this->brands->count(),
            'low_stock'        => $this->inventory->countLowStock(),
            'out_of_stock'     => $this->inventory->countOutOfStock(),
        ];
    }

    // ---------- Cross-entity reads ----------

    /**
     * Get a product with all related data for the edit page.
     *
     * Loads the product, its categories (full models), tags (ids only),
     * and for variable products, its variants and their inventory.
     *
     * @return array<string,mixed>|null
     */
    public function productForEdit(int $productId): ?array
    {
        $product = $this->products->findById($productId);
        if ($product === null) {
            return null;
        }

        return [
            'product'    => $product,
            'categoryIds'=> $this->products->categoryIdsFor($product->id),
            'tagIds'     => $this->products->tagIdsFor($product->id),
            'inventory'  => $this->inventory->listForProduct($product->id),
        ];
    }

    /**
     * Active categories as a flat list, for select dropdowns.
     * @return array<int, array{id:int, name:string}>
     */
    public function categoriesForSelect(): array
    {
        $cats = $this->categories->listAll();
        $out = [];
        foreach ($cats as $c) {
            $out[] = ['id' => $c->id, 'name' => $c->name];
        }
        return $out;
    }

    /**
     * Active brands as a flat list, for select dropdowns.
     * @return array<int, array{id:int, name:string}>
     */
    public function brandsForSelect(): array
    {
        return $this->brands->listForSelect();
    }

    // ---------- Direct access ----------
    //
    // Controllers that need just one repository can access it via
    // these getters rather than injecting four separate repositories.
    // Keeps controller constructors simple.

    public function products(): ProductRepository
    {
        return $this->products;
    }

    public function categories(): CategoryRepository
    {
        return $this->categories;
    }

    public function brands(): BrandRepository
    {
        return $this->brands;
    }

    public function inventory(): InventoryRepository
    {
        return $this->inventory;
    }
}
