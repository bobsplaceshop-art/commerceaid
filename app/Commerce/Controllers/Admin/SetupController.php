<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Csrf;
use CorporateAid\Core\Database;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Services\StoreService;
use CorporateAid\Commerce\Support\Validator;

/**
 * Setup / settings hub.
 *
 * One tab per business concern. Routes:
 *   GET  /commerce/ca-admin/setup              → redirect to /business
 *   GET  /commerce/ca-admin/setup/{tab}        → show tab
 *   POST /commerce/ca-admin/setup/{tab}        → save tab
 *   GET  /commerce/ca-admin/setup/skip         → hide reminder for this session
 *
 * Tab inventory (8):
 *   business, currency, branding, checkout    (real - writes to commerce_stores)
 *   payments, shipping, tax, advanced         (form now, save stubbed)
 *
 * Adding a tab: add a `show*` and `save*` method, add the tab to
 * TAB_META, add a view file at admin/setup/{tab}.php.
 */
final class SetupController extends AdminController
{
    /**
     * Tab metadata - order, label, icon, whether it's real or a stub.
     * Rendered by the sub-nav partial.
     */
    private const TAB_META = [
        'business'   => ['label' => 'Business',          'icon' => 'bi-shop',           'real' => true],
        'currency'   => ['label' => 'Currency & Region', 'icon' => 'bi-currency-exchange','real' => true],
        'branding'   => ['label' => 'Branding',          'icon' => 'bi-palette',        'real' => true],
        'checkout'   => ['label' => 'Checkout',          'icon' => 'bi-bag-check',      'real' => true],
        'payments'   => ['label' => 'Payments',          'icon' => 'bi-credit-card',    'real' => false],
        'shipping'   => ['label' => 'Shipping',          'icon' => 'bi-truck',          'real' => false],
        'tax'        => ['label' => 'Tax',               'icon' => 'bi-percent',        'real' => false],
        'advanced'   => ['label' => 'Advanced',          'icon' => 'bi-gear-wide-connected','real' => false],
    ];

    /** Human-friendly labels for the mandatory missing fields. */
    private const FIELD_LABELS = [
        'name'                => 'Business name',
        'slug'                => 'Store slug',
        'default_currency_id' => 'Default currency',
        'timezone'            => 'Timezone',
        'country_code'        => 'Country',
        'store_missing'       => 'Store record',
    ];

    /** Route: GET /commerce/ca-admin/setup */
    public function index(Request $request, array $params = []): Response
    {
        return Response::redirect('/commerce/ca-admin/setup/business');
    }

    /** Route: GET /commerce/ca-admin/setup/skip */
    public function skip(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $session->set('ca_setup_banner_hidden_until', time() + 3600);
        return Response::redirect('/commerce/ca-admin');
    }

    /**
     * Route: GET /commerce/ca-admin/setup/{tab}
     * Dispatches to the appropriate show method.
     */
    public function show(Request $request, array $params = []): Response
    {
        $tab = (string) ($params['tab'] ?? 'business');

        if (!isset(self::TAB_META[$tab])) {
            return Response::redirect('/commerce/ca-admin/setup/business');
        }

        return match ($tab) {
            'business' => $this->showBusiness(),
            'currency' => $this->showCurrency(),
            'branding' => $this->showBranding(),
            'checkout' => $this->showCheckout(),
            'payments' => $this->showPayments(),
            'shipping' => $this->showShipping(),
            'tax'      => $this->showTax(),
            'advanced' => $this->showAdvanced(),
        };
    }

    /**
     * Route: POST /commerce/ca-admin/setup/{tab}
     */
    public function save(Request $request, array $params = []): Response
    {
        $tab = (string) ($params['tab'] ?? 'business');

        if (!isset(self::TAB_META[$tab])) {
            return Response::redirect('/commerce/ca-admin/setup/business');
        }

        return match ($tab) {
            'business' => $this->saveBusiness($request),
            'currency' => $this->saveCurrency($request),
            'branding' => $this->saveBranding($request),
            'checkout' => $this->saveCheckout($request),
            'payments' => $this->saveStub($tab, 'Payment configuration'),
            'shipping' => $this->saveStub($tab, 'Shipping configuration'),
            'tax'      => $this->saveStub($tab, 'Tax configuration'),
            'advanced' => $this->saveStub($tab, 'Advanced configuration'),
        };
    }

    // =================================================================
    // Tab handlers - real tabs
    // =================================================================

    private function showBusiness(): Response
    {
        return $this->renderTab('business', [
            'title' => 'Setup - Business',
        ]);
    }

    private function showCurrency(): Response
    {
        return $this->renderTab('currency', [
            'title'      => 'Setup - Currency & Region',
            'currencies' => $this->availableCurrencies(),
        ]);
    }

    private function showBranding(): Response
    {
        return $this->renderTab('branding', [
            'title' => 'Setup - Branding',
        ]);
    }

