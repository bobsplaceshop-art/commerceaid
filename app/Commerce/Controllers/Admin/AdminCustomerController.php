<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Repositories\CustomerAddressRepository;
use CorporateAid\Commerce\Repositories\CustomerGroupRepository;
use CorporateAid\Commerce\Repositories\CustomerRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Admin customer detail.
 *
 * Routes (under /commerce/ca-admin/customers):
 *
 *   GET  /{id}                show (customer profile, addresses,
 *                             recent orders, approval history)
 *
 * Read-only in this sub-stage. List, group change, and approval
 * actions land in 008K.2.
 *
 * Permission: customers.view (GET).
 *
 * The customer detail page is the target of the "View customer
 * profile" link on the admin order detail page. It is included
 * in 008K.1 so the order detail page has a real target to link
 * to instead of a broken route or plain text.
 */
final class AdminCustomerController extends AdminController
{
    private const RECENT_ORDERS_LIMIT = 20;
    private const APPROVAL_HISTORY_LIMIT = 50;

    // =================================================================
    // GET /commerce/ca-admin/customers/{id}
    // =================================================================

    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $this->container->get(Session::class)
                ->flash('error', 'Invalid customer reference.');
            return Response::redirect('/commerce/ca-admin/orders');
        }

        $customers = $this->container->get(CustomerRepository::class);
        $customer  = $customers->findById($id);
        if ($customer === null) {
            $this->container->get(Session::class)
                ->flash('error', 'Customer not found.');
            return Response::redirect('/commerce/ca-admin/orders');
        }

        $addresses = $this->container->get(CustomerAddressRepository::class)
            ->listForCustomer($customer->id);

        $group = null;
        if ($customer->customerGroupId !== null) {
            $group = $this->container->get(CustomerGroupRepository::class)
                ->findById($customer->customerGroupId);
        }

        // Recent orders for the customer. Reuses the customer-tab
        // paginator with tab='all' to avoid a new repository method
        // that would do the same thing.
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

        return $this->render('admin.customers.show', [
            'title'           => $customer->fullName() ?: 'Customer',
            'customer'        => $customer,
            'addresses'       => $addresses,
            'group'           => $group,
            'recentOrders'    => $recentOrders,
            'approvalHistory' => $approvalHistory,
        ]);
    }
}
