<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Repositories\TaxRepository;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Admin tax management.
 *
 * Routes (all under /commerce/ca-admin/tax):
 *
 *   GET    /                              index
 *   POST   /classes                       storeClass
 *   POST   /classes/{id}                  updateClass
 *   GET    /rates/create                  showCreateRate
 *   POST   /rates                         storeRate
 *   GET    /rates/{id}/edit               showEditRate
 *   POST   /rates/{id}                    updateRate
 *   GET    /{type}/{id}/delete-info       deleteInfo
 *   POST   /{type}/{id}/delete            destroy
 *
 * where {type} is 'class' or 'rate'.
 *
 * Permissions:
 *   tax.view    index, edit views
 *   tax.manage  create, update, delete
 *
 * Design:
 *   - Class create/edit is a modal on the index page. Two
 *     fields (name, is_default). One form for both modes.
 *   - Rate create/edit is a separate page. Seven fields,
 *     including a country dropdown.
 *   - Delete is a modal, parameterized by type. The
 *     deleteInfo JSON endpoint returns blockers.
 *
 * Guards on delete:
 *   - A class with rates attached cannot be deleted.
 *   - A class with products assigned cannot be deleted.
 *   - The default class cannot be deleted.
 *   - A rate referenced by recent orders cannot be deleted.
 *
 * See docs/stages/008k.5_order-pricing-engine-plan.md.
 */
final class AdminTaxController extends AdminController
{
    private const VALID_TYPES = ['class', 'rate'];

    // =================================================================
    // GET /commerce/ca-admin/tax
    // =================================================================

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $taxes   = $this->container->get(TaxRepository::class);
        $session = $this->container->get(Session::class);

        $classes = $taxes->listClasses();
        $rates   = $taxes->listRates(); // all rates, grouped in view

        // Group rates by class_id for the inline tables.
        $ratesByClass = [];
        foreach ($rates as $r) {
            $cid = (int) ($r['tax_class_id'] ?? 0);
            $ratesByClass[$cid][] = $r;
        }

