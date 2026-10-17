<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Import\Handlers;

use CorporateAid\Commerce\Models\Product;
use CorporateAid\Commerce\Repositories\BrandRepository;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Services\Import\ImportHandler;

/**
 * Import handler for commerce_products. Stage 016.
 *
 * Eight fields:
 *
 *   name             required  - the product name
 *   sku              required  - the natural key
 *   slug             required  - the URL slug, unique per store
 *   price            required  - the sale price
 *   brand_slug       optional  - resolves against commerce_brands
 *   category_slugs   optional  - comma-separated, resolves against commerce_categories
 *   short_description optional
 *   description      optional
 *
 * Natural key: the sku, trimmed and uppercased. SKUs are
 * conventionally uppercase in this codebase. A CSV with
 * "sku-123" and "SKU-123" resolves to the same product.
 *
 * Multi-write: the product row goes to commerce_products
 * via ProductRepository::create/update; the category
 * associations go to commerce_product_categories via
 * syncCategories(). Both calls happen inside the pipeline's
 * chunk transaction. A failure in either rolls back both.
 *
 * Update semantics: name, sku, slug, price, brand,
 * descriptions, and category associations are the fields
 * the CSV touches. A missing brand_slug leaves the brand
 * unchanged. A missing category_slugs leaves the existing
 * category associations alone (the handler does not call
 * syncCategories when the CSV column is absent or empty).
 * Every other product field - tax class, weight,
 * dimensions, SEO, stock status, manage stock, featured,
 * published state - is untouched on update.
 *
 * Products import as drafts. The merchant publishes them
 * after reviewing the batch. The status field is not in
 * the CSV; the handler sets 'draft' on create. On update,
 * the existing status is preserved.
 *
 * Rollback: a create is soft-deleted (the codebase's
 * default for commerce_products; the row survives for
 * audit). An update restores the previous name, sku,
 * slug, price, brand_id, short_description,
 * long_description, and the previous category id set.
 *
 * See docs/stages/016_data-import-plan.md.
 */
final class ImportProductHandler implements ImportHandler
{
    public function __construct(
        private ProductRepository $products,
        private BrandRepository $brands,
        private CategoryRepository $categories,
    ) {
    }

    public function entityKey(): string
    {
        return 'product';
    }

    public function entityLabel(): string
    {
        return 'Product';
    }

    /**
     * @return array<string, array{label: string, required: bool, hint: string}>
     */
    public function fields(): array
    {
        return [
            'name' => [
                'label'    => 'Name',
                'required' => true,
                'hint'     => 'The product name as it appears to customers.',
            ],
            'sku' => [
                'label'    => 'SKU',
                'required' => true,
                'hint'     => 'Unique per store. Used as the natural key for idempotency.',
            ],
            'slug' => [
                'label'    => 'Slug',
                'required' => true,
                'hint'     => 'Lowercase, letters, digits, and hyphens. Unique per store. Used in URLs.',
            ],
            'price' => [
                'label'    => 'Price',
                'required' => true,
                'hint'     => 'The sale price. Decimal, e.g. 49.99.',
            ],
            'brand_slug' => [
                'label'    => 'Brand slug',
                'required' => false,
                'hint'     => 'Optional. The slug of an existing brand.',
            ],
            'category_slugs' => [
                'label'    => 'Category slugs',
                'required' => false,
                'hint'     => 'Optional. Comma-separated slugs of existing categories, e.g. "shirts,casual".',
            ],
            'short_description' => [
                'label'    => 'Short description',
                'required' => false,
                'hint'     => 'Optional. Plain text shown in product cards.',
            ],
            'description' => [
                'label'    => 'Description',
                'required' => false,
                'hint'     => 'Optional. Plain text shown on the product page.',
            ],
        ];
    }

    /**
     * @param array<string, string> $row
     */
    public function validate(array $row): ?string
    {
        $name  = trim((string) ($row['name'] ?? ''));
        $sku   = trim((string) ($row['sku'] ?? ''));
        $slug  = trim((string) ($row['slug'] ?? ''));
        $price = trim((string) ($row['price'] ?? ''));

        if ($name === '') {
            return 'Name is required.';
        }
        if (mb_strlen($name) > 255) {
            return 'Name must be 255 characters or fewer.';
        }
        if ($sku === '') {
            return 'SKU is required.';
        }
        if (mb_strlen($sku) > 80) {
            return 'SKU must be 80 characters or fewer.';
        }
        if ($slug === '') {
            return 'Slug is required.';
        }
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return 'Slug must be lowercase letters, digits, and hyphens, '
                . 'and cannot start or end with a hyphen.';
        }
        if (mb_strlen($slug) > 255) {
            return 'Slug must be 255 characters or fewer.';
        }
        if ($price === '') {
            return 'Price is required.';
        }
        if (!is_numeric($price)) {
            return 'Price must be a number.';
        }
        if ((float) $price < 0) {
            return 'Price must not be negative.';
        }

        $brandSlug = strtolower(trim((string) ($row['brand_slug'] ?? '')));
        if ($brandSlug !== '' && $this->brands->findBySlug($brandSlug) === null) {
            return "Brand '{$brandSlug}' not found. Import the brand first.";
        }

