<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Database;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Models\Product;
use CorporateAid\Commerce\Repositories\BrandRepository;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Repositories\InventoryRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Repositories\ProductImageRepository;
use CorporateAid\Commerce\Repositories\MediaRepository;
use CorporateAid\Commerce\Repositories\ProductVariantRepository;
use CorporateAid\Commerce\Repositories\AttributeRepository;
use CorporateAid\Commerce\Services\CatalogService;
use CorporateAid\Commerce\Support\Validator;

/**
 * Product admin CRUD.
 *
 * Routes (all under /commerce/ca-admin/products):
 *   GET    /                    list (filters, search, pagination)
 *   GET    /create              create form
 *   POST   /                    store
 *   GET    /{id}/edit           edit form
 *   POST   /{id}                update
 *   POST   /{id}/publish        set published
 *   POST   /{id}/unpublish      revert to draft
 *   POST   /{id}/archive        archive
 *   POST   /{id}/restore        restore from archive
 *   POST   /{id}/delete         soft-delete
 *   GET    /{id}/delete-info    JSON for confirm modal
 *
 * Sub-stage 006E handles simple products only. Variable products
 * (with variants) arrive in 006F.
 */
final class ProductController extends AdminController
{
    private const PER_PAGE = 25;

    // =================================================================
    // Reads
    // =================================================================

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $repo    = $this->container->get(ProductRepository::class);
        $catalog = $this->container->get(CatalogService::class);

        $filters = [
            'q'           => trim((string) $request->query('q', '')),
            'status'      => (string) $request->query('status', ''),
            'brand_id'    => (int) $request->query('brand_id', 0),
            'category_id' => (int) $request->query('category_id', 0),
            'featured'    => (bool) $request->query('featured', false),
            'type'        => (string) $request->query('type', ''),
        ];

        // Remove empty filters so the repository doesn't over-constrain
        $filters = array_filter($filters, fn ($v) => $v !== '' && $v !== 0 && $v !== false);

        $page = max(1, (int) $request->query('page', 1));
        $result = $repo->paginate($filters, $page, self::PER_PAGE);

