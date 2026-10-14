# Stage 010 - Payments (Deep) Plan

**Status:** Planning
**Opened:** 2026-10-14
**Depends on:** 008E (checkout), 008H (returns),
008K.5 (order pricing)
**Blocks:** Nothing. The other six providers ship as
sub-stages 010.1 through 010.6.

---

## Purpose

Real money movement. The storefront charges cards and
PayPal accounts. The admin issues refunds through the
provider that took the original payment. Webhooks
keep the order state in sync.

Stage 010 ships the payment infrastructure and two
providers end to end: Stripe and PayPal.

The remaining six providers the ROADMAP names
(M-Pesa, Pesapal, Flutterwave, Manual, COD, Bank
Transfer) ship as sub-stages. Each uses the
interface, the registry, and the HTTP client that
this stage defines. The precedent is 008K (Admin
Surface Completion, five sub-stages) and 008G.4
(four sub-stages).

---

## What exists today

Read from the code, 2026-10-14.

### The order schema is ready

`database/migrations/0205_customer_and_order_tables.sql`
carries every column the payments layer needs:

    payment_status ENUM('unpaid','paid','partially_refunded',
                        'refunded','failed') NOT NULL DEFAULT 'unpaid'
    paid_total     DECIMAL(18,4) NOT NULL DEFAULT 0
    paid_at        DATETIME NULL
    payment_provider_key VARCHAR(50) NULL
    payment_reference    VARCHAR(120) NULL

    INDEX idx_com_order_payment (payment_provider_key,
                                 payment_reference)

No schema migration is required to carry a payment
reference.

### The refund intent is written today

`ReturnRequestService::processRefund()`
(`app/Commerce/Services/ReturnRequestService.php:300`)
writes the refund intent to the order and to the
return request, inside one transaction. It does not
contact a provider. It returns a `float`.

`AdminReturnController::refund()`
(`app/Commerce/Controllers/Admin/AdminReturnController.php:392`)
is the sole caller. It catches `ReturnRequestException`
and flashes.

The refund modal
(`resources/views/admin/returns/_refund-modal.php`)
states in its own text:

> This records the refund against the order and
> updates the customer's order history. The actual
> money movement is handled by the payment provider
> in a later stage.

"Later stage" is 010.

### The checkout method list is hardcoded

`CheckoutController::availablePaymentMethods()`
(`app/Commerce/Controllers/Storefront/CheckoutController.php:668`)
returns a static array:

    ['cod' => 'Cash on delivery',
     'bank_transfer' => 'Bank transfer']

Plus `'purchase_order'` for B2B customers with net
terms. The method is called from `showPayment()`
(line 235) and `savePayment()` (line 279). The view
iterates it (`payment.php:110`). The
`savePayment()` guard
`if (!isset($availableMethods[$method]))` enforces
the selection.

### The settings mechanism is a definitions array

`StoreSettingDefinitions::DEFINITIONS`
(`app/Commerce/Support/StoreSettingDefinitions.php`,
453 lines) is the single source for every store
setting. One const entry per setting. Groups
declared as class constants. The admin settings page
renders the form from the array. No migration, no
new view.

Five groups today: general, branding, fonts,
compliance, newsletter.

### The cron entry point shape is fixed

`bin/send-emails.php` is the template for a CLI
worker: CLI SAPI guard, autoload check, flag
parsing, `App::boot()`, work loop, exit code.
`bin/retry-refunds.php` follows the same shape.

### No HTTP client exists

`app/` has no general-purpose HTTP client. The
`curl_*` and `file_get_contents` usages that exist
are in `FontDownloader`, `Migrator`, `Request`, and
`Response`. None is a reusable request wrapper.

### No webhook infrastructure exists

`grep -rn 'webhook' routes/ app/Commerce/` returns
two false positives: a docblock in
`FormSubmissionService` that says the webhook path
is deferred, and a comment in `CarrierTemplates`
about webhook-driven carrier updates.

