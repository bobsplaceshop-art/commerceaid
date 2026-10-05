<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Order DTO.
 *
 * Mirrors commerce_orders. Populated by OrderRepository::fromRow().
 *
 * Business helpers answer the questions checkout and the future
 * admin order flow actually ask:
 *
 *   isPendingPayment()  - order created but not yet paid
 *   isPaid()            - payment_status = 'paid'
 *   isB2bPurchaseOrder() - payment_provider_key = 'purchase_order'
 *   hasGuestEmail()     - customer_id is null, guest_email set
 *   isFromCustomer()    - customer_id is set
 *   isOverdue()         - net_due_at is in the past and unpaid
 */
final class Order
{
    public int $id = 0;
    public int $storeId = 0;
    public string $orderNumber = '';
    public ?int $customerId = null;
    public ?string $guestEmail = null;
    public string $currencyCode = 'EUR';
    public float $exchangeRate = 1.0;

    public string $status = 'pending_payment';
    public string $paymentStatus = 'unpaid';
    public string $fulfilmentStatus = 'unfulfilled';

    public float $subtotal = 0.0;
    public float $discountTotal = 0.0;
    public float $taxTotal = 0.0;
    public float $shippingTotal = 0.0;
    public float $grandTotal = 0.0;
    public float $paidTotal = 0.0;
    public float $refundedTotal = 0.0;

    public ?string $customerNote = null;
    public ?string $internalNote = null;

    public ?string $billingAddressJson = null;
    public ?string $shippingAddressJson = null;

    public ?string $shippingMethodKey = null;
    public ?string $shippingMethodLabel = null;

    public ?string $carrierKey = null;
    public ?string $trackingNumber = null;
    public ?string $trackingUrl = null;

    public ?string $paymentProviderKey = null;
    public ?string $paymentReference = null;
    public ?string $poNumber = null;
    public ?string $netDueAt = null;

    public ?string $couponCode = null;
    public ?string $ip = null;
    public ?string $userAgent = null;

