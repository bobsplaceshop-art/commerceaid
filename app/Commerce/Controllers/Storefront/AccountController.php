<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Commerce\Models\CustomerAddress;
use CorporateAid\Commerce\Repositories\CustomerAddressRepository;
use CorporateAid\Commerce\Repositories\CustomerGroupRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Repositories\CustomerRepository;
use CorporateAid\Commerce\Services\CustomerAuthService;
use CorporateAid\Commerce\Support\StorefrontController;
use CorporateAid\Commerce\Support\Validator;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Platform\Support\Hasher;

/**
 * Customer account pages.
 *
 * All routes are guarded by CustomerAuthMiddleware ('customer.auth'
 * alias). The customer is guaranteed non-null in every method.
 *
 * Routes (all under /account):
 *   GET  /account                    dashboard
 *   GET  /account/orders             order history (stub until 008E)
 *   GET  /account/addresses          address list
 *   GET  /account/addresses/create   new address form
 *   POST /account/addresses          store new address
 *   GET  /account/addresses/{id}/edit  edit form
 *   POST /account/addresses/{id}     update address
 *   POST /account/addresses/{id}/delete  delete
 *   POST /account/addresses/{id}/default set as default
 *   GET  /account/password           change password form
 *   POST /account/password           change password action
 *
 * IDOR defense: every address operation goes through
 * findForCustomer($id, $customer->id), which returns null if the
 * address belongs to a different customer. Route params alone are
 * never trusted.
 *
 * See docs/stages/008c_customer-auth-plan.md.
 */
final class AccountController extends StorefrontController
{
    // =================================================================
    // Dashboard
    // =================================================================

    /** @param array<string,string> $params */
    public function dashboard(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $customer     = $customerAuth->user();
        $groups       = $this->container->get(CustomerGroupRepository::class);

        // Resolve the customer's group for the dashboard. Null when
        // the customer has no group assigned.
        $group = null;
        if ($customer !== null && $customer->customerGroupId !== null) {
            $group = $groups->findById($customer->customerGroupId);
        }

        // Guest order claim: does this customer have any orders
        // marked as claimable that they have not yet decided on?
        // Fast COUNT check. Renders a banner on the dashboard when
        // true, unless the customer has already dismissed it for
        // this session.
        $hasClaimable = false;
        if ($customer !== null) {
            try {
                $orders = $this->container->get(OrderRepository::class);
                $hasClaimable = $orders->hasClaimableOrders($customer->id);
            } catch (\Throwable) {
                // A broken claim read should not break the dashboard.
                $hasClaimable = false;
            }
        }

        // Session-level dismissal.
        $dismissed = false;
        try {
            $dismissed = (bool) $this->container->get(Session::class)
                ->get('guest_claim_dismissed', false);
        } catch (\Throwable) {
            $dismissed = false;
        }

        return $this->render('storefront.account.dashboard', [
            'title'         => 'My Account',
            'customer'      => $customer,
            'hasClaimable'  => $hasClaimable && !$dismissed,
            'group'    => $group,
        ]);
    }

    // =================================================================
    // Orders (stub until 008E)
    // =================================================================

    /** @param array<string,string> $params */
    public function orders(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $customer = $customerAuth->user();

        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $orders = $this->container->get(OrderRepository::class);
        $page   = max(1, (int) $request->query('page', 1));
        $result = $orders->paginate(
            ['customer_id' => $customer->id],
            $page,
            20
        );

        return $this->render('storefront.account.orders', [
            'title'    => 'My Orders',
            'customer' => $customer,
            'result'   => $result,
        ]);
    }

    // =================================================================
    // Addresses - list
    // =================================================================

    /** @param array<string,string> $params */
    public function addresses(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $customer     = $customerAuth->user();
        $addresses    = $this->container->get(CustomerAddressRepository::class);
        $session      = $this->container->get(Session::class);

        $list = $customer !== null
            ? $addresses->listForCustomer($customer->id)
            : [];

        return $this->render('storefront.account.addresses', [
            'title'     => 'My Addresses',
            'customer'  => $customer,
            'addresses' => $list,
            'flashOk'   => $session->getFlash('address_success'),
        ]);
    }

