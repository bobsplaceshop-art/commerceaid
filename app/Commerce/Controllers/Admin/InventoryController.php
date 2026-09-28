<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Repositories\InventoryRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Services\CatalogService;
use CorporateAid\Commerce\Support\Validator;

/**
 * Inventory admin.
 *
 * Routes (all under /commerce/ca-admin/inventory):
 *   GET    /                    list (with filters + pagination)
 *   GET    /movements           movement history
 *   GET    /{productId}/adjust-info   JSON: current stock for the adjust modal
 *   POST   /adjust              apply an adjustment
 *   GET    /export.csv          CSV export of the inventory list
 *   GET    /movements/export.csv CSV export of movements
 */
final class InventoryController extends AdminController
{
    private const PER_PAGE = 25;
    private const MOVEMENTS_PER_PAGE = 50;

    // =================================================================
    // Reads
    // =================================================================

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $repo    = $this->container->get(InventoryRepository::class);
        $catalog = $this->container->get(CatalogService::class);

        $filters = [
            'q'           => trim((string) $request->query('q', '')),
            'status'      => (string) $request->query('status', ''),
            'category_id' => (int) $request->query('category_id', 0),
            'brand_id'    => (int) $request->query('brand_id', 0),
        ];
        $filters = array_filter($filters, fn ($v) => $v !== '' && $v !== 0);

        $page = max(1, (int) $request->query('page', 1));
        $result = $repo->paginateProductsWithStock($filters, $page, self::PER_PAGE);

