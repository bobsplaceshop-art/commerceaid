<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Core\Database;
use CorporateAid\Core\Request;
use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Exceptions\CheckoutException;
use CorporateAid\Commerce\Support\ComplianceMap;
use CorporateAid\Commerce\Models\CartLineItem;
use CorporateAid\Commerce\Models\Customer;
use CorporateAid\Commerce\Models\Order;
use CorporateAid\Commerce\Models\OrderItem;
use CorporateAid\Commerce\Repositories\CustomerAddressRepository;
use CorporateAid\Commerce\Repositories\CustomerGroupRepository;
use CorporateAid\Commerce\Repositories\InventoryRepository;
use CorporateAid\Commerce\Repositories\LegalPageRepository;
use CorporateAid\Commerce\Repositories\OrderItemRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Repositories\OrderStatusHistoryRepository;
use CorporateAid\Commerce\Repositories\StoreRepository;
use CorporateAid\Commerce\Repositories\CustomerRepository;
use CorporateAid\Commerce\Repositories\StoreSettingsRepository;
use CorporateAid\Commerce\Services\CustomerNotificationService;
use CorporateAid\Commerce\Services\CouponService;
use CorporateAid\Commerce\Services\Payment\PaymentProviderRegistry;
use CorporateAid\Commerce\Services\OrderPricingService;
use CorporateAid\Commerce\Services\TaxService;
use CorporateAid\Platform\Support\CodeGenerator;

/**
 * Order creation. The sale side of the ledger.
 *
 * The single public method placeOrder() runs the whole eight-step
 * sequence inside one database transaction. If any step fails,
 * nothing commits. There is no partial order.
 *
 * Design decisions:
 *
 *   - Stock is reserved (reserved += qty), not deducted
 *     (quantity -= qty). Physical stock is untouched until
 *     fulfilment. available = quantity - reserved, so reserving
 *     makes the units unavailable to other customers.
 *
 *   - The FOR UPDATE lock is held from step 2 (re-check) through
 *     step 7 (reserve). This serializes concurrent checkouts of
 *     the same sellable unit. Two customers buying the last unit
 *     cannot both pass the check.
 *
 *   - Order items snapshot the product state (name, sku, price)
 *     at the moment of purchase. The order renders from those
 *     snapshots even if the product is later renamed or deleted.
 *
 *   - The movement ledger is NOT written here. Reservation is
 *     not a stock change. When the order is fulfilled (Stage
 *     008F or the admin flow), the reservation converts to a
 *     real deduction and a 'sale' movement is appended then.
 *
 * See docs/stages/008e_checkout-plan.md.
 */
final class CheckoutService
{
    public function __construct(
        private Database $db,
        private CartService $cart,
        private OrderRepository $orders,
        private OrderItemRepository $orderItems,
        private OrderStatusHistoryRepository $statusHistory,
        private InventoryRepository $inventory,
        private LegalPageRepository $legalPages,
        private CustomerGroupRepository $customerGroups,
        private CustomerAddressRepository $addresses,
        private CodeGenerator $codeGen,
        private StoreContext $storeContext,
        private StoreRepository $storeRepository,
        private CustomerRepository $customerRepository,
        private StoreSettingsRepository $storeSettings,
        private CustomerNotificationService $customerNotifications,
        private OrderPricingService $pricing,
        private CouponService $coupons,
        private TaxService $taxes,
        private PaymentProviderRegistry $providers,
    ) {
    }

