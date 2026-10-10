<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Repositories\CouponRedemptionRepository;
use CorporateAid\Commerce\Repositories\CouponRepository;
use CorporateAid\Commerce\Services\CatalogService;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Admin coupon management.
 *
 * Routes (all under /commerce/ca-admin/coupons):
 *
 *   GET  /                          index
 *   GET  /create                    showCreate
 *   POST /                          store
 *   GET  /{id}/edit                 showEdit
 *   POST /{id}                      update
 *   POST /{id}/delete               destroy
 *   GET  /{id}/delete-info          deleteInfo
 *
 * Permissions:
 *   coupons.view    index, showEdit
 *   coupons.create  showCreate, store
 *   coupons.edit    update
 *   coupons.delete  destroy, deleteInfo
 *
 * Guards on delete:
 *   - A coupon with redemptions cannot be deleted. The
 *     controller redirects to the edit page with a message
 *     suggesting disable instead.
 *
 * Input flow (matches ProductController):
 *   collectInput() reads the form and normalizes. It
 *   uppercases the code, trims strings, encodes the category
 *   scope to JSON, and forces value to 0 for free_shipping
 *   coupons. validate() then checks the normalized array
 *   without mutating it.
 *
 * See docs/stages/008k.5_order-pricing-engine-plan.md.
 */
final class AdminCouponController extends AdminController
{
    private const PER_PAGE = 25;

    private const VALID_TABS   = ['all', 'active', 'disabled'];
    private const VALID_TYPES  = ['percentage', 'fixed_amount', 'free_shipping'];
    private const VALID_SCOPES = ['all_products', 'specific_categories'];

    // =================================================================
    // GET /commerce/ca-admin/coupons
    // =================================================================

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $coupons = $this->container->get(CouponRepository::class);

        $tab = (string) $request->query('status', 'all');
        if (!in_array($tab, self::VALID_TABS, true)) {
            $tab = 'all';
        }