        return $this->render('admin.tax.index', [
            'title'        => 'Tax',
            'classes'      => $classes,
            'ratesByClass' => $ratesByClass,
            'errors'       => $session->getFlash('tax_errors', []),
            'old'          => $_SESSION['_old'] ?? [],
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/tax/classes
    // =================================================================

    /** @param array<string,string> $params */
    public function storeClass(Request $request, array $params = []): Response
    {
        $taxes   = $this->container->get(TaxRepository::class);
        $session = $this->container->get(Session::class);

        $data   = $this->collectClassInput($request);
        $errors = $this->validateClass($data, $taxes, null);

        if ($errors !== []) {
            $session->flash('tax_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/tax');
        }

        // If is_default, clear the flag on other classes first.
        if ($data['is_default']) {
            $taxes->clearDefaultFlag();
        }

        $taxes->createClass($data);

        $session->clearOldInput();
        $session->flash('success', 'Tax class created.');
        return Response::redirect('/commerce/ca-admin/tax');
    }

    // =================================================================
    // POST /commerce/ca-admin/tax/classes/{id}
    // =================================================================

    /** @param array<string,string> $params */
    public function updateClass(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid class reference.');
        }

        $taxes   = $this->container->get(TaxRepository::class);
        $session = $this->container->get(Session::class);

        $class = $taxes->findClass($id);
        if ($class === null) {
            return $this->fail('Tax class not found.');
        }

        $data   = $this->collectClassInput($request);
        $errors = $this->validateClass($data, $taxes, $id);

        if ($errors !== []) {
            $session->flash('tax_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/tax');
        }

        // If the class is being set as default, clear the flag
        // on all others first.
        if ($data['is_default']) {
            $taxes->clearDefaultFlag();
        }

        $taxes->updateClass($id, $data);

        $session->clearOldInput();
        $session->flash('success', 'Tax class updated.');
        return Response::redirect('/commerce/ca-admin/tax');
    }

    // =================================================================
    // GET /commerce/ca-admin/tax/rates/create
    // =================================================================

    /** @param array<string,string> $params */
    public function showCreateRate(Request $request, array $params = []): Response
    {
        $taxes   = $this->container->get(TaxRepository::class);
        $session = $this->container->get(Session::class);

        $classes = $taxes->listClasses();
        if ($classes === []) {
            $session->flash('error', 'Create a tax class first.');
            return Response::redirect('/commerce/ca-admin/tax');
        }

        // Pre-select a class when ?class_id= is passed (the
        // "New rate" button on the class card).
        $preClassId = (int) $request->query('class_id', 0);

        return $this->render('admin.tax.rate-form', [
            'title'    => 'New tax rate',
            'mode'     => 'create',
            'rate'     => null,
            'classes'  => $classes,
            'preClass' => $preClassId,
            'errors'   => $session->getFlash('tax_errors', []),
            'old'      => $_SESSION['_old'] ?? [],
            'countries' => $this->commonCountries(),
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/tax/rates
    // =================================================================

    /** @param array<string,string> $params */
    public function storeRate(Request $request, array $params = []): Response
    {
        $taxes   = $this->container->get(TaxRepository::class);
        $session = $this->container->get(Session::class);

        $data   = $this->collectRateInput($request);
        $errors = $this->validateRate($data, $taxes, null);

        if ($errors !== []) {
            $session->flash('tax_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/tax/rates/create');
        }

        $taxes->createRate($data);

        $session->clearOldInput();
        $session->flash('success', 'Tax rate created.');
        return Response::redirect('/commerce/ca-admin/tax');
    }

    // =================================================================
    // GET /commerce/ca-admin/tax/rates/{id}/edit
    // =================================================================

    /** @param array<string,string> $params */
    public function showEditRate(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid rate reference.');
        }

        $taxes   = $this->container->get(TaxRepository::class);
        $session = $this->container->get(Session::class);

        $rate = $taxes->findRate($id);
        if ($rate === null) {
            return $this->fail('Tax rate not found.');
        }

        return $this->render('admin.tax.rate-form', [
            'title'     => 'Edit tax rate',
            'mode'      => 'edit',
            'rate'      => $rate,
            'classes'   => $taxes->listClasses(),
            'preClass'  => (int) ($rate['tax_class_id'] ?? 0),
            'errors'    => $session->getFlash('tax_errors', []),
            'old'       => $_SESSION['_old'] ?? [],
            'countries' => $this->commonCountries(),
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/tax/rates/{id}
    // =================================================================

    /** @param array<string,string> $params */
    public function updateRate(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid rate reference.');
        }

        $taxes   = $this->container->get(TaxRepository::class);
        $session = $this->container->get(Session::class);

        $rate = $taxes->findRate($id);
        if ($rate === null) {
            return $this->fail('Tax rate not found.');
        }

        $data   = $this->collectRateInput($request);
        $errors = $this->validateRate($data, $taxes, $id);

        if ($errors !== []) {
            $session->flash('tax_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/tax/rates/' . $id . '/edit');
        }

        $taxes->updateRate($id, $data);

        $session->clearOldInput();
        $session->flash('success', 'Tax rate updated.');
        return Response::redirect('/commerce/ca-admin/tax');
    }

    // =================================================================
    // GET /commerce/ca-admin/tax/{type}/{id}/delete-info
    // =================================================================

    /** @param array<string,string> $params */
    public function deleteInfo(Request $request, array $params = []): Response
    {
        $type = (string) ($params['type'] ?? '');
        $id   = (int) ($params['id'] ?? 0);

        if (!in_array($type, self::VALID_TYPES, true) || $id <= 0) {
            return Response::json(['ok' => false, 'error' => 'Invalid reference'], 400);
        }

        $taxes = $this->container->get(TaxRepository::class);

        if ($type === 'class') {
            return $this->classDeleteInfo($taxes, $id);
        }
        return $this->rateDeleteInfo($taxes, $id);
    }

    // =================================================================
    // POST /commerce/ca-admin/tax/{type}/{id}/delete
    // =================================================================

    /** @param array<string,string> $params */
    public function destroy(Request $request, array $params = []): Response
    {
        $type = (string) ($params['type'] ?? '');
        $id   = (int) ($params['id'] ?? 0);

        if (!in_array($type, self::VALID_TYPES, true) || $id <= 0) {
            return $this->fail('Invalid reference.');
        }

        return $type === 'class'
            ? $this->destroyClass($id)
            : $this->destroyRate($id);
    }

    // =================================================================
    // Type-specific destroy handlers
    // =================================================================

    private function destroyClass(int $id): Response
    {
        $taxes   = $this->container->get(TaxRepository::class);
        $session = $this->container->get(Session::class);

        $class = $taxes->findClass($id);
        if ($class === null) {
            return $this->fail('Tax class not found.');
        }

        $blockers = $this->classDeleteBlockers($taxes, $id, $class);
        if ($blockers !== []) {
            $session->flash('error', implode(' ', $blockers));
            return Response::redirect('/commerce/ca-admin/tax');
        }

        $taxes->deleteClass($id);
        $session->flash('success', 'Tax class deleted.');
        return Response::redirect('/commerce/ca-admin/tax');
    }

    private function destroyRate(int $id): Response
    {
        $taxes   = $this->container->get(TaxRepository::class);
        $session = $this->container->get(Session::class);

        $rate = $taxes->findRate($id);
        if ($rate === null) {
            return $this->fail('Tax rate not found.');
        }

        $blockers = $this->rateDeleteBlockers($taxes, $id);
        if ($blockers !== []) {
            $session->flash('error', implode(' ', $blockers));
            return Response::redirect('/commerce/ca-admin/tax');
        }

        $taxes->deleteRate($id);
        $session->flash('success', 'Tax rate deleted.');
        return Response::redirect('/commerce/ca-admin/tax');
    }

    // =================================================================
    // JSON delete-info
    // =================================================================

    private function classDeleteInfo(TaxRepository $taxes, int $id): Response
    {
        $class = $taxes->findClass($id);
        if ($class === null) {
            return Response::json(['ok' => false, 'error' => 'Tax class not found'], 404);
        }

        $blockers = $this->classDeleteBlockers($taxes, $id, $class);

        return Response::json([
            'ok'         => true,
            'label'      => (string) $class['name'],
            'can_delete' => $blockers === [],
            'blockers'   => $blockers,
        ]);
    }

    private function rateDeleteInfo(TaxRepository $taxes, int $id): Response
    {
        $rate = $taxes->findRate($id);
        if ($rate === null) {
            return Response::json(['ok' => false, 'error' => 'Tax rate not found'], 404);
        }

        $blockers = $this->rateDeleteBlockers($taxes, $id);
        $label = (string) ($rate['country_code'] ?? '')
            . (($rate['state_code'] ?? '') !== '' ? ' / ' . (string) $rate['state_code'] : '')
            . ' — ' . (string) $rate['rate_percent'] . '%';

        return Response::json([
            'ok'         => true,
            'label'      => $label,
            'can_delete' => $blockers === [],
            'blockers'   => $blockers,
        ]);
    }

    /**
     * @param array<string,mixed> $class
     * @return array<int,string>
     */
    private function classDeleteBlockers(TaxRepository $taxes, int $id, array $class): array
    {
        $blockers = [];

        if (((int) ($class['is_default'] ?? 0)) === 1) {
            $blockers[] = 'This is the default tax class. Set another class as default first.';
        }

        $rates = $taxes->listRates($id);
        if ($rates !== []) {
            $blockers[] = 'This class has ' . count($rates)
                . ' rate' . (count($rates) === 1 ? '' : 's')
                . ' attached. Delete them first.';
        }

        $products = $taxes->countProductsForClass($id);
        if ($products > 0) {
            $blockers[] = $products . ' product' . ($products === 1 ? ' is' : 's are')
                . ' assigned to this class.';
        }

        return $blockers;
    }

    /**
     * @return array<int,string>
     */
    private function rateDeleteBlockers(TaxRepository $taxes, int $id): array
    {
        $blockers = [];

        $recent = $taxes->countRecentOrdersForRate($id, 30);
        if ($recent > 0) {
            $blockers[] = 'This rate was applied to ' . $recent
                . ' order' . ($recent === 1 ? '' : 's')
                . ' in the last 30 days. Disable or replace the rate instead.';
        }

        return $blockers;
    }

    // =================================================================
    // Helpers
    // =================================================================

    private function fail(string $message): Response
    {
        $this->container->get(Session::class)->flash('error', $message);
        return Response::redirect('/commerce/ca-admin/tax');
    }

    /**
     * @return array<string,mixed>
     */
    private function collectClassInput(Request $request): array
    {
        return [
            'name'       => trim((string) $request->input('name', '')),
            'is_default' => (bool) $request->input('is_default', false),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function collectRateInput(Request $request): array
    {
        return [
            'tax_class_id'        => (int) $request->input('tax_class_id', 0),
            'country_code'        => strtoupper(trim((string) $request->input('country_code', ''))),
            'state_code'          => trim((string) $request->input('state_code', '')),
            'rate_percent'        => trim((string) $request->input('rate_percent', '0')),
            'is_inclusive'        => (bool) $request->input('is_inclusive', false),
            'is_shipping_taxable' => (bool) $request->input('is_shipping_taxable', false),
            'priority'            => (int) $request->input('priority', 0),
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function validateClass(array $data, TaxRepository $taxes, ?int $excludeId): array
    {
        $errors = [];

        $name = (string) ($data['name'] ?? '');
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        } elseif (mb_strlen($name) > 80) {
            $errors['name'] = 'Name must be 80 characters or fewer.';
        }

        return $errors;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function validateRate(array $data, TaxRepository $taxes, ?int $excludeId): array
    {
        $errors = [];

        $classId = (int) ($data['tax_class_id'] ?? 0);
        if ($classId <= 0) {
            $errors['tax_class_id'] = 'Select a tax class.';
        } elseif ($taxes->findClass($classId) === null) {
            $errors['tax_class_id'] = 'The selected class was not found.';
        }

        $country = (string) ($data['country_code'] ?? '');
        if ($country === '') {
            $errors['country_code'] = 'Country is required.';
        } elseif (!preg_match('/^[A-Z]{2}$/', $country)) {
            $errors['country_code'] = 'Country must be a 2-letter ISO code.';
        }

        $state = (string) ($data['state_code'] ?? '');
        if (mb_strlen($state) > 10) {
            $errors['state_code'] = 'State code must be 10 characters or fewer.';
        }

        $rateStr = (string) ($data['rate_percent'] ?? '');
        if (!is_numeric($rateStr)) {
            $errors['rate_percent'] = 'Rate must be a number.';
        } else {
            $rate = (float) $rateStr;
            if ($rate < 0) {
                $errors['rate_percent'] = 'Rate cannot be negative.';
            } elseif ($rate > 100) {
                $errors['rate_percent'] = 'Rate cannot exceed 100%.';
            }
        }

        // Uniqueness: (class, country, state). The repository
        // has rateExists() which we can use.
        if (empty($errors['tax_class_id']) && empty($errors['country_code'])) {
            if ($taxes->rateExists($classId, $country, $state, $excludeId)) {
                $errors['rate_percent'] = 'A rate already exists for this class, country, and state.';
            }
        }

        return $errors;
    }

    /**
     * Country list for the rate form. A short list covers the
     * common cases. The form allows a manual entry fallback
     * for countries not listed.
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