    // =================================================================
    // Addresses - create
    // =================================================================

    /** @param array<string,string> $params */
    public function showCreateAddress(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $session      = $this->container->get(Session::class);

        return $this->render('storefront.account.address-form', [
            'title'    => 'Add an address',
            'customer' => $customerAuth->user(),
            'address'  => null,
            'mode'     => 'create',
            'errors'   => $session->getFlash('address_errors', []),
            'old'      => $_SESSION['_old'] ?? [],
        ]);
    }

    /** @param array<string,string> $params */
    public function createAddress(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $customer     = $customerAuth->user();
        $repo         = $this->container->get(CustomerAddressRepository::class);
        $session      = $this->container->get(Session::class);

        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $data   = $this->collectAddressInput($request);
        $errors = $this->validateAddress($data);

        if ($errors !== []) {
            $session->flash('address_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/account/addresses/create');
        }

        $address = $this->hydrateAddress(new CustomerAddress(), $data);
        $address->customerId = $customer->id;
        $address->storeId    = $customer->storeId;

        // First address becomes the default automatically.
        if ($repo->countForCustomer($customer->id) === 0) {
            $address->isDefault = true;
        }

        $addressId = $repo->create($address);
        $address->id = $addressId;

        if ($address->isDefault) {
            $repo->setDefault($address);
        }

        $session->clearOldInput();
        $session->flash('address_success', 'Address added.');
        return Response::redirect('/account/addresses');
    }

    // =================================================================
    // Addresses - edit / update
    // =================================================================

    /** @param array<string,string> $params */
    public function showEditAddress(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $customer     = $customerAuth->user();
        $repo         = $this->container->get(CustomerAddressRepository::class);
        $session      = $this->container->get(Session::class);

        $id = (int) ($params['id'] ?? 0);
        $address = $customer !== null
            ? $repo->findForCustomer($id, $customer->id)
            : null;

        if ($address === null) {
            $session->flash('error', 'Address not found.');
            return Response::redirect('/account/addresses');
        }

        return $this->render('storefront.account.address-form', [
            'title'    => 'Edit address',
            'customer' => $customer,
            'address'  => $address,
            'mode'     => 'edit',
            'errors'   => $session->getFlash('address_errors', []),
            'old'      => $_SESSION['_old'] ?? [],
        ]);
    }

    /** @param array<string,string> $params */
    public function updateAddress(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $customer     = $customerAuth->user();
        $repo         = $this->container->get(CustomerAddressRepository::class);
        $session      = $this->container->get(Session::class);

        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $id = (int) ($params['id'] ?? 0);
        $existing = $repo->findForCustomer($id, $customer->id);
        if ($existing === null) {
            $session->flash('error', 'Address not found.');
            return Response::redirect('/account/addresses');
        }

        $data   = $this->collectAddressInput($request);
        $errors = $this->validateAddress($data);

        if ($errors !== []) {
            $session->flash('address_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/account/addresses/' . $id . '/edit');
        }

        $address = $this->hydrateAddress($existing, $data);
        $repo->update($address);

        $session->clearOldInput();
        $session->flash('address_success', 'Address updated.');
        return Response::redirect('/account/addresses');
    }

    // =================================================================
    // Addresses - delete / set default
    // =================================================================

