<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Commerce\Repositories\InventoryRepository;

/**
 * Commerce Aid admin dashboard.
 * Placeholder for Phase 5 - real dashboard tiles come later.
 */
final class DashboardController extends AdminController
{
    public function index(Request $request, array $params = []): Response
    {
        $inventory = $this->container->get(InventoryRepository::class);

        return $this->render('admin.dashboard', [
            'title' => 'Dashboard',
            'inventoryHealth' => [
                'low_stock'     => $inventory->countLowStock(),
                'out_of_stock'  => $inventory->countOutOfStock(),
                'units_on_hand' => $inventory->totalUnitsOnHand(),
            ],
        ]);
    }
}
