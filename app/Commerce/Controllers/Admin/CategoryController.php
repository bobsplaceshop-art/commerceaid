<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Models\Category;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Repositories\AttributeRepository;
use CorporateAid\Commerce\Repositories\CategoryAttributeRepository;
use CorporateAid\Commerce\Services\CatalogService;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Category admin CRUD.
 *
 * Routes (all under /commerce/ca-admin/categories):
 *   GET    /                    index (tree view)
 *   GET    /create              show create form
 *   POST   /                    store new category
 *   GET    /{id}/edit           show edit form
 *   POST   /{id}                update existing
 *   POST   /{id}/archive        mark archived
 *   POST   /{id}/restore        restore to active
 *   POST   /{id}/delete         soft-delete
 *   GET    /{id}/delete-info    JSON: dependent counts for the confirm modal
 */
final class CategoryController extends AdminController
{
    // =================================================================
    // Reads
    // =================================================================

    public function index(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(CategoryRepository::class);

        $includeArchived = (bool) $request->query('archived', false);
        $tree            = $repo->tree($includeArchived);
        $totalActive     = $repo->count();

        return $this->render('admin.categories.index', [
            'title'           => 'Categories',
            'tree'            => $tree,
            'includeArchived' => $includeArchived,
            'totalActive'     => $totalActive,
        ]);
    }

    public function showCreate(Request $request, array $params = []): Response
    {
        $repo    = $this->container->get(CategoryRepository::class);
        $session = $this->container->get(Session::class);

        return $this->render('admin.categories.edit', [
            'title'               => 'New category',
            'category'            => null,
            'parents'             => $this->parentOptions($repo, null),
            'errors'              => $session->getFlash('category_errors', []),
            'old'                 => $_SESSION['_old'] ?? [],
            'mode'                => 'create',
            'mappedAttributes'    => [],
            'availableAttributes' => [],
        ]);
    }


    public function showEdit(Request $request, array $params = []): Response
    {
        $repo         = $this->container->get(CategoryRepository::class);
        $catAttrs     = $this->container->get(CategoryAttributeRepository::class);
        $attrRepo     = $this->container->get(AttributeRepository::class);
        $session      = $this->container->get(Session::class);
        $id           = (int) ($params['id'] ?? 0);

        $category = $repo->findById($id);
        if ($category === null) {
            return Response::redirect('/commerce/ca-admin/categories');
        }

        // Currently-mapped attributes
        $mappedAttributes = $catAttrs->attributesForCategory($category->id);
        $mappedIds = array_map(fn ($a) => (int) $a['id'], $mappedAttributes);

        // Available attributes (all variant-enabled ones not already mapped)
        $allAttributes = $attrRepo->allWithValues();
        $availableAttributes = array_values(array_filter(
            $allAttributes,
            fn ($a) => !in_array((int) $a['id'], $mappedIds, true)
        ));

        return $this->render('admin.categories.edit', [
            'title'               => 'Edit: ' . $category->name,
            'category'            => $category,
            'parents'             => $this->parentOptions($repo, $category->id),
            'errors'              => $session->getFlash('category_errors', []),
            'old'                 => $_SESSION['_old'] ?? [],
            'mode'                => 'edit',
            'mappedAttributes'    => $mappedAttributes,
            'availableAttributes' => $availableAttributes,
        ]);
    }

    // =================================================================
    // Writes
    // =================================================================

    public function store(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(CategoryRepository::class);

        $data   = $this->collectInput($request);
        $errors = $this->validate($data, null, $repo);

        if ($errors !== []) {
            $session->flash('category_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/categories/create');
        }

        $category = new Category();
        $category->slug           = $data['slug'];
        $category->name           = $data['name'];
        $category->parentId       = $data['parent_id'];
        $category->description    = $data['description'];
        $category->sortOrder      = $data['sort_order'];
        $category->seoTitle       = $data['seo_title'];
        $category->seoDescription = $data['seo_description'];
        $category->seoKeywords    = $data['seo_keywords'];
        $category->status         = 'active';

        $repo->create($category);

        $session->flash('success', 'Category created.');
        $session->clearOldInput();
        return Response::redirect('/commerce/ca-admin/categories');
    }

