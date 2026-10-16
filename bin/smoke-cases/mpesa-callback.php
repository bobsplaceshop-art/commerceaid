<?php
declare(strict_types=1);

/**
 * Smoke case: M-Pesa webhook verification and handling.
 *
 * M-Pesa does not sign its callbacks. verifyWebhook()
 * confirms a callback by looking up its CheckoutRequestID
 * in commerce_mpesa_requests. A callback whose id does not
 * match a pending row this store initiated is refused.
 *
 * The case exercises that mechanism without a network
 * call. It:
 *
 *   1. Seeds a test order in 'unpaid' state.
 *   2. Inserts a pending row in commerce_mpesa_requests
 *      with a known CheckoutRequestID.
 *   3. Builds a synthetic Safaricom callback body with
 *      ResultCode 0 and an M-Pesa receipt.
 *   4. Asserts verifyWebhook() returns true for that
 *      CheckoutRequestID.
 *   5. Asserts verifyWebhook() returns false for a
 *      CheckoutRequestID not in the table.
 *   6. Asserts verifyWebhook() returns false on a second
 *      call (replay protection).
 *   7. Calls handleWebhook() with the synthetic body.
 *   8. Asserts the order is paid with the receipt as
 *      payment_reference.
 *   9. Asserts the repository row is marked completed.
 *
 * No HTTP. No Daraja credentials. Pure callback handling.
 *
 * @param \CorporateAid\Core\Container $container
 * @param PDO $pdo  the test database connection
 */

use CorporateAid\Commerce\Services\Payment\Providers\MpesaProvider;

