<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Models\Customer;
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
 *   GET  /                    index (filtered list)
 *   GET  /{id}                show (profile, addresses,
 *                             recent orders, approval history)
 *   GET  /{id}/edit           edit (profile form)
 *   POST /{id}                update (save profile changes)
 *   POST /{id}/group          changeGroup
 *   POST /{id}/status         changeStatus
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
 * requires verification. A merchant who needs to change a
 * customer's email uses the customer-side forgot-password
 * flow. See the profile edit form for the operator note.
 */
final class AdminCustomerController extends AdminController
{
    private const PER_PAGE = 25;
    private const RECENT_ORDERS_LIMIT = 20;
    private const APPROVAL_HISTORY_LIMIT = 50;

    private const VALID_TABS = ['all', 'pending', 'approved', 'rejected'];
    private const VALID_ACCOUNT_STATUSES = ['all', 'active', 'disabled', 'archived'];
    private const VALID_TRANSITION_STATUSES = ['active', 'disabled', 'archived'];

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

        // Email is not written here. The customer owns their
        // email; the admin cannot change it. The update() method
        // writes the current value back unchanged.
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
    // Helpers
    // =================================================================

    /**
     * Flash an error and redirect to the list. Convenience for the
     * early-exit paths.
     */
    private function fail(string $message): Response
    {
        $this->container->get(Session::class)->flash('error', $message);
        return Response::redirect('/commerce/ca-admin/customers');
    }

    /**
     * Validate the profile fields the admin can edit. Length
     * limits match the schema: VARCHAR(100) for names, VARCHAR(50)
     * for phone, VARCHAR(200) for company, VARCHAR(60) for tax id.
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