    public function update(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(CategoryRepository::class);
        $id      = (int) ($params['id'] ?? 0);

        $category = $repo->findById($id);
        if ($category === null) {
            return Response::redirect('/commerce/ca-admin/categories');
        }

        $data   = $this->collectInput($request);
        $errors = $this->validate($data, $id, $repo);

        if ($errors !== []) {
            $session->flash('category_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/categories/' . $id . '/edit');
        }

        $category->slug           = $data['slug'];
        $category->name           = $data['name'];
        $category->parentId       = $data['parent_id'];
        $category->description    = $data['description'];
        $category->sortOrder      = $data['sort_order'];
        $category->seoTitle       = $data['seo_title'];
        $category->seoDescription = $data['seo_description'];
        $category->seoKeywords    = $data['seo_keywords'];

        try {
            $repo->update($category);
        } catch (\InvalidArgumentException $e) {
            $session->flash('category_errors', ['parent_id' => $e->getMessage()]);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/categories/' . $id . '/edit');
        }

        $session->flash('success', 'Category updated.');
        $session->clearOldInput();
        return Response::redirect('/commerce/ca-admin/categories');
    }

    public function archive(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(CategoryRepository::class);
        $repo->archive((int) $params['id']);
        $this->container->get(Session::class)->flash('success', 'Category archived.');
        return Response::redirect('/commerce/ca-admin/categories');
    }

    public function restore(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(CategoryRepository::class);
        $repo->restore((int) $params['id']);
        $this->container->get(Session::class)->flash('success', 'Category restored.');
        return Response::redirect('/commerce/ca-admin/categories?archived=1');
    }

    public function destroy(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(CategoryRepository::class);
        $repo->softDelete((int) $params['id']);
        $this->container->get(Session::class)->flash('success', 'Category deleted.');
        return Response::redirect('/commerce/ca-admin/categories');
    }

    /**
     * JSON endpoint for the delete-confirm modal. Returns dependent
     * counts so the admin knows what will be affected.
     */
    public function deleteInfo(Request $request, array $params = []): Response
    {
        $repo    = $this->container->get(CategoryRepository::class);
        $db      = $this->container->get(\CorporateAid\Core\Database::class);
        $context = $this->container->get(\CorporateAid\Commerce\Context\StoreContext::class);
        $id      = (int) $params['id'];

        $category = $repo->findById($id);
        if ($category === null) {
            return Response::json(['error' => 'Category not found'], 404);
        }

        $productCount = (int) $db->scalar(
            "SELECT COUNT(*) FROM `commerce_product_categories`
              WHERE `category_id` = ? AND `store_id` = ?",
            [$id, $context->id()]
        );

        return Response::json([
            'name'          => $category->name,
            'product_count' => $productCount,
            'child_count'   => count($repo->descendantIds($id)),
        ]);
    }


    // =================================================================
    // Attribute mapping
    // =================================================================

    /**
     * POST /commerce/ca-admin/categories/{id}/attributes
     * Body: attribute_id
     */
    public function attachAttribute(Request $request, array $params = []): Response
    {
        $session  = $this->container->get(Session::class);
        $repo     = $this->container->get(CategoryRepository::class);
        $catAttrs = $this->container->get(CategoryAttributeRepository::class);
        $id       = (int) ($params['id'] ?? 0);

        $category = $repo->findById($id);
        if ($category === null) {
            return Response::redirect('/commerce/ca-admin/categories');
        }

        $attributeId = (int) $request->input('attribute_id', 0);
        if ($attributeId <= 0) {
            $session->flash('category_errors', ['attribute_id' => 'Select an attribute to add.']);
            return Response::redirect('/commerce/ca-admin/categories/' . $id . '/edit');
        }

        $attached = $catAttrs->attach($id, $attributeId);
        $session->flash(
            $attached ? 'success' : 'error',
            $attached ? 'Attribute added to this category.' : 'Attribute already mapped to this category.'
        );

        return Response::redirect('/commerce/ca-admin/categories/' . $id . '/edit');
    }