No route, no handler, no signature verification, no
`commerce_webhook_events` table. Stage 010 creates
the first webhook handler in the codebase.

### No payment provider interface exists

`grep -rn 'PaymentProvider' app/` returns
`SetupController::availablePaymentProviders()`
(`app/Commerce/Controllers/Admin/SetupController.php:414`),
a hardcoded six-entry array with a comment that it
will read `commerce_store_payment_methods` "when
wired." That table does not exist.

The six entries today: `mpesa`, `stripe`, `paypal`,
`manual`, `cod`, `bank`.

### The migration range

`database/migrations/` currently ends at 0338.
Number 0337 is skipped (an abandoned migration that
was never committed, per the store-aware logging
session). The next three free numbers are 0339,
0340, 0341.

---

## The three decisions, locked

### Q1 - Scope

Ship Stripe and PayPal end to end in this stage.
Six sub-stages for the rest.

    Stage 010 (this stage)
      - PaymentProvider interface
      - PaymentProviderRegistry
      - HttpClient wrapper
      - commerce_store_payment_methods table (0339)
      - commerce_payment_refunds table (0340)
      - commerce_webhook_events table (0341)
      - PaymentRefundService wrapper
      - Refund dispatch from AdminReturnController
      - Retry cron bin/retry-refunds.php
      - Stripe: charge, capture, refund, webhook,
        signature verification, setup UI
      - PayPal: charge, capture, refund, webhook,
        signature verification, setup UI
      - Admin provider configuration page
      - Checkout reads from enabled methods

    010.1 - M-Pesa
    010.2 - Pesapal
    010.3 - Flutterwave
    010.4 - Manual (offline record)
    010.5 - Cash on Delivery
    010.6 - Bank Transfer

### Q2 - Refunds are entities

Industry-standard shape. A new table:

    commerce_payment_refunds
      id                  BIGINT UNSIGNED PK AUTO_INCREMENT
      store_id            BIGINT UNSIGNED NOT NULL
      order_id            BIGINT UNSIGNED NOT NULL
      return_request_id   BIGINT UNSIGNED NULL
      provider_key        VARCHAR(50) NOT NULL
      provider_refund_id  VARCHAR(120) NULL
      provider_payment_id VARCHAR(120) NULL
      amount              DECIMAL(18,4) NOT NULL
      currency_code       CHAR(3) NOT NULL
      status              ENUM('pending','succeeded','failed',
                               'canceled')
                          NOT NULL DEFAULT 'pending'
      reason              VARCHAR(255) NULL
      failure_message     VARCHAR(500) NULL
      idempotency_key     VARCHAR(80) NOT NULL
      attempts            INT UNSIGNED NOT NULL DEFAULT 0
      last_attempt_at     DATETIME NULL
      succeeded_at        DATETIME NULL
      created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
      updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                          ON UPDATE CURRENT_TIMESTAMP

      UNIQUE KEY uniq_com_pref_idem (idempotency_key)
      KEY idx_com_pref_order  (order_id)
      KEY idx_com_pref_return (return_request_id)
      KEY idx_com_pref_status (status, created_at)
      FK store_id          -> commerce_stores(id) CASCADE
      FK order_id          -> commerce_orders(id) CASCADE
      FK return_request_id -> commerce_return_requests(id) SET NULL

The order's `refunded_total` becomes a derived value:
the sum of `commerce_payment_refunds.amount` where
`status = 'succeeded'` and `order_id = X`.

`payment_status` transitions:

    refunded_total = 0                          -> unpaid or paid
    refunded_total < grand_total                -> partially_refunded
    refunded_total >= grand_total - 0.001       -> refunded

The order's `payment_status` enum is unchanged. No
migration alters it. The refund-pending state lives
in `commerce_payment_refunds.status`, not on the
order.

### Q3 - The refund wrapper

`ReturnRequestService::processRefund()` is untouched.

