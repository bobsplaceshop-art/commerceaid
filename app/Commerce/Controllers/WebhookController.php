<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers;

use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Repositories\WebhookEventRepository;
use CorporateAid\Commerce\Services\Payment\PaymentProvider;
use CorporateAid\Commerce\Services\Payment\PaymentProviderRegistry;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Platform\Support\Logger;

/**
 * Webhook receiver for payment providers.
 *
 * Routes:
 *   POST /webhooks/stripe
 *   POST /webhooks/paypal
 *
 * Not admin-authenticated, not customer-authenticated. The
 * provider posts the webhook; the signature is what proves
 * the request came from the provider.
 *
 * Idempotency is enforced at the database level. The row
 * in commerce_webhook_events carries a unique key on
 * (provider_key, event_id). A duplicate delivery is a
 * no-op.
 *
 * Status codes:
 *   200 - accepted (whether or not the handler acted)
 *   400 - malformed payload or invalid signature
 *   404 - unknown provider key
 *
 * A 200 with no action is the correct response to a
 * webhook whose provider class has not shipped yet, or
 * whose event id has already been processed. Returning a
 * non-2xx causes the provider to retry indefinitely.
 *
 * Stage 010 Session 3.
 */
final class WebhookController
{
    public function __construct(private Container $container)
    {
    }

    /** @param array<string,string> $params */
    public function stripe(Request $request, array $params = []): Response
    {
        return $this->handle($request, 'stripe');
    }

    /** @param array<string,string> $params */
    public function paypal(Request $request, array $params = []): Response
    {
        return $this->handle($request, 'paypal');
    }

    /**
     * The single webhook path. Every provider funnels here.
     */
    private function handle(Request $request, string $providerKey): Response
    {
        $rawBody = $request->rawBody();
        if ($rawBody === null || $rawBody === '') {
            return Response::json(['error' => 'Empty body.'], 400);
        }

        // Resolve the provider. If the class has not shipped
        // yet (or the store has not configured it), there is
        // nothing to verify. Return 200 so the provider does
        // not retry forever.
        $registry = $this->container->get(PaymentProviderRegistry::class);
        $provider = $registry->resolve($providerKey);
        if (!$provider instanceof PaymentProvider) {
            Logger::info('webhook.provider-unresolved', [
                'provider' => $providerKey,
            ]);
            return Response::noContent();
        }

        // Extract the signature header. The interface's
        // verifyWebhook() receives lowercased header names.
        // The header value is not modified.
        $headers = $this->lowercasedHeaders($request);

        if (!$provider->verifyWebhook($rawBody, $headers)) {
            // Do not log the body. A forged webhook is a
            // security event, not a debugging aid.
            Logger::warning('webhook.signature-invalid', [
                'provider' => $providerKey,
                'ip'       => $request->ip(),
            ]);
            return Response::json(['error' => 'Invalid signature.'], 400);
        }

        // Parse the event id and type before calling the
        // handler, so idempotency can be checked first.
        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            return Response::json(['error' => 'Malformed payload.'], 400);
        }
        $eventId   = (string) ($event['id'] ?? '');
        $eventType = (string) ($event['type'] ?? '');
        if ($eventId === '') {
            return Response::json(['error' => 'Missing event id.'], 400);
        }

        $events = $this->container->get(WebhookEventRepository::class);

        // Idempotency: an event with this (provider, id)
        // pair already exists. Nothing to do.
        if ($events->existsByProviderEvent($providerKey, $eventId)) {
            return Response::noContent();
        }

        // Record the row with a NULL order id. The order id
        // is written after the handler resolves it.
        $rowId = $events->record([
            'provider_key' => $providerKey,
            'event_id'     => $eventId,
            'event_type'   => $eventType,
            'order_id'     => null,
            'payload_json' => $rawBody,
        ]);

        // Act on the event.
        $result = $provider->handleWebhook($rawBody, $headers);

        // Backfill the order id on the row when the handler
        // resolved one.
        $resolvedOrderId = (int) ($result['order_id'] ?? 0);
        if ($resolvedOrderId > 0) {
            $orders = $this->container->get(OrderRepository::class);
            $order  = $orders->findById($resolvedOrderId);
            if ($order !== null) {
                $this->backfillOrderId($rowId, $resolvedOrderId);
            }
        }

        // Mark processed when the handler returned handled
        // = true. A handled = false result leaves the row's
        // processed_at NULL for admin review.
        if (($result['handled'] ?? false) === true) {
            $events->markProcessed($rowId);
        }

        Logger::info('webhook.processed', [
            'provider'   => $providerKey,
            'event_id'   => $eventId,
            'event_type' => $eventType,
            'order_id'   => $resolvedOrderId,
            'handled'    => (bool) ($result['handled'] ?? false),
        ]);

        return Response::noContent();
    }

    /**
     * Write the resolved order id back to the event row.
     * The row was created with order_id = NULL because the
     * id is not known until the handler reads the payload.
     *
     * Delegates to WebhookEventRepository::backfillOrderId()
     * so the query stays in the repository. Controllers do
     * not access Database directly.
     */
    private function backfillOrderId(int $rowId, int $orderId): void
    {
        $events = $this->container->get(WebhookEventRepository::class);
        $events->backfillOrderId($rowId, $orderId);
    }

    /**
     * Read every request header into a lowercased-key array.
     * Values are not modified.
     *
     * @return array<string,string>
     */
    private function lowercasedHeaders(Request $request): array
    {
        // Request::header() reads one header at a time. The
        // named headers this controller needs are the ones
        // every provider's signature scheme uses.
        //
        // To keep the interface general, iterate a small set
        // of header names the providers are documented to
        // read. A provider that needs a different header
        // adds it to this list.
        $names = [
            'Stripe-Signature',
            'PayPal-Transmission-Id',
            'PayPal-Transmission-Time',
            'PayPal-Transmission-Sig',
            'PayPal-Cert-Url',
            'PayPal-Auth-Algo',
        ];

        $out = [];
        foreach ($names as $name) {
            $value = $request->header($name, null);
            if (is_string($value) && $value !== '') {
                $out[strtolower($name)] = $value;
            }
        }
        return $out;
    }
}
