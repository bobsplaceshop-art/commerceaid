<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Commerce\Repositories\CustomerRepository;
use CorporateAid\Commerce\Repositories\InventoryRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Repositories\ReportRepository;

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

        $reports = $this->container->get(ReportRepository::class);

        // Totals and trends for the last 30 days
        $salesSeries     = $reports->salesOverTime(30);
        $totalRevenue    = array_sum(array_column($salesSeries, 'revenue'));
        $totalOrders     = array_sum(array_column($salesSeries, 'orders'));
        $averageOrder    = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0.0;
        $topProducts     = $reports->topProducts(30, 5);
        $salesByCategory = $reports->salesByCategory(30);
        $statusBreakdown = $reports->orderStatusBreakdown();
        $customerStats   = $reports->customerStats(30);

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
            'salesSeries'     => $salesSeries,
            'totalRevenue'    => $totalRevenue,
            'totalOrders'     => $totalOrders,
            'averageOrder'    => $averageOrder,
            'topProducts'     => $topProducts,
            'salesByCategory' => $salesByCategory,
            'statusBreakdown' => $statusBreakdown,
            'customerStats'   => $customerStats,
        ]);
    }
}