New `PaymentRefundService::refund(int $returnRequestId,
int $adminUserId): RefundResult`:

  1. Calls ReturnRequestService::processRefund(). That
     writes the intent (order columns, line returned
     quantities, request status) and commits.

  2. Inserts a commerce_payment_refunds row with
     status = 'pending' and a fresh idempotency key.

  3. Resolves the provider from the order's
     payment_provider_key via the registry.

  4. Dispatches Provider::refund(order, amount,
     idempotency_key).

  5. On success: transitions the row to 'succeeded',
     fills provider_refund_id and succeeded_at, and
     re-derives the order's refunded_total and
     payment_status.

  6. On failure: transitions the row to 'failed'
     with failure_message. The order columns are
     unchanged.

  7. Returns RefundResult.

`AdminReturnController::refund()` switches its call
from `ReturnRequestService::processRefund()` to
`PaymentRefundService::refund()`.

When the order's `payment_provider_key` is not a
real provider (`cod`, `bank_transfer`,
`purchase_order`), the wrapper records the refund
row as `succeeded` without a provider call. The
intent is the record for offline methods.

---

## The payment provider contract

    interface PaymentProvider
    {
        public function key(): string;
        public function label(): string;
        public function isConfigured(StoreContext $ctx): bool;

        /**
         * @return array{ok:bool, redirect_url?:string,
         *               provider_reference?:string,
         *               error?:string}
         */
        public function charge(Order $order, array $context): array;

        /**
         * @return array{ok:bool, provider_reference?:string,
         *               error?:string}
         */
        public function capture(Order $order, string $providerReference): array;

        /**
         * @return array{ok:bool, refund_id?:string,
         *               error?:string}
         */
        public function refund(Order $order, float $amount,
                               string $idempotencyKey): array;

        /**
         * @param array<string,string> $headers
         */
        public function verifyWebhook(string $rawBody,
                                       array $headers): bool;

        /**
         * @return array{handled:bool, event_type:string,
         *               order_id?:int, note?:string}
         */
        public function handleWebhook(string $rawBody,
                                       array $headers): array;
    }

Small interface. Charge, capture, refund, verify,
handle.

---

## The registry

    final class PaymentProviderRegistry
    {
        public function resolve(string $key): ?PaymentProvider;
        public function all(): array;              // key => instance
        public function enabledForStore(int $storeId): array;
    }

`resolve()` maps provider keys to instances. The
mapping is a fixed list of `key => ClassName`
entries in the registry's constructor. Adding a
provider in a future sub-stage is one line.

`enabledForStore()` consults
`commerce_store_payment_methods`. A store that has
disabled Stripe cannot offer Stripe at checkout.

`commerce_store_payment_methods` schema:

    id             BIGINT UNSIGNED PK AUTO_INCREMENT
    store_id       BIGINT UNSIGNED NOT NULL
    provider_key   VARCHAR(50) NOT NULL
    is_enabled     TINYINT(1) NOT NULL DEFAULT 0
    sort_order     INT UNSIGNED NOT NULL DEFAULT 0
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                   ON UPDATE CURRENT_TIMESTAMP

    UNIQUE KEY uniq_com_spm_store_key (store_id, provider_key)
    KEY idx_com_spm_store_enabled (store_id, is_enabled, sort_order)
    FK store_id -> commerce_stores(id) CASCADE

One row per provider per store. Missing row = not
configured = not offered at checkout. The settings
tab writes these rows on save. No settings-key row
is needed for the enable toggle; the table is the
source.

---

## The HTTP client

Hand-rolled. No Composer dependency.

    final class HttpClient
    {
        public function postJson(string $url, array $payload,
                                 array $headers, int $timeout = 15): array;
        public function get(string $url, array $headers,
                            int $timeout = 15): array;
        public function post(string $url, string $body,
                             array $headers,
                             int $timeout = 15): array;
    }

Built on `curl_*`. Sets `CURLOPT_TIMEOUT`,
`CURLOPT_CONNECTTIMEOUT`, and
`CURLOPT_FAILONERROR = false` so 4xx and 5xx bodies
are readable. Logs the URL, method, status, and
elapsed time to `storage/logs/app.log` at debug
level. Never logs secrets or full payloads.

