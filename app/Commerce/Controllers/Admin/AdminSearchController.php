<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;

final class AdminSearchController extends AdminController
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $q = trim((string) $request->query('q', ''));
        
        $products = [];
        $orders = [];
        
        if ($q !== '') {
            $productRepo = $this->container->get(ProductRepository::class);
            $orderRepo = $this->container->get(OrderRepository::class);
            
            // Search products by name or SKU
            $products = $productRepo->search($q, 10);
            
            // Search orders by number or customer email
            $orders = $orderRepo->search($q, 10);
        }

        return $this->render('admin.search.index', [
            'title' => 'Search Results',
            'q' => $q,
            'products' => $products,
            'orders' => $orders,
        ]);
    }

    /** @param array<string,string> $params */
    public function live(Request $request, array $params = []): Response
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return Response::json(['products' => [], 'orders' => []]);
        }

        $productRepo = $this->container->get(ProductRepository::class);
        $orderRepo = $this->container->get(OrderRepository::class);

        // Return only the essential fields for the dropdown
        $products = array_map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'price' => $p->price,
            'url' => "/commerce/ca-admin/products/{$p->id}/edit"
        ], $productRepo->search($q, 5));

        $orders = array_map(fn($o) => [
            'id' => $o->id,
            'number' => $o->orderNumber,
            'customer' => 'Order', // Fallback since the model doesn't expose customerName
            'status' => $o->status,
            'url' => "/commerce/ca-admin/orders/{$o->id}"
        ], $orderRepo->search($q, 3));

        return Response::json([
            'products' => $products,
            'orders' => $orders,
            'total' => count($products) + count($orders)
        ]);
    }

}
