<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
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
     * Apply bulk adjustments posted from the detail page.
     *
     * POST body shape:
     *   adjustments[<inventory_id>] = <new_quantity>
     *
     * Every row is processed independently. A failure on one
     * row does not abort the others. Each successful change
     * writes one movement through InventoryRepository::adjust().
     *
     * @param array<string,string> $params
     */
    public function save(Request $request, array $params = []): Response
    {
        $productId = (int) ($params['productId'] ?? 0);
        $inventory = $this->container->get(InventoryRepository::class);
        $products  = $this->container->get(ProductRepository::class);
        $session   = $this->container->get(Session::class);
        $auth      = $this->container->get(Auth::class);

        $product = $products->findById($productId);
        if ($product === null) {
            $session->flash('error', 'Product not found.');
            return Response::redirect('/commerce/ca-admin/inventory');
        }

        $redirect = '/commerce/ca-admin/inventory/item/' . $productId;

        $posted = $request->input('adjustments', []);
        if (!is_array($posted) || $posted === []) {
            $session->flash('info', 'No changes submitted.');
            return Response::redirect($redirect);
        }

        /** @var array<int|string,mixed> $posted */

        // Build a map of inventory_id => current_quantity from
        // the same query the detail page uses. This is the
        // authoritative source of the current value; we do not
        // trust anything from the POST body except the new number.
        $units = $inventory->stockForProduct($productId);

        /** @var array<int, array{quantity:int, variant_id:int}> $current */
        $current = [];
        foreach ($units as $u) {
            $id = (int) ($u['inventory_id'] ?? 0);
            if ($id > 0) {
                $current[$id] = [
                    'quantity'   => (int) ($u['quantity'] ?? 0),
                    'variant_id' => (int) ($u['variant_id'] ?? 0),
                ];
            }
        }

        $updated = 0;
        $skipped = 0;
        $failed  = 0;
        $errors  = [];

        $actorId = (int) ($auth->user()?->id ?? 0);

        foreach ($posted as $rawId => $rawValue) {
            $inventoryId = (int) $rawId;

            // Reject rows that do not belong to this product.
            // Guards against cross-product tampering via POST.
            if (!isset($current[$inventoryId])) {
                $failed++;
                continue;
            }

            $raw = is_string($rawValue) ? trim($rawValue) : $rawValue;
            if ($raw === '' || !is_numeric($raw)) {
                $failed++;
                $errors[] = 'Row ' . $inventoryId . ': quantity must be a number.';
                continue;
            }

            $newQty = (int) $raw;
            if ($newQty < 0) {
                $failed++;
                $errors[] = 'Row ' . $inventoryId . ': quantity cannot be negative.';
                continue;
            }

            $currentQty = $current[$inventoryId]['quantity'];
            $variantId  = $current[$inventoryId]['variant_id'];
            $delta      = $newQty - $currentQty;

            if ($delta === 0) {
                $skipped++;
                continue;
            }

            try {
                $inventory->adjust(
                    $productId,
                    $variantId,
                    $delta,
                    'adjustment',
                    [
                        'reason'     => 'Bulk adjustment via detail page',
                        'created_by' => $actorId,
                    ]
                );
                $updated++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = 'Row ' . $inventoryId . ': ' . $e->getMessage();
            }
        }

        // Compose the flash summary.
        $parts = [];
        if ($updated > 0) { $parts[] = $updated . ' updated'; }
        if ($skipped > 0) { $parts[] = $skipped . ' unchanged'; }
        if ($failed  > 0) { $parts[] = $failed . ' failed'; }

        $summary = $parts === [] ? 'No changes made.' : implode(', ', $parts) . '.';

        if ($failed > 0) {
            $session->flash('error', $summary);
        } elseif ($updated > 0) {
            $session->flash('success', $summary);
        } else {
            $session->flash('info', $summary);
        }

        if ($errors !== []) {
            $session->flash('inventory_item_errors', $errors);
        }

        return Response::redirect($redirect);
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
