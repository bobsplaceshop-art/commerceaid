<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Commerce\Repositories\InventoryRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;

/**
 * Per-product inventory detail page.
 *
 * Route:
 *   GET /commerce/ca-admin/inventory/item/{productId}
 *
 * Shows every sellable unit for one product in a single table:
 * simple products get one row (variant_id = 0), variable products
 * get one row per variant. Inline adjust controls plus a sticky
 * save bar.
 *
 * The save handler (POST to the same path) lands in the next
 * commit. Until then the submit button renders disabled.
 */
final class InventoryItemController extends AdminController
{
    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $productId   = (int) ($params['productId'] ?? 0);
        $inventory   = $this->container->get(InventoryRepository::class);
        $products    = $this->container->get(ProductRepository::class);

        $product = $products->findById($productId);
        if ($product === null) {
            return Response::redirect('/commerce/ca-admin/inventory');
        }

        $units = $inventory->stockForProduct($productId);
        $units = $this->decorateUnits($units);

        $stats = $this->aggregateStats($product->productType, $units);

        return $this->render('admin.inventory.item', [
            'title'   => 'Inventory - ' . $product->name,
            'product' => $product,
            'units'   => $units,
            'stats'   => $stats,
        ]);
    }

    /**
     * Attach display fields (status key, badge class, label) to
     * each unit row. Server-side so the view stays dumb.
     *
     * @param  array<int, array<string,mixed>> $units
     * @return array<int, array<string,mixed>>
     */
    private function decorateUnits(array $units): array
    {
        foreach ($units as &$u) {
            $available = (int) $u['available'];
            $threshold = (int) ($u['unit_threshold']
                ?? $u['product_threshold']
                ?? 5);

            if ($available <= 0) {
                $u['status_key']   = 'out_of_stock';
                $u['status_label'] = 'Out of stock';
                $u['status_class'] = 'ra-badge-danger';
            } elseif ($available <= $threshold) {
                $u['status_key']   = 'low_stock';
                $u['status_label'] = 'Low stock';
                $u['status_class'] = 'ra-badge-warning';
            } else {
                $u['status_key']   = 'in_stock';
                $u['status_label'] = 'In stock';
                $u['status_class'] = 'ra-badge-success';
            }

            $u['display_label'] = $u['variant_summary']
                ?? ($u['variant_sku'] ?? null)
                ?? null;
        }
        unset($u);

        return $units;
    }

    /**
     * Product-level stats derived from the unit rows. Not a
     * database round-trip - we already have everything.
     *
     * @param  array<int, array<string,mixed>> $units
     * @return array<string,int|string>
     */
    private function aggregateStats(string $productType, array $units): array
    {
        $unitCount    = count($units);
        $unitsInStock = 0;
        $lowCount     = 0;
        $totalUnits   = 0;
        $totalReserved= 0;

        foreach ($units as $u) {
            $available = (int) $u['available'];
            $quantity  = (int) $u['quantity'];
            $reserved  = (int) $u['reserved'];
            $threshold = (int) ($u['unit_threshold']
                ?? $u['product_threshold']
                ?? 5);

            $totalUnits    += $quantity;
            $totalReserved += $reserved;

            if ($available > 0) {
                $unitsInStock++;
            }

            if ($available > 0 && $available <= $threshold) {
                $lowCount++;
            }
        }

        if ($unitCount === 0) {
            $statusKey   = 'no_stock';
            $statusLabel = 'No stock row';
            $statusClass = 'ra-badge-muted';
        } elseif ($unitsInStock === 0) {
            $statusKey   = 'out_of_stock';
            $statusLabel = 'Out of stock';
            $statusClass = 'ra-badge-danger';
        } elseif ($lowCount > 0) {
            $statusKey   = 'low_stock';
            $statusLabel = 'Low stock';
            $statusClass = 'ra-badge-warning';
        } else {
            $statusKey   = 'in_stock';
            $statusLabel = 'In stock';
            $statusClass = 'ra-badge-success';
        }

        return [
            'unit_count'      => $unitCount,
            'units_in_stock'  => $unitsInStock,
            'low_count'       => $lowCount,
            'total_units'     => $totalUnits,
            'total_reserved'  => $totalReserved,
            'status_key'      => $statusKey,
            'status_label'    => $statusLabel,
            'status_class'    => $statusClass,
            'is_variable'     => $productType === 'variable' ? 1 : 0,
        ];
    }
}
