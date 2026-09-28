<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Exceptions\CheckoutException;
use CorporateAid\Commerce\Models\Customer;
use CorporateAid\Commerce\Repositories\CustomerAddressRepository;
use CorporateAid\Commerce\Repositories\CustomerGroupRepository;
use CorporateAid\Commerce\Repositories\LegalPageRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Services\CartService;
use CorporateAid\Commerce\Services\CheckoutService;
use CorporateAid\Commerce\Services\CustomerAuthService;
use CorporateAid\Commerce\Support\StorefrontController;

/**
 * Storefront checkout. Five steps plus confirmation.
 *
 *   GET  /checkout              step 1 - cart review
 *   GET  /checkout/info         step 2 - customer info form
 *   POST /checkout/info         save info
 *   GET  /checkout/shipping     step 3 - shipping address
 *   POST /checkout/shipping     save address
 *   GET  /checkout/payment      step 4 - payment + legal checkboxes
 *   POST /checkout/payment      save payment method + checkboxes
 *   GET  /checkout/confirm      step 5 - read-only review
 *   POST /checkout/place        call CheckoutService, redirect
 *   GET  /checkout/confirmation/{orderNumber}  confirmation page
 *
 * All in-progress state lives under $_SESSION['checkout'] in the
 * customer session. Each step reads that array, validates, writes
 * back. The customer can navigate back without losing entered data.
 *
 * The controller never writes to the admin session. Every flash
 * write and every checkout-state read resolves 'customer_session'
 * by string key. See docs/stages/008e_checkout-plan.md and the
 * ROADMAP entry "Storefront Flash Session Mismatch" for why.
 */
final class CheckoutController extends StorefrontController
{
    private const SESSION_KEY = 'checkout';

    // =================================================================
    // Step 1 - Cart review
    // =================================================================

    /** @param array<string,string> $params */
    public function review(Request $request, array $params = []): Response
    {
        $cart = $this->container->get(CartService::class);
        $lines = $cart->items();

        if ($lines === []) {
            return Response::redirect('/cart');
        }

        $blocking = $this->blockingWarnings($lines);

        return $this->render('storefront.checkout.review', [
            'title'    => 'Checkout',
            'step'     => 1,
            'lines'    => $lines,
            'subtotal' => $cart->subtotal(),
            'blocking' => $blocking,
        ]);
    }

    // =================================================================
    // Step 2 - Customer info
    // =================================================================

    /** @param array<string,string> $params */
    public function info(Request $request, array $params = []): Response
    {
        $state = $this->state();
        $customer = $this->currentCustomer();

        // Pre-fill from the customer record if this is the first visit.
        if ($state['customer_data'] === [] && $customer !== null) {
            $state['customer_data'] = [
                'first_name' => $customer->firstName ?? '',
                'last_name'  => $customer->lastName ?? '',
                'phone'      => $customer->phone ?? '',
                'company'    => $customer->company ?? '',
            ];
            $state['guest_email'] = $customer->email;
            $this->saveState($state);
        }

        return $this->render('storefront.checkout.info', [
            'title'    => 'Your details',
            'step'     => 2,
            'state'    => $state,
            'customer' => $customer,
            'errors'   => $this->pullErrors('info'),
        ]);
    }

    /** @param array<string,string> $params */
    public function saveInfo(Request $request, array $params = []): Response
    {
        $customer = $this->currentCustomer();

        $firstName = trim((string) $request->input('first_name', ''));
        $lastName  = trim((string) $request->input('last_name', ''));
        $email     = trim((string) $request->input('email', ''));
        $phone     = trim((string) $request->input('phone', ''));
        $company   = trim((string) $request->input('company', ''));

        $errors = [];
        if ($firstName === '') { $errors['first_name'] = 'First name is required.'; }
        if ($lastName === '')  { $errors['last_name']  = 'Last name is required.'; }
        if ($customer === null) {
            if ($email === '') {
                $errors['email'] = 'Email is required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Please enter a valid email address.';
            }
        }

        if ($errors !== []) {
            $this->flashErrors('info', $errors);
            return Response::redirect('/checkout/info');
        }

        $state = $this->state();
        $state['customer_data'] = [
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'phone'      => $phone,
            'company'    => $company,
        ];
        $state['guest_email'] = $customer === null ? $email : $customer->email;
        $state['completed_info'] = true;
        $this->saveState($state);

        return Response::redirect('/checkout/shipping');
    }

