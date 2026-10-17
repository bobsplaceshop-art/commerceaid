<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Payment;

use CorporateAid\Core\Container;
use CorporateAid\Commerce\Repositories\PaymentMethodRepository;

/**
 * Resolves a payment provider key to a concrete instance.
 *
 * The provider map names class names as strings. When a
 * provider's class exists, Container::get() constructs it
 * through the ADR-018 auto-construction path. When it does
 * not exist yet (a sub-stage 010.N has not shipped), the
 * registry returns null for that key.
 *
 * Adding a provider in a future sub-stage is one line in
 * PROVIDERS plus the class file. No binding in the service
 * provider, no registry edit.
 *
 * Stage 010.
 */
final class PaymentProviderRegistry
{
    /**
     * The namespace that holds every payment provider class.
     * Concatenated with a class short name to form the fully
     * qualified class string.
     */
    private const PROVIDER_NAMESPACE = 'CorporateAid\\Commerce\\Services\\Payment\\Providers\\';

    /**
     * The provider catalog.
     *
     * Keys match commerce_store_payment_methods.provider_key
     * and commerce_orders.payment_provider_key.
     *
     * Built at runtime rather than declared as a const so
     * PHPStan does not resolve the class strings at analysis
     * time. The classes ship in Sessions 3 and 4; naming
     * them literally would trip class.notFound and make the
     * runtime class_exists() check look impossible.
     *
     * resolve() returns null for any key not present, and
     * for any present key whose class does not exist.
     *
     * @return array<string, string>
     */
    private static function providerMap(): array
    {
        return [
            'stripe'        => self::PROVIDER_NAMESPACE . 'StripeProvider',
            'paypal'        => self::PROVIDER_NAMESPACE . 'PayPalProvider',
            'mpesa'         => self::PROVIDER_NAMESPACE . 'MpesaProvider',
            'pesapal'       => self::PROVIDER_NAMESPACE . 'PesapalProvider',
            'flutterwave'   => self::PROVIDER_NAMESPACE . 'FlutterwaveProvider',
            'cod'           => self::PROVIDER_NAMESPACE . 'CashOnDeliveryProvider',
            'bank_transfer' => self::PROVIDER_NAMESPACE . 'BankTransferProvider',
            'manual'        => self::PROVIDER_NAMESPACE . 'ManualProvider',
        ];
    }

    public function __construct(
        private Container $container,
        private PaymentMethodRepository $methods,
    ) {
    }

    /**
     * Resolve one provider by key. Returns null when the
     * key is unknown, when the provider's class does not
     * exist yet, or when construction fails.
     */
    public function resolve(string $key): ?PaymentProvider
    {
        $class = self::providerMap()[$key] ?? null;
        if ($class === null) {
            return null;
        }
        if (!class_exists($class)) {
            return null;
        }
        try {
            $provider = $this->container->get($class);
        } catch (\Throwable) {
            return null;
        }
        if (!$provider instanceof PaymentProvider) {
            return null;
        }
        return $provider;
    }

    /**
     * Every provider that exists in code today, keyed by
     * provider_key. Providers whose classes have not
     * shipped yet are omitted.
     *
     * @return array<string, PaymentProvider>
     */
    public function all(): array
    {
        $out = [];
        foreach (array_keys(self::providerMap()) as $key) {
            $provider = $this->resolve($key);
            if ($provider !== null) {
                $out[$key] = $provider;
            }
        }
        return $out;
    }

    /**
     * Every provider enabled for the current store, keyed
     * by provider_key, that is also configured and whose
     * class exists.
     *
     * The "enabled for store" gate is the
     * commerce_store_payment_methods row. The "configured"
     * gate is the provider's own isConfigured() check. Both
     * must pass for the provider to be offered at checkout.
     *
     * @return array<string, PaymentProvider>
     */
    public function enabledForStore(): array
    {
        $enabledKeys = $this->methods->enabledKeysForStore();

        $out = [];
        foreach ($enabledKeys as $key) {
            $provider = $this->resolve($key);
            if ($provider === null) {
                continue;
            }
            $out[$key] = $provider;
        }
        return $out;
    }

    /**
     * The provider keys that exist in code today. Used by
     * the admin settings page (Session 5) to render a row
     * per provider regardless of whether the store has it
     * enabled.
     *
     * @return array<int, string>
     */
    public function knownKeys(): array
    {
        return array_keys(self::providerMap());
    }

    /**
     * The human labels for every provider whose class
     * exists, keyed by provider_key.
     *
     * Used by the admin Settings > Payments tab to render
     * a row per provider with its display name. Providers
     * whose class has not shipped are omitted so the
     * returned set matches what the registry can actually
     * resolve.
     *
     * The label comes from the provider instance, not a
     * second catalog. One source of truth for the display
     * name.
     *
     * @return array<string, string>
     */
    public function labels(): array
    {
        $out = [];
        foreach (array_keys(self::providerMap()) as $key) {
            $provider = $this->resolve($key);
            if ($provider !== null) {
                $out[$key] = $provider->label();
            }
        }
        return $out;
    }
}