    /** @param array<string,string> $params */
    public function deleteAddress(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $customer     = $customerAuth->user();
        $repo         = $this->container->get(CustomerAddressRepository::class);
        $session      = $this->container->get(Session::class);

        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $id = (int) ($params['id'] ?? 0);
        $address = $repo->findForCustomer($id, $customer->id);

        if ($address === null) {
            $session->flash('error', 'Address not found.');
            return Response::redirect('/account/addresses');
        }

        $wasDefault = $address->isDefault;
        $wasBilling  = $address->isBilling();
        $wasShipping = $address->isShipping();

        $repo->delete($id, $customer->id);

        // If the deleted address was the customer's default for a
        // type, clear the corresponding column so the customer is
        // not left with a dangling default id.
        if ($wasDefault) {
            if ($wasBilling) {
                $repo->clearCustomerDefault($customer->id, 'billing');
            }
            if ($wasShipping) {
                $repo->clearCustomerDefault($customer->id, 'shipping');
            }
        }

        $session->flash('address_success', 'Address removed.');
        return Response::redirect('/account/addresses');
    }

    /** @param array<string,string> $params */
    public function setDefaultAddress(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $customer     = $customerAuth->user();
        $repo         = $this->container->get(CustomerAddressRepository::class);
        $session      = $this->container->get(Session::class);

        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $id = (int) ($params['id'] ?? 0);
        $address = $repo->findForCustomer($id, $customer->id);

        if ($address === null) {
            $session->flash('error', 'Address not found.');
            return Response::redirect('/account/addresses');
        }

        $repo->setDefault($address);
        $session->flash('address_success', 'Default address updated.');
        return Response::redirect('/account/addresses');
    }

    // =================================================================
    // Change password
    // =================================================================

    /** @param array<string,string> $params */
    public function showPassword(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $session      = $this->container->get(Session::class);

        return $this->render('storefront.account.password', [
            'title'    => 'Change password',
            'customer' => $customerAuth->user(),
            'errors'   => $session->getFlash('password_errors', []),
        ]);
    }

    /** @param array<string,string> $params */
    public function changePassword(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $customer     = $customerAuth->user();
        $repo         = $this->container->get(CustomerRepository::class);
        $session      = $this->container->get(Session::class);

        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $current = (string) $request->input('current_password', '');
        $new     = (string) $request->input('new_password', '');
        $confirm = (string) $request->input('new_password_confirmation', '');

        $errors = [];

        if ($e = Validator::required($current, 'Current password')) {
            $errors['current_password'] = $e;
        } elseif ($customer->passwordHash === null
                  || !Hasher::verify($current, $customer->passwordHash)) {
            $errors['current_password'] = 'Your current password is not correct.';
        }

        if ($e = Validator::required($new, 'New password')) {
            $errors['new_password'] = $e;
        } elseif ($e = Validator::minLength($new, 8, 'New password')) {
            $errors['new_password'] = $e;
        } elseif ($new === $current) {
            $errors['new_password'] = 'Your new password must be different from your current one.';
        } elseif ($new !== $confirm) {
            $errors['new_password_confirmation'] = 'Passwords do not match.';
        }

        if ($errors !== []) {
            $session->flash('password_errors', $errors);
            return Response::redirect('/account/password');
        }

        $repo->setPassword($customer->id, Hasher::hash($new));

        // Invalidate any outstanding reset tokens now that the
        // password has changed. An old reset link should not work.
        $repo->invalidatePasswordResets($customer->id);

        // Rotate the session so the current browser stays logged in
        // on a fresh session id.
        $customerAuth->login($customer);

        $session->flash('success', 'Your password has been changed.');
        return Response::redirect('/account');
    }

    // =================================================================
    // Address helpers
    // =================================================================

