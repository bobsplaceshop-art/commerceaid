<?php
declare(strict_types=1);

/**
 * Shared smoke case fixtures.
 *
 * Returns an array of closures that create and tear down
 * the rows a case needs. Every closure receives $pdo and
 * $storeId through the closure's `use` list. The array is
 * not tied to the container; a case that needs the
 * container receives it from the harness.
 *
 * Names starting with an underscore are excluded from the
 * harness's case discovery. This file is a fixtures
 * library, not a runnable case.
 *
 * Fixture helpers:
 *   createProduct()       - a published product
 *   createOrder()         - an order with a given provider
 *   createOrderItem()     - one line item on the order
 *   createReturnRequest() - a return request in a given status
 *   cleanupOrder()        - removes everything a case created
 *
 * Stage 010 Session 2.
 *
 * @return array<string, callable>
 */

return (function (): array {
    // The closures share $pdo and $storeId through `use`.
    // The harness resolves both before requiring this file:
    // $pdo is the test database handle, $storeId is the
    // seeded store's id. Neither is available here as a
    // parameter, so the closures are built lazily.
    //
    // The pattern: each closure accepts ($pdo, $storeId) as
    // its first two parameters, and the case supplies them.
    // That keeps this file stateless and each closure
    // independently testable.

    $findAnyCustomer = function (PDO $pdo, int $storeId): int {
        $row = $pdo->prepare(
            "SELECT `id` FROM `commerce_customers`
              WHERE `store_id` = ?
              ORDER BY `id` ASC LIMIT 1"
        );
        $row->execute([$storeId]);
        $id = (int) ($row->fetchColumn() ?: 0);
        if ($id <= 0) {
            throw new \RuntimeException(
                'Test database has no customer. The seeders should have created one.'
            );
        }
        return $id;
    };

    $createProduct = function (
        PDO $pdo,
        int $storeId,
        string $name = 'Smoke Product',
        float $price = 50.0
    ): int {
        $slug = 'smoke-' . bin2hex(random_bytes(4));
        $sku  = 'SMOKE-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $pdo->prepare(
            "INSERT INTO `commerce_products`
                (`store_id`, `slug`, `sku`, `name`, `product_type`,
                 `price`, `status`, `published_at`)
             VALUES (?, ?, ?, ?, 'simple', ?, 'published', NOW())"
        );
        $stmt->execute([$storeId, $slug, $sku, $name, $price]);
        return (int) $pdo->lastInsertId();
    };

    $createOrder = function (
        PDO $pdo,
        int $storeId,
        string $providerKey,
        float $grandTotal = 100.0,
        float $refundedTotal = 0.0
    ): int {
        $stmt = $pdo->prepare(
            "INSERT INTO `commerce_orders`
                (`store_id`, `order_number`, `customer_id`, `guest_email`,
                 `currency_code`, `status`, `payment_status`, `fulfilment_status`,
                 `subtotal`, `discount_total`, `tax_total`, `shipping_total`,
                 `grand_total`, `paid_total`, `refunded_total`,
                 `payment_provider_key`, `payment_reference`)
             VALUES (?, ?, NULL, ?, 'EUR', 'delivered', 'paid', 'fulfilled',
                     ?, 0, 0, 0, ?, ?, ?, ?, ?)"
        );
        $orderNumber = 'SMOKE-' . bin2hex(random_bytes(4));
        $stmt->execute([
            $storeId,
            $orderNumber,
            'smoke-' . bin2hex(random_bytes(4)) . '@example.test',
            $grandTotal,
            $grandTotal,
            $grandTotal,
            $refundedTotal,
            $providerKey,
            $providerKey !== '' ? 'pi_smoke_' . bin2hex(random_bytes(4)) : null,
        ]);
        return (int) $pdo->lastInsertId();
    };

    $createOrderItem = function (
        PDO $pdo,
        int $storeId,
        int $orderId,
        int $productId,
        int $quantity = 2,
        float $unitPrice = 50.0
    ): int {
        $lineTotal = $unitPrice * $quantity;
        $stmt = $pdo->prepare(
            "INSERT INTO `commerce_order_items`
                (`store_id`, `order_id`, `product_id`, `variant_id`,
                 `product_name`, `variant_label`, `sku`,
                 `quantity`, `unit_price`, `line_subtotal`,
                 `line_discount`, `line_tax`, `line_total`,
                 `returned_quantity`)
             VALUES (?, ?, ?, NULL, 'Smoke Product', NULL, 'SMOKE-1',
                     ?, ?, ?, 0, 0, ?, 0)"
        );
        $stmt->execute([
            $storeId,
            $orderId,
            $productId,
            $quantity,
            $unitPrice,
            $lineTotal,
            $lineTotal,
        ]);
        return (int) $pdo->lastInsertId();
    };

    $createReturnRequest = function (
        PDO $pdo,
        int $storeId,
        int $orderId,
        int $customerId,
        int $orderItemId,
        int $quantity,
        float $unitRefund,
        string $status = 'received'
    ): int {
        $requestNumber = 'RMA-SMOKE-' . bin2hex(random_bytes(3));
        $lineRefund    = round($unitRefund * $quantity, 4);

        $pdo->prepare(
            "INSERT INTO `commerce_return_requests`
                (`store_id`, `order_id`, `customer_id`,
                 `request_number`, `status`, `reason_code`,
                 `refund_total`, `reviewed_at`)
             VALUES (?, ?, ?, ?, ?, 'damaged', ?, NOW())"
        )->execute([
            $storeId,
            $orderId,
            $customerId,
            $requestNumber,
            $status,
            $lineRefund,
        ]);
        $requestId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            "INSERT INTO `commerce_return_request_items`
                (`store_id`, `request_id`, `order_item_id`, `quantity`,
                 `unit_refund`, `line_refund`)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([
            $storeId,
            $requestId,
            $orderItemId,
            $quantity,
            $unitRefund,
            $lineRefund,
        ]);

        return $requestId;
    };

    $cleanupOrder = function (PDO $pdo, int $orderId): void {
        // Order of deletion respects the FKs.
        //   payment_refunds        -> order
        //   return_request_items   -> return_requests -> order
        //   return_requests        -> order
        //   order_items            -> order (and -> product)
        //   orders                 -> (nothing here depends on it after this)
        $pdo->prepare("DELETE FROM `commerce_payment_refunds` WHERE `order_id` = ?")
            ->execute([$orderId]);
        $pdo->prepare(
            "DELETE FROM `commerce_return_request_items`
              WHERE `request_id` IN (
                  SELECT `id` FROM `commerce_return_requests` WHERE `order_id` = ?
              )"
        )->execute([$orderId]);
        $pdo->prepare("DELETE FROM `commerce_return_requests` WHERE `order_id` = ?")
            ->execute([$orderId]);
        $pdo->prepare("DELETE FROM `commerce_order_items` WHERE `order_id` = ?")
            ->execute([$orderId]);
        $pdo->prepare("DELETE FROM `commerce_orders` WHERE `id` = ?")
            ->execute([$orderId]);
    };

    return [
        'findAnyCustomer'     => $findAnyCustomer,
        'createProduct'       => $createProduct,
        'createOrder'         => $createOrder,
        'createOrderItem'     => $createOrderItem,
        'createReturnRequest' => $createReturnRequest,
        'cleanupOrder'        => $cleanupOrder,
    ];
})();
