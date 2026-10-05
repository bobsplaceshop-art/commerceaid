<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Commerce\Repositories\CustomerRepository;
use CorporateAid\Commerce\Repositories\InventoryRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;

/**
 * Commerce Aid admin dashboard.
 *
 * Three counter tiles (Products, Orders, Customers) plus
 * the Inventory health card. The tiles link to their
 * respective admin surfaces.
 *
 * Orders shows the pending-action count as a hint so the
 * merchant knows how many orders need attention. The other
 * two tiles show the total.
 */
final class DashboardController extends AdminController
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $inventory = $this->container->get(InventoryRepository::class);
        $orders    = $this->container->get(OrderRepository::class);
        $products  = $this->container->get(ProductRepository::class);
        $customers = $this->container->get(CustomerRepository::class);

        return $this->render('admin.dashboard', [
            'title' => 'Dashboard',
            'tiles' => [
                'products' => [
                    'total' => $products->count(),
                ],
                'orders' => [
                    'total'   => $orders->count(),
                    'pending' => admin_pending_order_count(),
                ],
                'customers' => [
                    'total' => $customers->count(),
                ],
            ],
            'inventoryHealth' => [
                'low_stock'     => $inventory->countLowStock(),
                'out_of_stock'  => $inventory->countOutOfStock(),
                'units_on_hand' => $inventory->totalUnitsOnHand(),
            ],
        ]);
    }
}