    // =================================================================
    // Step 3 - Shipping address
    // =================================================================

    /** @param array<string,string> $params */
    public function shipping(Request $request, array $params = []): Response
    {
        $state = $this->state();
        $customer = $this->currentCustomer();

        // Guard: can't reach step 3 without completing step 2.
        if (empty($state['completed_info'])) {
            return Response::redirect('/checkout/info');
        }

        $savedAddresses = [];
        if ($customer !== null) {
            $addressRepo = $this->container->get(CustomerAddressRepository::class);
            $savedAddresses = $addressRepo->listForCustomer($customer->id);
        }

        return $this->render('storefront.checkout.shipping', [
            'title'          => 'Shipping',
            'step'           => 3,
            'state'          => $state,
            'savedAddresses' => $savedAddresses,
            'errors'         => $this->pullErrors('shipping'),
        ]);
    }

    /** @param array<string,string> $params */
    public function saveShipping(Request $request, array $params = []): Response
    {
        $state = $this->state();

        // Shipping address - either picked from saved, or entered fresh.
        $savedId = (int) $request->input('saved_shipping_address_id', 0);
        $shippingAddress = $this->buildAddressFromRequest($request, 'shipping', $savedId);

        // Billing address - same as shipping, or separate.
        $billingSame = (bool) $request->input('billing_same', true);
        if ($billingSame) {
            $billingAddress = $shippingAddress;
        } else {
            $savedBillingId = (int) $request->input('saved_billing_address_id', 0);
            $billingAddress = $this->buildAddressFromRequest($request, 'billing', $savedBillingId);
        }

        $errors = [];
        if ($shippingAddress === null) {
            $errors['shipping'] = 'Please provide a shipping address.';
        }
        if ($billingAddress === null) {
            $errors['billing'] = 'Please provide a billing address.';
        }

        if ($errors !== []) {
            $this->flashErrors('shipping', $errors);
            return Response::redirect('/checkout/shipping');
        }

        $state['shipping_address'] = $shippingAddress;
        $state['billing_address']  = $billingAddress;
        $state['completed_shipping'] = true;
        $this->saveState($state);

        return Response::redirect('/checkout/payment');
    }

    // =================================================================
    // Step 4 - Payment + legal
    // =================================================================

    /** @param array<string,string> $params */
    public function payment(Request $request, array $params = []): Response
    {
        $state = $this->state();

        if (empty($state['completed_shipping'])) {
            return Response::redirect('/checkout/shipping');
        }

        $customer = $this->currentCustomer();
        $availableMethods = $this->availablePaymentMethods($customer);

        return $this->render('storefront.checkout.payment', [
            'title'            => 'Payment',
            'step'             => 4,
            'state'            => $state,
            'customer'         => $customer,
            'availableMethods' => $availableMethods,
            'errors'           => $this->pullErrors('payment'),
        ]);
    }

    /** @param array<string,string> $params */
    public function savePayment(Request $request, array $params = []): Response
    {
        $state = $this->state();

        if (empty($state['completed_shipping'])) {
            return Response::redirect('/checkout/shipping');
        }

        $customer = $this->currentCustomer();
        $availableMethods = $this->availablePaymentMethods($customer);

        $method = (string) $request->input('payment_method', '');
        $poNumber = trim((string) $request->input('po_number', ''));
        $agbAccepted = (bool) $request->input('agb_accepted', false);
        $widerrufAccepted = (bool) $request->input('widerruf_accepted', false);

        $errors = [];

        if (!isset($availableMethods[$method])) {
            $errors['payment_method'] = 'Please select a payment method.';
        }

        if ($method === 'purchase_order' && $poNumber === '') {
            $errors['po_number'] = 'Please enter a purchase order number.';
        }

        if (!$agbAccepted) {
            $errors['agb_accepted'] = 'You must accept the Terms and Conditions.';
        }

        if (!$widerrufAccepted) {
            $errors['widerruf_accepted'] = 'You must accept the withdrawal policy.';
        }

        if ($errors !== []) {
            $this->flashErrors('payment', $errors);
            return Response::redirect('/checkout/payment');
        }

        $state['payment_method'] = $method;
        $state['po_number'] = $poNumber;
        $state['agb_accepted'] = true;
        $state['widerruf_accepted'] = true;
        $state['completed_payment'] = true;
        $this->saveState($state);

        return Response::redirect('/checkout/confirm');
    }

