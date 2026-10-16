<?php
declare(strict_types=1);

/**
 * Smoke case: M-Pesa webhook route.
 *
 * Exercises WebhookController::mpesa() end to end through
 * the same code path the route uses. The sibling case
 * (mpesa-callback) calls the provider methods directly;
 * this case proves the controller handles the request
 * shape, records the event row, marks it processed, and
 * delegates to the provider.
 *
 * The case:
 *
 *   1. Seeds a test order in 'unpaid' state with
 *      payment_provider_key = 'mpesa'.
 *   2. Inserts a pending row in commerce_mpesa_requests
 *      with a known CheckoutRequestID.
 *   3. Builds a synthetic Safaricom callback body with
 *      ResultCode 0 and an M-Pesa receipt.
 *   4. Constructs a Request carrying that body.
 *   5. Calls WebhookController::mpesa() directly.
 *   6. Asserts the response status is 200 or 204.
 *   7. Asserts the order is paid with the receipt as
 *      payment_reference.
 *   8. Asserts the commerce_webhook_events row exists
 *      with provider_key = 'mpesa', the correct event id
 *      (the CheckoutRequestID), and processed_at set.
 *   9. Asserts the commerce_mpesa_requests row is
 *      completed.
 *  10. Asserts a second delivery is refused with 400.
 *      The provider's verifyWebhook() looks up the
 *      CheckoutRequestID in commerce_mpesa_requests;
 *      after the first delivery, the row is 'completed',
 *      not 'pending'. The provider refuses the replay.
 *      This is the M-Pesa plan's design: a callback whose
 *      CheckoutRequestID is no longer pending is treated
 *      as a forgery or a replay and is refused.
 *
 *      The Stripe-webhook case asserts the opposite
 *      behaviour (200 or 204 on a second delivery) because
 *      Stripe retries webhooks by design and the idempotency
 *      check on commerce_webhook_events handles the retry.
 *      M-Pesa does not retry callbacks; the CheckoutRequestID
 *      is a one-shot token.
 *
 * No HTTP. No Daraja credentials. The full webhook path
 * through the controller.
 *
 * @param \CorporateAid\Core\Container $container
 * @param PDO $pdo  the test database connection
 */

use CorporateAid\Commerce\Controllers\WebhookController;
use CorporateAid\Core\Request;

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
    // Fixture: an unpaid order and a pending mpesa row
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
    // Synthetic Safaricom success callback
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
        $callbackBody = '';
    }

    // --------------------------------------------------------------
    // Build a Request the way the router does
    // --------------------------------------------------------------
    $request = new Request(
        'POST',
        '/webhooks/mpesa',
        '',
        [],
        [],
        [],
        [],
        [],
        [],
        $callbackBody
    );

    /** @var WebhookController $controller */
    $controller = $container->get(WebhookController::class);

    // --------------------------------------------------------------
    // Assertion 1: the controller returns 200 or 204
    // --------------------------------------------------------------
    $response = $controller->mpesa($request, []);
    if ($response->statusCode() !== 200 && $response->statusCode() !== 204) {
        $failures[] = 'assertion 1: handler returned status ' . $response->statusCode()
                    . ' (expected 200 or 204)';
    }

    // --------------------------------------------------------------
    // Assertion 2: the order is paid
    // --------------------------------------------------------------
    $order = $pdo->query(
        "SELECT `payment_status`, `payment_reference`
           FROM `commerce_orders` WHERE `id` = " . (int) $orderId
    )->fetch();

    if ($order === false) {
        $failures[] = 'assertion 2: could not re-read the order';
    } else {
        if ($order['payment_status'] !== 'paid') {
            $failures[] = "assertion 2: payment_status is {$order['payment_status']}, expected paid";
        }
        if ($order['payment_reference'] !== $receipt) {
            $failures[] = "assertion 2: payment_reference is {$order['payment_reference']}, expected {$receipt}";
        }
    }

    // --------------------------------------------------------------
    // Assertion 3: the webhook event row exists and is processed
    // --------------------------------------------------------------
    $eventRow = $pdo->query(
        "SELECT `id`, `order_id`, `processed_at`
           FROM `commerce_webhook_events`
          WHERE `provider_key` = 'mpesa' AND `event_id` = "
        . $pdo->quote($checkoutRequestId)
    )->fetch();

    if ($eventRow === false) {
        $failures[] = 'assertion 3: event row not found for provider_key = mpesa';
    } else {
        if ((int) $eventRow['order_id'] !== $orderId) {
            $failures[] = "assertion 3: event row order_id is {$eventRow['order_id']}, expected {$orderId}";
        }
        if ($eventRow['processed_at'] === null || $eventRow['processed_at'] === '') {
            $failures[] = 'assertion 3: event row not marked processed';
        }
    }

    // --------------------------------------------------------------
    // Assertion 4: the mpesa request row is completed
    // --------------------------------------------------------------
    $row = $pdo->query(
        "SELECT `status`, `mpesa_receipt`
           FROM `commerce_mpesa_requests` WHERE `id` = " . (int) $mpesaRowId
    )->fetch();

    if ($row === false) {
        $failures[] = 'assertion 4: could not re-read the mpesa row';
    } else {
        if ($row['status'] !== 'completed') {
            $failures[] = "assertion 4: row status is {$row['status']}, expected completed";
        }
        if ($row['mpesa_receipt'] !== $receipt) {
            $failures[] = "assertion 4: mpesa_receipt is {$row['mpesa_receipt']}, expected {$receipt}";
        }
    }

    // --------------------------------------------------------------
    // Assertion 5: replay refusal
    // --------------------------------------------------------------
    // After the first delivery, the mpesa_requests row is
    // 'completed'. The provider's verifyWebhook() refuses
    // any callback whose CheckoutRequestID is no longer
    // pending. The second delivery of the same callback
    // returns 400.
    //
    // Stripe's case asserts the opposite; Stripe retries
    // webhooks by design and the commerce_webhook_events
    // idempotency check handles the retry. M-Pesa's
    // CheckoutRequestID is a one-shot token. The plan
    // names this explicitly: a callback whose
    // CheckoutRequestID is not in the pending set is a
    // forgery or a replay and is refused.

    $second = $controller->mpesa($request, []);
    if ($second->statusCode() !== 400) {
        $failures[] = 'assertion 5: replay was not refused - got status '
                    . $second->statusCode() . ', expected 400';
    }

    // The mpesa_requests row must still be 'completed'. The
    // replay was refused before any write.
    $rowAfter = $pdo->query(
        "SELECT `status` FROM `commerce_mpesa_requests` WHERE `id` = " . (int) $mpesaRowId
    )->fetch();

    if ($rowAfter === false || $rowAfter['status'] !== 'completed') {
        $failures[] = 'assertion 5: mpesa_requests row changed state on a refused replay';
    }

    // --------------------------------------------------------------
    // Cleanup
    // --------------------------------------------------------------
    $pdo->prepare(
        "DELETE FROM `commerce_webhook_events`
          WHERE `provider_key` = 'mpesa' AND `event_id` = ?"
    )->execute([$checkoutRequestId]);
    $pdo->prepare("DELETE FROM `commerce_mpesa_requests` WHERE `id` = ?")
        ->execute([$mpesaRowId]);
    $fx['cleanupOrder']($pdo, $orderId);
    $cleanupProduct($productId);

    if ($failures !== []) {
        return implode(' | ', $failures);
    }
    return true;
};