        return $this->render('admin.products.index', [
            'title'         => 'Products',
            'result'        => $result,
            'filters'       => $filters,
            'categories'    => $catalog->categoriesForSelect(),
            'brands'        => $catalog->brandsForSelect(),
        ]);
    }

    /** @param array<string,string> $params */
    public function showCreate(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $catalog = $this->container->get(CatalogService::class);

        return $this->render('admin.products.edit', [
            'title'         => 'New product',
            'product'       => null,
            'errors'        => $session->getFlash('product_errors', []),
            'old'           => $_SESSION['_old'] ?? [],
            'mode'          => 'create',
            'categories'    => $catalog->categoriesForSelect(),
            'brands'        => $catalog->brandsForSelect(),
            'categoryIds'   => [],
            'tagIds'        => [],
            'inventory'     => null,
            'variants'      => [],
            'attributes'    => [],
            'productImages'  => [],
            'mediaLibrary'   => $this->resolveMediaLibrary(),
            'variantAxes'    => [],
            'variantImages'  => [],
            'activeTab'      => (string) $request->query('tab', 'basic'),
        ]);
    }

    /** @param array<string,string> $params */
    public function showEdit(Request $request, array $params = []): Response
    {
        $repo      = $this->container->get(ProductRepository::class);
        $inventory = $this->container->get(InventoryRepository::class);
        $session   = $this->container->get(Session::class);
        $catalog   = $this->container->get(CatalogService::class);
        $id        = (int) ($params['id'] ?? 0);

        $product = $repo->findById($id);
        if ($product === null) {
            return Response::redirect('/commerce/ca-admin/products');
        }

        $productInventory = $inventory->forUnit($product->id, 0);

        // Resolve the product's images to MediaItem instances
        // for the Images tab and for the picker preview.
        $productImages = $this->resolveProductImages($product->id);

        // The store's media library, for the picker modal.
        // Capped at 200 items - the picker paginates if it
        // ever needs more, but 200 covers every realistic
        // merchant's library on day one.
        $mediaItems = $this->resolveMediaLibrary();

        return $this->render('admin.products.edit', [
            'title'         => 'Edit: ' . $product->name,
            'product'       => $product,
            'errors'        => $session->getFlash('product_errors', []),
            'old'           => $_SESSION['_old'] ?? [],
            'mode'          => 'edit',
            'categories'    => $catalog->categoriesForSelect(),
            'brands'        => $catalog->brandsForSelect(),
            'categoryIds'   => $repo->categoryIdsFor($product->id),
            'tagIds'        => $repo->tagIdsFor($product->id),
            'inventory'     => $productInventory,
            'productImages'  => $productImages,
            'mediaLibrary'   => $mediaItems,
            // swatchAxesFor() produces the shape the Images
            // tab's variant-images card needs (each axis carries
            // is_color_axis). categoryVariantAxes() produces a
            // different shape for the variations page and the
            // regeneration flow — those are unaffected.
            'variantAxes'    => $this->container
                ->get(ProductVariantRepository::class)
                ->swatchAxesFor($product->id),
            'variantImages'  => $this->resolveVariantImages($product->id),
            'activeTab'      => (string) $request->query('tab', 'basic'),
        ]);
    }

    // =================================================================
    // Writes
    // =================================================================

    /** @param array<string,string> $params */
    public function store(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(ProductRepository::class);

        $data   = $this->collectInput($request);
        $errors = $this->validate($data, null, $repo);

        if ($errors !== []) {
            $session->flash('product_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/products/create');
        }

        $product = $this->hydrate(new Product(), $data);

        $auth = $this->container->get(Auth::class);
        $product->createdBy = $auth->user()?->id;
        $product->updatedBy = $auth->user()?->id;

        $productId = $repo->create($product);

        // Junction writes
        $repo->syncCategories($productId, $data['category_ids']);
        $repo->syncTags($productId, $data['tag_ids']);

        // Create the initial inventory row for simple products.
        //
        // Variable products get NO parent inventory row. A
        // variable product is a marketing container, not a
        // sellable unit. Each variant gets its own row, created
        // when the variant is born (see
        // ProductVariantRepository::create()). The 008D.1
        // migration enforces this invariant in existing data.
        $isSimpleProduct = ($data['product_type'] === 'simple');
        if ($data['manage_stock'] && $isSimpleProduct) {
            $this->container->get(InventoryRepository::class)->adjust(
                $productId,
                0, // variant_id = 0 for simple products only
                (int) $data['stock_quantity'],
                'opening',
                [
                    'reason'     => 'Initial stock on product creation',
                    'created_by' => $auth->user()?->id,
                ]
            );
        }

        // Product images. Reads the images[] / new_images[] /
        // delete_images[] POST payload written by the Images tab.
        // On create there are no existing rows to update, so the
        // images[] array is expected to be empty; new_images[]
        // carries the file IDs. Failures are swallowed inside the
        // method so a broken image save never rolls back the
        // product row, categories, tags, or inventory.
        $this->syncImages($productId);

        $session->flash('success', 'Product created.');
        $session->clearOldInput();
        return Response::redirect('/commerce/ca-admin/products');
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(ProductRepository::class);
        $id      = (int) ($params['id'] ?? 0);

        $product = $repo->findById($id);
        if ($product === null) {
            return Response::redirect('/commerce/ca-admin/products');
        }

        $data   = $this->collectInput($request);
        $errors = $this->validate($data, $id, $repo);

        if ($errors !== []) {
            $session->flash('product_errors', $errors);
            $session->flashOldInput($data);

            $tab = (string) $request->input('current_tab', 'basic');
            $allowedTabs = ['basic','pricing','inventory','shipping','variants','seo','images'];
            if (!in_array($tab, $allowedTabs, true)) {
                $tab = 'basic';
            }

            return Response::redirect('/commerce/ca-admin/products/' . $id . '/edit?tab=' . $tab);
        }

        $auth = $this->container->get(Auth::class);
        $product = $this->hydrate($product, $data);
        $product->updatedBy = $auth->user()?->id;

        $repo->update($product);
        $repo->syncCategories($id, $data['category_ids']);
        $repo->syncTags($id, $data['tag_ids']);

        // Inventory adjustment when manage_stock is on and quantity changed
        if ($data['manage_stock']) {
            $inventory = $this->container->get(InventoryRepository::class);
            $current = $inventory->forUnit($id, 0);
            $currentQty = $current?->quantity ?? 0;
            $newQty = (int) $data['stock_quantity'];

            // Variable products have no variant_id = 0 row. Their
            // stock is managed per variant through the variations
            // page. Skip the parent-row adjustment for them.
            $isSimpleProduct = ($product->productType === 'simple');
            if ($isSimpleProduct && $newQty !== $currentQty) {
                $inventory->adjust(
                    $id,
                    0,
                    $newQty - $currentQty,
                    'adjustment',
                    [
                        'reason'     => 'Manual adjustment from product editor',
                        'created_by' => $auth->user()?->id,
                    ]
                );
            }
        }

        // Product images. Reads the images[] / new_images[] /
        // delete_images[] POST payload written by the Images tab.
        $this->syncImages($id);

        $session->flash('success', 'Product updated.');
        $session->clearOldInput();

        // Preserve the current tab across save
        $tab = (string) $request->input('current_tab', 'basic');
        $allowedTabs = ['basic','pricing','inventory','shipping','variants','seo','images'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'basic';
        }

        return Response::redirect('/commerce/ca-admin/products/' . $id . '/edit?tab=' . $tab);
    }

    /** @param array<string,string> $params */
    public function publish(Request $request, array $params = []): Response
    {
        $id   = (int) $params['id'];
        $auth = $this->container->get(Auth::class);
        $this->container->get(ProductRepository::class)
            ->publish($id, $auth->user()?->id);

        $this->container->get(Session::class)->flash('success', 'Product published.');
        return Response::redirect('/commerce/ca-admin/products/' . $id . '/edit');
    }

    /** @param array<string,string> $params */
    public function unpublish(Request $request, array $params = []): Response
    {
        $id   = (int) $params['id'];
        $auth = $this->container->get(Auth::class);
        $this->container->get(ProductRepository::class)
            ->unpublish($id, $auth->user()?->id);

        $this->container->get(Session::class)->flash('success', 'Product reverted to draft.');
        return Response::redirect('/commerce/ca-admin/products/' . $id . '/edit');
    }

    /** @param array<string,string> $params */
    public function archive(Request $request, array $params = []): Response
    {
        $this->container->get(ProductRepository::class)->archive((int) $params['id']);
        $this->container->get(Session::class)->flash('success', 'Product archived.');
        return Response::redirect('/commerce/ca-admin/products');
    }

    /** @param array<string,string> $params */
    public function restore(Request $request, array $params = []): Response
    {
        $this->container->get(ProductRepository::class)->restore((int) $params['id']);
        $this->container->get(Session::class)->flash('success', 'Product restored to draft.');
        return Response::redirect('/commerce/ca-admin/products?status=archived');
    }

    /** @param array<string,string> $params */
    public function destroy(Request $request, array $params = []): Response
    {
        $this->container->get(ProductRepository::class)->softDelete((int) $params['id']);
        $this->container->get(Session::class)->flash('success', 'Product deleted.');
        return Response::redirect('/commerce/ca-admin/products');
    }

    /**
     * JSON for the delete-confirm modal.
     */
    /** @param array<string,string> $params */
    public function deleteInfo(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(ProductRepository::class);
        $id   = (int) $params['id'];

        $product = $repo->findById($id);
        if ($product === null) {
            return Response::json(['error' => 'Product not found'], 404);
        }

        return Response::json([
            'name'      => $product->name,
            'status'    => $product->status,
        ]);
    }


    // =================================================================
    // Variants
    // =================================================================

    /**
     * Variant manager page.
     *
     * Renders a full-page variant editor. Auto-generates variants
     * from category attributes on first visit. Warns on drift.
     */
    /** @param array<string,string> $params */
    public function variants(Request $request, array $params = []): Response
    {
        $productId = (int) ($params['id'] ?? 0);
        $repo      = $this->container->get(ProductRepository::class);
        $variantRepo = $this->container->get(ProductVariantRepository::class);
        $inventoryRepo = $this->container->get(InventoryRepository::class);
        $session   = $this->container->get(Session::class);

        $product = $repo->findById($productId);
        if ($product === null) {
            return Response::redirect('/commerce/ca-admin/products');
        }

        if (!$product->isVariable()) {
            $session->flash('error', 'Variations are only available for variable products.');
            return Response::redirect('/commerce/ca-admin/products/' . $productId . '/edit');
        }

        $variantAxes = $this->categoryVariantAxes($productId);

        $incompleteAxes = array_values(array_filter(
            $variantAxes,
            fn ($a) => empty($a['values'])
        ));

        $expected = empty($incompleteAxes)
            ? $this->expectedCombinations($variantAxes)
            : [];

        $allVariants = $variantRepo->listForProduct($productId);

        // Auto-generate on first visit ONLY if axes are complete
        if (empty($allVariants) && !empty($expected)) {
            $this->autoGenerate($productId, $expected, $variantAxes, (float) $product->price);
            $allVariants = $variantRepo->listForProduct($productId);
        }

        $drift = empty($incompleteAxes)
            ? $this->detectDrift($productId, $allVariants, $expected)
            : ['drift' => false, 'missing' => 0, 'extra' => 0, 'expected_count' => 0];

        // ---------- Filtering ----------
        $query     = trim((string) $request->query('q', ''));
        $groupBy   = trim((string) $request->query('group', ''));

        $filtered = $allVariants;

        if ($query !== '') {
            $needle = mb_strtolower($query);
            $filtered = array_values(array_filter($filtered, function ($v) use ($needle) {
                if ($v->sku !== null && mb_strpos(mb_strtolower($v->sku), $needle) !== false) return true;
                if (mb_strpos(mb_strtolower($v->attributeSummary), $needle) !== false) return true;
                return false;
            }));
        }

        if ($groupBy !== '' && !empty($variantAxes)) {
            $firstSlug = $variantAxes[0]['slug'] ?? '';
            if ($firstSlug !== '') {
                $filtered = array_values(array_filter($filtered, function ($v) use ($firstSlug, $groupBy) {
                    return ($v->attributeValues[$firstSlug] ?? '') === $groupBy;
                }));
            }
        }

        // ---------- Pagination ----------
        $perPage = 25;
        $page    = max(1, (int) $request->query('page', 1));
        $total   = count($filtered);
        $pages   = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) $page = $pages;
        $offset  = ($page - 1) * $perPage;
        $variants = array_slice($filtered, $offset, $perPage);

        // ---------- Stock map (only for visible variants) ----------
        $variantStock = [];
        foreach ($variants as $v) {
            $inv = $inventoryRepo->forUnit($productId, $v->id);
            $variantStock[$v->id] = $inv?->quantity ?? 0;
        }

        // ---------- Group count metadata ----------
        // Build "groups" from first axis values with counts (based on full unfiltered list)
        $groups = [];
        if (!empty($variantAxes)) {
            $firstSlug = $variantAxes[0]['slug'] ?? '';
            $firstAxis = $variantAxes[0];
            if ($firstSlug !== '' && !empty($firstAxis['values'])) {
                foreach ($firstAxis['values'] as $val) {
                    $groups[] = [
                        'value' => (string) $val['value'],
                        'count' => count(array_filter($allVariants, function ($v) use ($firstSlug, $val) {
                            return ($v->attributeValues[$firstSlug] ?? '') === (string) $val['value'];
                        })),
                    ];
                }
            }
        }

        return $this->render('admin.products.variants', [
            'title'          => 'Variations: ' . $product->name,
            'product'        => $product,
            'variants'       => $variants,
            'variantStock'   => $variantStock,
            'variantAxes'    => $variantAxes,
            'incompleteAxes' => $incompleteAxes,
            'drift'          => $drift,
            'groups'         => $groups,
            'filters'        => [
                'q'     => $query,
                'group' => $groupBy,
            ],
            'pagination'     => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => $total,
                'pages'    => $pages,
                'from'     => $total > 0 ? $offset + 1 : 0,
                'to'       => min($offset + $perPage, $total),
            ],
            'totalVariations' => count($allVariants),
            'errors'         => $session->getFlash('variant_errors', []),
        ]);
    }

    /**
     * Generate variants from a selection of attribute values.
     *
     * POST body:
     *   attribute_values[] : array of attribute_value_ids
     *   The endpoint builds the Cartesian product and creates any
     *   combination that doesn't already exist.
     */
    /** @param array<string,string> $params */
    public function generateVariants(Request $request, array $params = []): Response
    {
        $session   = $this->container->get(Session::class);
        $productId = (int) ($params['id'] ?? 0);
        $repo      = $this->container->get(ProductRepository::class);
        $variantRepo = $this->container->get(ProductVariantRepository::class);
        $db        = $this->container->get(Database::class);

        $product = $repo->findById($productId);
        if ($product === null) {
            return Response::redirect('/commerce/ca-admin/products');
        }

        $valueIds = $request->input('attribute_values', []);
        if (!is_array($valueIds)) $valueIds = [];
        $valueIds = array_values(array_unique(array_map('intval', $valueIds)));

        if (empty($valueIds)) {
            $session->flash('error', 'Select at least one attribute value to generate variants.');
            return Response::redirect('/commerce/ca-admin/products/' . $productId . '/variants');
        }

        // Fetch the attribute for each value so we can group by attribute
        $rows = $db->select(
            "SELECT av.`id` AS value_id, av.`value`, av.`attribute_id`, ad.`name` AS attr_name
               FROM `commerce_attribute_values` av
               JOIN `commerce_attribute_definitions` ad ON ad.`id` = av.`attribute_id`
              WHERE av.`id` IN (" . implode(',', array_fill(0, count($valueIds), '?')) . ")
              ORDER BY ad.`sort_order` ASC, ad.`name` ASC, av.`sort_order` ASC",
            $valueIds
        );

        // Group values by attribute
        $byAttr = [];
        foreach ($rows as $row) {
            $aid = (int) $row['attribute_id'];
            $byAttr[$aid]['name'] = (string) $row['attr_name'];
            $byAttr[$aid]['values'][] = [
                'id'    => (int) $row['value_id'],
                'value' => (string) $row['value'],
            ];
        }

        if (empty($byAttr)) {
            $session->flash('error', 'None of the selected values are valid.');
            return Response::redirect('/commerce/ca-admin/products/' . $productId . '/variants');
        }

        // Cartesian product
        $combos = [[]];
        foreach ($byAttr as $attr) {
            $next = [];
            foreach ($combos as $combo) {
                foreach ($attr['values'] as $v) {
                    $next[] = array_merge($combo, [$v['id']]);
                }
            }
            $combos = $next;
        }

        // Skip existing combinations
        $existing = $variantRepo->existingCombinations($productId);
        $created = 0;

        foreach ($combos as $valueIdCombo) {
            sort($valueIdCombo);
            $key = implode(':', $valueIdCombo);
            if (isset($existing[$key])) continue;

            $variantRepo->create(
                $productId,
                $valueIdCombo,
                '',
                (float) $product->price,
                count($existing) + $created
            );
            $created++;
        }

        $session->flash('success', $created > 0
            ? $created . ' variant' . ($created === 1 ? '' : 's') . ' generated.'
            : 'All selected combinations already exist.');

        return Response::redirect('/commerce/ca-admin/products/' . $productId . '/variants');
    }

    /**
     * Bulk-save inline variant edits.
     *
     * POST body:
     *   variants[id][sku], variants[id][price], variants[id][compare_at_price],
     *   variants[id][stock_quantity], variants[id][low_stock_threshold]
     */
    /** @param array<string,string> $params */
    public function saveVariants(Request $request, array $params = []): Response
    {
        $session   = $this->container->get(Session::class);
        $productId = (int) ($params['id'] ?? 0);
        $repo      = $this->container->get(ProductRepository::class);
        $variantRepo = $this->container->get(ProductVariantRepository::class);
        $inventoryRepo = $this->container->get(InventoryRepository::class);

        $product = $repo->findById($productId);
        if ($product === null) {
            return Response::redirect('/commerce/ca-admin/products');
        }

        $rows = $request->input('variants', []);
        if (!is_array($rows)) $rows = [];

        $auth = $this->container->get(Auth::class);
        $userId = $auth->user()?->id;
        $saved = 0;

        foreach ($rows as $vid => $row) {
            $vid = (int) $vid;
            if ($vid <= 0) continue;

            $variant = $variantRepo->findById($vid);
            if ($variant === null || $variant->productId !== $productId) continue;

            // Update fields
            $variant->sku          = trim((string) ($row['sku'] ?? '')) ?: null;
            $variant->price        = (float) ($row['price'] ?? 0);
            $variant->compareAtPrice = ($row['compare_at_price'] ?? '') !== ''
                ? (float) $row['compare_at_price']
                : null;
            $variant->lowStockThreshold = ($row['low_stock_threshold'] ?? '') !== ''
                ? (int) $row['low_stock_threshold']
                : null;

            $variantRepo->update($variant);

            // Inventory adjustment if quantity changed
            $newQty = (int) ($row['stock_quantity'] ?? 0);
            $current = $inventoryRepo->forUnit($productId, $vid);
            $currentQty = $current?->quantity ?? 0;

            if ($newQty !== $currentQty) {
                $inventoryRepo->adjust(
                    $productId,
                    $vid,
                    $newQty - $currentQty,
                    'adjustment',
                    ['reason' => 'Variant stock edit', 'created_by' => $userId]
                );
            }

            $saved++;
        }

        $session->flash('success', $saved . ' variant' . ($saved === 1 ? '' : 's') . ' saved.');
        return Response::redirect('/commerce/ca-admin/products/' . $productId . '/variants');
    }

    /**
     * Delete one variant. Removes attribute links and inventory row.
     * Inventory movements are kept for the ledger.
     */
    /** @param array<string,string> $params */
    public function deleteVariant(Request $request, array $params = []): Response
    {
        $session   = $this->container->get(Session::class);
        $productId = (int) ($params['id'] ?? 0);
        $variantId = (int) ($params['vid'] ?? 0);

        $this->container->get(ProductVariantRepository::class)->delete($variantId);
        $session->flash('success', 'Variant removed.');

        return Response::redirect('/commerce/ca-admin/products/' . $productId . '/variants');
    }


    /**
     * Regenerate variants from current category attributes.
     * Deletes all existing variants and rebuilds.
     */
    /** @param array<string,string> $params */
    public function regenerateVariants(Request $request, array $params = []): Response
    {
        $session    = $this->container->get(Session::class);
        $productId  = (int) ($params['id'] ?? 0);
        $repo       = $this->container->get(ProductRepository::class);
        $variantRepo = $this->container->get(ProductVariantRepository::class);

        $product = $repo->findById($productId);
        if ($product === null || !$product->isVariable()) {
            return Response::redirect('/commerce/ca-admin/products');
        }

        // Delete all existing variants
        $existing = $variantRepo->listForProduct($productId);
        foreach ($existing as $v) {
            $variantRepo->delete($v->id);
        }

        // Recompute and regenerate
        $variantAxes = $this->categoryVariantAxes($productId);
        $expected    = $this->expectedCombinations($variantAxes);

        if (!empty($expected)) {
            $this->autoGenerate($productId, $expected, $variantAxes, (float) $product->price);
            $session->flash('success', count($expected) . ' variants regenerated.');
        } else {
            $session->flash('error', 'No attributes available to generate variants from.');
        }

        return Response::redirect('/commerce/ca-admin/products/' . $productId . '/variants');
    }

    // =================================================================
    // Variant helpers
    // =================================================================

    /**
     * Load variant-axis attributes mapped to the product's categories.
     * Union across all categories. Deduped by attribute id.
     *
     * @return array<int, array<string,mixed>>
     */
    private function categoryVariantAxes(int $productId): array
    {
        $repo     = $this->container->get(ProductRepository::class);
        $catAttrs = $this->container->get(\CorporateAid\Commerce\Repositories\CategoryAttributeRepository::class);
        $attrRepo = $this->container->get(\CorporateAid\Commerce\Repositories\AttributeRepository::class);

        $categoryIds = $repo->categoryIdsFor($productId);
        if (empty($categoryIds)) {
            return [];
        }

        // Get attributes mapped to those categories
        $mapped = $catAttrs->attributesForCategories($categoryIds);

        // Filter to variant axes only
        $variantAxes = array_values(array_filter(
            $mapped,
            fn ($a) => !empty($a['is_variant_axis'])
        ));

        if (empty($variantAxes)) {
            return [];
        }

        // Attach values to each
        $all = $attrRepo->allWithValues();
        $valueMap = [];
        foreach ($all as $a) {
            $valueMap[(int) $a['id']] = $a['values'] ?? [];
        }

        foreach ($variantAxes as &$axis) {
            $axis['values'] = $valueMap[(int) $axis['id']] ?? [];
        }
        unset($axis);

        return $variantAxes;
    }

    /**
     * Compute the Cartesian product of variant-axis values.
     *
     * @param array<int, array<string,mixed>> $axes Each with 'id' and 'values'
     * @return array<int, array{ids: int[], key: string}>
     */
    private function expectedCombinations(array $axes): array
    {
        if (empty($axes)) {
            return [];
        }

        $combos = [[]];
        foreach ($axes as $axis) {
            if (empty($axis['values'])) {
                return []; // cannot generate with missing values
            }
            $next = [];
            foreach ($combos as $combo) {
                foreach ($axis['values'] as $v) {
                    $next[] = array_merge($combo, [(int) $v['id']]);
                }
            }
            $combos = $next;
        }

        $out = [];
        foreach ($combos as $ids) {
            sort($ids);
            $out[] = ['ids' => $ids, 'key' => implode(':', $ids)];
        }
        return $out;
    }

    /**
     * Compare expected combinations against existing variants.
     *
     * @param array<int, \CorporateAid\Commerce\Models\ProductVariant> $variants
     * @param array<int, array{ids:array<int,int>, key:string}> $expected result of expectedCombinations()
     * @return array{drift: bool, missing: int, extra: int, expected_count: int}
     */
    private function detectDrift(int $productId, array $variants, array $expected): array
    {
        $variantRepo = $this->container->get(ProductVariantRepository::class);
        $existing = $variantRepo->existingCombinations($productId);

        $existingKeys = array_keys($existing);
        $expectedKeys = array_column($expected, 'key');

        $missing = count(array_diff($expectedKeys, $existingKeys));
        $extra = count(array_diff($existingKeys, $expectedKeys));

        return [
            'drift' => ($missing > 0 || $extra > 0),
            'missing' => $missing,
            'extra' => $extra,
            'expected_count' => count($expected),
        ];
    }

    /**
     * Insert variant rows for any combination not already in the DB.
     *
     * @param array<int, array{ids:array<int,int>, key:string}> $expected
     * @param array<int, array<string,mixed>> $axes for display/logging
     */
    private function autoGenerate(int $productId, array $expected, array $axes, float $basePrice): void
    {
        $variantRepo = $this->container->get(ProductVariantRepository::class);
        $existing = $variantRepo->existingCombinations($productId);

        $sort = count($existing);
        foreach ($expected as $combo) {
            if (isset($existing[$combo['key']])) continue;
            $variantRepo->create($productId, $combo['ids'], '', $basePrice, $sort);
            $sort++;
        }
    }

    // =================================================================
    // Helpers
    // =================================================================

    /** @return array<string,mixed> */
    private function collectInput(Request $request): array
    {
        $slug = (string) $request->input('slug', '');
        if ($slug === '') {
            $slug = $this->slugify((string) $request->input('name', ''));
        }

        $categoryIds = $request->input('category_ids', []);
        if (!is_array($categoryIds)) $categoryIds = [];

        $tagIds = $request->input('tag_ids', []);
        if (!is_array($tagIds)) $tagIds = [];

        return [
            'name'                => trim((string) $request->input('name', '')),
            'product_type'        => (string) $request->input('product_type', 'simple'),
            'slug'                => strtolower(trim($slug)),
            'sku'                 => trim((string) $request->input('sku', '')) ?: null,
            'short_description'   => trim((string) $request->input('short_description', '')) ?: null,
            'description'         => trim((string) $request->input('description', '')) ?: null,
            'brand_id'            => $this->nullableInt($request->input('brand_id')),
            'primary_category_id' => $this->nullableInt($request->input('primary_category_id')),
            'category_ids'        => array_values(array_map('intval', $categoryIds)),
            'tag_ids'             => array_values(array_map('intval', $tagIds)),
            'price'               => (float) $request->input('price', 0),
            'compare_at_price'    => $this->nullableFloat($request->input('compare_at_price')),
            'cost_price'          => $this->nullableFloat($request->input('cost_price')),
            'tax_status'          => (string) $request->input('tax_status', 'taxable'),
            'tax_class_id'        => $this->nullableInt($request->input('tax_class_id')),
            'is_featured'         => (bool) $request->input('is_featured', false),
            'is_new'              => (bool) $request->input('is_new', false),
            'manage_stock'        => (bool) $request->input('manage_stock', false),
            'stock_quantity'      => (int) $request->input('stock_quantity', 0),
            'low_stock_threshold' => $this->nullableInt($request->input('low_stock_threshold')),
            'backorders_allowed'  => (bool) $request->input('backorders_allowed', false),
            'requires_shipping'   => (bool) $request->input('requires_shipping', true),
            'weight'              => $this->nullableFloat($request->input('weight')),
            'length'              => $this->nullableFloat($request->input('length')),
            'width'               => $this->nullableFloat($request->input('width')),
            'height'              => $this->nullableFloat($request->input('height')),
            'seo_title'           => trim((string) $request->input('seo_title', '')) ?: null,
            'seo_description'     => trim((string) $request->input('seo_description', '')) ?: null,
            'seo_keywords'        => trim((string) $request->input('seo_keywords', '')) ?: null,
            'status'              => (string) $request->input('status', 'draft'),
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function validate(array $data, ?int $excludeId, ProductRepository $repo): array
    {
        $errors = [];

        if ($e = Validator::required($data['name'], 'Name')) {
            $errors['name'] = $e;
        } elseif ($e = Validator::maxLength($data['name'], 255, 'Name')) {
            $errors['name'] = $e;
        }

        if ($data['slug'] === '') {
            $errors['slug'] = 'Slug is required.';
        } elseif ($e = Validator::slug($data['slug'], 200, 'Slug')) {
            $errors['slug'] = $e;
        } elseif ($repo->slugExists($data['slug'], $excludeId)) {
            $errors['slug'] = 'This slug is already used.';
        }

        if ($data['sku'] !== null) {
            if ($e = Validator::maxLength($data['sku'], 80, 'SKU')) {
                $errors['sku'] = $e;
            } elseif ($repo->skuExists($data['sku'], $excludeId)) {
                $errors['sku'] = 'This SKU is already used.';
            }
        }

        if ($data['short_description'] !== null
            && ($e = Validator::maxLength($data['short_description'], 500, 'Short description'))) {
            $errors['short_description'] = $e;
        }

        if ($e = Validator::nonNegativeNumber($data['price'], 'Price')) {
            $errors['price'] = $e;
        }

        if ($data['compare_at_price'] !== null
            && ($e = Validator::nonNegativeNumber($data['compare_at_price'], 'Compare-at price'))) {
            $errors['compare_at_price'] = $e;
        }

        if ($data['cost_price'] !== null
            && ($e = Validator::nonNegativeNumber($data['cost_price'], 'Cost price'))) {
            $errors['cost_price'] = $e;
        }

        if ($data['compare_at_price'] !== null
            && $data['compare_at_price'] > 0
            && $data['compare_at_price'] <= $data['price']) {
            $errors['compare_at_price'] = 'Compare-at price must be higher than the selling price.';
        }

        if ($data['manage_stock'] && $data['stock_quantity'] < 0) {
            $errors['stock_quantity'] = 'Stock quantity cannot be negative.';
        }

        if ($data['low_stock_threshold'] !== null && $data['low_stock_threshold'] < 0) {
            $errors['low_stock_threshold'] = 'Low stock threshold cannot be negative.';
        }

        if ($data['requires_shipping']) {
            if ($data['weight'] !== null && $data['weight'] < 0) {
                $errors['weight'] = 'Weight cannot be negative.';
            }
            if ($data['length'] !== null && $data['length'] < 0) {
                $errors['length'] = 'Length cannot be negative.';
            }
            if ($data['width'] !== null && $data['width'] < 0) {
                $errors['width'] = 'Width cannot be negative.';
            }
            if ($data['height'] !== null && $data['height'] < 0) {
                $errors['height'] = 'Height cannot be negative.';
            }
        }

        if ($data['seo_title'] !== null
            && ($e = Validator::maxLength($data['seo_title'], 200, 'SEO title'))) {
            $errors['seo_title'] = $e;
        }
        if ($data['seo_description'] !== null
            && ($e = Validator::maxLength($data['seo_description'], 300, 'Meta description'))) {
            $errors['seo_description'] = $e;
        }
        if ($data['seo_keywords'] !== null
            && ($e = Validator::maxLength($data['seo_keywords'], 300, 'Keywords'))) {
            $errors['seo_keywords'] = $e;
        }

        if (!in_array($data['status'], ['draft', 'published', 'archived'], true)) {
            $errors['status'] = 'Status is invalid.';
        }

        if (!in_array($data['tax_status'], ['taxable', 'none'], true)) {
            $errors['tax_status'] = 'Tax status is invalid.';
        }

        return $errors;
    }

    /** @param array<string,mixed> $data */
    private function hydrate(Product $product, array $data): Product
    {
        $product->productType       = in_array($data['product_type'], ['simple','variable'], true) ? $data['product_type'] : 'simple';
        $product->name              = $data['name'];
        $product->slug              = $data['slug'];
        $product->sku               = $data['sku'];
        $product->shortDescription  = $data['short_description'];
        $product->description       = $data['description'];
        $product->brandId           = $data['brand_id'];
        $product->primaryCategoryId = $data['primary_category_id'];
        $product->price             = $data['price'];
        $product->compareAtPrice    = $data['compare_at_price'];
        $product->costPrice         = $data['cost_price'];
        $product->taxStatus         = $data['tax_status'];
        $product->taxClassId        = $data['tax_class_id'];
        $product->isFeatured        = $data['is_featured'];
        $product->isNew             = $data['is_new'];
        $product->manageStock       = $data['manage_stock'];
        $product->lowStockThreshold = $data['low_stock_threshold'];
        $product->backordersAllowed = $data['backorders_allowed'];
        $product->requiresShipping  = $data['requires_shipping'];
        $product->weight            = $data['weight'];
        $product->length            = $data['length'];
        $product->width             = $data['width'];
        $product->height            = $data['height'];
        $product->seoTitle          = $data['seo_title'];
        $product->seoDescription    = $data['seo_description'];
        $product->seoKeywords       = $data['seo_keywords'];
        $product->status            = $data['status'];

        // Stock status derived from quantity + backorders
        if ($data['manage_stock']) {
            if ($data['stock_quantity'] > 0) {
                $product->stockStatus = 'in_stock';
            } elseif ($data['backorders_allowed']) {
                $product->stockStatus = 'backorder';
            } else {
                $product->stockStatus = 'out_of_stock';
            }
        } else {
            $product->stockStatus = 'in_stock';
        }

        // published_at set automatically when status becomes published
        if ($data['status'] === 'published' && $product->publishedAt === null) {
            $product->publishedAt = date('Y-m-d H:i:s');
        }

        return $product;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === '0') return null;
        $i = (int) $value;
        return $i > 0 ? $i : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        return (float) $value;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-');
    }

    // =================================================================
    // Image helpers
    // =================================================================

    /**
     * Resolve a product's images to a list of MediaItem
     * instances, ordered by sort_order. Each item is paired
     * with its ProductImage row so the view can render
     * primary state, alt text, and variant assignment.
     *
     * @return array<int, array{image: \CorporateAid\Commerce\Models\ProductImage, media: \CorporateAid\Commerce\Models\MediaItem}>
     */
    private function resolveProductImages(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $imageRepo = $this->container->get(ProductImageRepository::class);
        $mediaRepo = $this->container->get(MediaRepository::class);

        $rows = $imageRepo->listForProduct($productId);
        if ($rows === []) {
            return [];
        }

        // Bulk-load the underlying MediaItem for each image
        // in one query. No N+1.
        $fileIds = [];
        foreach ($rows as $row) {
            $fileIds[] = $row->fileId;
        }
        $mediaById = [];
        foreach ($mediaRepo->findByIds($fileIds) as $item) {
            $mediaById[$item->file->id] = $item;
        }

        $out = [];
        foreach ($rows as $row) {
            $media = $mediaById[$row->fileId] ?? null;
            if ($media === null) {
                // The underlying file is missing or soft-
                // deleted. Skip the row - a broken image
                // reference in the admin gallery is not
                // useful.
                continue;
            }
            $out[] = [
                'image' => $row,
                'media' => $media,
            ];
        }
        return $out;
    }

    /**
     * The current store's media library as a flat list of
     * MediaItem, for the picker modal. Capped at 200 items.
     *
     * @return array<int, \CorporateAid\Commerce\Models\MediaItem>
     */
    private function resolveMediaLibrary(): array
    {
        try {
            $storeContext = $this->container->get(\CorporateAid\Commerce\Context\StoreContext::class);
            if (!$storeContext->has()) {
                return [];
            }
            $mediaRepo = $this->container->get(MediaRepository::class);
            $result = $mediaRepo->paginateForStore(
                $storeContext->id(),
                [],
                1,
                200
            );
            return $result['items'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Resolve the product's variant images (swatch previews)
     * to MediaItem pairs, keyed by attribute_value_id.
     *
     * Used by the Images tab's "Variant images" card. Each
     * Color value (Red, Blue, ...) has at most one image
     * assigned. Rows with attribute_value_id set are read by
     * swatchPreviewsFor(); the gallery's listForProduct()
     * excludes them.
     *
     * @return array<int, array{image: \CorporateAid\Commerce\Models\ProductImage,
     *                          media: \CorporateAid\Commerce\Models\MediaItem}>
     *               keyed by attribute_value_id
     */
    private function resolveVariantImages(int $productId): array
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

        // Bulk-load MediaItems in one query.
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

    // =================================================================
    // Image sync
    // =================================================================

    /**
     * Read the image POST payload and apply it to the product.
     *
     * Payload shape written by _tab_images.php:
     *
     *   images[<imageId>][sort_order]          int
     *   images[<imageId>][alt_text]            string (may be empty)
     *   images[<imageId>][is_primary]          0|1
     *   images[<imageId>][attribute_value_id]  int|string ("0" or "" = null)
     *
     *   new_images_order[]                     int  (file ids, in
     *                                          display order)
     *   delete_images[]                        int  (image ids to remove)
     *
     * Order of operations:
     *
     *   1. Deletions first, so a delete + re-add of the same
     *      file does not collide on the primary flag.
     *   2. Updates to existing rows.
     *   3. Insertions of new rows, in the declared order.
     *   4. Primary fallback: if the product has images but none
     *      is flagged primary, promote the lowest-sort_order row.
     *      Guarantees a stable card image across saves where the
     *      operator removed the previous primary.
     *
     * Failure handling: the entire body is wrapped in a
     * try/catch. A broken image save must not roll back the
     * product row, categories, tags, or inventory. The exception
     * is logged via Logger::error() (a static class) and the
     * method returns normally. The merchant sees "Product
     * saved" and their image changes are silently dropped.
     * That is the correct trade: text and category changes are
     * more important than image ordering.
     */
    private function syncImages(int $productId): void
    {
        if ($productId <= 0) {
            return;
        }

        try {
            $repo = $this->container->get(ProductImageRepository::class);

            // 1. Deletions.
            $delete = $_POST['delete_images'] ?? [];
            if (is_array($delete)) {
                foreach ($delete as $imageId) {
                    $imageId = (int) $imageId;
                    if ($imageId > 0) {
                        $repo->remove($imageId);
                    }
                }
            }

            // 2. Updates to existing rows.
            $updates = $_POST['images'] ?? [];
            if (is_array($updates)) {
                foreach ($updates as $imageId => $fields) {
                    $imageId = (int) $imageId;
                    if ($imageId <= 0 || !is_array($fields)) {
                        continue;
                    }

                    $patch = [];

                    if (array_key_exists('alt_text', $fields)) {
                        $patch['alt_text'] = trim((string) $fields['alt_text']);
                    }
                    if (array_key_exists('sort_order', $fields)) {
                        $patch['sort_order'] = (int) $fields['sort_order'];
                    }
                    if (array_key_exists('is_primary', $fields)) {
                        $patch['is_primary'] = (bool) $fields['is_primary'];
                    }
                    if (array_key_exists('attribute_value_id', $fields)) {
                        $av = $fields['attribute_value_id'];
                        $patch['attribute_value_id'] = ($av === '' || $av === null || $av === '0' || $av === 0)
                            ? null
                            : (int) $av;
                    }

                    if ($patch !== []) {
                        $repo->update($imageId, $patch);
                    }
                }
            }

            // 3. Insertions. The uploader and the picker both
            // append file ids to new_images_order[] in display
            // order. Optional alt_text and attribute_value_id
            // for each new row come from new_images_meta[<fileId>]
            // - set by the JS placeholder tiles that render
            // immediately after upload, before save.
            $newOrder = $_POST['new_images_order'] ?? [];
            $newMeta  = $_POST['new_images_meta'] ?? [];
            if (is_array($newOrder)) {
                foreach ($newOrder as $fileId) {
                    $fileId = (int) $fileId;
                    if ($fileId <= 0) {
                        continue;
                    }

                    $imageId = $repo->add($productId, $fileId);
                    if ($imageId <= 0) {
                        continue;
                    }

                    // Apply per-image metadata if the placeholder
                    // tile collected any. Missing keys = leave the
                    // row's defaults in place.
                    if (is_array($newMeta)
                        && isset($newMeta[$fileId])
                        && is_array($newMeta[$fileId])
                    ) {
                        $meta = $newMeta[$fileId];
                        $patch = [];

                        if (array_key_exists('alt_text', $meta)) {
                            $patch['alt_text'] = trim((string) $meta['alt_text']);
                        }
                        if (array_key_exists('attribute_value_id', $meta)) {
                            $av = $meta['attribute_value_id'];
                            $patch['attribute_value_id'] = ($av === '' || $av === null || $av === '0')
                                ? null
                                : (int) $av;
                        }

                        if ($patch !== []) {
                            $repo->update($imageId, $patch);
                        }
                    }
                }
            }

            // 4. Primary fallback. The repository also enforces
            // this on add() for the first image, but a save where
            // the operator deleted the previous primary leaves no
            // flagged row. Promote the first.
            $all = $repo->listForProduct($productId);
            if ($all !== []) {
                $hasPrimary = false;
                foreach ($all as $img) {
                    if ($img->isPrimary) {
                        $hasPrimary = true;
                        break;
                    }
                }
                if (!$hasPrimary) {
                    $repo->setPrimary($productId, $all[0]->id);
                }
            }

            // 5. Variant images. One per attribute value. The
            // POST carries variant_images[<attribute_value_id>]
            // = <file_id>. A value of "" or 0 clears the
            // assignment; a positive value sets it.
            //
            // Because listForProduct() now filters on
            // attribute_value_id IS NULL, the existing
            // swatchPreviewsFor() is the source of truth for
            // what is currently assigned.
            $variantImages = $_POST['variant_images'] ?? [];
            if (is_array($variantImages)) {
                $existing = $repo->swatchPreviewsFor($productId);

                foreach ($variantImages as $attributeValueId => $fileId) {
                    $attributeValueId = (int) $attributeValueId;
                    $fileId = (int) $fileId;

                    if ($attributeValueId <= 0) {
                        continue;
                    }

                    $current = $existing[$attributeValueId] ?? null;

                    if ($fileId <= 0) {
                        // Clear the assignment. Delete the
                        // existing swatch row if there was one.
                        if ($current !== null) {
                            $repo->remove($current->id);
                        }
                        continue;
                    }

                    if ($current !== null) {
                        // Update the file id in place.
                        if ($current->fileId !== $fileId) {
                            $repo->update($current->id, ['file_id' => $fileId]);
                        }
                    } else {
                        // Insert a new swatch row.
                        $repo->add(
                            $productId,
                            $fileId,
                            null,
                            $attributeValueId
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            \CorporateAid\Platform\Support\Logger::error(
                'Product image sync failed',
                [
                    'product_id' => $productId,
                    'error'      => $e->getMessage(),
                    'file'       => $e->getFile(),
                    'line'       => $e->getLine(),
                ]
            );
        }
    }
}