    // =================================================================
    // Step 5 - Confirm
    // =================================================================

    /** @param array<string,string> $params */
    public function confirm(Request $request, array $params = []): Response
    {
        $state = $this->state();

        if (empty($state['completed_payment'])) {
            return Response::redirect('/checkout/payment');
        }

        $cart = $this->container->get(CartService::class);
        $lines = $cart->items();
        if ($lines === []) {
            return Response::redirect('/cart');
        }

        return $this->render('storefront.checkout.confirm', [
            'title'    => 'Confirm your order',
            'step'     => 5,
            'state'    => $state,
            'lines'    => $lines,
            'subtotal' => $cart->subtotal(),
            'errors'   => $this->pullErrors('confirm'),
        ]);
    }

    // =================================================================
    // Place the order
    // =================================================================

    /** @param array<string,string> $params */
    public function place(Request $request, array $params = []): Response
    {
        $state = $this->state();

        if (empty($state['completed_payment'])) {
            return Response::redirect('/checkout/payment');
        }

        $customer = $this->currentCustomer();

        try {
            $service = $this->container->get(CheckoutService::class);
            $order = $service->placeOrder([
                'customer'             => $customer,
                'guest_email'          => $state['guest_email'] ?? '',
                'billing_address'      => $state['billing_address'] ?? null,
                'shipping_address'     => $state['shipping_address'] ?? null,
                'payment_provider_key' => $state['payment_method'] ?? '',
                'po_number'            => $state['po_number'] ?? null,
                'customer_note'        => $state['customer_note'] ?? null,
                'ip'                   => $request->ip(),
                'user_agent'           => $request->userAgent(),
            ]);
        } catch (CheckoutException $e) {
            $this->flashErrors('confirm', ['_general' => $e->getMessage()]);
            return Response::redirect('/checkout/confirm');
        }

        // Clear the checkout state - the order is placed.
        $session = $this->customerSession();
        $session->forget(self::SESSION_KEY);

        return Response::redirect('/checkout/confirmation/' . $order->orderNumber);
    }

    // =================================================================
    // Confirmation
    // =================================================================

    /** @param array<string,string> $params */
    public function confirmation(Request $request, array $params = []): Response
    {
        $orderNumber = (string) ($params['orderNumber'] ?? '');
        if ($orderNumber === '') {
            return Response::redirect('/');
        }

        $orders = $this->container->get(OrderRepository::class);
        $order = $orders->findByNumber($orderNumber);

        if ($order === null) {
            return Response::redirect('/');
        }

        return $this->render('storefront.checkout.confirmation', [
            'title' => 'Order confirmed',
            'order' => $order,
        ]);
    }

    // =================================================================
    // Legal pages (public route)
    // =================================================================

    /** @param array<string,string> $params */
    public function legalPage(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        if ($slug === '') {
            return Response::redirect('/');
        }

        $pages = $this->container->get(LegalPageRepository::class);
        $page = $pages->findBySlug($slug);

        if ($page === null) {
            return Response::redirect('/');
        }

        return $this->render('storefront.legal.show', [
            'title' => $page->title,
            'page'  => $page,
        ]);
    }

    // =================================================================
    // Helpers
    // =================================================================