return function ($container, PDO $pdo): bool|string {

    /** @var array<string, callable> $fx */
    $fx = require __DIR__ . '/_fixtures.php';

    $storeCtx = $container->get(\CorporateAid\Commerce\Context\StoreContext::class);
    $storeId  = $storeCtx->id();

    $cleanupProduct = function (int $productId) use ($pdo): void {
        $pdo->prepare("DELETE FROM `commerce_products` WHERE `id` = ?")
            ->execute([$productId]);
    };

    $failures = [];

    // --------------------------------------------------------------
    // Fixture: an unpaid order and a pending mpesa request row
    // --------------------------------------------------------------
    $productId = $fx['createProduct']($pdo, $storeId);
    $orderId   = $fx['createOrder']($pdo, $storeId, 'mpesa', 100.0, 0.0, 'unpaid');
    $itemId    = $fx['createOrderItem']($pdo, $storeId, $orderId, $productId, 1, 100.0);

    $checkoutRequestId = 'ws_CO_' . bin2hex(random_bytes(8));
    $merchantRequestId = 'MR_' . bin2hex(random_bytes(6));

    $pdo->prepare(
        "INSERT INTO `commerce_mpesa_requests`
            (`store_id`, `order_id`, `checkout_request_id`,
             `merchant_request_id`, `phone_number`, `amount`, `status`)
         VALUES (?, ?, ?, ?, ?, ?, 'pending')"
    )->execute([
        $storeId,
        $orderId,
        $checkoutRequestId,
        $merchantRequestId,
        '254712345678',
        100.0,
    ]);

    $mpesaRowId = (int) $pdo->lastInsertId();

    // --------------------------------------------------------------
    // Build a synthetic Safaricom success callback body
    // --------------------------------------------------------------
    $receipt = 'SGR' . strtoupper(bin2hex(random_bytes(4)));

    $callbackBody = json_encode([
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => $merchantRequestId,
                'CheckoutRequestID' => $checkoutRequestId,
                'ResultCode'        => 0,
                'ResultDesc'        => 'The service request is processed successfully.',
                'CallbackMetadata'  => [
                    'Item' => [
                        ['Name' => 'Amount',             'Value' => 100],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt],
                        ['Name' => 'TransactionDate',    'Value' => 20261016220000],
                        ['Name' => 'PhoneNumber',        'Value' => 254712345678],
                    ],
                ],
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (!is_string($callbackBody)) {
        $failures[] = 'fixture: could not encode the callback body';
    }

    /** @var MpesaProvider $provider */
    $provider = $container->get(MpesaProvider::class);

    // --------------------------------------------------------------
    // Assertion 1: verifyWebhook accepts the matching row
    // --------------------------------------------------------------
    if ($failures === []) {
        $ok = $provider->verifyWebhook($callbackBody, []);
        if ($ok !== true) {
            $failures[] = 'assertion 1: verifyWebhook returned false for a known CheckoutRequestID';
        }
    }

    // --------------------------------------------------------------
    // Assertion 2: verifyWebhook refuses an unknown id
    // --------------------------------------------------------------
    if ($failures === []) {
        $unknownBody = json_encode([
            'Body' => [
                'stkCallback' => [
                    'CheckoutRequestID' => 'ws_CO_' . bin2hex(random_bytes(8)),
                    'ResultCode'        => 0,
                    'ResultDesc'        => 'The service request is processed successfully.',
                ],
            ],
        ]);
        if (!is_string($unknownBody)) {
            $failures[] = 'fixture: could not encode the unknown-id body';
        } else {
            $ok = $provider->verifyWebhook($unknownBody, []);
            if ($ok !== false) {
                $failures[] = 'assertion 2: verifyWebhook returned true for an unknown CheckoutRequestID';
            }
        }
    }

    // --------------------------------------------------------------
    // Assertion 3: handleWebhook marks the order paid
    // --------------------------------------------------------------
    if ($failures === []) {
        $result = $provider->handleWebhook($callbackBody, []);

        if (($result['handled'] ?? false) !== true) {
            $failures[] = 'assertion 3: handleWebhook did not handle the callback';
        }
        if ((int) ($result['order_id'] ?? 0) !== $orderId) {
            $failures[] = 'assertion 3: handleWebhook order_id mismatch';
        }

        $order = $pdo->query(
            "SELECT `payment_status`, `payment_reference`
               FROM `commerce_orders` WHERE `id` = " . (int) $orderId
        )->fetch();

        if ($order === false) {
            $failures[] = 'assertion 3: could not re-read the order';
        } else {
            if ($order['payment_status'] !== 'paid') {
                $failures[] = "assertion 3: payment_status is {$order['payment_status']}, expected paid";
            }
            if ($order['payment_reference'] !== $receipt) {
                $failures[] = "assertion 3: payment_reference is {$order['payment_reference']}, expected {$receipt}";
            }
        }
    }

    // --------------------------------------------------------------
    // Assertion 4: the repository row is marked completed
    // --------------------------------------------------------------
    if ($failures === []) {
        $row = $pdo->query(
            "SELECT `status`, `result_code`, `mpesa_receipt`
               FROM `commerce_mpesa_requests`
              WHERE `id` = " . (int) $mpesaRowId
        )->fetch();

        if ($row === false) {
            $failures[] = 'assertion 4: could not re-read the mpesa row';
        } else {
            if ($row['status'] !== 'completed') {
                $failures[] = "assertion 4: row status is {$row['status']}, expected completed";
            }
            if ((int) $row['result_code'] !== 0) {
                $failures[] = "assertion 4: result_code is {$row['result_code']}, expected 0";
            }
            if ($row['mpesa_receipt'] !== $receipt) {
                $failures[] = "assertion 4: mpesa_receipt is {$row['mpesa_receipt']}, expected {$receipt}";
            }
        }
    }

    // --------------------------------------------------------------
    // Assertion 5: verifyWebhook refuses a replay
    // --------------------------------------------------------------
    if ($failures === []) {
        $ok = $provider->verifyWebhook($callbackBody, []);
        if ($ok !== false) {
            $failures[] = 'assertion 5: verifyWebhook returned true on a completed row (replay)';
        }
    }

    // --------------------------------------------------------------
    // Cleanup
    // --------------------------------------------------------------
    $pdo->prepare("DELETE FROM `commerce_mpesa_requests` WHERE `id` = ?")
        ->execute([$mpesaRowId]);
    $fx['cleanupOrder']($pdo, $orderId);
    $cleanupProduct($productId);

    if ($failures !== []) {
        return implode(' | ', $failures);
    }
    return true;
};