Return shape:

    array{status:int, body:array<string,mixed>,
          raw:string, error:?string}

---

## Webhook routing

    POST /webhooks/stripe
    POST /webhooks/paypal

Both routes live in `routes/commerce.php` under the
`store` middleware. They bypass CSRF (no session)
but not store resolution. The store is resolved from
`commerce_store_domains` or from a `?store=` query
parameter.

`WebhookController`:

  1. Reads the raw body.
  2. Extracts the signature header.
  3. Calls Provider::verifyWebhook($rawBody, $headers).
  4. On failure: 400 with an empty body.
  5. On success: Provider::handleWebhook($rawBody, $headers).
  6. Records the event in commerce_webhook_events,
     keyed unique for idempotency.
  7. Handles the order-side effect.
  8. Returns 200 with an empty body.

`commerce_webhook_events` schema:

    id              BIGINT UNSIGNED PK AUTO_INCREMENT
    store_id        BIGINT UNSIGNED NOT NULL
    provider_key    VARCHAR(50) NOT NULL
    event_id        VARCHAR(120) NOT NULL
    event_type      VARCHAR(120) NOT NULL
    order_id        BIGINT UNSIGNED NULL
    payload_json    JSON NOT NULL
    received_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    processed_at    DATETIME NULL

    UNIQUE KEY uniq_com_wh_ev_provider_event
        (provider_key, event_id)
    KEY idx_com_wh_ev_order (order_id)
    FK store_id -> commerce_stores(id) CASCADE
    FK order_id -> commerce_orders(id) SET NULL

The unique key on `(provider_key, event_id)`
provides idempotency.

---

## Stripe, end to end

### API endpoints

    POST /v1/payment_intents               - create the intent
    POST /v1/payment_intents/{id}/capture  - capture
    POST /v1/refunds                       - refund
    GET  /v1/payment_intents/{id}          - status read

### Flow

Checkout:

  1. Customer selects "Stripe".
  2. CheckoutController::place() creates the order
     with payment_status = 'unpaid' and
     payment_provider_key = 'stripe'.
  3. PaymentService::beginCharge($order) calls
     Provider::charge(). Stripe creates a
     PaymentIntent with the order's grand total and
     returns the client_secret.
  4. The controller redirects to a Stripe Checkout
     session (or renders Stripe Elements with the
     client secret).
  5. The customer pays.
  6. Stripe sends payment_intent.succeeded.
  7. The webhook handler writes payment_status =
     'paid', paid_at = now(),
     payment_reference = $intent->id.
  8. The customer returns to
     /checkout/confirmation/{orderNumber}.

Refund:

  1. Admin processes a return refund.
  2. PaymentRefundService::refund() inserts a
     commerce_payment_refunds row with
     provider_key = 'stripe'.
  3. The Stripe provider calls POST /v1/refunds
     with the order's payment_reference and amount.
  4. Stripe returns the Refund object.
  5. The service transitions the row to succeeded.
  6. Order refunded_total and payment_status are
     re-derived.

### Webhook events consumed

    payment_intent.succeeded         -> mark paid
    payment_intent.payment_failed    -> mark failed
    charge.refunded                  -> update refund row
    charge.refund.updated            -> update refund row

### Signature

`Stripe-Signature` header, HMAC-SHA256, computed
over `{timestamp}.{raw_body}` with the webhook
signing secret. 5-minute timestamp tolerance.

### Settings

    payments.stripe.enabled         boolean
    payments.stripe.mode            select: sandbox | live
    payments.stripe.publishable_key string
    payments.stripe.secret_key      string
    payments.stripe.webhook_secret  string

---

## PayPal, end to end

### API endpoints

    POST /v2/checkout/orders                       - create order
    POST /v2/checkout/orders/{id}/capture          - capture
    POST /v2/payments/captures/{id}/refund         - refund
    POST /v1/notifications/verify-webhook-signature - verify

### Flow

