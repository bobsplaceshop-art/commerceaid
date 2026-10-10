<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Repositories\ShippingRepository;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Admin shipping management.
 *
 * Routes (all under /commerce/ca-admin/shipping):
 *
 *   GET    /                              index
 *   GET    /methods/create                showCreateMethod
 *   POST   /methods                       storeMethod
 *   GET    /methods/{id}/edit             showEditMethod
 *   POST   /methods/{id}                  updateMethod
 *   GET    /zones/create                  showCreateZone
 *   POST   /zones                         storeZone
 *   GET    /zones/{id}/edit               showEditZone
 *   POST   /zones/{id}                    updateZone
 *   GET    /{type}/{id}/delete-info       deleteInfo
 *   POST   /{type}/{id}/delete            destroy
 *
 * where {type} is 'method' or 'zone'.
 *
 * Permissions:
 *   shipping.view    index, edit views
 *   shipping.manage  create, update, delete
 *
 * Method types and their config_json shape:
 *   flat          {"rate": 5.00}
 *   free_over     {"rate": 5.00, "threshold": 50.00}
 *   weight_based  {"per_kg": 2.50, "min_charge": 5.00}
 *   price_based   {"per_unit": 0.05, "min_charge": 5.00,
 *                  "unit_size": 1.00}
 *   local_pickup  {} (no config)
 *
 * Guards on delete:
 *   - A method referenced by orders in the last 30 days
 *     cannot be deleted. Disable it instead.
 *   - A zone has no guard. It can always be deleted.
 *
 * See docs/stages/008k.5_order-pricing-engine-plan.md.
 */
final class AdminShippingController extends AdminController
{
    private const VALID_TYPES = ['method', 'zone'];

    private const VALID_METHOD_TYPES = [
        'flat',
        'free_over',
        'weight_based',
        'price_based',
        'local_pickup',
    ];

    // =================================================================
    // GET /commerce/ca-admin/shipping
    // =================================================================

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $repo    = $this->container->get(ShippingRepository::class);
        $session = $this->container->get(Session::class);

        $methods = $repo->listMethods();

        // Group zones by method id.
        $zonesByMethod = [];
        foreach ($repo->listZones() as $z) {
            $mid = (int) ($z['method_id'] ?? 0);
            $zonesByMethod[$mid][] = $z;
        }