    /**
     * POST /commerce/ca-admin/categories/{id}/attributes/{attrId}/detach
     */
    public function detachAttribute(Request $request, array $params = []): Response
    {
        $session  = $this->container->get(Session::class);
        $repo     = $this->container->get(CategoryRepository::class);
        $catAttrs = $this->container->get(CategoryAttributeRepository::class);
        $id       = (int) ($params['id'] ?? 0);
        $attrId   = (int) ($params['attrId'] ?? 0);

        $category = $repo->findById($id);
        if ($category === null) {
            return Response::redirect('/commerce/ca-admin/categories');
        }

        $catAttrs->detach($id, $attrId);
        $session->flash('success', 'Attribute removed from this category.');

        return Response::redirect('/commerce/ca-admin/categories/' . $id . '/edit');
    }

    /**
     * JSON: how many products in this category use the given attribute.
     * Used by the detach-confirm modal.
     */
    public function attributeUsageInfo(Request $request, array $params = []): Response
    {
        $repo     = $this->container->get(CategoryRepository::class);
        $catAttrs = $this->container->get(CategoryAttributeRepository::class);
        $id       = (int) ($params['id'] ?? 0);
        $attrId   = (int) ($params['attrId'] ?? 0);

        $category = $repo->findById($id);
        if ($category === null) {
            return Response::json(['error' => 'Category not found'], 404);
        }

        return Response::json([
            'usage_count' => $catAttrs->productUsageCount($id, $attrId),
        ]);
    }

    // =================================================================
    // Helpers
    // =================================================================

    /**
     * @return array<string,mixed>
     */
    private function collectInput(Request $request): array
    {
        $slug = (string) $request->input('slug', '');
        if ($slug === '') {
            $slug = $this->slugify((string) $request->input('name', ''));
        }

        return [
            'name'            => trim((string) $request->input('name', '')),
            'slug'            => strtolower(trim($slug)),
            'parent_id'       => $this->nullableInt($request->input('parent_id')),
            'description'     => trim((string) $request->input('description', '')) ?: null,
            'sort_order'      => max(0, (int) $request->input('sort_order', 0)),
            'seo_title'       => trim((string) $request->input('seo_title', '')) ?: null,
            'seo_description' => trim((string) $request->input('seo_description', '')) ?: null,
            'seo_keywords'    => trim((string) $request->input('seo_keywords', '')) ?: null,
        ];
    }

    /**
     * @return array<string,string>
     */
    private function validate(array $data, ?int $excludeId, CategoryRepository $repo): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors['name'] = 'Name is required.';
        }

        if ($data['slug'] === '') {
            $errors['slug'] = 'Slug is required.';
        } elseif (!preg_match('/^[a-z0-9][a-z0-9-]{0,118}[a-z0-9]$/', $data['slug'])) {
            $errors['slug'] = 'Slug must be lowercase letters, digits, and hyphens.';
        } else {
            $existing = $repo->findBySlug($data['slug']);
            if ($existing !== null && ($excludeId === null || $existing->id !== $excludeId)) {
                $errors['slug'] = 'This slug is already used.';
            }
        }

        if ($data['parent_id'] !== null && $excludeId !== null && $data['parent_id'] === $excludeId) {
            $errors['parent_id'] = 'A category cannot be its own parent.';
        }

        return $errors;
    }

    /**
     * List of categories eligible to be a parent (excludes self and
     * descendants when editing).
     *
     * @return array<int, array{id:int, name:string, depth:int}>
     */
    private function parentOptions(CategoryRepository $repo, ?int $excludeId): array
    {
        $tree = $repo->tree(true);
        $flat = [];
        $this->flatten($tree, 0, $flat, $excludeId);
        return $flat;
    }

    /**
     * @param array<int, array<string,mixed>> $tree
     * @param array<int, array{id:int,name:string,depth:int}> $out
     */
    private function flatten(array $tree, int $depth, array &$out, ?int $excludeId): void
    {
        foreach ($tree as $node) {
            $category = $node['category'];
            $id = $category->id;

            if ($excludeId !== null && ($id === $excludeId)) {
                continue;
            }

            $out[] = [
                'id'    => (int) $id,
                'name'  => str_repeat('— ', $depth) . $category->name,
                'depth' => $depth,
            ];

            if (!empty($node['children'])) {
                $this->flatten($node['children'], $depth + 1, $out, $excludeId);
            }
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === '0') {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $text = trim($text, '-');
        return $text;
    }
}
