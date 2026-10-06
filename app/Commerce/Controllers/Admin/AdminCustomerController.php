<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Models\Customer;
use CorporateAid\Commerce\Models\CustomerAddress;
use CorporateAid\Commerce\Repositories\CustomerAddressRepository;
use CorporateAid\Commerce\Repositories\CustomerGroupRepository;
use CorporateAid\Commerce\Repositories\CustomerRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Admin customers.
 *
 * Routes (all under /commerce/ca-admin/customers):
 *
 *   GET  /                            index
 *   GET  /{id}                        show
 *   GET  /{id}/edit                   edit
 *   POST /{id}                        update
 *   POST /{id}/group                  changeGroup
 *   POST /{id}/status                 changeStatus
 *
 *   GET  /{id}/addresses/create       addressCreate
 *   POST /{id}/addresses              addressStore
 *   GET  /{id}/addresses/{aid}/edit   addressEdit
 *   POST /{id}/addresses/{aid}        addressUpdate
 *   POST /{id}/addresses/{aid}/delete addressDelete
 *   POST /{id}/addresses/{aid}/default addressDefault
 *
 * Permissions: customers.view for GETs, customers.manage
 * for POSTs.
 *
 * Approval actions (approve, reject, reset) are NOT handled
 * here. They stay in CustomerApprovalController and the
 * detail view posts to those routes. No duplication.
 *
 * Email address is read-only on the admin side. The customer
 * owns their email; changing it is a security concern that
 * requires verification.
 *
 * Address writes mirror the storefront's AccountController
 * shape exactly so the two surfaces behave the same for the
 * customer and for the merchant.
 */
final class AdminCustomerController extends AdminController
{
    private const PER_PAGE = 25;
    private const RECENT_ORDERS_LIMIT = 20;
    private const APPROVAL_HISTORY_LIMIT = 50;

    private const VALID_TABS = ['all', 'pending', 'approved', 'rejected'];
    private const VALID_ACCOUNT_STATUSES = ['all', 'active', 'disabled', 'archived'];
    private const VALID_TRANSITION_STATUSES = ['active', 'disabled', 'archived'];
    private const VALID_ADDRESS_TYPES = ['billing', 'shipping', 'both'];

    // =================================================================
    // GET /commerce/ca-admin/customers
    // =================================================================

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(CustomerRepository::class);

        $tab = (string) $request->query('status', 'all');
        if (!in_array($tab, self::VALID_TABS, true)) {
            $tab = 'all';
        }

        $state = (string) $request->query('state', 'all');
        if (!in_array($state, self::VALID_ACCOUNT_STATUSES, true)) {
            $state = 'all';
        }

        $q        = trim((string) $request->query('q', ''));
        $groupRaw = (string) $request->query('group', '');
        $groupId  = $groupRaw !== '' && ctype_digit($groupRaw) ? (int) $groupRaw : 0;
        $page     = max(1, (int) $request->query('page', 1));

        $filters = [];
        if ($tab !== 'all') {
            $filters['approval_status'] = $tab;
        }
        if ($state !== 'all') {
            $filters['status'] = $state;
        }
        if ($q !== '') {
            $filters['q'] = $q;
        }
        if ($groupId > 0) {
            $filters['group_id'] = $groupId;
        }

        $result = $repo->paginate($filters, $page, self::PER_PAGE);

        $counts = [
            'all'      => $repo->countByApprovalStatus(null),
            'pending'  => $repo->countByApprovalStatus('pending'),
            'approved' => $repo->countByApprovalStatus('approved'),
            'rejected' => $repo->countByApprovalStatus('rejected'),
        ];

        $groupOptions = $this->container->get(CustomerGroupRepository::class)
            ->listForSelect();