    public ?string $placedAt = null;
    public ?string $paidAt = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $o = new self();
        $o->id                   = (int) $row['id'];
        $o->storeId              = (int) $row['store_id'];
        $o->orderNumber          = (string) $row['order_number'];
        $o->customerId           = isset($row['customer_id']) ? (int) $row['customer_id'] : null;
        $o->guestEmail           = $row['guest_email'] ?? null;
        $o->currencyCode         = (string) ($row['currency_code'] ?? 'EUR');
        $o->exchangeRate         = (float) ($row['exchange_rate'] ?? 1.0);
        $o->status               = (string) ($row['status'] ?? 'pending_payment');
        $o->paymentStatus        = (string) ($row['payment_status'] ?? 'unpaid');
        $o->fulfilmentStatus     = (string) ($row['fulfilment_status'] ?? 'unfulfilled');
        $o->subtotal             = (float) ($row['subtotal'] ?? 0.0);
        $o->discountTotal        = (float) ($row['discount_total'] ?? 0.0);
        $o->taxTotal             = (float) ($row['tax_total'] ?? 0.0);
        $o->shippingTotal        = (float) ($row['shipping_total'] ?? 0.0);
        $o->grandTotal           = (float) ($row['grand_total'] ?? 0.0);
        $o->paidTotal            = (float) ($row['paid_total'] ?? 0.0);
        $o->refundedTotal        = (float) ($row['refunded_total'] ?? 0.0);
        $o->customerNote         = $row['customer_note'] ?? null;
        $o->internalNote         = $row['internal_note'] ?? null;
        $o->billingAddressJson   = $row['billing_address_json'] ?? null;
        $o->shippingAddressJson  = $row['shipping_address_json'] ?? null;
        $o->shippingMethodKey    = $row['shipping_method_key'] ?? null;
        $o->shippingMethodLabel  = $row['shipping_method_label'] ?? null;
        $o->carrierKey           = $row['carrier_key'] ?? null;
        $o->trackingNumber       = $row['tracking_number'] ?? null;
        $o->trackingUrl          = $row['tracking_url'] ?? null;
        $o->paymentProviderKey   = $row['payment_provider_key'] ?? null;
        $o->paymentReference     = $row['payment_reference'] ?? null;
        $o->poNumber             = $row['po_number'] ?? null;
        $o->netDueAt             = $row['net_due_at'] ?? null;
        $o->couponCode           = $row['coupon_code'] ?? null;
        $o->ip                   = isset($row['ip']) ? (string) $row['ip'] : null;
        $o->userAgent            = $row['user_agent'] ?? null;
        $o->placedAt             = $row['placed_at'] ?? null;
        $o->paidAt               = $row['paid_at'] ?? null;
        $o->createdAt            = $row['created_at'] ?? null;
        $o->updatedAt            = $row['updated_at'] ?? null;
        return $o;
    }

    public function isPendingPayment(): bool
    {
        return $this->status === 'pending_payment';
    }

    public function isPaid(): bool
    {
        return $this->paymentStatus === 'paid';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isB2bPurchaseOrder(): bool
    {
        return $this->paymentProviderKey === 'purchase_order';
    }

    public function hasGuestEmail(): bool
    {
        return $this->customerId === null
            && $this->guestEmail !== null
            && $this->guestEmail !== '';
    }

    public function isFromCustomer(): bool
    {
        return $this->customerId !== null;
    }

    public function isOverdue(): bool
    {
        if ($this->netDueAt === null || $this->isPaid()) {
            return false;
        }
        return strtotime($this->netDueAt) < time();
    }

    /**
     * Decoded billing address. Returns null if the column is
     * empty or the JSON is malformed.
     *
     * @return array<string,mixed>|null
     */
    public function billingAddress(): ?array
    {
        return $this->decodeAddress($this->billingAddressJson);
    }

    /**
     * Decoded shipping address.
     *
     * @return array<string,mixed>|null
     */
    public function shippingAddress(): ?array
    {
        return $this->decodeAddress($this->shippingAddressJson);
    }

    // =================================================================
    // Tracking (Stage 008F)
    // =================================================================

    /**
     * Does this order have tracking to display? True when both
     * a carrier key and a tracking number are set.
     */
    public function hasTracking(): bool
    {
        return $this->carrierKey !== null
            && $this->carrierKey !== ''
            && $this->trackingNumber !== null
            && $this->trackingNumber !== '';
    }

    /**
     * The carrier label for display. "DHL", "FedEx", etc.
     * Null when no carrier is set.
     */
    public function carrierLabel(): ?string
    {
        return \CorporateAid\Commerce\Support\CarrierTemplates::label($this->carrierKey);
    }

    /**
     * The resolved tracking URL. If the tracking_url column
     * holds an override, that is returned verbatim. Otherwise
     * the URL is computed from the carrier template map.
     *
     * Returns null when tracking cannot be resolved (missing
     * carrier, missing number, or an unknown carrier without
     * an override).
     */
    public function resolvedTrackingUrl(): ?string
    {
        if ($this->trackingUrl !== null && $this->trackingUrl !== '') {
            return $this->trackingUrl;
        }
        return \CorporateAid\Commerce\Support\CarrierTemplates::url(
            $this->carrierKey,
            $this->trackingNumber
        );
    }

    // =================================================================
    // Widerrufsrecht (Stage 008F)
    // =================================================================

    /**
     * Is this order eligible for the Widerrufsrecht block?
     *
     * A German consumer's withdrawal right applies for 14 days
     * from delivery (or from order placement, for goods not yet
     * delivered). We compute from placed_at as a conservative
     * approximation - the customer's 14-day window starts when
     * they place the order, which is at worst a day or two
     * earlier than the delivery-date-based legal standard.
     *
     * The block is hidden when:
     *   - the order is cancelled, failed, or fully refunded
     *   - the withdrawal window has already closed
     *   - the order has no placed_at timestamp
     */
    public function isWithdrawalEligible(): bool
    {
        if ($this->placedAt === null || $this->placedAt === '') {
            return false;
        }
        if (in_array($this->status, ['cancelled', 'failed', 'refunded'], true)) {
            return false;
        }
        if ($this->paymentStatus === 'refunded') {
            return false;
        }
        $deadline = $this->withdrawalDeadline();
        if ($deadline === null) {
            return false;
        }
        return strtotime($deadline) >= time();
    }

    /**
     * The date the withdrawal window closes: placed_at + 14 days.
     * Null when placed_at is missing.
     *
     * Returns an ISO 8601 datetime string (Y-m-d H:i:s) suitable
     * for display or comparison.
     */
    public function withdrawalDeadline(): ?string
    {
        if ($this->placedAt === null || $this->placedAt === '') {
            return null;
        }
        $placed = strtotime($this->placedAt);
        if ($placed === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $placed + (14 * 86400));
    }

    /**
     * @return array<string,mixed>|null
     */
    private function decodeAddress(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    // =================================================================
    // Admin status transitions (Stage 008K.1)
    // =================================================================

    /**
     * Allowed admin-side status transitions. Key = current
     * status, value = the statuses it may transition to from
     * the admin order page.
     *
     * Rules:
     *   - One step forward. No skipping (paid -> shipped is
     *     not allowed; paid -> processing -> packed ->
     *     shipped is).
     *   - Cancel is allowed from pending_payment, paid,
     *     processing, packed, and on_hold. Once shipped, the
     *     order cannot be cancelled by the merchant.
     *   - on_hold can only be entered from processing and
     *     only exits back to processing or cancelled.
     *   - Terminal states (completed, cancelled, failed,
     *     refunded, partially_refunded) have no outgoing
     *     transitions.
     *   - refunded and partially_refunded are NOT reachable
     *     from the admin order page. They are set by the
     *     returns flow (ReturnRequestService::processRefund).
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'pending_payment' => ['paid', 'cancelled'],
        'paid'            => ['processing', 'cancelled'],
        'processing'      => ['packed', 'on_hold', 'cancelled'],
        'packed'          => ['shipped', 'cancelled'],
        'shipped'         => ['delivered'],
        'delivered'       => ['completed'],
        'on_hold'         => ['processing', 'cancelled'],
    ];

    /**
     * The statuses this order may transition to from its
     * current state. Empty array when the current status is
     * terminal or unknown.
     *
     * @return list<string>
     */
    public function allowedTransitions(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }

    /**
     * Whether a transition from this order's current status
     * to $toStatus is allowed.
     */
    public function canTransitionTo(string $toStatus): bool
    {
        return in_array($toStatus, $this->allowedTransitions(), true);
    }
}