        $categorySlugs = $this->splitSlugs((string) ($row['category_slugs'] ?? ''));
        foreach ($categorySlugs as $catSlug) {
            if ($this->categories->findBySlug($catSlug) === null) {
                return "Category '{$catSlug}' not found. Import the category first.";
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $row
     */
    public function naturalKey(array $row): string
    {
        return strtoupper(trim((string) ($row['sku'] ?? '')));
    }

    public function exists(string $naturalKey): bool
    {
        return $this->products->findBySku($naturalKey) !== null;
    }

    /**
     * @param array<string, string> $row
     */
    public function create(array $row): int
    {
        $product = new Product();
        $product->name             = trim((string) ($row['name'] ?? ''));
        $product->sku              = strtoupper(trim((string) ($row['sku'] ?? '')));
        $product->slug             = strtolower(trim((string) ($row['slug'] ?? '')));
        $product->price            = (float) ($row['price'] ?? 0);
        $product->productType      = 'simple';
        $product->brandId          = $this->resolveBrandId((string) ($row['brand_slug'] ?? ''));
        $product->shortDescription = $this->nullIfBlank($row['short_description'] ?? null);
        $product->description      = $this->nullIfBlank($row['description'] ?? null);
        $product->status           = 'draft';

        $id = $this->products->create($product);

        $categoryIds = $this->resolveCategoryIds((string) ($row['category_slugs'] ?? ''));
        if ($categoryIds !== []) {
            $this->products->syncCategories($id, $categoryIds);
        }

        return $id;
    }

    /**
     * @param array<string, string> $row
     * @return array<string, mixed>
     */
    public function update(string $naturalKey, array $row): array
    {
        $existing = $this->products->findBySku($naturalKey);
        if ($existing === null) {
            return [];
        }

        $previousCategories = $this->products->categoryIdsFor($existing->id);

        $previous = [
            'name'              => $existing->name,
            'sku'               => $existing->sku,
            'slug'              => $existing->slug,
            'price'             => $existing->price,
            'brand_id'          => $existing->brandId,
            'short_description' => $existing->shortDescription,
            'description'       => $existing->description,
            'category_ids'      => $previousCategories,
        ];

        $existing->name  = trim((string) ($row['name'] ?? $existing->name));
        $existing->sku   = strtoupper(trim((string) ($row['sku'] ?? $existing->sku)));
        $existing->slug  = strtolower(trim((string) ($row['slug'] ?? $existing->slug)));
        if (array_key_exists('price', $row) && is_numeric($row['price'])) {
            $existing->price = (float) $row['price'];
        }
        if (array_key_exists('brand_slug', $row)) {
            $existing->brandId = $this->resolveBrandId((string) $row['brand_slug']);
        }
        if (array_key_exists('short_description', $row)) {
            $existing->shortDescription = $this->nullIfBlank($row['short_description']);
        }
        if (array_key_exists('description', $row)) {
            $existing->description = $this->nullIfBlank($row['description']);
        }

        $this->products->update($existing);

        // Only sync categories when the CSV column is
        // present and non-empty. A missing or blank column
        // means "leave the associations alone."
        if (array_key_exists('category_slugs', $row)
            && trim((string) $row['category_slugs']) !== ''
        ) {
            $categoryIds = $this->resolveCategoryIds((string) $row['category_slugs']);
            $this->products->syncCategories($existing->id, $categoryIds);
        }

        return $previous;
    }

    public function delete(int $entityId): void
    {
        $this->products->softDelete($entityId);
    }

    /**
     * @param array<string, mixed> $previousValues
     */
    public function restore(int $entityId, array $previousValues): void
    {
        $existing = $this->products->findById($entityId);
        if ($existing === null) {
            return;
        }

        if (array_key_exists('name', $previousValues)) {
            $existing->name = (string) $previousValues['name'];
        }
        if (array_key_exists('sku', $previousValues)) {
            $existing->sku = $previousValues['sku'] === null
                ? null
                : (string) $previousValues['sku'];
        }
        if (array_key_exists('slug', $previousValues)) {
            $existing->slug = (string) $previousValues['slug'];
        }
        if (array_key_exists('price', $previousValues)) {
            $existing->price = (float) $previousValues['price'];
        }
        if (array_key_exists('brand_id', $previousValues)) {
            $existing->brandId = $previousValues['brand_id'] === null
                ? null
                : (int) $previousValues['brand_id'];
        }
        if (array_key_exists('short_description', $previousValues)) {
            $existing->shortDescription = $previousValues['short_description'] === null
                ? null
                : (string) $previousValues['short_description'];
        }
        if (array_key_exists('description', $previousValues)) {
            $existing->description = $previousValues['description'] === null
                ? null
                : (string) $previousValues['description'];
        }

        $this->products->update($existing);

        if (array_key_exists('category_ids', $previousValues)
            && is_array($previousValues['category_ids'])
        ) {
            $this->products->syncCategories(
                $entityId,
                array_map('intval', $previousValues['category_ids'])
            );
        }
    }

    // -----------------------------------------------------------------

    private function resolveBrandId(string $brandSlug): ?int
    {
        $slug = strtolower(trim($brandSlug));
        if ($slug === '') {
            return null;
        }
        $brand = $this->brands->findBySlug($slug);
        return $brand?->id;
    }

    /**
     * @return int[]
     */
    private function resolveCategoryIds(string $csv): array
    {
        $slugs = $this->splitSlugs($csv);
        $ids = [];
        foreach ($slugs as $slug) {
            $category = $this->categories->findBySlug($slug);
            if ($category !== null) {
                $ids[] = $category->id;
            }
        }
        return $ids;
    }

    /**
     * @return string[]
     */
    private function splitSlugs(string $csv): array
    {
        $parts = explode(',', $csv);
        $out = [];
        foreach ($parts as $p) {
            $trimmed = strtolower(trim($p));
            if ($trimmed !== '') {
                $out[] = $trimmed;
            }
        }
        return array_values(array_unique($out));
    }

    private function nullIfBlank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