Checkout:

  1. Customer selects "PayPal".
  2. CheckoutController::place() creates the order
     with payment_status = 'unpaid' and
     payment_provider_key = 'paypal'.
  3. PaymentService::beginCharge($order) calls
     Provider::charge(). PayPal creates an Order and
     returns the approval URL.
  4. The controller redirects to the approval URL.
  5. The customer approves.
  6. PayPal redirects to
     /checkout/paypal/return?token=...
  7. The controller calls Provider::capture().
  8. PayPal sends PAYMENT.CAPTURE.COMPLETED.
  9. The webhook handler writes payment_status =
     'paid', paid_at = now(),
     payment_reference = $capture->id.

Refund:

  1. Admin processes a return refund.
  2. PaymentRefundService::refund() inserts a
     commerce_payment_refunds row with
     provider_key = 'paypal'.
  3. The PayPal provider calls
     POST /v2/payments/captures/{id}/refund.
  4. PayPal returns the Refund object.
  5. The service transitions the row to succeeded.

### Webhook events consumed

    PAYMENT.CAPTURE.COMPLETED        -> mark paid
    PAYMENT.CAPTURE.DENIED           -> mark failed
    PAYMENT.CAPTURE.REFUNDED         -> update refund row
    PAYMENT.CAPTURE.REVERSED         -> update refund row

### Signature

`PAYPAL-TRANSMISSION-ID`, `PAYPAL-TRANSMISSION-TIME`,
`PAYPAL-TRANSMISSION-SIG`, `PAYPAL-CERT-URL`,
`PAYPAL-AUTH-ALGO` headers. The provider class posts
them and the raw body to PayPal's
verify-webhook-signature endpoint and trusts the
SUCCESS response.

### Settings

    payments.paypal.enabled         boolean
    payments.paypal.mode            select: sandbox | live
    payments.paypal.client_id       string
    payments.paypal.client_secret   string
    payments.paypal.webhook_id      string

---

## Admin surfaces

### Payment provider configuration

A new tab on Store Settings: `payments`. The
StoreSettingDefinitions gains a GROUP_PAYMENTS
constant and the ten settings above. The existing
settings page renders them. No new view.

The tab renders two cards, one per provider. Each
card has an enable toggle, a mode selector, the
credentials fields, and a status line computed from
Provider::isConfigured(). Toggle state writes
commerce_store_payment_methods rows.

### Order detail - the payment card gains refund rows

The admin order detail
(`resources/views/admin/orders/show.php`, card at
lines 160-170) shows paymentStatusLabel,
paymentLabel, and paymentReference. The refund
entity is not surfaced today.

Additive change: the card gains a list of any
commerce_payment_refunds rows for the order. The
list is passed as a new variable from
AdminOrderController::show(). No controller logic
changes - a repository read.

### Return detail - the refund modal shows the outcome

The refund modal gains a conditional note when the
order's payment_provider_key is a real provider.
For Stripe:

> This refund will be sent to Stripe for processing.
> The customer receives the money within 5-10
> business days.

For PayPal, similar. For cod and bank_transfer, the
note stays as it is today.

---

## Checkout changes

`CheckoutController::availablePaymentMethods()`
becomes:

    private function availablePaymentMethods(?Customer $customer): array
    {
        $registry = $this->container->get(PaymentProviderRegistry::class);
        $storeCtx = $this->container->get(StoreContext::class);

        $methods = [];
        foreach ($registry->enabledForStore($storeCtx->id()) as $key => $provider) {
            if (!$provider->isConfigured($storeCtx)) {
                continue;
            }
            $methods[$key] = $provider->label();
        }

        $methods['cod']           = 'Cash on delivery';
        $methods['bank_transfer'] = 'Bank transfer';

        if ($customer instanceof Customer && $customer->canCheckout()) {
            // existing B2B purchase_order logic unchanged
        }

        return $methods;
    }

The savePayment() guard is unchanged. A disabled
provider is not in the list, so a forged POST fails
validation.

