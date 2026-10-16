<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Payment\Providers;

use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Models\Order;
use CorporateAid\Commerce\Services\Payment\PaymentProvider;

/**
 * Cash on delivery.
 *
 * An offline payment method. The customer places the order
 * and pays the courier in cash on delivery. No external API,
 * no redirect, no webhook.
 *
 * The provider class exists so the registry can resolve the
 * key and the admin Settings > Payments tab can render a
 * toggle row. The charge and refund paths short-circuit on
 * the key via PaymentChargeService::OFFLINE_KEYS and
 * PaymentRefundService::OFFLINE_KEYS; the class itself is
 * not called during a charge or refund.
 *
 * Stage 010, sub-stage 010.5.
 */
final class CashOnDeliveryProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'cod';
    }

    public function label(): string
    {
        return 'Cash on delivery';
    }

    public function isConfigured(StoreContext $ctx): bool
    {
        // An offline method has no credentials. The merchant
        // enables or disables it via the admin toggle; the
        // toggle state lives in commerce_store_payment_methods.
        return true;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        return [
            'ok'      => true,
            'message' => 'Offline method. No connection to test.',
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array{ok: bool, redirect_url?: string,
     *               provider_reference?: string, error?: string}
     */
    public function charge(Order $order, array $context): array
    {
        // Offline methods never reach this method. The
        // charge service short-circuits on the key. The
        // return exists to satisfy the interface.
        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, provider_reference?: string,
     *               error?: string}
     */
    public function capture(Order $order, string $providerReference): array
    {
        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, refund_id?: string,
     *               error?: string}
     */
    public function refund(Order $order, float $amount, string $idempotencyKey): array
    {
        // Offline refunds are recorded by
        // PaymentRefundService without a provider call.
        // The return exists to satisfy the interface.
        return ['ok' => true, 'refund_id' => 'offline'];
    }

    /**
     * Offline methods have no webhook, so no event id to
     * extract. The method exists to satisfy the interface
     * contract. The controller never reaches it: the
     * provider's verifyWebhook() returns false for every
     * request, so the controller returns 400 before
     * calling this method.
     */
    public function extractEventId(string $rawBody): string
    {
        return '';
    }

    /**
     * @param array<string,string> $headers
     */
    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        // Offline methods have no webhooks. Any incoming
        // webhook claim is refused.
        return false;
    }

    /**
     * @param array<string,string> $headers
     * @return array{handled: bool, event_type: string}
     */
    public function handleWebhook(string $rawBody, array $headers): array
    {
        return ['handled' => false, 'event_type' => 'none'];
    }
}