        $q    = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));

        $filters = [];
        if ($tab !== 'all') {
            $filters['status'] = $tab;
        }
        if ($q !== '') {
            $filters['q'] = $q;
        }

        $result = $coupons->paginate($filters, $page, self::PER_PAGE);

        // Counts per tab. Four tiny queries; the paginate()
        // call with perPage 1 is a cheap way to reuse the
        // filter builder.
        $counts = [
            'all'      => $coupons->paginate(['status' => 'all'],      1, 1)['total'],
            'active'   => $coupons->paginate(['status' => 'active'],   1, 1)['total'],
            'disabled' => $coupons->paginate(['status' => 'disabled'], 1, 1)['total'],
        ];

        return $this->render('admin.coupons.index', [
            'title'  => 'Coupons',
            'result' => $result,
            'tab'    => $tab,
            'q'      => $q,
            'counts' => $counts,
        ]);
    }

    // =================================================================
    // GET /commerce/ca-admin/coupons/create
    // =================================================================

    /** @param array<string,string> $params */
    public function showCreate(Request $request, array $params = []): Response
    {
        $catalog = $this->container->get(CatalogService::class);
        $session = $this->container->get(Session::class);

        return $this->render('admin.coupons.create', [
            'title'      => 'New coupon',
            'categories' => $catalog->categoriesForSelect(),
            'errors'     => $session->getFlash('coupon_errors', []),
            'old'        => $_SESSION['_old'] ?? [],
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/coupons
    // =================================================================

    /** @param array<string,string> $params */
    public function store(Request $request, array $params = []): Response
    {
        $coupons = $this->container->get(CouponRepository::class);
        $session = $this->container->get(Session::class);

        $data   = $this->collectInput($request);
        $errors = $this->validate($data, $coupons, null);

        if ($errors !== []) {
            $session->flash('coupon_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/coupons/create');
        }

        $id = $coupons->create($this->toRepositoryShape($data));

        $session->clearOldInput();
        $session->flash('success', 'Coupon created.');
        return Response::redirect('/commerce/ca-admin/coupons/' . $id . '/edit');
    }

    // =================================================================
    // GET /commerce/ca-admin/coupons/{id}/edit
    // =================================================================

    /** @param array<string,string> $params */
    public function showEdit(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid coupon reference.');
        }

        $coupons     = $this->container->get(CouponRepository::class);
        $redemptions = $this->container->get(CouponRedemptionRepository::class);
        $catalog     = $this->container->get(CatalogService::class);
        $session     = $this->container->get(Session::class);

        $coupon = $coupons->findById($id);
        if ($coupon === null) {
            return $this->fail('Coupon not found.');
        }

        return $this->render('admin.coupons.edit', [
            'title'           => 'Edit ' . (string) $coupon['code'],
            'coupon'          => $coupon,
            'categories'      => $catalog->categoriesForSelect(),
            'selectedIds'     => $this->decodeCategoryIds($coupon['applies_to_ids_json'] ?? null),
            'redemptionCount' => $redemptions->countForCoupon($id),
            'redemptions'     => $redemptions->listForCoupon($id),
            'errors'          => $session->getFlash('coupon_errors', []),
            'old'             => $_SESSION['_old'] ?? [],
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/coupons/{id}
    // =================================================================

    /** @param array<string,string> $params */
    public function update(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid coupon reference.');
        }

        $coupons = $this->container->get(CouponRepository::class);
        $session = $this->container->get(Session::class);

        $coupon = $coupons->findById($id);
        if ($coupon === null) {
            return $this->fail('Coupon not found.');
        }

        $data   = $this->collectInput($request);
        $errors = $this->validate($data, $coupons, $id);

        if ($errors !== []) {
            $session->flash('coupon_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/coupons/' . $id . '/edit');
        }

        $coupons->update($id, $this->toRepositoryShape($data));

        $session->clearOldInput();
        $session->flash('success', 'Coupon updated.');
        return Response::redirect('/commerce/ca-admin/coupons/' . $id . '/edit');
    }

    // =================================================================
    // POST /commerce/ca-admin/coupons/{id}/delete
    // =================================================================

    /** @param array<string,string> $params */
    public function destroy(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid coupon reference.');
        }

        $coupons     = $this->container->get(CouponRepository::class);
        $redemptions = $this->container->get(CouponRedemptionRepository::class);
        $session     = $this->container->get(Session::class);

        $coupon = $coupons->findById($id);
        if ($coupon === null) {
            return $this->fail('Coupon not found.');
        }

        $count = $redemptions->countForCoupon($id);
        if ($count > 0) {
            $session->flash(
                'error',
                'Cannot delete: this coupon has been redeemed '
                . $count . ' time' . ($count === 1 ? '' : 's')
                . '. Disable it instead so the redemption history '
                . 'stays intact.'
            );
            return Response::redirect('/commerce/ca-admin/coupons/' . $id . '/edit');
        }

        $coupons->delete($id);
        $session->flash('success', 'Coupon deleted.');
        return Response::redirect('/commerce/ca-admin/coupons');
    }

    // =================================================================
    // GET /commerce/ca-admin/coupons/{id}/delete-info
    // =================================================================

    /** @param array<string,string> $params */
    public function deleteInfo(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::json(['ok' => false, 'error' => 'Invalid id'], 400);
        }

        $coupons     = $this->container->get(CouponRepository::class);
        $redemptions = $this->container->get(CouponRedemptionRepository::class);

        $coupon = $coupons->findById($id);
        if ($coupon === null) {
            return Response::json(['ok' => false, 'error' => 'Coupon not found'], 404);
        }

        $count = $redemptions->countForCoupon($id);
        $blockers = [];
        if ($count > 0) {
            $blockers[] = 'This coupon has been redeemed ' . $count
                . ' time' . ($count === 1 ? '' : 's')
                . '. Disable it instead of deleting.';
        }

        return Response::json([
            'ok'         => true,
            'code'       => (string) $coupon['code'],
            'can_delete' => $blockers === [],
            'blockers'   => $blockers,
        ]);
    }

    // =================================================================
    // Helpers
    // =================================================================

    private function fail(string $message): Response
    {
        $this->container->get(Session::class)->flash('error', $message);
        return Response::redirect('/commerce/ca-admin/coupons');
    }

    /**
     * Read the form and normalize. This is the single place
     * where input becomes the shape the controller works with.
     * validate() reads this shape and never mutates it.
     *
     * @return array<string,mixed>
     */
    private function collectInput(Request $request): array
    {
        $type      = (string) $request->input('type', 'percentage');
        $appliesTo = (string) $request->input('applies_to', 'all_products');

        // Value: force 0 for free_shipping (the type ignores it).
        $valueRaw = trim((string) $request->input('value', '0'));
        $value    = $type === 'free_shipping' ? '0' : $valueRaw;

        // Category scope: read the posted category_ids[]
        // array, dedupe, keep positive ints.
        $categoryIds = $request->input('category_ids', []);
        if (!is_array($categoryIds)) {
            $categoryIds = [];
        }
        $cleanIds = [];
        foreach ($categoryIds as $cid) {
            $cid = (int) $cid;
            if ($cid > 0) {
                $cleanIds[$cid] = true;
            }
        }
        $cleanIds = array_keys($cleanIds);

        // Encode to JSON only when the scope actually uses it.
        $appliesToIdsJson = null;
        if ($appliesTo === 'specific_categories' && $cleanIds !== []) {
            $encoded = json_encode($cleanIds, JSON_UNESCAPED_UNICODE);
            $appliesToIdsJson = $encoded === false ? null : $encoded;
        }

        return [
            'code'                 => strtoupper(trim((string) $request->input('code', ''))),
            'description'          => trim((string) $request->input('description', '')),
            'type'                 => $type,
            'value'                => $value,
            'valid_from'           => trim((string) $request->input('valid_from', '')),
            'valid_to'             => trim((string) $request->input('valid_to', '')),
            'min_order_subtotal'   => trim((string) $request->input('min_order_subtotal', '')),
            'usage_limit'          => trim((string) $request->input('usage_limit', '')),
            'per_customer_limit'   => trim((string) $request->input('per_customer_limit', '')),
            'applies_to'           => $appliesTo,
            'applies_to_ids_json'  => $appliesToIdsJson,
            'status'               => (string) $request->input('status', 'active'),
            'category_ids'         => $cleanIds,
        ];
    }

    /**
     * Convert the normalized input array into the shape the
     * repository expects. Empty strings become NULL where the
     * column is nullable.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function toRepositoryShape(array $data): array
    {
        return [
            'code'                => (string) $data['code'],
            'description'         => $data['description'] !== '' ? $data['description'] : null,
            'type'                => (string) $data['type'],
            'value'               => (float) $data['value'],
            'valid_from'          => $data['valid_from'] !== '' ? $data['valid_from'] : null,
            'valid_to'            => $data['valid_to'] !== '' ? $data['valid_to'] : null,
            'min_order_subtotal'  => $data['min_order_subtotal'] !== '' ? (float) $data['min_order_subtotal'] : null,
            'usage_limit'         => $data['usage_limit'] !== '' ? (int) $data['usage_limit'] : null,
            'per_customer_limit'  => $data['per_customer_limit'] !== '' ? (int) $data['per_customer_limit'] : null,
            'applies_to'          => (string) $data['applies_to'],
            'applies_to_ids_json' => $data['applies_to_ids_json'],
            'status'              => (string) $data['status'],
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function validate(array $data, CouponRepository $coupons, ?int $excludeId): array
    {
        $errors = [];

        // Code
        $code = (string) ($data['code'] ?? '');
        if ($code === '') {
            $errors['code'] = 'Code is required.';
        } elseif (mb_strlen($code) > 60) {
            $errors['code'] = 'Code must be 60 characters or fewer.';
        } elseif (!preg_match('/^[A-Z0-9_-]+$/', $code)) {
            $errors['code'] = 'Code may contain only letters, digits, hyphens, and underscores.';
        } elseif ($coupons->codeExists($code, $excludeId)) {
            $errors['code'] = 'A coupon with that code already exists.';
        }

        // Description
        if (mb_strlen((string) ($data['description'] ?? '')) > 255) {
            $errors['description'] = 'Description must be 255 characters or fewer.';
        }

        // Type
        $type = (string) ($data['type'] ?? '');
        if (!in_array($type, self::VALID_TYPES, true)) {
            $errors['type'] = 'Select a valid coupon type.';
        }

        // Value
        if ($type !== 'free_shipping') {
            $valueRaw = (string) ($data['value'] ?? '');
            if (!is_numeric($valueRaw)) {
                $errors['value'] = 'Enter a value.';
            } else {
                $value = (float) $valueRaw;
                if ($value < 0) {
                    $errors['value'] = 'Value cannot be negative.';
                } elseif ($type === 'percentage' && $value > 100) {
                    $errors['value'] = 'Percentage cannot exceed 100.';
                }
            }
        }

        // Valid window
        foreach (['valid_from', 'valid_to'] as $field) {
            $v = (string) ($data[$field] ?? '');
            if ($v === '') {
                continue;
            }
            if (strtotime($v) === false) {
                $errors[$field] = 'Enter a valid date.';
            }
        }
        if (!isset($errors['valid_from']) && !isset($errors['valid_to'])) {
            $from = (string) ($data['valid_from'] ?? '');
            $to   = (string) ($data['valid_to'] ?? '');
            if ($from !== '' && $to !== '' && strtotime($from) >= strtotime($to)) {
                $errors['valid_to'] = 'End date must be after the start date.';
            }
        }

        // Min order
        $minOrder = (string) ($data['min_order_subtotal'] ?? '');
        if ($minOrder !== '' && (!is_numeric($minOrder) || (float) $minOrder < 0)) {
            $errors['min_order_subtotal'] = 'Enter a non-negative number.';
        }

        // Usage limits
        foreach (['usage_limit', 'per_customer_limit'] as $field) {
            $v = (string) ($data[$field] ?? '');
            if ($v === '') {
                continue;
            }
            if (!ctype_digit($v) || (int) $v < 1) {
                $errors[$field] = 'Enter a positive whole number.';
            }
        }

        // Scope
        $scope = (string) ($data['applies_to'] ?? '');
        if (!in_array($scope, self::VALID_SCOPES, true)) {
            $errors['applies_to'] = 'Select a valid scope.';
        }
        if ($scope === 'specific_categories') {
            $ids = $data['category_ids'] ?? [];
            if (!is_array($ids) || $ids === []) {
                $errors['category_ids'] = 'Select at least one category.';
            }
        }

        // Status
        $status = (string) ($data['status'] ?? '');
        if (!in_array($status, ['active', 'disabled'], true)) {
            $errors['status'] = 'Select a valid status.';
        }

        return $errors;
    }

    /**
     * @return array<int,int>
     */
    private function decodeCategoryIds(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $ids = [];
        foreach ($decoded as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        return array_keys($ids);
    }
}