The view iterates the list automatically. Three
additive conditionals render hints for stripe and
paypal.

---

## Order state transitions

Every transition is written by a webhook handler or
by the checkout controller.

    checkout                        -> unpaid
    payment_intent.succeeded        -> paid + paid_at
    payment_intent.payment_failed   -> failed
    PAYMENT.CAPTURE.COMPLETED       -> paid + paid_at
    PAYMENT.CAPTURE.DENIED          -> failed
    processRefund()                 -> partially_refunded or refunded
    charge.refunded                 -> re-derive from refund rows

The provider client returns the response. The caller
writes the state. One write path per transition.

---

## Sessions

Seven sessions. Each ends with a commit.

### Session 1 - Infrastructure

  - PaymentProvider interface
  - PaymentProviderRegistry (empty provider map)
  - HttpClient wrapper
  - Migration 0339: commerce_store_payment_methods
  - Migration 0340: commerce_payment_refunds
  - Migration 0341: commerce_webhook_events
  - GROUP_PAYMENTS constant on StoreSettingDefinitions
  - Provider bindings in CommerceServiceProvider

No provider implementations yet. No checkout
change.

### Session 2 - The refund wrapper

  - PaymentRefundService
  - RefundResult value object
  - PaymentRefundException
  - AdminReturnController::refund() switches to the
    wrapper
  - bin/retry-refunds.php
  - The refund-modal conditional note

### Session 3 - Stripe

  - StripeProvider implements PaymentProvider
  - Signature verification
  - Webhook event handlers
  - PaymentService::beginCharge() resolves to Stripe
  - CheckoutController redirects to Stripe Checkout
  - The order detail payment card gains the refund
    list
  - Browser test: sandbox charge, sandbox refund

### Session 4 - PayPal

  - PayPalProvider implements PaymentProvider
  - Signature verification (verify-webhook-signature)
  - Webhook event handlers
  - PaymentService::beginCharge() resolves to PayPal
  - CheckoutController redirects to PayPal
  - The return URL handler
  - Browser test: sandbox charge, sandbox refund

### Session 5 - Admin configuration

  - payments tab on Store Settings
  - Two provider cards
  - Status computation
  - The provider enable/disable gate at checkout
  - Browser test: enable Stripe, disable Stripe,
    verify checkout method list

### Session 6 - Compliance and docs

  - COMPLIANCE-DE.md gains entries for Stripe and
    PayPal as data processors (DSGVO Art. 28)
  - The payment provider fee and its VAT treatment
  - Any per-provider documentation in the admin guide
  - The stage-close docs

### Session 7 - Stage close

  - Session file
  - STATUS
  - ROADMAP
  - Tag stage-010-complete

---

## Sub-stages (after Stage 010 closes)

Each adds one provider. Small.

    010.1 M-Pesa
    010.2 Pesapal
    010.3 Flutterwave
    010.4 Manual
    010.5 Cash on Delivery
    010.6 Bank Transfer

Each opens its own plan document at its own open.

---