    /**
     * Read the checkout state from the customer session. Returns
     * a default-shaped array if nothing is stored yet.
     *
     * @return array<string,mixed>
     */
    private function state(): array
    {
        $session = $this->customerSession();
        $stored = $session->get(self::SESSION_KEY, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        return array_merge([
            'customer_data'       => [],
            'guest_email'         => '',
            'shipping_address'    => null,
            'billing_address'     => null,
            'payment_method'      => '',
            'po_number'           => '',
            'customer_note'       => '',
            'completed_info'      => false,
            'completed_shipping'  => false,
            'completed_payment'   => false,
        ], $stored);
    }

    /**
     * Write the checkout state back to the customer session.
     *
     * @param array<string,mixed> $state
     */
    private function saveState(array $state): void
    {
        $this->customerSession()->set(self::SESSION_KEY, $state);
    }

    /**
     * Resolve the customer session by string key. NOT
     * Session::class - that binding is the admin session.
     */
    private function customerSession(): Session
    {
        return $this->container->get('customer_session');
    }

    /**
     * The logged-in customer, or null for guests.
     */
    private function currentCustomer(): ?Customer
    {
        try {
            $auth = $this->container->get(CustomerAuthService::class);
            return $auth->user();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Validation errors written by the previous POST, consumed
     * on this GET.
     *
     * @return array<string,string>
     */
    private function pullErrors(string $step): array
    {
        $key = 'checkout_' . $step . '_errors';
        $errors = $this->customerSession()->getFlash($key, []);
        return is_array($errors) ? $errors : [];
    }

    /**
     * Write validation errors so the next GET for this step
     * renders them.
     *
     * @param array<string,string> $errors
     */
    private function flashErrors(string $step, array $errors): void
    {
        $key = 'checkout_' . $step . '_errors';
        $this->customerSession()->flash($key, $errors);
    }

    /**
     * Blocking warnings on the cart (price change, out of stock,
     * product removed). Blocks checkout until the customer
     * resolves them.
     *
     * @param array<int,\CorporateAid\Commerce\Models\CartLineItem> $lines
     * @return array<int,string>
     */
    private function blockingWarnings(array $lines): array
    {
        $warnings = [];
        foreach ($lines as $line) {
            if ($line->productRemoved) {
                $warnings[] = $line->item->productName . ' is no longer available.';
            } elseif ($line->outOfStock) {
                $warnings[] = $line->item->productName . ' is out of stock.';
            } elseif ($line->priceChanged) {
                $warnings[] = $line->item->productName . ' has a new price.';
            }
        }
        return $warnings;
    }

    /**
     * Build a payment-method map. Keys are the provider key,
     * values are display labels. Purchase order is only shown to
     * approved B2B customers whose group has net terms.
     *
     * @return array<string,string>
     */
    private function availablePaymentMethods(?Customer $customer): array
    {
        $methods = [
            'cod'           => 'Cash on delivery',
            'bank_transfer' => 'Bank transfer',
        ];

        if ($customer instanceof Customer && $customer->canCheckout()) {
            $group = null;
            if ($customer->customerGroupId !== null) {
                $groups = $this->container->get(CustomerGroupRepository::class);
                $group = $groups->findById($customer->customerGroupId);
            }
            if ($group !== null && $group->hasNetTerms()) {
                $methods['purchase_order'] = 'Purchase order';
            }
        }

        return $methods;
    }

    /**
     * Build an address array from the request. Returns null if
     * the address is incomplete. When $savedId is provided, loads
     * the address from the customer's address book instead.
     *
     * @return array<string,mixed>|null
     */
    private function buildAddressFromRequest(
        Request $request,
        string $prefix,
        int $savedId
    ): ?array {
        // Saved address picked from the list.
        if ($savedId > 0) {
            $repo = $this->container->get(CustomerAddressRepository::class);
            $addr = $repo->findById($savedId);
            if ($addr !== null) {
                return [
                    'first_name'     => $addr->firstName,
                    'last_name'      => $addr->lastName,
                    'company'        => $addr->company,
                    'address_line_1' => $addr->addressLine1,
                    'address_line_2' => $addr->addressLine2,
                    'city'           => $addr->city,
                    'state'          => $addr->state,
                    'postal_code'    => $addr->postalCode,
                    'country_code'   => $addr->countryCode,
                    'phone'          => $addr->phone,
                    'email'          => $addr->email,
                ];
            }
        }

        // Fresh entry from the form.
        $required = ['address_line_1', 'city', 'postal_code', 'country_code'];
        $address = [
            'first_name'     => trim((string) $request->input($prefix . '_first_name', '')),
            'last_name'      => trim((string) $request->input($prefix . '_last_name', '')),
            'company'        => trim((string) $request->input($prefix . '_company', '')),
            'address_line_1' => trim((string) $request->input($prefix . '_address_line_1', '')),
            'address_line_2' => trim((string) $request->input($prefix . '_address_line_2', '')),
            'city'           => trim((string) $request->input($prefix . '_city', '')),
            'state'          => trim((string) $request->input($prefix . '_state', '')),
            'postal_code'    => trim((string) $request->input($prefix . '_postal_code', '')),
            'country_code'   => trim((string) $request->input($prefix . '_country_code', 'DE')),
            'phone'          => trim((string) $request->input($prefix . '_phone', '')),
            'email'          => trim((string) $request->input($prefix . '_email', '')),
        ];

        foreach ($required as $field) {
            if ($address[$field] === '') {
                return null;
            }
        }

        return $address;
    }
}