    /**
     * @return array<string,mixed>
     */
    private function collectAddressInput(Request $request): array
    {
        return [
            'label'          => trim((string) $request->input('label', '')),
            'type'           => (string) $request->input('type', 'both'),
            'first_name'     => trim((string) $request->input('first_name', '')),
            'last_name'      => trim((string) $request->input('last_name', '')),
            'company'        => trim((string) $request->input('company', '')),
            'address_line_1' => trim((string) $request->input('address_line_1', '')),
            'address_line_2' => trim((string) $request->input('address_line_2', '')),
            'city'           => trim((string) $request->input('city', '')),
            'state'          => trim((string) $request->input('state', '')),
            'postal_code'    => trim((string) $request->input('postal_code', '')),
            'country_code'   => strtoupper(trim((string) $request->input('country_code', ''))),
            'phone'          => trim((string) $request->input('phone', '')),
            'email'          => strtolower(trim((string) $request->input('email', ''))),
            'is_default'     => (bool) $request->input('is_default', false),
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function validateAddress(array $data): array
    {
        $errors = [];

        if ($e = Validator::required($data['address_line_1'], 'Address line 1')) {
            $errors['address_line_1'] = $e;
        } elseif ($e = Validator::maxLength($data['address_line_1'], 255, 'Address line 1')) {
            $errors['address_line_1'] = $e;
        }

        if ($data['address_line_2'] !== ''
            && ($e = Validator::maxLength($data['address_line_2'], 255, 'Address line 2'))) {
            $errors['address_line_2'] = $e;
        }

        if ($e = Validator::required($data['city'], 'City')) {
            $errors['city'] = $e;
        } elseif ($e = Validator::maxLength($data['city'], 120, 'City')) {
            $errors['city'] = $e;
        }

        if ($e = Validator::required($data['country_code'], 'Country')) {
            $errors['country_code'] = $e;
        } elseif ($e = Validator::countryCode($data['country_code'], 'Country')) {
            $errors['country_code'] = $e;
        }

        if (!in_array($data['type'], ['billing', 'shipping', 'both'], true)) {
            $errors['type'] = 'Address type is invalid.';
        }

        if ($data['state'] !== ''
            && ($e = Validator::maxLength($data['state'], 120, 'State'))) {
            $errors['state'] = $e;
        }

        if ($data['postal_code'] !== ''
            && ($e = Validator::maxLength($data['postal_code'], 20, 'Postal code'))) {
            $errors['postal_code'] = $e;
        }

        if ($data['label'] !== ''
            && ($e = Validator::maxLength($data['label'], 60, 'Label'))) {
            $errors['label'] = $e;
        }

        if ($data['first_name'] !== ''
            && ($e = Validator::maxLength($data['first_name'], 100, 'First name'))) {
            $errors['first_name'] = $e;
        }

        if ($data['last_name'] !== ''
            && ($e = Validator::maxLength($data['last_name'], 100, 'Last name'))) {
            $errors['last_name'] = $e;
        }

        if ($data['company'] !== ''
            && ($e = Validator::maxLength($data['company'], 200, 'Company'))) {
            $errors['company'] = $e;
        }

        if ($data['phone'] !== ''
            && ($e = Validator::maxLength($data['phone'], 50, 'Phone'))) {
            $errors['phone'] = $e;
        }

        if ($data['email'] !== '' && ($e = Validator::email($data['email'], 'Email'))) {
            $errors['email'] = $e;
        }

        return $errors;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function hydrateAddress(CustomerAddress $address, array $data): CustomerAddress
    {
        $address->label        = $data['label']        !== '' ? $data['label']        : null;
        $address->type         = (string) $data['type'];
        $address->firstName    = $data['first_name']   !== '' ? $data['first_name']   : null;
        $address->lastName     = $data['last_name']    !== '' ? $data['last_name']    : null;
        $address->company      = $data['company']      !== '' ? $data['company']      : null;
        $address->addressLine1 = (string) $data['address_line_1'];
        $address->addressLine2 = $data['address_line_2'] !== '' ? $data['address_line_2'] : null;
        $address->city         = (string) $data['city'];
        $address->state        = $data['state']        !== '' ? $data['state']        : null;
        $address->postalCode   = $data['postal_code']  !== '' ? $data['postal_code']  : null;
        $address->countryCode  = (string) $data['country_code'];
        $address->phone        = $data['phone']        !== '' ? $data['phone']        : null;
        $address->email        = $data['email']        !== '' ? $data['email']        : null;
        $address->isDefault    = (bool) $data['is_default'];
        return $address;
    }
}