    /**
     * Place an order from the current cart.
     *
     * $input shape (all optional except where noted):
     *
     *   customer            ?Customer  - null for guests
     *   guest_email         string     - required if customer is null
     *   billing_address     array      - snapshot
     *   shipping_address    array      - snapshot
     *   payment_provider_key string    - 'cod'|'bank_transfer'|'purchase_order'
     *   po_number           ?string    - required for purchase_order
     *   customer_note       ?string
     *   ip                  string
     *   user_agent          string
     *
     * @param array<string,mixed> $input
     * @throws CheckoutException
     */
    public function placeOrder(array $input): Order
    {
        // -----------------------------------------------------------------
        // Validate the cart (before opening a transaction)
        // -----------------------------------------------------------------
        $lines = $this->cart->items();
        if ($lines === []) {
            throw new CheckoutException(
                'Your cart is empty. Add items before checking out.'
            );
        }

        foreach ($lines as $line) {
            if ($line->productRemoved) {
                throw new CheckoutException(
                    'One of the items in your cart is no longer available. '
                    . 'Please remove it before checking out.'
                );
            }
            if ($line->priceChanged) {
                throw new CheckoutException(
                    'The price of one of the items in your cart has changed. '
                    . 'Please review your cart before checking out.'
                );
            }
            if ($line->outOfStock) {
                throw new CheckoutException(
                    'One of the items in your cart is out of stock in the '
                    . 'requested quantity. Please update your cart.'
                );
            }
        }

        // -----------------------------------------------------------------
        // Validate the legal pages exist (Germany compliance gate)
        // -----------------------------------------------------------------
        // Country-driven compliance gate. The store's country_code
        // selects which legal pages must exist. Germany requires
        // impressum, agb, widerruf, datenschutz. Other countries
        // declare their own requirements via ComplianceMap.
        $store = $this->storeRepository->findById($this->storeContext->id());
        $countryCode = $store !== null ? (string) $store->countryCode : '';

        $requiredSlugs = ComplianceMap::requiredLegalSlugs($countryCode);
        $missing = [];
        foreach ($requiredSlugs as $slug) {
            if ($this->legalPages->findBySlug($slug) === null) {
                $missing[] = $slug;
            }
        }

        if ($missing !== []) {
            throw new CheckoutException(
                'Checkout is temporarily unavailable. The store has not '
                . 'yet published all required legal pages.'
            );
        }

        // -----------------------------------------------------------------
        // Validate the payment method
        // -----------------------------------------------------------------
        $paymentKey = (string) ($input['payment_provider_key'] ?? '');
        $offline = ['cod', 'bank_transfer', 'purchase_order'];
        if (!in_array($paymentKey, $offline, true)) {
            // Not an offline method. The key must resolve to
            // a real provider through the registry. A store
            // that has not enabled Stripe does not have a
            // StripeProvider that resolves, so a forged POST
            // of payment_method=stripe fails here.
            if ($this->providers->resolve($paymentKey) === null) {
                throw new CheckoutException(
                    'Please select a valid payment method.'
                );
            }
        }

        $customer = $input['customer'] ?? null;

        // Gate: an authenticated customer whose account is not
        // approved cannot place any order. Pending and rejected
        // B2B customers can browse and add to cart, but checkout
        // is blocked regardless of payment method. This mirrors
        // the design in docs/stages/008e_checkout-plan.md: the
        // purchase-order method is hidden for them, but so is
        // every other path to completion.
        //
        // Guests (customer === null) are not subject to this
        // gate - they have no account to be pending.
        if ($customer instanceof Customer && !$customer->canCheckout()) {
            throw new CheckoutException(
                'Your account is pending approval. You will be able '
                . 'to place orders once it has been reviewed.'
            );
        }

        $isPurchaseOrder = $paymentKey === 'purchase_order';

        if ($isPurchaseOrder) {
            if (!$customer instanceof Customer) {
                throw new CheckoutException(
                    'Purchase orders are only available to approved '
                    . 'business customers.'
                );
            }
            if (!$customer->canCheckout()) {
                throw new CheckoutException(
                    'Your account is not approved for purchase orders.'
                );
            }

            $poNumber = trim((string) ($input['po_number'] ?? ''));
            if ($poNumber === '') {
                throw new CheckoutException(
                    'Please enter a purchase order number.'
                );
            }

            $group = $customer->customerGroupId !== null
                ? $this->customerGroups->findById($customer->customerGroupId)
                : null;

            if ($group === null || !$group->hasNetTerms()) {
                throw new CheckoutException(
                    'Your account does not have net payment terms.'
                );
            }
        }

        // -----------------------------------------------------------------
        // Compute the totals via the pricing engine.
        //
        // OrderPricingService runs the six-step sequence:
        //   subtotal, discount, taxable base, shipping, tax,
        //   grand total.
        //
        // The engine is the single source of truth. Nothing
        // else in this file computes order arithmetic.
        //
        // computeOrFail throws CheckoutException when a coupon
        // has become invalid or a shipping method is no longer
        // available. That happens before the transaction
        // opens, so a failure leaves the cart untouched.
        // -----------------------------------------------------------------
        $shippingAddress = is_array($input['shipping_address'] ?? null)
            ? $input['shipping_address']
            : [];

        $pricingContext = [
            'coupon_code'          => $this->cart->couponCode(),
            'shipping_method_code' => (string) ($input['shipping_method_code'] ?? ''),
            'country_code'         => (string) ($shippingAddress['country_code'] ?? ''),
            'state_code'           => $shippingAddress['state_code'] ?? null,
            'customer_id'          => $customer instanceof Customer ? $customer->id : null,
            'guest_email'          => $customer instanceof Customer
                                        ? null
                                        : (string) ($input['guest_email'] ?? ''),
        ];

        $pricing = $this->pricing->computeOrFail($lines, $pricingContext);

        $subtotal          = (float) $pricing['subtotal'];
        $discountTotal     = (float) $pricing['discount_total'];
        $taxTotal          = (float) $pricing['tax_total'];
        $shippingTotal     = (float) $pricing['shipping_total'];
        $grandTotal        = (float) $pricing['grand_total'];
        $coupon            = $pricing['coupon'];
        $shippingMethod    = $pricing['shipping_method'];
        $taxClasses        = $pricing['tax_classes'];

        $shippingMethodKey   = $shippingMethod !== null
            ? (string) $shippingMethod['code']
            : '';
        $shippingMethodLabel = $shippingMethod !== null
            ? (string) $shippingMethod['label']
            : '';

        $currencyCode = 'EUR';

        // -----------------------------------------------------------------
        // Begin the transaction
        // -----------------------------------------------------------------
        $this->db->beginTransaction();

        try {
            // Step 1. Re-read the cart inside the transaction.
            // (Cart may have changed between the initial read and
            // the transaction opening. Re-reading is cheap.)
            // For simplicity we reuse $lines captured above; the
            // cart is session-based and cannot change within a
            // single HTTP request. If this ever becomes a concern,
            // re-query here.

            // Step 2. Lock the inventory rows and re-check stock.
            $units = [];
            foreach ($lines as $line) {
                $units[] = [
                    'product_id' => $line->item->productId,
                    'variant_id' => $line->item->variantId,
                ];
            }
            $locked = $this->inventory->forUnitsLocked($units);

            foreach ($lines as $line) {
                $key = $line->item->productId . ':' . $line->item->variantId;
                $inv = $locked[$key] ?? null;

                // Products without an inventory row do not track
                // stock. Treat as always sellable.
                if ($inv === null) {
                    continue;
                }

                if (!$inv->canSell($line->item->quantity)) {
                    throw new CheckoutException(
                        'Sorry, ' . $line->item->productName . ' is no longer '
                        . 'available in the requested quantity.'
                    );
                }
            }

            // Step 3. Generate the order number.
            $orderNumber = $this->codeGen->next(
                'commerce_orders',
                'ORD',
                6,
                'order_number'
            );

            // Step 3.5. Guest order claim resolution.
            //
            // If the order is a guest order (customer is null) and
            // the merchant has enabled the guest-claim toggle, look
            // up an existing customer by the guest email. If found,
            // mark the order as claimable by that customer.
            //
            // The customer can then claim the order from their
            // account dashboard after logging in. Ownership is
            // asserted only at claim time, not here. This is a
            // tentative email match, not a link.
            //
            // See docs/stages/008e.2_guest-order-claim-plan.md.
            $claimableByCustomerId = null;

            if ($customer === null) {
                $claimEnabled = $this->storeSettings->bool(
                    'compliance.guest_claim.enabled',
                    false
                );

                if ($claimEnabled) {
                    $guestEmail = (string) ($input['guest_email'] ?? '');
                    if ($guestEmail !== '') {
                        $matchedCustomer = $this->customerRepository->findByEmail($guestEmail);
                        if ($matchedCustomer !== null) {
                            $claimableByCustomerId = $matchedCustomer->id;
                        }
                    }
                }
            }

            // Step 4. Insert the order header.
            // json_encode() returns string|false. The billing_address_json
            // and shipping_address_json columns are MySQL JSON type, which
            // rejects the literal `false` value. Guard against encode failure
            // so a bad address field stores NULL rather than aborting the
            // whole transaction.
            $billingEncoded = !empty($input['billing_address'])
                ? json_encode($input['billing_address'], JSON_UNESCAPED_UNICODE)
                : null;
            $billingJson = $billingEncoded === false ? null : $billingEncoded;

            $shippingEncoded = !empty($input['shipping_address'])
                ? json_encode($input['shipping_address'], JSON_UNESCAPED_UNICODE)
                : null;
            $shippingJson = $shippingEncoded === false ? null : $shippingEncoded;

            $orderId = $this->orders->create([
                'store_id'              => $this->storeContext->id(),
                'order_number'          => $orderNumber,
                'customer_id'           => $customer instanceof Customer ? $customer->id : null,
                'claimable_by_customer_id' => $claimableByCustomerId,
                'guest_email'           => $customer instanceof Customer
                                            ? null
                                            : (string) ($input['guest_email'] ?? ''),
                'currency_code'         => $currencyCode,
                'exchange_rate'         => 1.0,
                'status'                => 'pending_payment',
                'payment_status'        => 'unpaid',
                'fulfilment_status'     => 'unfulfilled',
                'subtotal'              => $subtotal,
                'discount_total'        => $discountTotal,
                'tax_total'             => $taxTotal,
                'shipping_total'        => $shippingTotal,
                'grand_total'           => $grandTotal,
                'paid_total'            => 0.0,
                'refunded_total'        => 0.0,
                'customer_note'         => $input['customer_note'] ?? null,
                'billing_address_json'  => $billingJson,
                'shipping_address_json' => $shippingJson,
                'coupon_code'           => $coupon !== null
                                            ? (string) $coupon['code']
                                            : null,
                'shipping_method_key'   => $shippingMethodKey,
                'shipping_method_label' => $shippingMethodLabel,
                'payment_provider_key'  => $paymentKey,
                'po_number'             => $isPurchaseOrder
                                            ? trim((string) $input['po_number'])
                                            : null,
                'net_due_at'            => $isPurchaseOrder
                                            ? $this->computeNetDueAt($customer)
                                            : null,
                'ip'                    => $this->packIp((string) ($input['ip'] ?? '')),
                'user_agent'            => mb_substr((string) ($input['user_agent'] ?? ''), 0, 500),
                'placed_at'             => date('Y-m-d H:i:s'),
            ]);

            // Step 5. Insert the line items.
            $itemRows = [];
            foreach ($lines as $line) {
                $lineSubtotal = $line->effectiveSubtotal();
                $itemRows[] = [
                    'store_id'          => $this->storeContext->id(),
                    'order_id'          => $orderId,
                    'product_id'        => $line->item->productId,
                    // NULL for simple products (variantId = 0).
                    // The FK to commerce_product_variants has no
                    // row with id 0. See migration 0303.
                    'variant_id'        => $line->item->variantId > 0
                                            ? $line->item->variantId
                                            : null,
                    'product_name'      => $line->item->productName,
                    'variant_label'     => $line->item->variantLabel,
                    'sku'               => $line->item->sku,
                    'quantity'          => $line->item->quantity,
                    'unit_price'        => $line->effectiveUnitPrice(),
                    'line_subtotal'     => $lineSubtotal,
                    'line_discount'     => 0.0,
                    'line_tax'          => 0.0,
                    'line_total'        => $lineSubtotal,
                ];
            }
            $this->orderItems->createMany($itemRows);

            // Step 6. Status history - creation row.
            $this->statusHistory->append(
                $orderId,
                null,
                'pending_payment',
                null,
                'Order placed via storefront'
            );

            // Order placed notification. Only for logged-in
            // customers - guests have no account to receive it
            // against. Best-effort, never throws. The customer's
            // notify_order_updates preference gates delivery.
            // See docs/stages/008g.3_message-center-plan.md.
            if ($customer instanceof Customer) {
                $this->customerNotifications->orderPlaced(
                    $customer->id,
                    $orderNumber,
                    $orderId
                );
            }

            // Step 6.5. Coupon redemption and tax ledger.
            //
            // Both writes are inside the transaction. If
            // either fails, the order rolls back. A coupon
            // cannot be consumed without an order; tax lines
            // cannot exist for an order that does not.
            if ($coupon !== null && $discountTotal > 0.0) {
                $this->coupons->recordRedemption(
                    (int) $coupon['id'],
                    $orderId,
                    $customer instanceof Customer ? $customer->id : null,
                    $customer instanceof Customer
                        ? null
                        : (string) ($input['guest_email'] ?? ''),
                    $discountTotal
                );
            }

            if ($taxClasses !== []) {
                $this->taxes->persistOrderTaxes($orderId, $taxClasses);
            }

            // Step 7. Reserve inventory. One call per line.
            // The FOR UPDATE lock acquired in step 2 is still held
            // (we are inside the same transaction).
            foreach ($lines as $line) {
                $this->inventory->reserve(
                    $line->item->productId,
                    $line->item->variantId,
                    $line->item->quantity
                );
            }

            // Step 8. Commit.
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();

            // Log the underlying exception so debugging is
            // possible without exposing internal details.
            error_log('[checkout.placeOrder] '
                . get_class($e) . ': ' . $e->getMessage()
                . ' at ' . $e->getFile() . ':' . $e->getLine());

            if ($e instanceof CheckoutException) {
                throw $e;
            }
            throw new CheckoutException(
                'We could not place your order. Please try again or '
                . 'contact support if the problem persists.'
            );
        }

        // -----------------------------------------------------------------
        // After commit: load the fresh order.
        //
        // The cart is NOT cleared here. It survives the
        // redirect to the payment provider and the return
        // through the return controller. A customer who
        // abandons on the provider's page and returns later
        // still has their items. The cart is cleared in
        // CheckoutController::confirmation(), once the order
        // has been shown to the customer.
        //
        // This matches the industry standard: clear the cart
        // on confirmed, not on created.
        // -----------------------------------------------------------------
        $order = $this->orders->findById($orderId);
        if ($order === null) {
            // This should never happen - the row was just inserted.
            // But if it does, we still have the order id so the
            // customer can be shown a confirmation.
            throw new CheckoutException(
                'Your order was placed but could not be loaded. '
                . 'Order number: ' . $orderNumber
            );
        }

        return $order;
    }

    /**
     * Compute net_due_at for a PO order using the customer's
     * group's default_net_days. MySQL computes the timestamp so
     * PHP and MySQL clocks cannot drift.
     */
    private function computeNetDueAt(?Customer $customer): ?string
    {
        if (!$customer instanceof Customer || $customer->customerGroupId === null) {
            return null;
        }

        $group = $this->customerGroups->findById($customer->customerGroupId);
        if ($group === null || $group->defaultNetDays <= 0) {
            return null;
        }

        $days = (int) $group->defaultNetDays;

        // Let MySQL compute the timestamp. Use a SELECT so we
        // never touch PHP's clock for a value MySQL will later
        // compare.
        $row = $this->db->selectOne(
            "SELECT DATE_ADD(NOW(), INTERVAL ? DAY) AS due_at",
            [$days]
        );
        return $row['due_at'] ?? null;
    }

    /**
     * Convert a textual IP into the packed binary form that
     * VARBINARY(16) expects. Returns null for empty or invalid
     * input.
     */
    private function packIp(string $ip): ?string
    {
        if ($ip === '') {
            return null;
        }
        $packed = @inet_pton($ip);
        return $packed === false ? null : $packed;
    }
}