        return $this->render('admin.customers.index', [
            'title'        => 'Customers',
            'result'       => $result,
            'tab'          => $tab,
            'state'        => $state,
            'q'            => $q,
            'groupId'      => $groupId,
            'counts'       => $counts,
            'groupOptions' => $groupOptions,
        ]);
    }

    // =================================================================
    // GET /commerce/ca-admin/customers/{id}
    // =================================================================

    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid customer reference.');
        }

        $customers = $this->container->get(CustomerRepository::class);
        $customer  = $customers->findById($id);
        if ($customer === null) {
            return $this->fail('Customer not found.');
        }

        $addresses = $this->container->get(CustomerAddressRepository::class)
            ->listForCustomer($customer->id);

        $group = null;
        if ($customer->customerGroupId !== null) {
            $group = $this->container->get(CustomerGroupRepository::class)
                ->findById($customer->customerGroupId);
        }

        $recentOrders = $this->container->get(OrderRepository::class)
            ->paginateForCustomerTab(
                $customer->id,
                'all',
                1,
                self::RECENT_ORDERS_LIMIT
            );

        $approvalHistory = $customers->approvalHistory(
            $customer->id,
            self::APPROVAL_HISTORY_LIMIT
        );

        $groupOptions = $this->container->get(CustomerGroupRepository::class)
            ->listForSelect();

        return $this->render('admin.customers.show', [
            'title'           => $customer->fullName() ?: 'Customer',
            'customer'        => $customer,
            'addresses'       => $addresses,
            'group'           => $group,
            'recentOrders'    => $recentOrders,
            'approvalHistory' => $approvalHistory,
            'groupOptions'    => $groupOptions,
        ]);
    }

    // =================================================================
    // GET /commerce/ca-admin/customers/{id}/edit
    // =================================================================

    /** @param array<string,string> $params */
    public function edit(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid customer reference.');
        }

        $customers = $this->container->get(CustomerRepository::class);
        $customer  = $customers->findById($id);
        if ($customer === null) {
            return $this->fail('Customer not found.');
        }

        return $this->render('admin.customers.edit', [
            'title'    => 'Edit ' . ($customer->fullName() ?: $customer->email),
            'customer' => $customer,
            'errors'   => $this->container->get(Session::class)->getFlash('customer_errors', []),
            'old'      => $_SESSION['_old'] ?? [],
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/customers/{id}
    // =================================================================

    /** @param array<string,string> $params */
    public function update(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id'] ?? 0);
        $session = $this->container->get(Session::class);

        if ($id <= 0) {
            return $this->fail('Invalid customer reference.');
        }

        $customers = $this->container->get(CustomerRepository::class);
        $customer  = $customers->findById($id);
        if ($customer === null) {
            return $this->fail('Customer not found.');
        }

        $data = [
            'first_name'    => trim((string) $request->input('first_name', '')),
            'last_name'     => trim((string) $request->input('last_name', '')),
            'phone'         => trim((string) $request->input('phone', '')),
            'company'       => trim((string) $request->input('company', '')),
            'tax_id'        => trim((string) $request->input('tax_id', '')),
            'date_of_birth' => trim((string) $request->input('date_of_birth', '')),
        ];

        $errors = $this->validateProfile($data);
        if ($errors !== []) {
            $session->flash('customer_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/customers/' . $customer->id . '/edit');
        }

        $customer->firstName   = $data['first_name']    !== '' ? $data['first_name']    : null;
        $customer->lastName    = $data['last_name']     !== '' ? $data['last_name']     : null;
        $customer->phone       = $data['phone']         !== '' ? $data['phone']         : null;
        $customer->company     = $data['company']       !== '' ? $data['company']       : null;
        $customer->taxId       = $data['tax_id']        !== '' ? $data['tax_id']        : null;
        $customer->dateOfBirth = $data['date_of_birth'] !== '' ? $data['date_of_birth'] : null;

        $customers->update($customer);

        $session->clearOldInput();
        $session->flash('success', 'Customer profile updated.');
        return Response::redirect('/commerce/ca-admin/customers/' . $customer->id);
    }

    // =================================================================
    // POST /commerce/ca-admin/customers/{id}/group
    // =================================================================

    /** @param array<string,string> $params */
    public function changeGroup(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id'] ?? 0);
        $session = $this->container->get(Session::class);

        if ($id <= 0) {
            return $this->fail('Invalid customer reference.');
        }

        $customers = $this->container->get(CustomerRepository::class);
        $customer  = $customers->findById($id);
        if ($customer === null) {
            return $this->fail('Customer not found.');
        }

        $groupIdRaw = (string) $request->input('group_id', '');
        $groupId    = $groupIdRaw !== '' && ctype_digit($groupIdRaw) ? (int) $groupIdRaw : 0;

        if ($groupId <= 0) {
            $session->flash('error', 'Select a group.');
            return Response::redirect('/commerce/ca-admin/customers/' . $customer->id);
        }

        $group = $this->container->get(CustomerGroupRepository::class)
            ->findById($groupId);
        if ($group === null) {
            $session->flash('error', 'Selected group not found.');
            return Response::redirect('/commerce/ca-admin/customers/' . $customer->id);
        }

        $customer->customerGroupId = $group->id;
        $customers->update($customer);

        $session->flash('success', 'Customer moved to ' . $group->name . '.');
        return Response::redirect('/commerce/ca-admin/customers/' . $customer->id);
    }

    // =================================================================
    // POST /commerce/ca-admin/customers/{id}/status
    // =================================================================

    /** @param array<string,string> $params */
    public function changeStatus(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id'] ?? 0);
        $session = $this->container->get(Session::class);

        if ($id <= 0) {
            return $this->fail('Invalid customer reference.');
        }

        $customers = $this->container->get(CustomerRepository::class);
        $customer  = $customers->findById($id);
        if ($customer === null) {
            return $this->fail('Customer not found.');
        }

        $newStatus = (string) $request->input('status', '');
        if (!in_array($newStatus, self::VALID_TRANSITION_STATUSES, true)) {
            $session->flash('error', 'Invalid account status.');
            return Response::redirect('/commerce/ca-admin/customers/' . $customer->id);
        }

        if ($customer->status === $newStatus) {
            $session->flash('success', 'Account is already in that state.');
            return Response::redirect('/commerce/ca-admin/customers/' . $customer->id);
        }

        $customer->status = $newStatus;
        $customers->update($customer);

        // Only 'active' needs translation. The other two
        // statuses read the same as the input.
        $label = $newStatus === 'active' ? 'enabled' : $newStatus;

        $session->flash('success', 'Customer account ' . $label . '.');
        return Response::redirect('/commerce/ca-admin/customers/' . $customer->id);
    }

    // =================================================================
    // GET /commerce/ca-admin/customers/{id}/addresses/create
    // =================================================================

    /** @param array<string,string> $params */
    public function addressCreate(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid customer reference.');
        }

        $customers = $this->container->get(CustomerRepository::class);
        $customer  = $customers->findById($id);
        if ($customer === null) {
            return $this->fail('Customer not found.');
        }

        return $this->render('admin.customers.address-form', [
            'title'    => 'Add address for ' . ($customer->fullName() ?: $customer->email),
            'customer' => $customer,
            'address'  => null,
            'mode'     => 'create',
            'errors'   => $this->container->get(Session::class)->getFlash('address_errors', []),
            'old'      => $_SESSION['_old'] ?? [],
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/customers/{id}/addresses
    // =================================================================

    /** @param array<string,string> $params */
    public function addressStore(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id'] ?? 0);
        $session = $this->container->get(Session::class);

        if ($id <= 0) {
            return $this->fail('Invalid customer reference.');
        }

        $customers = $this->container->get(CustomerRepository::class);
        $customer  = $customers->findById($id);
        if ($customer === null) {
            return $this->fail('Customer not found.');
        }

        $repo = $this->container->get(CustomerAddressRepository::class);

        $data   = $this->collectAddressInput($request);
        $errors = $this->validateAddress($data);

        if ($errors !== []) {
            $session->flash('address_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/customers/' . $customer->id . '/addresses/create');
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
        $session->flash('success', 'Address added.');
        return Response::redirect('/commerce/ca-admin/customers/' . $customer->id);
    }

    // =================================================================
    // GET /commerce/ca-admin/customers/{id}/addresses/{aid}/edit
    // =================================================================

    /** @param array<string,string> $params */
    public function addressEdit(Request $request, array $params = []): Response
    {
        $id  = (int) ($params['id']  ?? 0);
        $aid = (int) ($params['aid'] ?? 0);

        if ($id <= 0 || $aid <= 0) {
            return $this->fail('Invalid reference.');
        }

        $customers = $this->container->get(CustomerRepository::class);
        $customer  = $customers->findById($id);
        if ($customer === null) {
            return $this->fail('Customer not found.');
        }

        $repo    = $this->container->get(CustomerAddressRepository::class);
        $address = $repo->findForCustomer($aid, $customer->id);
        if ($address === null) {
            $this->container->get(Session::class)->flash('error', 'Address not found.');
            return Response::redirect('/commerce/ca-admin/customers/' . $customer->id);
        }

        return $this->render('admin.customers.address-form', [
            'title'    => 'Edit address for ' . ($customer->fullName() ?: $customer->email),
            'customer' => $customer,
            'address'  => $address,
            'mode'     => 'edit',
            'errors'   => $this->container->get(Session::class)->getFlash('address_errors', []),
            'old'      => $_SESSION['_old'] ?? [],
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/customers/{id}/addresses/{aid}
    // =================================================================

    /** @param array<string,string> $params */
    public function addressUpdate(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id']  ?? 0);
        $aid     = (int) ($params['aid'] ?? 0);
        $session = $this->container->get(Session::class);

        if ($id <= 0 || $aid <= 0) {
            return $this->fail('Invalid reference.');
        }

        $customers = $this->container->get(CustomerRepository::class);
        $customer  = $customers->findById($id);
        if ($customer === null) {
            return $this->fail('Customer not found.');
        }

        $repo     = $this->container->get(CustomerAddressRepository::class);
        $existing = $repo->findForCustomer($aid, $customer->id);
        if ($existing === null) {
            $session->flash('error', 'Address not found.');
            return Response::redirect('/commerce/ca-admin/customers/' . $customer->id);
        }

        $data   = $this->collectAddressInput($request);
        $errors = $this->validateAddress($data);

        if ($errors !== []) {
            $session->flash('address_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/customers/' . $customer->id . '/addresses/' . $aid . '/edit');
        }

        $address = $this->hydrateAddress($existing, $data);
        $repo->update($address);

        // If the form asked for this address to be default, honour it.
        if (!empty($data['is_default']) && !$existing->isDefault) {
            $repo->setDefault($address);
        }

        $session->clearOldInput();
        $session->flash('success', 'Address updated.');
        return Response::redirect('/commerce/ca-admin/customers/' . $customer->id);
    }

    // =================================================================
    // POST /commerce/ca-admin/customers/{id}/addresses/{aid}/delete
    // =================================================================

    /** @param array<string,string> $params */
    public function addressDelete(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id']  ?? 0);
        $aid     = (int) ($params['aid'] ?? 0);
        $session = $this->container->get(Session::class);

        if ($id <= 0 || $aid <= 0) {
            return $this->fail('Invalid reference.');
        }

        $customers = $this->container->get(CustomerRepository::class);
        $customer  = $customers->findById($id);
        if ($customer === null) {
            return $this->fail('Customer not found.');
        }

        $repo    = $this->container->get(CustomerAddressRepository::class);
        $address = $repo->findForCustomer($aid, $customer->id);
        if ($address === null) {
            $session->flash('error', 'Address not found.');
            return Response::redirect('/commerce/ca-admin/customers/' . $customer->id);
        }

        // Capture default state before the delete, so we can clear
        // the customer row's default id column if this was the
        // default address for a type.
        $wasDefault  = $address->isDefault;
        $wasBilling  = $address->isBilling();
        $wasShipping = $address->isShipping();

        $repo->delete($aid, $customer->id);

        if ($wasDefault) {
            if ($wasBilling) {
                $repo->clearCustomerDefault($customer->id, 'billing');
            }
            if ($wasShipping) {
                $repo->clearCustomerDefault($customer->id, 'shipping');
            }
        }

        $session->flash('success', 'Address removed.');
        return Response::redirect('/commerce/ca-admin/customers/' . $customer->id);
    }

    // =================================================================
    // POST /commerce/ca-admin/customers/{id}/addresses/{aid}/default
    // =================================================================

    /** @param array<string,string> $params */
    public function addressDefault(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id']  ?? 0);
        $aid     = (int) ($params['aid'] ?? 0);
        $session = $this->container->get(Session::class);

        if ($id <= 0 || $aid <= 0) {
            return $this->fail('Invalid reference.');
        }

        $customers = $this->container->get(CustomerRepository::class);
        $customer  = $customers->findById($id);
        if ($customer === null) {
            return $this->fail('Customer not found.');
        }

        $repo    = $this->container->get(CustomerAddressRepository::class);
        $address = $repo->findForCustomer($aid, $customer->id);
        if ($address === null) {
            $session->flash('error', 'Address not found.');
            return Response::redirect('/commerce/ca-admin/customers/' . $customer->id);
        }

        $repo->setDefault($address);
        $session->flash('success', 'Default address updated.');
        return Response::redirect('/commerce/ca-admin/customers/' . $customer->id);
    }

    // =================================================================
    // Helpers
    // =================================================================

    /**
     * Flash an error and redirect to the customer list.
     */
    private function fail(string $message): Response
    {
        $this->container->get(Session::class)->flash('error', $message);
        return Response::redirect('/commerce/ca-admin/customers');
    }

    /**
     * Read every address field from the request. Mirrors the
     * storefront AccountController's collectAddressInput().
     *
     * @return array<string,string>
     */
    private function collectAddressInput(Request $request): array
    {
        return [
            'label'          => trim((string) $request->input('label', '')),
            'type'           => trim((string) $request->input('type', 'both')),
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
            'email'          => trim((string) $request->input('email', '')),
            'is_default'     => (string) $request->input('is_default', ''),
        ];
    }

    /**
     * Validate address input. Mirrors the storefront validation.
     *
     * @param array<string,string> $data
     * @return array<string,string>
     */
    private function validateAddress(array $data): array
    {
        $errors = [];

        if (!in_array($data['type'], self::VALID_ADDRESS_TYPES, true)) {
            $errors['type'] = 'Invalid address type.';
        }

        if ($data['address_line_1'] === '') {
            $errors['address_line_1'] = 'Address line 1 is required.';
        } elseif (mb_strlen($data['address_line_1']) > 255) {
            $errors['address_line_1'] = 'Address line 1 must be 255 characters or fewer.';
        }

        if ($data['city'] === '') {
            $errors['city'] = 'City is required.';
        } elseif (mb_strlen($data['city']) > 120) {
            $errors['city'] = 'City must be 120 characters or fewer.';
        }

        if ($data['country_code'] === '') {
            $errors['country_code'] = 'Country code is required.';
        } elseif (!preg_match('/^[A-Z]{2}$/', $data['country_code'])) {
            $errors['country_code'] = 'Country code must be a two-letter ISO code.';
        }

        if ($data['label'] !== '' && mb_strlen($data['label']) > 60) {
            $errors['label'] = 'Label must be 60 characters or fewer.';
        }
        if ($data['first_name'] !== '' && mb_strlen($data['first_name']) > 100) {
            $errors['first_name'] = 'First name must be 100 characters or fewer.';
        }
        if ($data['last_name'] !== '' && mb_strlen($data['last_name']) > 100) {
            $errors['last_name'] = 'Last name must be 100 characters or fewer.';
        }
        if ($data['company'] !== '' && mb_strlen($data['company']) > 200) {
            $errors['company'] = 'Company must be 200 characters or fewer.';
        }
        if ($data['address_line_2'] !== '' && mb_strlen($data['address_line_2']) > 255) {
            $errors['address_line_2'] = 'Address line 2 must be 255 characters or fewer.';
        }
        if ($data['state'] !== '' && mb_strlen($data['state']) > 120) {
            $errors['state'] = 'State must be 120 characters or fewer.';
        }
        if ($data['postal_code'] !== '' && mb_strlen($data['postal_code']) > 20) {
            $errors['postal_code'] = 'Postal code must be 20 characters or fewer.';
        }
        if ($data['phone'] !== '' && mb_strlen($data['phone']) > 50) {
            $errors['phone'] = 'Phone must be 50 characters or fewer.';
        }
        if ($data['email'] !== '' && mb_strlen($data['email']) > 190) {
            $errors['email'] = 'Email must be 190 characters or fewer.';
        } elseif ($data['email'] !== '' && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Email is not a valid address.';
        }

        return $errors;
    }

    /**
     * Copy validated input onto a CustomerAddress model.
     *
     * @param array<string,string> $data
     */
    private function hydrateAddress(CustomerAddress $address, array $data): CustomerAddress
    {
        $address->label         = $data['label']          !== '' ? $data['label']          : null;
        $address->type          = $data['type']           !== '' ? $data['type']           : 'both';
        $address->firstName     = $data['first_name']     !== '' ? $data['first_name']     : null;
        $address->lastName      = $data['last_name']      !== '' ? $data['last_name']      : null;
        $address->company       = $data['company']        !== '' ? $data['company']        : null;
        $address->addressLine1  = $data['address_line_1'];
        $address->addressLine2  = $data['address_line_2'] !== '' ? $data['address_line_2'] : null;
        $address->city          = $data['city'];
        $address->state         = $data['state']          !== '' ? $data['state']          : null;
        $address->postalCode    = $data['postal_code']    !== '' ? $data['postal_code']    : null;
        $address->countryCode   = $data['country_code'];
        $address->phone         = $data['phone']          !== '' ? $data['phone']          : null;
        $address->email         = $data['email']          !== '' ? $data['email']          : null;
        $address->isDefault     = $data['is_default'] === '1';

        return $address;
    }

    /**
     * Validate the profile fields the admin can edit. Length
     * limits match the schema: VARCHAR(100) for names, VARCHAR(50)
     * for phone, VARCHAR(200) for company, VARCHAR(60) for tax id.
     * Tightened to realistic human values in 008K.2 Session 1.
     *
     * @param array<string,string> $data
     * @return array<string,string>
     */
    private function validateProfile(array $data): array
    {
        $errors = [];

        if (mb_strlen($data['first_name']) > 50) {
            $errors['first_name'] = 'First name must be 50 characters or fewer.';
        }
        if (mb_strlen($data['last_name']) > 50) {
            $errors['last_name'] = 'Last name must be 50 characters or fewer.';
        }
        if (mb_strlen($data['phone']) > 30) {
            $errors['phone'] = 'Phone must be 30 characters or fewer.';
        }
        if (mb_strlen($data['company']) > 100) {
            $errors['company'] = 'Company must be 100 characters or fewer.';
        }
        if (mb_strlen($data['tax_id']) > 30) {
            $errors['tax_id'] = 'Tax ID must be 30 characters or fewer.';
        }

        return $errors;
    }
}