## What is not in this stage

  - Subscriptions (Stage 011)
  - Recurring billing
  - Multi-currency charge (the store's currency is
    charged; conversion is the provider's job)
  - Partial authorizations
  - Provider-specific promotions (Stripe Radar rules,
    PayPal Smart Buttons customization)
  - A payment provider selection UI on the checkout
    confirmation page

---

## Open questions

  1. When a store has zero configured providers and
     zero offline methods enabled, what does the
     checkout do? Recommendation: refuse to open
     the payment step. The store is misconfigured.

  2. When a webhook arrives for an order whose store
     is disabled or archived, does the handler still
     write? Recommendation: yes. The order was
     placed; the money moved; the record should
     reflect it.

  3. Retry limit on failed refunds before manual
     intervention. Recommendation: 5 attempts with
     exponential backoff (1m, 5m, 30m, 2h, 8h).
     After that, the refund row stays failed until
     an admin retries manually.

These are resolved in the plan above and reviewed at
Session 1 open.

---

## Post-session updates

### Session 1 close (2026-10-14)

The PaymentProviderRegistry shipped with a
runtime-built provider map rather than an empty
class const. A statically empty const is reported
by PHPStan at level 8 as dead code at every read
site: the null-coalesce on a known-empty array,
the guard after it, the foreach over it. The
runtime method `providerMap()` keeps the plan's
intent (nothing resolves in Session 1) while
satisfying level 8 without a suppression and
without a provider class stub.

The interface, the HTTP client, the repository,
the three migrations, and the settings entries
shipped exactly as the plan named them.

Session 3 and Session 4 each add one entry to
`providerMap()` when the Stripe and PayPal
provider classes land. The map's keys, the class
namespace, and the resolve path are fixed; the
entries are the only thing that changes.

The five-round PHPStan iteration that produced
this shape is recorded in the Session 1 file
(`docs/sessions/2026-10-14-3.md`) so the next
provider sub-stage starts from the final shape,
not from a fresh attempt at the const.

### Session 2 close (2026-10-14)

Two additions shipped that the plan's Session 2
bullet list did not name:

  - `bin/smoke.php` and the first smoke case
    (`bin/smoke-cases/refund.php`). The harness
    boots the app against a separate test
    database, migrates and seeds it, and runs
    cases. The first case exercises the offline
    and registry-miss paths of the refund
    wrapper.
  - `composer smoke`. Runs the harness. Joins
    lint, analyse, and prevenger as a distinct
    check command.

The plan named PaymentRefundService,
RefundResult, PaymentRefundException,
PaymentRefundRepository, the controller switch,
the retry cron, and the modal note. All seven
shipped.

The smoke harness is the codebase's first logic
check. The three existing check categories
(syntax via bin/lint.php, types via PHPStan,
structure via the prevenger) cannot verify the
refund wrapper's offline path, its registry-miss
path, or its order re-derive step. The smoke
case does.

The wrapper passed its first real smoke run. The
four bugs that surfaced during the harness's
first day were all in its own fixtures
(product_id, variant_id, customer_id, store_id),
not in the payment code. The session file
`docs/sessions/2026-10-14-4.md` records the
sequence.

### Session 3 close (2026-10-14)

Two plan statements needed correction.

**Checkout vs PaymentIntent.** The plan's Stripe
section named both Stripe Checkout (the hosted
payment page) and a PaymentIntent in the same
flow. They are two different Stripe APIs.
Checkout is the redirect flow; PaymentIntents are
the on-page (Elements) flow. The charge uses
Checkout - it matches the `redirect_url` return
shape the interface's `charge()` docblock
specifies, and it requires no client-side JS.

The refund flow still uses the PaymentIntent id
stored on the order's `payment_reference`. Stripe
creates a PaymentIntent internally when the
Checkout Session is paid; the webhook writes its
id to the order. `POST /v1/refunds` accepts a
PaymentIntent id.

**payment_failed does not transition the order.**
The plan's state transition table says
`payment_intent.payment_failed` → mark failed. The
handler records the event and returns
`handled: true` without writing the order state.
The order stays `unpaid` so the customer can
retry with a different card. A `failed` order
cannot be retried against the same order number.
Marking the order failed would close the retry
path.

**Metadata propagation.** The charge flow sets
`metadata[order_id]` on the Checkout Session AND
`payment_intent_data[metadata][order_id]`. Stripe
does not propagate session metadata to the
PaymentIntent. Without the second path, the
webhook handler would find empty metadata on
every `payment_intent.succeeded` and the order
would never be marked paid.

**CLI session guard.** `App::loadRoutes()`
skips `Session->start()` when `PHP_SAPI === 'cli'`.
`session_name()` throws after any byte reaches
STDOUT on this PHP build, even inside an
`ob_start()` buffer. Every CLI tool that echoes
progress before `App::boot()` would crash
without the guard.

The three commits (`4571f82`, `052ca4e`,
`e8ea8f8`) are recorded in
`docs/sessions/2026-10-14-5.md`.
