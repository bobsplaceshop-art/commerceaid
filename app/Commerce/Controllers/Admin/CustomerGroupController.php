<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Models\CustomerGroup;
use CorporateAid\Commerce\Models\CustomerGroupPrice;
use CorporateAid\Commerce\Repositories\CustomerGroupRepository;
use CorporateAid\Commerce\Repositories\CustomerGroupPriceRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Support\Validator;

/**
 * Customer group admin CRUD + price list management.
 *
 * Routes (under /commerce/ca-admin/customer-groups):
 *   GET    /                         index
 *   GET    /create                   create form
 *   POST   /                         store
 *   GET    /{id}/edit                edit form
 *   POST   /{id}                     update
 *   POST   /{id}/archive             archive
 *   POST   /{id}/restore             restore
 *   POST   /{id}/delete              soft-archive (no hard delete for groups)
 *
 *   GET    /{id}/prices              price list
 *   POST   /{id}/prices              add override
 *   POST   /{id}/prices/{pid}/delete delete override
 */
final class CustomerGroupController extends AdminController
{
    // =================================================================
    // Group CRUD
    // =================================================================

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $repo   = $this->container->get(CustomerGroupRepository::class);
        $prices = $this->container->get(CustomerGroupPriceRepository::class);

        $includeArchived = (bool) $request->query('archived', false);
        $groups = $repo->listAll($includeArchived);

        // Attach override counts + customer counts
        $db = $this->container->get(\CorporateAid\Core\Database::class);
        $groupData = [];
        foreach ($groups as $g) {
            $overrideCount = $prices->countForGroup($g->id);
            // Customers table doesn't exist yet (Stage 007B); placeholder = 0
            $customerCount = 0;
            $groupData[] = [
                'group'           => $g,
                'override_count'  => $overrideCount,
                'customer_count'  => $customerCount,
            ];
        }

