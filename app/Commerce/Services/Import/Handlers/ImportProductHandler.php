<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Import\Handlers;

use CorporateAid\Commerce\Models\Category;
use CorporateAid\Commerce\Models\Product;
use CorporateAid\Commerce\Repositories\BrandRepository;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Repositories\InventoryRepository;
use CorporateAid\Commerce\Repositories\ProductImageRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Services\Import\ImportHandler;
use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Core\Database;

final class ImportProductHandler implements ImportHandler
{
    public function __construct(
        private Database $db,
        private StoreContext $storeContext,
        private ProductRepository $products,
        private BrandRepository $brands,
        private CategoryRepository $categories,
        private InventoryRepository $inventory,
        private ProductImageRepository $images,
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
                'hint'     => 'Lowercase, letters, digits, and hyphens. Unique per store.',
            ],
            'price' => [
                'label'    => 'Price',
                'required' => true,
                'hint'     => 'The sale price. Decimal, e.g. 49.99.',
            ],
            'cost_price' => [
                'label'    => 'Cost Price',
                'required' => false,
                'hint'     => 'Optional. The cost to the merchant, for margin calculation.',
            ],
            'tax_status' => [
                'label'    => 'Tax Status',
                'required' => false,
                'hint'     => 'Either "taxable" or "none". Defaults to "taxable".',
            ],
            'category_path' => [
                'label'    => 'Category Path',
                'required' => false,
                'hint'     => 'Optional. Path to the category, e.g. "Women/Dresses". The system will auto-create it.',
            ],
            'brand_slug' => [
                'label'    => 'Brand slug',
                'required' => false,
                'hint'     => 'Optional. The slug of an existing brand.',
            ],
            'stock_quantity' => [
                'label'    => 'Stock Quantity',
                'required' => false,
                'hint'     => 'Optional. Opening stock. Defaults to 0 if blank.',
            ],
            'min_order_quantity' => [
                'label'    => 'Min Order Qty',
                'required' => false,
                'hint'     => 'Optional. Defaults to 1.',
            ],
            'product_type' => [
                'label'    => 'Product Type',
                'required' => false,
                'hint'     => 'Either "simple" or "variable". Defaults to "simple".',
            ],
            'image_filename' => [
                'label'    => 'Image Filename',
                'required' => false,
                'hint'     => 'Optional. Matches a file in the Media Library by name.',
            ],
            'low_stock_threshold' => [
                'label'    => 'Low Stock Threshold',
                'required' => false,
                'hint'     => 'Optional. Alert when stock falls below this number.',
            ],
            'weight' => [
                'label'    => 'Weight',
                'required' => false,
                'hint'     => 'Optional. Uses the store\'s default weight unit.',
            ],
            'length' => [
                'label'    => 'Length',
                'required' => false,
                'hint'     => 'Optional.',
            ],
            'width' => [
                'label'    => 'Width',
                'required' => false,
                'hint'     => 'Optional.',
            ],
            'height' => [
                'label'    => 'Height',
                'required' => false,
                'hint'     => 'Optional.',
            ],
        ];
    }

    public function validate(array $row): ?string
    {
        $name  = trim((string) ($row['name'] ?? ''));
        $sku   = trim((string) ($row['sku'] ?? ''));
        $slug  = trim((string) ($row['slug'] ?? ''));
        $price = trim((string) ($row['price'] ?? ''));

        if ($name === '') return 'Name is required.';
        if ($sku === '') return 'SKU is required.';
        if ($slug === '') return 'Slug is required.';
        if (!is_numeric($price) || (float)$price < 0) return 'Price must be a positive number.';

        $stock = trim((string) ($row['stock_quantity'] ?? ''));
        if ($stock !== '' && (!ctype_digit($stock) || (int)$stock < 0)) {
            return 'Stock Quantity must be a non-negative integer.';
        }

        $brandSlug = strtolower(trim((string) ($row['brand_slug'] ?? '')));
        if ($brandSlug !== '' && $this->brands->findBySlug($brandSlug) === null) {
            return "Brand '{$brandSlug}' not found. Import the brand first.";
        }

        // Note: We do not validate category_path here because the importer
        // auto-creates missing categories during create(). Validation only
        // checks that the brand exists (since we don't auto-create brands).

        return null;
    }

    public function naturalKey(array $row): string
    {
        return strtoupper(trim((string) ($row['sku'] ?? '')));
    }

    public function exists(string $naturalKey): bool
    {
        return $this->products->findBySku($naturalKey) !== null;
    }

    public function create(array $row): int
    {
        $product = new Product();
        $product->name             = trim((string) ($row['name'] ?? ''));
        $product->sku              = strtoupper(trim((string) ($row['sku'] ?? '')));
        $product->slug             = strtolower(trim((string) ($row['slug'] ?? '')));
        $product->price            = (float) ($row['price'] ?? 0);
        $product->status           = 'draft';
        $product->manageStock      = true;
        $product->costPrice        = isset($row['cost_price']) && is_numeric($row['cost_price']) ? (float)$row['cost_price'] : null;
        $product->minOrderQuantity = isset($row['min_order_quantity']) && is_numeric($row['min_order_quantity']) ? (int)$row['min_order_quantity'] : 1;
        $product->lowStockThreshold = isset($row['low_stock_threshold']) && is_numeric($row['low_stock_threshold']) ? (int)$row['low_stock_threshold'] : null;
        $product->weight           = isset($row['weight']) && is_numeric($row['weight']) ? (float)$row['weight'] : null;
        $product->length           = isset($row['length']) && is_numeric($row['length']) ? (float)$row['length'] : null;
        $product->width            = isset($row['width']) && is_numeric($row['width']) ? (float)$row['width'] : null;
        $product->height           = isset($row['height']) && is_numeric($row['height']) ? (float)$row['height'] : null;
        
        $brandSlug = trim((string) ($row['brand_slug'] ?? ''));
        $product->brandId = $brandSlug !== '' ? $this->resolveBrandId($brandSlug) : null;

        $taxStatus = strtolower(trim((string) ($row['tax_status'] ?? 'taxable')));
        $product->taxClassId = $taxStatus === 'none' 
            ? $this->resolveTaxClassId('Zero') 
            : $this->resolveTaxClassId('Standard');

        $productType = strtolower(trim((string) ($row['product_type'] ?? 'simple')));
        $product->productType = in_array($productType, ['simple', 'variable'], true) ? $productType : 'simple';

        $id = $this->products->create($product);

        $imageFilename = trim((string) ($row['image_filename'] ?? ''));
        if ($imageFilename !== '') {
            $this->attachImage($id, $imageFilename);
        }

        $categoryPath = trim((string) ($row['category_path'] ?? ''));
        if ($categoryPath !== '') {
            $categoryId = $this->resolveOrCreateCategoryPath($categoryPath);
            if ($categoryId > 0) {
                $this->products->syncCategories($id, [$categoryId]);
            }
        }

        $stock = (int) ($row['stock_quantity'] ?? 0);
        if ($stock > 0) {
            $this->inventory->adjust($id, 0, $stock, 'opening', [
                'reason' => 'Imported via Master File',
            ]);
        }

        return $id;
    }

    public function update(string $naturalKey, array $row): array
    {
        $existing = $this->products->findBySku($naturalKey);
        if ($existing === null) return [];

        $previous = [
            'name'    => $existing->name,
            'price'   => $existing->price,
            'brand_id'=> $existing->brandId,
        ];

        $existing->name = trim((string) ($row['name'] ?? $existing->name));
        if (array_key_exists('price', $row) && is_numeric($row['price'])) {
            $existing->price = (float) $row['price'];
        }
        if (array_key_exists('brand_slug', $row)) {
            $existing->brandId = $this->resolveBrandId((string) $row['brand_slug']);
        }

        $this->products->update($existing);

        $categoryPath = trim((string) ($row['category_path'] ?? ''));
        if ($categoryPath !== '') {
            $categoryId = $this->resolveOrCreateCategoryPath($categoryPath);
            if ($categoryId > 0) {
                $this->products->syncCategories($existing->id, [$categoryId]);
            }
        }

        $newStock = trim((string) ($row['stock_quantity'] ?? ''));
        if ($newStock !== '' && ctype_digit($newStock)) {
            $current = $this->inventory->forUnit($existing->id, 0);
            $currentQty = $current ? $current->quantity : 0;
            $delta = (int)$newStock - $currentQty;
            if ($delta !== 0) {
                $this->inventory->adjust($existing->id, 0, $delta, 'stocktake', [
                    'reason' => 'Imported via Master File update',
                ]);
            }
        }

        return $previous;
    }

    public function delete(int $entityId): void
    {
        $this->products->softDelete($entityId);
    }

    public function restore(int $entityId, array $previousValues): void
    {
        $existing = $this->products->findById($entityId);
        if ($existing === null) return;

        if (array_key_exists('name', $previousValues)) $existing->name = (string) $previousValues['name'];
        if (array_key_exists('price', $previousValues)) $existing->price = (float) $previousValues['price'];
        if (array_key_exists('brand_id', $previousValues)) $existing->brandId = $previousValues['brand_id'] === null ? null : (int) $previousValues['brand_id'];

        $this->products->update($existing);
    }

    public function findIdByNaturalKey(string $naturalKey): ?int
    {
        return $this->products->findBySku($naturalKey)?->id;
    }

    // -----------------------------------------------------------------

    private function resolveBrandId(string $brandSlug): ?int
    {
        $slug = strtolower(trim($brandSlug));
        if ($slug === '') return null;
        return $this->brands->findBySlug($slug)?->id;
    }

    private function resolveTaxClassId(string $className): ?int
    {
        try {
            $id = $this->db->scalar(
                "SELECT `id` FROM `commerce_tax_classes` WHERE `store_id` = ? AND `name` = ? LIMIT 1",
                [$this->storeContext->id(), $className]
            );
            return $id !== false && $id !== null ? (int)$id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function attachImage(int $productId, string $filename): void
    {
        try {
            $fileId = $this->db->scalar(
                "SELECT `id` FROM `core_files` WHERE `store_id` = ? AND `original_name` = ? AND `deleted_at` IS NULL LIMIT 1",
                [$this->storeContext->id(), $filename]
            );

            if ($fileId !== false && $fileId !== null && (int)$fileId > 0) {
                $this->images->add($productId, (int)$fileId, null, null);
            }
        } catch (\Throwable $e) {
            // Fail silently
        }
    }

    private function resolveOrCreateCategoryPath(string $path): int
    {
        $parts = array_filter(array_map('trim', explode('/', $path)));
        $parentId = null;

        foreach ($parts as $part) {
            $slug = strtolower(preg_replace('/[^a-z0-9-]+/i', '-', $part) ?? '');
            $slug = trim($slug, '-');
            if ($slug === '') continue;

            $existing = $this->categories->findBySlug($slug);
            if ($existing !== null) {
                $parentId = $existing->id;
                continue;
            }

            $category = new Category();
            $category->name = $part;
            $category->slug = $slug;
            $category->parentId = $parentId;
            $category->status = 'active';
            
            $parentId = $this->categories->create($category);
        }

        return (int) $parentId;
    }
}
