<?php
/**
 * Setup tab: Checkout.
 *
 * @var array  $store
 * @var array  $errors
 * @var array  $old
 * @var string $csrf
 */
$store  = $store  ?? [];
$errors = $errors ?? [];
$old    = $old    ?? [];
$csrf   = $csrf   ?? '';

$guestCheckout = (int) ($old['allow_guest_checkout'] ?? $store['allow_guest_checkout'] ?? 1);
$requirePhone  = (int) ($old['require_phone']        ?? $store['require_phone']        ?? 0);
$requireComp   = (int) ($old['require_company']      ?? $store['require_company']      ?? 0);
?>

<form method="POST" action="/commerce/ca-admin/setup/checkout" novalidate>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Customer requirements</h2>
        </div>
        <div class="ra-card-body">

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="hidden" name="allow_guest_checkout" value="0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="allow_guest_checkout" name="allow_guest_checkout" value="1"
                           <?= $guestCheckout ? 'checked' : '' ?>>
                    <label class="form-check-label" for="allow_guest_checkout">
                        <strong>Allow guest checkout</strong>
                    </label>
                </div>
                <div class="form-text ms-4">
                    Customers can buy without creating an account. Recommended: on.
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="hidden" name="require_phone" value="0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="require_phone" name="require_phone" value="1"
                           <?= $requirePhone ? 'checked' : '' ?>>
                    <label class="form-check-label" for="require_phone">
                        <strong>Require phone number</strong>
                    </label>
                </div>
                <div class="form-text ms-4">
                    Collects a phone number on every order. Useful for delivery.
                </div>
            </div>

            <div class="mb-0">
                <div class="form-check form-switch">
                    <input type="hidden" name="require_company" value="0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="require_company" name="require_company" value="1"
                           <?= $requireComp ? 'checked' : '' ?>>
                    <label class="form-check-label" for="require_company">
                        <strong>Require company name</strong>
                    </label>
                </div>
                <div class="form-text ms-4">
                    Useful for B2B. Turn on if you need invoices addressed to a company.
                </div>
            </div>

        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="ra-btn ra-btn-primary">Save</button>
    </div>
</form>
