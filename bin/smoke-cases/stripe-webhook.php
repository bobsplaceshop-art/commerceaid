<?php
declare(strict_types=1);

/**
 * Smoke case: Stripe webhook handler.
 *
 * Exercises WebhookController::stripe() end to end without
 * a network call. The case:
 *
 *   1. Seeds a test webhook secret into commerce_store_settings.
 *   2. Creates a test order in 'unpaid' state with
 *      payment_provider_key = 'stripe'.
 *   3. Builds a Stripe-formatted payment_intent.succeeded
 *      payload pointing at the order via metadata.order_id.
 *   4. Signs the payload with the same HMAC-SHA256 scheme
 *      StripeProvider::verifyWebhook() uses.
 *   5. Constructs a Request with the raw body and the
 *      Stripe-Signature header.
 *   6. Calls WebhookController::stripe() directly.
 *   7. Asserts the order is paid, the event row exists,
 *      and a second delivery is idempotent.
 *
 * Returns true on pass, a string describing the failure
 * on fail.
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

    // A fixed, random webhook secret for this run. Deleted
    // from settings in the cleanup block.
    $webhookSecret = 'whsec_test_' . bin2hex(random_bytes(16));

    $pdo->prepare(
        "INSERT INTO `commerce_store_settings`
            (`store_id`, `key_name`, `value`)
         VALUES (?, 'payments.stripe.webhook_secret', ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
    )->execute([$storeId, $webhookSecret]);

    $cleanupSettings = function () use ($pdo, $storeId): void {
        $pdo->prepare(
            "DELETE FROM `commerce_store_settings`
              WHERE `store_id` = ? AND `key_name` = 'payments.stripe.webhook_secret'"
        )->execute([$storeId]);
    };

    // --------------------------------------------------------------
    // Fixture: a paid-able order
    // --------------------------------------------------------------
    $productId = $fx['createProduct']($pdo, $storeId);
    $orderId   = $fx['createOrder']($pdo, $storeId, 'stripe', 100.0, 0.0, 'unpaid');
    $itemId    = $fx['createOrderItem']($pdo, $storeId, $orderId, $productId, 1, 100.0);

    $orderNumber = (string) $pdo->query(
        "SELECT `order_number` FROM `commerce_orders` WHERE `id` = " . (int) $orderId
    )->fetchColumn();

    if ($orderNumber === '') {
        $failures[] = 'fixture: could not read the order number';
    }

    // --------------------------------------------------------------
    // Build and sign a Stripe payment_intent.succeeded payload
    // --------------------------------------------------------------
    $eventId   = 'evt_test_' . bin2hex(random_bytes(8));
    $intentId  = 'pi_test_' . bin2hex(random_bytes(8));

    $payload = [
        'id'   => $eventId,
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id'       => $intentId,
                'metadata' => [
                    'order_id'     => (string) $orderId,
                    'order_number' => $orderNumber,
                ],
            ],
        ],
    ];

    $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($rawBody === false) {
        $cleanupSettings();
        $fx['cleanupOrder']($pdo, $orderId);
        $cleanupProduct($productId);
        return 'fixture: could not encode the payload';
    }

    $timestamp     = time();
    $signedPayload = $timestamp . '.' . $rawBody;
    $signature     = hash_hmac('sha256', $signedPayload, $webhookSecret);
    $headerValue   = "t={$timestamp},v1={$signature}";

    // --------------------------------------------------------------
    // Build a Request that carries the raw body and the header
    // --------------------------------------------------------------
    $request = new Request(
        'POST',
        '/webhooks/stripe',
        '',
        [],
        [],
        [],
        [],
        [],
        ['Stripe-Signature' => $headerValue],
        $rawBody
    );

    // --------------------------------------------------------------
    // Invoke the controller
    // --------------------------------------------------------------
    $controller = $container->get(WebhookController::class);
    $response   = $controller->stripe($request, []);

    if ($response->statusCode() !== 204 && $response->statusCode() !== 200) {
        $failures[] = 'handler returned status ' . $response->statusCode() . ' (expected 200 or 204)';
    }

    // --------------------------------------------------------------
    // Assertion 1: the order is now paid
    // --------------------------------------------------------------
    $order = $pdo->query(
        "SELECT `payment_status`, `payment_reference`
           FROM `commerce_orders` WHERE `id` = " . (int) $orderId
    )->fetch();

    if ($order === false) {
        $failures[] = 'assertion 1: could not re-read the order';
    } else {
        if ($order['payment_status'] !== 'paid') {
            $failures[] = "assertion 1: payment_status is {$order['payment_status']}, expected paid";
        }
        if ($order['payment_reference'] !== $intentId) {
            $failures[] = "assertion 1: payment_reference is {$order['payment_reference']}, expected {$intentId}";
        }
    }

    // --------------------------------------------------------------
    // Assertion 2: the event row exists
    // --------------------------------------------------------------
    $eventRow = $pdo->query(
        "SELECT `id`, `order_id`, `processed_at`
           FROM `commerce_webhook_events`
          WHERE `provider_key` = 'stripe' AND `event_id` = "
        . $pdo->quote($eventId)
    )->fetch();

    if ($eventRow === false) {
        $failures[] = 'assertion 2: event row not found';
    } else {
        if ((int) $eventRow['order_id'] !== $orderId) {
            $failures[] = "assertion 2: event row order_id is {$eventRow['order_id']}, expected {$orderId}";
        }
        if ($eventRow['processed_at'] === null || $eventRow['processed_at'] === '') {
            $failures[] = 'assertion 2: event row not marked processed';
        }
    }

    // --------------------------------------------------------------
    // Assertion 3: idempotency - a second delivery is a no-op
    // --------------------------------------------------------------
    $paidAtBefore = (string) $pdo->query(
        "SELECT `paid_at` FROM `commerce_orders` WHERE `id` = " . (int) $orderId
    )->fetchColumn();

    // Backdate the order's paid_at so a second write is
    // visible. If the handler runs again, paid_at moves.
    $pdo->prepare(
        "UPDATE `commerce_orders` SET `paid_at` = '2000-01-01 00:00:00' WHERE `id` = ?"
    )->execute([$orderId]);

    $secondResponse = $controller->stripe($request, []);
    if ($secondResponse->statusCode() !== 204 && $secondResponse->statusCode() !== 200) {
        $failures[] = 'assertion 3: second delivery returned status ' . $secondResponse->statusCode();
    }

    $paidAtAfter = (string) $pdo->query(
        "SELECT `paid_at` FROM `commerce_orders` WHERE `id` = " . (int) $orderId
    )->fetchColumn();

    if ($paidAtAfter !== '2000-01-01 00:00:00') {
        $failures[] = "assertion 3: idempotency broken - paid_at moved to {$paidAtAfter}";
    }

    // Restore a sane paid_at for any later assertion / reader.
    $pdo->prepare(
        "UPDATE `commerce_orders` SET `paid_at` = ? WHERE `id` = ?"
    )->execute([$paidAtBefore !== '' ? $paidAtBefore : date('Y-m-d H:i:s'), $orderId]);

    // --------------------------------------------------------------
    // Cleanup
    // --------------------------------------------------------------
    $pdo->prepare("DELETE FROM `commerce_webhook_events` WHERE `event_id` = ?")
        ->execute([$eventId]);
    $fx['cleanupOrder']($pdo, $orderId);
    $cleanupProduct($productId);
    $cleanupSettings();

    if ($failures !== []) {
        return implode(' | ', $failures);
    }
    return true;
};