        return $this->render('admin.inventory.index', [
            'title'      => 'Inventory',
            'result'     => $result,
            'filters'    => $filters,
            'categories' => $catalog->categoriesForSelect(),
            'brands'     => $catalog->brandsForSelect(),
            'summary'    => [
                'low_stock'    => $repo->countLowStock(),
                'out_of_stock' => $repo->countOutOfStock(),
                'units_on_hand'=> $repo->totalUnitsOnHand(),
            ],
        ]);
    }

    /** @param array<string,string> $params */
    public function movements(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(InventoryRepository::class);

        $filters = [
            'q'    => trim((string) $request->query('q', '')),
            'type' => (string) $request->query('type', ''),
            'from' => (string) $request->query('from', ''),
            'to'   => (string) $request->query('to', ''),
        ];
        $filters = array_filter($filters, fn ($v) => $v !== '');

        $page = max(1, (int) $request->query('page', 1));
        $result = $repo->movementsPaginate($filters, $page, self::MOVEMENTS_PER_PAGE);

        return $this->render('admin.inventory.movements', [
            'title'   => 'Inventory Movements',
            'result'  => $result,
            'filters' => $filters,
        ]);
    }

    /**
     * JSON: current stock for a product/variant, for the adjust modal.
     */
    /** @param array<string,string> $params */
    public function adjustInfo(Request $request, array $params = []): Response
    {
        $repo      = $this->container->get(InventoryRepository::class);
        $productId = (int) ($params['productId'] ?? 0);
        $variantId = (int) $request->query('variant_id', 0);

        $unit = $repo->forUnit($productId, $variantId);
        if ($unit === null) {
            return Response::json([
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity'   => 0,
                'available'  => 0,
                'reserved'   => 0,
            ]);
        }

        return Response::json([
            'product_id' => $unit->productId,
            'variant_id' => $unit->variantId,
            'quantity'   => $unit->quantity,
            'available'  => $unit->available,
            'reserved'   => $unit->reserved,
        ]);
    }

    // =================================================================
    // Writes
    // =================================================================

    /** @param array<string,string> $params */
    public function adjust(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(InventoryRepository::class);
        $auth    = $this->container->get(Auth::class);

        $productId   = (int) $request->input('product_id', 0);
        $variantId   = (int) $request->input('variant_id', 0);
        $newQuantity = $request->input('new_quantity');
        $type        = (string) $request->input('movement_type', 'adjustment');
        $reason      = trim((string) $request->input('reason', ''));

        $errors = [];

        if ($productId <= 0) {
            $errors['product_id'] = 'Product is required.';
        }
        if ($newQuantity === null || $newQuantity === '') {
            $errors['new_quantity'] = 'New quantity is required.';
        } elseif (!is_numeric($newQuantity) || (int) $newQuantity < 0) {
            $errors['new_quantity'] = 'Quantity must be zero or greater.';
        }

        $allowedTypes = ['opening','adjustment','sale','return','refund','cancel','transfer_in','transfer_out','damage','stocktake'];
        if (!in_array($type, $allowedTypes, true)) {
            $errors['movement_type'] = 'Invalid movement type.';
        }

        if ($errors !== []) {
            $session->flash('inventory_errors', $errors);
            return Response::redirect('/commerce/ca-admin/inventory');
        }

        $current = $repo->forUnit($productId, $variantId);
        $currentQty = $current?->quantity ?? 0;
        $newQty = (int) $newQuantity;
        $delta = $newQty - $currentQty;

        if ($delta === 0) {
            $session->flash('info', 'Quantity unchanged. No movement logged.');
            return Response::redirect('/commerce/ca-admin/inventory');
        }

        $repo->adjust($productId, $variantId, $delta, $type, [
            'reason'     => $reason !== '' ? $reason : null,
            'created_by' => $auth->user()?->id,
        ]);

        $session->flash('success', 'Stock adjusted. New quantity: ' . $newQty . '.');
        return Response::redirect('/commerce/ca-admin/inventory');
    }

    // =================================================================
    // CSV exports
    // =================================================================

    /** @param array<string,string> $params */
    public function exportCsv(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(InventoryRepository::class);

        $filters = [
            'q'           => trim((string) $request->query('q', '')),
            'status'      => (string) $request->query('status', ''),
            'category_id' => (int) $request->query('category_id', 0),
            'brand_id'    => (int) $request->query('brand_id', 0),
        ];
        $filters = array_filter($filters, fn ($v) => $v !== '' && $v !== 0);

        // Fetch up to 10,000 rows for export
        $result = $repo->paginate($filters, 1, 10000);

        $rows = [];
        $rows[] = ['Product', 'SKU', 'Variant', 'Quantity', 'Reserved', 'Available', 'Threshold', 'Status'];

        foreach ($result['items'] as $r) {
            $threshold = $r['unit_threshold'] ?? $r['product_threshold'] ?? 5;
            $available = (int) $r['available'];
            $status = 'In Stock';
            if ($available <= 0) {
                $status = 'Out of Stock';
            } elseif ($available <= $threshold) {
                $status = 'Low Stock';
            }

            $rows[] = [
                $r['product_name'],
                $r['product_sku'] ?? '',
                $r['variant_summary'] ?? '—',
                (int) $r['quantity'],
                (int) $r['reserved'],
                $available,
                (int) $threshold,
                $status,
            ];
        }

        return $this->csvResponse('inventory-' . date('Y-m-d') . '.csv', $rows);
    }

    /** @param array<string,string> $params */
    public function exportMovementsCsv(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(InventoryRepository::class);

        $filters = [
            'q'    => trim((string) $request->query('q', '')),
            'type' => (string) $request->query('type', ''),
            'from' => (string) $request->query('from', ''),
            'to'   => (string) $request->query('to', ''),
        ];
        $filters = array_filter($filters, fn ($v) => $v !== '');

        $result = $repo->movementsPaginate($filters, 1, 10000);

        $rows = [];
        $rows[] = ['Date', 'Product', 'Variant', 'Type', 'Delta', 'After', 'Reason', 'Reference', 'Actor'];

        foreach ($result['items'] as $r) {
            $rows[] = [
                $r['created_at'],
                $r['product_name'],
                $r['variant_summary'] ?? '—',
                $r['movement_type'],
                (int) $r['quantity_delta'],
                (int) $r['quantity_after'],
                $r['reason'] ?? '',
                $r['reference_type'] ? ($r['reference_type'] . '#' . $r['reference_id']) : '',
                $r['actor_name'] ?? 'System',
            ];
        }

        return $this->csvResponse('inventory-movements-' . date('Y-m-d') . '.csv', $rows);
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     */
    private function csvResponse(string $filename, array $rows): Response
    {
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return Response::html('Could not open temporary stream for CSV export.', 500);
        }
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        $csv = $csv === false ? '' : $csv;

        return new Response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