        return $this->render('admin.shipping.index', [
            'title'         => 'Shipping',
            'methods'       => $methods,
            'zonesByMethod' => $zonesByMethod,
            'errors'        => $session->getFlash('shipping_errors', []),
            'old'           => $_SESSION['_old'] ?? [],
        ]);
    }

    // =================================================================
    // GET /commerce/ca-admin/shipping/methods/create
    // =================================================================

    /** @param array<string,string> $params */
    public function showCreateMethod(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);

        return $this->render('admin.shipping.method-form', [
            'title'   => 'New shipping method',
            'mode'    => 'create',
            'method'  => null,
            'errors'  => $session->getFlash('shipping_errors', []),
            'old'     => $_SESSION['_old'] ?? [],
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/shipping/methods
    // =================================================================

    /** @param array<string,string> $params */
    public function storeMethod(Request $request, array $params = []): Response
    {
        $repo    = $this->container->get(ShippingRepository::class);
        $session = $this->container->get(Session::class);

        $data   = $this->collectMethodInput($request);
        $errors = $this->validateMethod($data, $repo, null);

        if ($errors !== []) {
            $session->flash('shipping_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/shipping/methods/create');
        }

        $repo->createMethod($data);

        $session->clearOldInput();
        $session->flash('success', 'Shipping method created.');
        return Response::redirect('/commerce/ca-admin/shipping');
    }

    // =================================================================
    // GET /commerce/ca-admin/shipping/methods/{id}/edit
    // =================================================================

    /** @param array<string,string> $params */
    public function showEditMethod(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid method reference.');
        }

        $repo    = $this->container->get(ShippingRepository::class);
        $session = $this->container->get(Session::class);

        $method = $repo->findMethod($id);
        if ($method === null) {
            return $this->fail('Shipping method not found.');
        }

        return $this->render('admin.shipping.method-form', [
            'title'  => 'Edit ' . (string) $method['label'],
            'mode'   => 'edit',
            'method' => $method,
            'errors' => $session->getFlash('shipping_errors', []),
            'old'    => $_SESSION['_old'] ?? [],
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/shipping/methods/{id}
    // =================================================================

    /** @param array<string,string> $params */
    public function updateMethod(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid method reference.');
        }

        $repo    = $this->container->get(ShippingRepository::class);
        $session = $this->container->get(Session::class);

        $method = $repo->findMethod($id);
        if ($method === null) {
            return $this->fail('Shipping method not found.');
        }

        $data   = $this->collectMethodInput($request);
        $errors = $this->validateMethod($data, $repo, $id);

        if ($errors !== []) {
            $session->flash('shipping_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/shipping/methods/' . $id . '/edit');
        }

        $repo->updateMethod($id, $data);

        $session->clearOldInput();
        $session->flash('success', 'Shipping method updated.');
        return Response::redirect('/commerce/ca-admin/shipping');
    }

    // =================================================================
    // GET /commerce/ca-admin/shipping/zones/create
    // =================================================================

    /** @param array<string,string> $params */
    public function showCreateZone(Request $request, array $params = []): Response
    {
        $repo    = $this->container->get(ShippingRepository::class);
        $session = $this->container->get(Session::class);

        $methods = $repo->listMethods();
        if ($methods === []) {
            $session->flash('error', 'Create a shipping method first.');
            return Response::redirect('/commerce/ca-admin/shipping');
        }

        $preMethodId = (int) $request->query('method_id', 0);

        return $this->render('admin.shipping.zone-form', [
            'title'         => 'New shipping zone',
            'mode'          => 'create',
            'zone'          => null,
            'methods'       => $methods,
            'preMethod'     => $preMethodId,
            'errors'        => $session->getFlash('shipping_errors', []),
            'old'           => $_SESSION['_old'] ?? [],
            'countries'     => $this->commonCountries(),
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/shipping/zones
    // =================================================================

    /** @param array<string,string> $params */
    public function storeZone(Request $request, array $params = []): Response
    {
        $repo    = $this->container->get(ShippingRepository::class);
        $session = $this->container->get(Session::class);

        $data   = $this->collectZoneInput($request);
        $errors = $this->validateZone($data, $repo, null);

        if ($errors !== []) {
            $session->flash('shipping_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/shipping/zones/create');
        }

        $repo->createZone($data);

        $session->clearOldInput();
        $session->flash('success', 'Shipping zone created.');
        return Response::redirect('/commerce/ca-admin/shipping');
    }

    // =================================================================
    // GET /commerce/ca-admin/shipping/zones/{id}/edit
    // =================================================================

    /** @param array<string,string> $params */
    public function showEditZone(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid zone reference.');
        }

        $repo    = $this->container->get(ShippingRepository::class);
        $session = $this->container->get(Session::class);

        $zone = $repo->findZone($id);
        if ($zone === null) {
            return $this->fail('Shipping zone not found.');
        }

        return $this->render('admin.shipping.zone-form', [
            'title'     => 'Edit shipping zone',
            'mode'      => 'edit',
            'zone'      => $zone,
            'methods'   => $repo->listMethods(),
            'preMethod' => (int) ($zone['method_id'] ?? 0),
            'errors'    => $session->getFlash('shipping_errors', []),
            'old'       => $_SESSION['_old'] ?? [],
            'countries' => $this->commonCountries(),
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/shipping/zones/{id}
    // =================================================================

    /** @param array<string,string> $params */
    public function updateZone(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid zone reference.');
        }

        $repo    = $this->container->get(ShippingRepository::class);
        $session = $this->container->get(Session::class);

        $zone = $repo->findZone($id);
        if ($zone === null) {
            return $this->fail('Shipping zone not found.');
        }

        $data   = $this->collectZoneInput($request);
        $errors = $this->validateZone($data, $repo, $id);

        if ($errors !== []) {
            $session->flash('shipping_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/shipping/zones/' . $id . '/edit');
        }

        $repo->updateZone($id, $data);

        $session->clearOldInput();
        $session->flash('success', 'Shipping zone updated.');
        return Response::redirect('/commerce/ca-admin/shipping');
    }

    // =================================================================
    // GET /commerce/ca-admin/shipping/{type}/{id}/delete-info
    // =================================================================

    /** @param array<string,string> $params */
    public function deleteInfo(Request $request, array $params = []): Response
    {
        $type = (string) ($params['type'] ?? '');
        $id   = (int) ($params['id'] ?? 0);

        if (!in_array($type, self::VALID_TYPES, true) || $id <= 0) {
            return Response::json(['ok' => false, 'error' => 'Invalid reference'], 400);
        }

        $repo = $this->container->get(ShippingRepository::class);

        return $type === 'method'
            ? $this->methodDeleteInfo($repo, $id)
            : $this->zoneDeleteInfo($repo, $id);
    }

    // =================================================================
    // POST /commerce/ca-admin/shipping/{type}/{id}/delete
    // =================================================================

    /** @param array<string,string> $params */
    public function destroy(Request $request, array $params = []): Response
    {
        $type = (string) ($params['type'] ?? '');
        $id   = (int) ($params['id'] ?? 0);

        if (!in_array($type, self::VALID_TYPES, true) || $id <= 0) {
            return $this->fail('Invalid reference.');
        }

        return $type === 'method'
            ? $this->destroyMethod($id)
            : $this->destroyZone($id);
    }

    // =================================================================
    // Type-specific destroy handlers
    // =================================================================

    private function destroyMethod(int $id): Response
    {
        $repo    = $this->container->get(ShippingRepository::class);
        $session = $this->container->get(Session::class);

        $method = $repo->findMethod($id);
        if ($method === null) {
            return $this->fail('Shipping method not found.');
        }

        $blockers = $this->methodDeleteBlockers($repo, $method);
        if ($blockers !== []) {
            $session->flash('error', implode(' ', $blockers));
            return Response::redirect('/commerce/ca-admin/shipping');
        }

        $repo->deleteMethod($id);
        $session->flash('success', 'Shipping method deleted.');
        return Response::redirect('/commerce/ca-admin/shipping');
    }

    private function destroyZone(int $id): Response
    {
        $repo    = $this->container->get(ShippingRepository::class);
        $session = $this->container->get(Session::class);

        $zone = $repo->findZone($id);
        if ($zone === null) {
            return $this->fail('Shipping zone not found.');
        }

        $repo->deleteZone($id);
        $session->flash('success', 'Shipping zone deleted.');
        return Response::redirect('/commerce/ca-admin/shipping');
    }

    // =================================================================
    // JSON delete-info
    // =================================================================

    private function methodDeleteInfo(ShippingRepository $repo, int $id): Response
    {
        $method = $repo->findMethod($id);
        if ($method === null) {
            return Response::json(['ok' => false, 'error' => 'Shipping method not found'], 404);
        }

        $blockers = $this->methodDeleteBlockers($repo, $method);

        return Response::json([
            'ok'         => true,
            'label'      => (string) $method['label'],
            'can_delete' => $blockers === [],
            'blockers'   => $blockers,
        ]);
    }

    private function zoneDeleteInfo(ShippingRepository $repo, int $id): Response
    {
        $zone = $repo->findZone($id);
        if ($zone === null) {
            return Response::json(['ok' => false, 'error' => 'Shipping zone not found'], 404);
        }

        $label = (string) ($zone['country_code'] ?? '')
            . (($zone['region'] ?? '') !== '' ? ' / ' . (string) $zone['region'] : '')
            . ' (method #' . (int) ($zone['method_id'] ?? 0) . ')';

        return Response::json([
            'ok'         => true,
            'label'      => $label,
            'can_delete' => true,
            'blockers'   => [],
        ]);
    }

    /**
     * @param array<string,mixed> $method
     * @return array<int,string>
     */
    private function methodDeleteBlockers(ShippingRepository $repo, array $method): array
    {
        $blockers = [];

        $code = (string) ($method['code'] ?? '');
        if ($code === '') {
            return $blockers;
        }

        $recent = $repo->countRecentOrdersForMethod($code, 30);
        if ($recent > 0) {
            $blockers[] = 'This method was used by ' . $recent
                . ' order' . ($recent === 1 ? '' : 's')
                . ' in the last 30 days. Disable it instead.';
        }

        return $blockers;
    }

    // =================================================================
    // Helpers
    // =================================================================

    private function fail(string $message): Response
    {
        $this->container->get(Session::class)->flash('error', $message);
        return Response::redirect('/commerce/ca-admin/shipping');
    }

    /**
     * Read the method form and normalize. The config_json is
     * built from the type-specific fields, then encoded.
     *
     * @return array<string,mixed>
     */
    private function collectMethodInput(Request $request): array
    {
        $type = (string) $request->input('type', 'flat');
        if (!in_array($type, self::VALID_METHOD_TYPES, true)) {
            $type = 'flat';
        }

        // Type-specific config.
        $config = [];
        switch ($type) {
            case 'flat':
                $config['rate'] = $this->floatOr($request->input('config_rate', 0), 0.0);
                break;
            case 'free_over':
                $config['rate']      = $this->floatOr($request->input('config_rate', 0), 0.0);
                $config['threshold'] = $this->floatOr($request->input('config_threshold', 0), 0.0);
                break;
            case 'weight_based':
                $config['per_kg']     = $this->floatOr($request->input('config_per_kg', 0), 0.0);
                $config['min_charge'] = $this->floatOr($request->input('config_min_charge', 0), 0.0);
                break;
            case 'price_based':
                $config['per_unit']   = $this->floatOr($request->input('config_per_unit', 0), 0.0);
                $config['min_charge'] = $this->floatOr($request->input('config_min_charge', 0), 0.0);
                $config['unit_size']  = $this->floatOr($request->input('config_unit_size', 1), 1.0);
                break;
            case 'local_pickup':
                // No config.
                break;
        }

        $encoded = $config === []
            ? null
            : json_encode($config, JSON_UNESCAPED_UNICODE);
        $configJson = $encoded === false ? null : $encoded;

        return [
            'code'        => strtolower(trim((string) $request->input('code', ''))),
            'label'       => trim((string) $request->input('label', '')),
            'description' => trim((string) $request->input('description', '')),
            'type'        => $type,
            'config_json' => $configJson,
            'is_active'   => (bool) $request->input('is_active', false),
            'sort_order'  => (int) $request->input('sort_order', 0),
            // Preserve the raw config for form repopulation on error.
            '_config'     => $config,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function collectZoneInput(Request $request): array
    {
        return [
            'method_id'     => (int) $request->input('method_id', 0),
            'country_code'  => strtoupper(trim((string) $request->input('country_code', ''))),
            'region'        => trim((string) $request->input('region', '')),
            'sort_order'    => (int) $request->input('sort_order', 0),
            'rate_override' => $this->floatOr($request->input('rate_override', ''), null),
            'is_active'     => (bool) $request->input('is_active', false),
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function validateMethod(array $data, ShippingRepository $repo, ?int $excludeId): array
    {
        $errors = [];

        $code = (string) ($data['code'] ?? '');
        if ($code === '') {
            $errors['code'] = 'Code is required.';
        } elseif (mb_strlen($code) > 60) {
            $errors['code'] = 'Code must be 60 characters or fewer.';
        } elseif (!preg_match('/^[a-z0-9_-]+$/', $code)) {
            $errors['code'] = 'Code may contain only lowercase letters, digits, hyphens, and underscores.';
        } elseif ($repo->methodCodeExists($code, $excludeId)) {
            $errors['code'] = 'A method with that code already exists.';
        }

        $label = (string) ($data['label'] ?? '');
        if ($label === '') {
            $errors['label'] = 'Label is required.';
        } elseif (mb_strlen($label) > 120) {
            $errors['label'] = 'Label must be 120 characters or fewer.';
        }

        if (mb_strlen((string) ($data['description'] ?? '')) > 255) {
            $errors['description'] = 'Description must be 255 characters or fewer.';
        }

        $type = (string) ($data['type'] ?? '');
        if (!in_array($type, self::VALID_METHOD_TYPES, true)) {
            $errors['type'] = 'Select a valid method type.';
        }

        // Type-specific config validation.
        $config = is_array($data['_config'] ?? null) ? $data['_config'] : [];
        switch ($type) {
            case 'flat':
                if (!isset($config['rate']) || $config['rate'] < 0) {
                    $errors['config_rate'] = 'Enter a non-negative rate.';
                }
                break;
            case 'free_over':
                if (!isset($config['rate']) || $config['rate'] < 0) {
                    $errors['config_rate'] = 'Enter a non-negative rate.';
                }
                if (!isset($config['threshold']) || $config['threshold'] <= 0) {
                    $errors['config_threshold'] = 'Enter a positive threshold.';
                }
                break;
            case 'weight_based':
                if (!isset($config['per_kg']) || $config['per_kg'] < 0) {
                    $errors['config_per_kg'] = 'Enter a non-negative per-kg rate.';
                }
                break;
            case 'price_based':
                if (!isset($config['per_unit']) || $config['per_unit'] < 0) {
                    $errors['config_per_unit'] = 'Enter a non-negative per-unit rate.';
                }
                if (!isset($config['unit_size']) || $config['unit_size'] <= 0) {
                    $errors['config_unit_size'] = 'Enter a positive unit size.';
                }
                break;
        }

        return $errors;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function validateZone(array $data, ShippingRepository $repo, ?int $excludeId): array
    {
        $errors = [];

        $methodId = (int) ($data['method_id'] ?? 0);
        if ($methodId <= 0) {
            $errors['method_id'] = 'Select a shipping method.';
        } elseif ($repo->findMethod($methodId) === null) {
            $errors['method_id'] = 'The selected method was not found.';
        }

        $country = (string) ($data['country_code'] ?? '');
        if ($country === '') {
            $errors['country_code'] = 'Country is required.';
        } elseif (!preg_match('/^[A-Z]{2}$/', $country)) {
            $errors['country_code'] = 'Country must be a 2-letter ISO code.';
        }

        $region = (string) ($data['region'] ?? '');
        if (mb_strlen($region) > 60) {
            $errors['region'] = 'Region must be 60 characters or fewer.';
        }

        // Uniqueness: (method, country, region).
        if (empty($errors['method_id']) && empty($errors['country_code'])) {
            if ($repo->zoneExists($methodId, $country, $region, $excludeId)) {
                $errors['region'] = 'A zone already exists for this method, country, and region.';
            }
        }

        return $errors;
    }

    /**
     * Coerce a value to float, or return the fallback.
     */
    private function floatOr(mixed $value, ?float $fallback): ?float
    {
        if ($value === '' || $value === null) {
            return $fallback;
        }
        if (!is_numeric($value)) {
            return $fallback;
        }
        return (float) $value;
    }

    /**
     * Country list for the zone form. Same short list as the tax
     * controller.
     *
     * @return array<string,string>
     */
    private function commonCountries(): array
    {
        return [
            'DE' => 'Germany',
            'AT' => 'Austria',
            'CH' => 'Switzerland',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'PL' => 'Poland',
            'GB' => 'United Kingdom',
            'IE' => 'Ireland',
            'US' => 'United States',
            'CA' => 'Canada',
            'KE' => 'Kenya',
            'TZ' => 'Tanzania',
            'UG' => 'Uganda',
            'ZA' => 'South Africa',
        ];
    }
}
