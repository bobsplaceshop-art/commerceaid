<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Import\Handlers;

use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Repositories\CustomerRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Services\Import\ImportHandler;

final class ImportSalesHistoryHandler implements ImportHandler
{
    public function __construct(
        private OrderRepository $orders,
        private CustomerRepository $customers,
        private ProductRepository $products,
    ) {
    }

    public function entityKey(): string { return 'sales'; }
    public function entityLabel(): string { return 'Sales History'; }

    public function fields(): array
    {
        return [
            'order_number' => ['label' => 'Order Number', 'required' => true, 'hint' => 'e.g. ORD-123'],
            'customer_email' => ['label' => 'Customer Email', 'required' => true, 'hint' => 'Must match an existing customer.'],
            'order_date' => ['label' => 'Order Date', 'required' => true, 'hint' => 'YYYY-MM-DD'],
            'product_sku' => ['label' => 'Product SKU', 'required' => true, 'hint' => 'Must match an existing product.'],
            'quantity' => ['label' => 'Quantity', 'required' => true, 'hint' => 'Integer > 0'],
            'total_amount' => ['label' => 'Total Amount', 'required' => true, 'hint' => 'Total paid for this line.'],
        ];
    }

    public function validate(array $row): ?string
    {
        if (empty($row['order_number'])) return 'Order Number required.';
        if (empty($row['customer_email'])) return 'Customer Email required.';
        if (empty($row['order_date'])) return 'Order Date required.';
        if (empty($row['product_sku'])) return 'Product SKU required.';
        
        $product = $this->products->findBySku($row['product_sku']);
        if (!$product) return "Product {$row['product_sku']} not found. Import catalog first.";

        $customer = $this->customers->findByEmail($row['customer_email']);
        if (!$customer) return "Customer {$row['customer_email']} not found. Import customers first.";

        return null;
    }

    public function naturalKey(array $row): string
    {
        // Order number + SKU makes it unique per line
        return $row['order_number'] . ':' . $row['product_sku'];
    }

    public function exists(string $naturalKey): bool
    {
        // In a real implementation, we would check if this exact line was already imported.
        // For now, if the order exists, we skip.
        $parts = explode(':', $naturalKey);
        $orderNumber = $parts[0] ?? '';
        return $this->orders->findByNumber($orderNumber) !== null;
    }

    public function create(array $row): int
    {
        // NOTE: This requires a method in OrderRepository to create a historical order
        // bypassing the checkout flow. This is a placeholder.
        return 0; 
    }

    public function update(string $naturalKey, array $row): array { return []; }
    public function delete(int $entityId): void {}
    public function restore(int $entityId, array $previousValues): void {}
    public function findIdByNaturalKey(string $naturalKey): ?int { return null; }
}