    private function showCheckout(): Response
    {
        return $this->renderTab('checkout', [
            'title' => 'Setup - Checkout',
        ]);
    }

    private function saveBusiness(Request $request): Response
    {
        return $this->saveStoreFields($request, [
            'name'       => ['required' => true,  'label' => 'Business name', 'max_length' => 200],
            'slug'       => ['required' => true,  'label' => 'Store slug',    'validator' => 'slug', 'max_length' => 80],
            'tagline'    => ['required' => false, 'max_length' => 200],
            'legal_name' => ['required' => false, 'max_length' => 200],
        ], 'business');
    }

    private function saveCurrency(Request $request): Response
    {
        return $this->saveStoreFields($request, [
            'default_currency_id' => ['required' => true,  'label' => 'Default currency', 'type' => 'int'],
            'country_code'        => ['required' => true,  'label' => 'Country',          'validator' => 'countryCode'],
            'timezone'            => ['required' => true,  'label' => 'Timezone',         'validator' => 'timezone'],
            'locale'              => ['required' => false, 'max_length' => 10],
            'date_format'         => ['required' => false, 'max_length' => 20],
            'number_format'       => ['required' => false, 'max_length' => 20],
            'weight_unit'         => ['required' => false, 'in' => ['kg', 'lb']],
            'dimension_unit'      => ['required' => false, 'in' => ['cm', 'in']],
        ], 'currency');
    }

    private function saveBranding(Request $request): Response
    {
        return $this->saveStoreFields($request, [
            'primary_color'        => ['required' => false, 'validator' => 'hexColor'],
            'secondary_color'      => ['required' => false, 'validator' => 'hexColor'],
            'pwa_name'             => ['required' => false, 'max_length' => 100],
            'pwa_short_name'       => ['required' => false, 'max_length' => 50],
            'pwa_theme_color'      => ['required' => false, 'validator' => 'hexColor'],
            'pwa_background_color' => ['required' => false, 'validator' => 'hexColor'],
        ], 'branding');
    }

    private function saveCheckout(Request $request): Response
    {
        return $this->saveStoreFields($request, [
            'allow_guest_checkout' => ['required' => false, 'type' => 'bool'],
            'require_phone'        => ['required' => false, 'type' => 'bool'],
            'require_company'      => ['required' => false, 'type' => 'bool'],
        ], 'checkout');
    }

    // =================================================================
    // Tab handlers - stubs (form renders, save is a no-op with feedback)
    // =================================================================

    private function showPayments(): Response
    {
        return $this->renderTab('payments', [
            'title'     => 'Setup - Payments',
            'providers' => $this->availablePaymentProviders(),
        ]);
    }

    private function showShipping(): Response
    {
        return $this->renderTab('shipping', [
            'title' => 'Setup - Shipping',
        ]);
    }

    private function showTax(): Response
    {
        return $this->renderTab('tax', [
            'title' => 'Setup - Tax',
        ]);
    }

    private function showAdvanced(): Response
    {
        return $this->renderTab('advanced', [
            'title' => 'Setup - Advanced',
        ]);
    }

    private function saveStub(string $tab, string $label): Response
    {
        $session = $this->container->get(Session::class);
        $session->flash('info', $label . ' received. Persistent storage ships in a later stage.');
        return Response::redirect('/commerce/ca-admin/setup/' . $tab);
    }

    // =================================================================
    // Shared helpers
    // =================================================================

    /**
     * Common render pipeline for every setup tab.
     * Injects the tab nav metadata, progress, and store row.
     */
    private function renderTab(string $tab, array $data = []): Response
    {
        $storeService = $this->container->get(StoreService::class);
        $context      = $this->container->get(StoreContext::class);
        $session      = $this->container->get(Session::class);

        $storeId = $context->has() ? $context->id() : 0;
        $store   = $storeId > 0 ? $storeService->resolveById($storeId) : null;

        $missing = $storeId > 0
            ? $storeService->mandatoryFieldsMissing($storeId)
            : ['store_missing'];

        $data['store']         = $store;
        $data['missing']       = $missing;
        $data['missingLabels'] = array_map(
            fn($k) => self::FIELD_LABELS[$k] ?? $k,
            $missing
        );
        $data['tabMeta']       = self::TAB_META;
        $data['currentTab']    = $tab;
        $data['setupComplete'] = $this->isSetupComplete();
        $data['csrf']          = $this->container->get(Csrf::class)->token();

        // Validation errors and previous input from a failed save.
        // These come from the session (set by saveStoreFields on
        // validation failure) and are consumed once, then cleared.
        $data['errors'] = $session->getFlash('setup_errors', []);

        // Old input: read once, then clear so a page refresh doesn't
        // re-populate stale values.
        $data['old'] = $_SESSION['_old'] ?? [];
        $session->clearOldInput();

        // Success and info flashes are consumed by the layout.
        // Nothing more to do here for those.

        return $this->render('admin.setup.index', $data);
    }