        return $this->render('admin.customer_groups.index', [
            'title'           => 'Customer Groups',
            'groups'          => $groupData,
            'includeArchived' => $includeArchived,
            'totalActive'     => $repo->count(),
        ]);
    }

    /** @param array<string,string> $params */
    public function showCreate(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);

        return $this->render('admin.customer_groups.edit', [
            'title'  => 'New customer group',
            'group'  => null,
            'errors' => $session->getFlash('group_errors', []),
            'old'    => $_SESSION['_old'] ?? [],
            'mode'   => 'create',
        ]);
    }

    /** @param array<string,string> $params */
    public function showEdit(Request $request, array $params = []): Response
    {
        $repo    = $this->container->get(CustomerGroupRepository::class);
        $session = $this->container->get(Session::class);
        $id      = (int) ($params['id'] ?? 0);

        $group = $repo->findById($id);
        if ($group === null) {
            return Response::redirect('/commerce/ca-admin/customer-groups');
        }

        return $this->render('admin.customer_groups.edit', [
            'title'  => 'Edit: ' . $group->name,
            'group'  => $group,
            'errors' => $session->getFlash('group_errors', []),
            'old'    => $_SESSION['_old'] ?? [],
            'mode'   => 'edit',
        ]);
    }

    /** @param array<string,string> $params */
    public function store(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(CustomerGroupRepository::class);

        $data   = $this->collectInput($request);
        $errors = $this->validateGroup($data, null, $repo);

        if ($errors !== []) {
            $session->flash('group_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/customer-groups/create');
        }

        $group = new CustomerGroup();
        $group->slug             = $data['slug'];
        $group->name             = $data['name'];
        $group->description      = $data['description'];
        $group->isDefault        = $data['is_default'];
        $group->isB2b            = $data['is_b2b'];
        $group->requiresApproval = $data['requires_approval'];
        $group->defaultNetDays   = $data['default_net_days'];
        $group->sortOrder        = $data['sort_order'];
        $group->status           = 'active';

        $repo->create($group);

        $session->flash('success', 'Customer group created.');
        $session->clearOldInput();
        return Response::redirect('/commerce/ca-admin/customer-groups');
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(CustomerGroupRepository::class);
        $id      = (int) ($params['id'] ?? 0);

        $group = $repo->findById($id);
        if ($group === null) {
            return Response::redirect('/commerce/ca-admin/customer-groups');
        }

        $data   = $this->collectInput($request);
        $errors = $this->validateGroup($data, $id, $repo);

        if ($errors !== []) {
            $session->flash('group_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/customer-groups/' . $id . '/edit');
        }

        $group->slug             = $data['slug'];
        $group->name             = $data['name'];
        $group->description      = $data['description'];
        $group->isDefault        = $data['is_default'];
        $group->isB2b            = $data['is_b2b'];
        $group->requiresApproval = $data['requires_approval'];
        $group->defaultNetDays   = $data['default_net_days'];
        $group->sortOrder        = $data['sort_order'];

        $repo->update($group);

        $session->flash('success', 'Customer group updated.');
        $session->clearOldInput();
        return Response::redirect('/commerce/ca-admin/customer-groups/' . $id . '/edit');
    }

    /** @param array<string,string> $params */
    public function archive(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(CustomerGroupRepository::class);
        $id      = (int) ($params['id'] ?? 0);

        try {
            $repo->archive($id);
            $session->flash('success', 'Customer group archived.');
        } catch (\InvalidArgumentException $e) {
            $session->flash('error', $e->getMessage());
        }

        return Response::redirect('/commerce/ca-admin/customer-groups');
    }

    /** @param array<string,string> $params */
    public function restore(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(CustomerGroupRepository::class);
        $repo->restore((int) $params['id']);
        $this->container->get(Session::class)->flash('success', 'Customer group restored.');
        return Response::redirect('/commerce/ca-admin/customer-groups?archived=1');
    }

    // =================================================================
    // Price list
    // =================================================================

    /** @param array<string,string> $params */
    public function prices(Request $request, array $params = []): Response
    {
        $groupRepo = $this->container->get(CustomerGroupRepository::class);
        $priceRepo = $this->container->get(CustomerGroupPriceRepository::class);
        $session   = $this->container->get(Session::class);
        $id        = (int) ($params['id'] ?? 0);

        $group = $groupRepo->findById($id);
        if ($group === null) {
            return Response::redirect('/commerce/ca-admin/customer-groups');
        }

        $overrides = $priceRepo->listForGroup($id);

        // Available products for the add form (dropdown)
        $products = $this->container->get(ProductRepository::class)
            ->paginate([], 1, 500)['items'];

        $productOptions = [];
        foreach ($products as $p) {
            $productOptions[] = ['id' => $p->id, 'name' => $p->name];
        }

        return $this->render('admin.customer_groups.prices', [
            'title'          => 'Prices: ' . $group->name,
            'group'          => $group,
            'overrides'      => $overrides,
            'productOptions' => $productOptions,
            'errors'         => $session->getFlash('price_errors', []),
            'old'            => $_SESSION['_old'] ?? [],
        ]);
    }

    /** @param array<string,string> $params */
    public function addPrice(Request $request, array $params = []): Response
    {
        $session   = $this->container->get(Session::class);
        $groupRepo = $this->container->get(CustomerGroupRepository::class);
        $priceRepo = $this->container->get(CustomerGroupPriceRepository::class);
        $id        = (int) ($params['id'] ?? 0);

        $group = $groupRepo->findById($id);
        if ($group === null) {
            return Response::redirect('/commerce/ca-admin/customer-groups');
        }

        $productId = (int) $request->input('product_id', 0);
        $variantId = (int) $request->input('variant_id', 0);
        $price     = $request->input('price');
        $compareAt = $request->input('compare_at_price');
        $minQty    = max(1, (int) $request->input('min_quantity', 1));
        $maxQtyRaw = $request->input('max_quantity');
        $maxQty    = ($maxQtyRaw === null || $maxQtyRaw === '') ? null : (int) $maxQtyRaw;

        $errors = [];

        if ($productId <= 0) {
            $errors['product_id'] = 'Select a product.';
        }

        if ($price === null || $price === '' || !is_numeric($price) || (float) $price < 0) {
            $errors['price'] = 'Price must be zero or greater.';
        }

        if ($compareAt !== null && $compareAt !== '' && (!is_numeric($compareAt) || (float) $compareAt < 0)) {
            $errors['compare_at_price'] = 'Compare-at price must be zero or greater.';
        }

        if ($maxQty !== null && $maxQty < $minQty) {
            $errors['max_quantity'] = 'Max quantity must be greater than min quantity.';
        }

        if ($errors !== []) {
            $session->flash('price_errors', $errors);
            $session->flashOldInput([
                'product_id'       => $productId,
                'variant_id'       => $variantId,
                'price'            => $price,
                'compare_at_price' => $compareAt,
                'min_quantity'     => $minQty,
                'max_quantity'     => $maxQty,
            ]);
            return Response::redirect('/commerce/ca-admin/customer-groups/' . $id . '/prices');
        }

        $record = new CustomerGroupPrice();
        $record->customerGroupId = $id;
        $record->productId       = $productId;
        $record->variantId       = $variantId;
        $record->price           = (float) $price;
        $record->compareAtPrice  = ($compareAt !== null && $compareAt !== '') ? (float) $compareAt : null;
        $record->minQuantity     = $minQty;
        $record->maxQuantity     = $maxQty;

        try {
            $priceRepo->create($record);
            $session->flash('success', 'Price override added.');
            $session->clearOldInput();
        } catch (\Throwable $e) {
            $session->flash('error', 'Could not add override: ' . $e->getMessage());
        }

        return Response::redirect('/commerce/ca-admin/customer-groups/' . $id . '/prices');
    }

    /** @param array<string,string> $params */
    public function deletePrice(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $id      = (int) ($params['id'] ?? 0);
        $priceId = (int) ($params['pid'] ?? 0);

        $this->container->get(CustomerGroupPriceRepository::class)->delete($priceId);
        $session->flash('success', 'Price override removed.');

        return Response::redirect('/commerce/ca-admin/customer-groups/' . $id . '/prices');
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

        return [
            'name'              => trim((string) $request->input('name', '')),
            'slug'              => strtolower(trim($slug)),
            'description'       => trim((string) $request->input('description', '')) ?: null,
            'is_default'        => (bool) $request->input('is_default', false),
            'is_b2b'            => (bool) $request->input('is_b2b', false),
            'requires_approval' => (bool) $request->input('requires_approval', false),
            'default_net_days'  => max(0, (int) $request->input('default_net_days', 0)),
            'sort_order'        => max(0, (int) $request->input('sort_order', 0)),
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function validateGroup(array $data, ?int $excludeId, CustomerGroupRepository $repo): array
    {
        $errors = [];

        if ($e = Validator::required($data['name'], 'Name')) {
            $errors['name'] = $e;
        } elseif ($e = Validator::maxLength($data['name'], 120, 'Name')) {
            $errors['name'] = $e;
        }

        if ($data['slug'] === '') {
            $errors['slug'] = 'Slug is required.';
        } elseif ($e = Validator::slug($data['slug'], 80, 'Slug')) {
            $errors['slug'] = $e;
        } elseif ($repo->slugExists($data['slug'], $excludeId)) {
            $errors['slug'] = 'This slug is already used.';
        }

        if ($data['description'] !== null
            && ($e = Validator::maxLength($data['description'], 500, 'Description'))) {
            $errors['description'] = $e;
        }

        if ($data['default_net_days'] > 365) {
            $errors['default_net_days'] = 'Net terms must be 365 days or fewer.';
        }

        return $errors;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-');
    }
}
