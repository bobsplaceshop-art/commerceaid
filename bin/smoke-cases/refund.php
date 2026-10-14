<?php
declare(strict_types=1);

/**
 * Smoke case: payment refund wrapper.
 *
 * Exercises PaymentRefundService through the paths that
 * exist today:
 *
 *   1. Offline refund (cod) - no provider call, row is
 *      inserted as succeeded.
 *
 *   2. Provider key with no class in the registry (mpesa)
 *      - row is inserted as pending, no exception thrown.
 *
 * Paths 3 and 4 (provider returns error, provider
 * succeeds) need a provider that resolves and a
 * configured credential. They ship after Stripe and
 * PayPal land and after a store setting carries the
 * credentials. This case detects whether a provider
 * resolves for 'stripe' and notes the two paths as
 * "not yet implemented" when it does, rather than
 * failing.
 *
 * Returns true on pass, a string describing the failure
 * on fail. The harness catches thrown exceptions itself.
 *
 * @param \CorporateAid\Core\Container $container
 * @param PDO $pdo  the test database connection
 */

use CorporateAid\Commerce\Services\Payment\PaymentRefundService;
use CorporateAid\Commerce\Services\Payment\PaymentProviderRegistry;

return function ($container, PDO $pdo): bool|string {

    /** @var array<string, callable> $fx */
    $fx = require __DIR__ . '/_fixtures.php';

    $storeCtx = $container->get(\CorporateAid\Commerce\Context\StoreContext::class);
    $storeId  = $storeCtx->id();

    // Two-line closure: delete the product row after the
    // order that referenced it is gone. ON DELETE SET NULL
    // on the order-item FK means deleting the product
    // before the order item would leave a NULL ref, which
    // is valid but unusual. Order first, product second.
    $cleanupProduct = function (int $productId) use ($pdo): void {
        $pdo->prepare("DELETE FROM `commerce_products` WHERE `id` = ?")
            ->execute([$productId]);
    };

    $failures = [];

    /** @var PaymentRefundService $svc */
    $svc = $container->get(PaymentRefundService::class);

    // The returns table requires a real customer id. The
    // seeders create one. Read it once for the whole case.
    $customerId = $fx['findAnyCustomer']($pdo, $storeId);

    // --------------------------------------------------------------
    // Path 1: offline refund (cod)
    // --------------------------------------------------------------
    $productId = $fx['createProduct']($pdo, $storeId);
    $orderId   = $fx['createOrder']($pdo, $storeId, 'cod', 100.0);
    $itemId    = $fx['createOrderItem']($pdo, $storeId, $orderId, $productId, 2, 50.0);
    $reqId     = $fx['createReturnRequest']($pdo, $storeId, $orderId, $customerId, $itemId, 2, 50.0);

    $result = $svc->refund($reqId, 1);

    if ($result->status !== 'succeeded') {
        $failures[] = "path 1 (offline): expected status succeeded, got {$result->status}";
    } elseif ($result->providerKey !== 'cod') {
        $failures[] = "path 1 (offline): expected provider cod, got {$result->providerKey}";
    } else {
        $row = $pdo->query(
            "SELECT `status`, `provider_refund_id`
               FROM `commerce_payment_refunds`
              WHERE `order_id` = " . (int) $orderId . " LIMIT 1"
        )->fetch();
        if ($row === false || $row['status'] !== 'succeeded') {
            $failures[] = "path 1 (offline): refund row not succeeded";
        }
        // The order's refunded_total must equal the refund
        // amount after the re-derive.
        $order = $pdo->query(
            "SELECT `refunded_total`, `payment_status`
               FROM `commerce_orders` WHERE `id` = " . (int) $orderId
        )->fetch();
        if ($order === false) {
            $failures[] = "path 1 (offline): could not re-read the order";
        } else {
            $expectedRefund = 100.0;
            if (abs((float) $order['refunded_total'] - $expectedRefund) > 0.001) {
                $failures[] = "path 1 (offline): refunded_total is {$order['refunded_total']}, expected {$expectedRefund}";
            }
            if ($order['payment_status'] !== 'refunded') {
                $failures[] = "path 1 (offline): payment_status is {$order['payment_status']}, expected refunded";
            }
        }
    }

    $fx['cleanupOrder']($pdo, $orderId);
    $cleanupProduct($productId);

    // --------------------------------------------------------------
    // Path 2: provider key with no class in the registry (mpesa)
    // --------------------------------------------------------------
    // The M-Pesa provider class does not exist yet. The
    // registry's resolve() returns null for it. The wrapper
    // inserts the row as pending and returns without
    // dispatching.
    //
    // Stripe and PayPal cannot be used for this path: both
    // classes ship in Session 3 and Session 4 respectively
    // and now resolve. The registry would return a real
    // provider.
    $productId = $fx['createProduct']($pdo, $storeId);
    $orderId   = $fx['createOrder']($pdo, $storeId, 'mpesa', 100.0);
    $itemId    = $fx['createOrderItem']($pdo, $storeId, $orderId, $productId, 1, 100.0);
    $reqId     = $fx['createReturnRequest']($pdo, $storeId, $orderId, $customerId, $itemId, 1, 100.0);

    $result = $svc->refund($reqId, 1);

    if ($result->status !== 'pending') {
        $failures[] = "path 2 (registry-miss): expected status pending, got {$result->status}";
    } elseif ($result->providerKey !== 'mpesa') {
        $failures[] = "path 2 (registry-miss): expected provider mpesa, got {$result->providerKey}";
    } else {
        $row = $pdo->query(
            "SELECT `status` FROM `commerce_payment_refunds`
              WHERE `order_id` = " . (int) $orderId . " LIMIT 1"
        )->fetch();
        if ($row === false || $row['status'] !== 'pending') {
            $failures[] = "path 2 (registry-miss): refund row not pending";
        }
    }

    $fx['cleanupOrder']($pdo, $orderId);
    $cleanupProduct($productId);

    // --------------------------------------------------------------
    // Paths 3 and 4: provider-level flows
    // --------------------------------------------------------------
    // Exercisable once a provider resolves AND has
    // credentials. The StripeProvider class exists today
    // but the test database carries no
    // payments.stripe.secret_key setting. isConfigured()
    // therefore returns false, and the wrapper cannot
    // dispatch a real call. Paths 3 and 4 stay unexercised
    // until the harness seeds a test credential.
    //
    // The condition below is documented, not silent. When
    // a real credential ships into the test database, the
    // case fails loudly because the placeholder branch
    // still fires. That is the signal to write the real
    // assertions.
    $registry = $container->get(PaymentProviderRegistry::class);
    $stripeProvider = $registry->resolve('stripe');

    if ($stripeProvider !== null) {
        $secret = $pdo->query(
            "SELECT `value` FROM `commerce_store_settings`
              WHERE `key_name` = 'payments.stripe.secret_key' LIMIT 1"
        )->fetchColumn();
        if ($secret !== false && (string) $secret !== '') {
            // A real credential is present. The placeholder
            // fires as a failure so the case is not silently
            // passing paths it has not asserted.
            $failures[] = "paths 3 and 4: Stripe is configured but the case's provider-flow assertions are not implemented yet";
        }
        // No credential: paths 3 and 4 remain unexercised.
        // The case does not fail because the paths are not
        // testable without a live credential.
    }

    if ($failures !== []) {
        return implode(' | ', $failures);
    }
    return true;
};