    /**
     * Generic field saver for real tabs.
     *
     * @param array<string, array> $rules Map of field name -> rule set
     */
    private function saveStoreFields(Request $request, array $rules, string $tab): Response
    {
        $session      = $this->container->get(Session::class);
        $storeService = $this->container->get(StoreService::class);
        $context      = $this->container->get(StoreContext::class);

        $storeId = $context->id();
        $input   = [];
        $errors  = [];

        foreach ($rules as $field => $rule) {
            $raw = $request->input($field, null);

            // Type casting
            if (($rule['type'] ?? null) === 'bool') {
                $value = (int) ($raw ? 1 : 0);
            } elseif (($rule['type'] ?? null) === 'int') {
                $value = (int) $raw;
            } else {
                $value = is_string($raw) ? trim($raw) : $raw;
            }

            // Transform
            if (($rule['transform'] ?? null) === 'upper' && is_string($value)) {
                $value = strtoupper($value);
            }

            // Required check
            $isEmpty = $value === null || $value === '' || (($rule['type'] ?? null) === 'int' && $value === 0);
            if (!empty($rule['required']) && $isEmpty) {
                $errors[$field] = ($rule['label'] ?? $field) . ' is required.';
                continue;
            }

            // Skip empty optional fields
            if ($isEmpty && empty($rule['required'])) {
                continue;
            }

            $label = $rule['label'] ?? $field;

            // Length check — opt-in via 'max_length'
            if (!empty($rule['max_length']) && is_string($value)) {
                if ($e = Validator::maxLength($value, (int) $rule['max_length'], $label)) {
                    $errors[$field] = $e;
                    continue;
                }
            }

            // Format validator — opt-in via 'validator' key
            // Supported: 'url', 'email', 'timezone', 'slug', 'hexColor', 'countryCode'
            if (!empty($rule['validator']) && is_string($value)) {
                $validatorFn = $rule['validator'];
                $e = match ($validatorFn) {
                    'url'         => Validator::url($value, $label),
                    'email'       => Validator::email($value, $label),
                    'timezone'    => Validator::timezone($value, $label),
                    'slug'        => Validator::slug($value, (int) ($rule['max_length'] ?? 120), $label),
                    'hexColor'    => Validator::hexColor($value, $label),
                    'countryCode' => Validator::countryCode($value, $label),
                    default       => null,
                };
                if ($e !== null) {
                    $errors[$field] = $e;
                    continue;
                }
            }

            // Legacy pattern support (still honoured)
            if (!empty($rule['pattern']) && is_string($value)) {
                if (!preg_match($rule['pattern'], $value)) {
                    $errors[$field] = $label . ' format is invalid.';
                    continue;
                }
            }

            // Allowed values
            if (!empty($rule['in']) && !in_array($value, $rule['in'], true)) {
                $errors[$field] = $label . ' has an invalid value.';
                continue;
            }

            $input[$field] = $value;
        }

        if ($errors !== []) {
            $session->flash('setup_errors', $errors);
            $session->flashOldInput($input);
            return Response::redirect('/commerce/ca-admin/setup/' . $tab);
        }

        $storeService->updateStore($storeId, $input);
        $session->flash('success', 'Saved.');
        $session->clearOldInput();

        if ($storeService->isSetupComplete($storeId)) {
            $session->set('ca_setup_banner_active', false);
        }

        return Response::redirect('/commerce/ca-admin/setup/' . $tab);
    }


    private function availableCurrencies(): array
    {
        return $this->container->get(Database::class)
            ->select("SELECT `id`, `code`, `name`, `symbol`, `decimal_places` FROM `core_currencies` WHERE `is_active` = 1 ORDER BY `code`");
    }

    /**
     * Payment provider catalog. Right now this is a static list.
     * When commerce_store_payment_methods is wired, this will read
     * the store's enabled/disabled state from that table.
     */
    private function availablePaymentProviders(): array
    {
        return [
            ['key' => 'mpesa',   'label' => 'M-Pesa',        'icon' => 'bi-phone',       'desc' => 'Safaricom mobile money (Kenya)'],
            ['key' => 'stripe',  'label' => 'Stripe',        'icon' => 'bi-credit-card', 'desc' => 'Cards and digital wallets (global)'],
            ['key' => 'paypal',  'label' => 'PayPal',        'icon' => 'bi-paypal',      'desc' => 'PayPal account and cards'],
            ['key' => 'manual',  'label' => 'Manual',        'icon' => 'bi-pencil',      'desc' => 'Record payments taken offline'],
            ['key' => 'cod',     'label' => 'Cash on Delivery','icon' => 'bi-cash-coin', 'desc' => 'Collect payment on delivery'],
            ['key' => 'bank',    'label' => 'Bank Transfer', 'icon' => 'bi-bank',        'desc' => 'Direct bank transfer'],
        ];
    }
}
