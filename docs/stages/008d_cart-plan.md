# Stage 008D - Cart

**Date opened:** 2026-09-27
**Status:** Planned
**Depends on:** Stages 008B (storefront), 008C (customer auth)

---

## Purpose

Session-based cart for guests, database-persisted cart for
logged-in customers. Cart merge on login. Mini-cart in the
storefront header with live count. Full cart page with quantity
controls, remove, and subtotal.

This is the bridge between product browsing (008B) and checkout
(008E). It holds items in a "soft reservation" state - the
inventory row is checked at add time but stock is not actually
reserved until the checkout transaction creates the order.

---

## Scope

**In scope:**

- Guest cart in `$_SESSION['cart']`
- Logged-in customer cart in `commerce_carts` + `commerce_cart_items`
- Add to cart (product detail page)
- Update quantity, remove, clear
- Cart page with per-item price/stock state
- Mini-cart badge in header with AJAX live count
- Merge guest cart into DB cart on login
- Price-change and out-of-stock detection (display warnings)

**Out of scope (later stages):**

- Checkout (008E)
- Coupons / discount codes (010)
- Tax calculation (010 - cart shows subtotal only)
- Shipping estimate (010)
- Real payment methods (010)
- Abandoned cart recovery emails (010+)
- Save for later (later)
- Wishlist (later)

---

## The Two Carts

### Guest cart - session

Session key: `$_SESSION['cart']` in the **customer session**
(`ca_customer_session`), not the admin session.

Format:

    [
        'items' => [
            [
                'product_id'    => int,
                'variant_id'    => int,   // 0 for simple products
                'quantity'      => int,
                'unit_price'    => float, // snapshot at add time
                'product_name'  => string, // snapshot
                'variant_label' => ?string, // snapshot
                'sku'           => ?string, // snapshot
                'added_at'      => int,   // unix timestamp
            ],
            ...
        ],
        'updated_at' => int,
    ]

The item shape mirrors `commerce_cart_items` rows exactly. This
makes the login merge a copy operation with no field mapping.

### Customer cart - database

`commerce_carts` (one row per customer per store):

    id             BIGINT UNSIGNED AUTO_INCREMENT
    store_id       BIGINT UNSIGNED NOT NULL
    customer_id    BIGINT UNSIGNED NOT NULL
    status         ENUM('active','converted','abandoned') DEFAULT 'active'
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
    updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE
    expires_at     DATETIME NULL

    UNIQUE KEY (store_id, customer_id)
    FOREIGN KEY (store_id)   -> commerce_stores(id)  ON DELETE CASCADE
    FOREIGN KEY (customer_id) -> commerce_customers(id) ON DELETE CASCADE

The `UNIQUE KEY (store_id, customer_id)` enforces one cart per
customer. If a customer's cart is converted to an order, a new
cart row is created on the next add.

`commerce_cart_items` (one row per product/variant in a cart):

    id             BIGINT UNSIGNED AUTO_INCREMENT
    cart_id        BIGINT UNSIGNED NOT NULL
    product_id     BIGINT UNSIGNED NOT NULL
    variant_id     BIGINT UNSIGNED NOT NULL DEFAULT 0
    quantity       INT UNSIGNED NOT NULL
    unit_price     DECIMAL(18,4) NOT NULL
    product_name   VARCHAR(255) NOT NULL
    variant_label  VARCHAR(255) NULL
    sku            VARCHAR(80) NULL
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
    updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE

    UNIQUE KEY (cart_id, product_id, variant_id)
    FOREIGN KEY (cart_id) -> commerce_carts(id) ON DELETE CASCADE

The `product_name`, `variant_label`, `sku`, and `unit_price`
columns are **snapshots** taken at add time. They mirror the
same fields on `commerce_order_items`, which carry over at
checkout. Storing snapshots means the cart renders correctly
even if the product is renamed or its price changes. The cart
view compares snapshots against live values and warns if
either has drifted.

---

## The CartService API

    final class CartService
    {
        public function __construct(
            Session $session,                    // customer_session
            CartRepository $carts,
            CartItemRepository $items,
            ProductRepository $products,
            ProductVariantRepository $variants,
            InventoryRepository $inventory,
            CustomerAuthService $customerAuth,
        ) {}

        // Mutations
        public function add(int $productId, int $variantId, int $qty): void
        public function updateQuantity(int $productId, int $variantId, int $qty): void
        public function remove(int $productId, int $variantId): void
        public function clear(): void

        // Reads
        public function items(): array         // array<int, CartLineItem>
        public function count(): int           // total units
        public function subtotal(): float
        public function isEmpty(): bool

        // Lifecycle
        public function mergeGuestCartOnLogin(): void
    }

`add()` resolves the customer session for identity. If a
customer is logged in, the cart is DB-backed. Otherwise it
writes to `$_SESSION['cart']`.

`add()` validates:
- The product exists, is published, not deleted
- For variable products: the variant exists and belongs to the product
- The requested quantity passes `Inventory::canSell($qty)` (or the
  product allows backorders)

If validation fails, `add()` throws `CartException` with a
human-readable message. The controller catches and flashes or
returns JSON.

---

## CartLineItem value object

The view doesn't render raw cart rows. It renders a value object
that pairs the cart snapshot with the current live state:

    final class CartLineItem
    {
        public int $productId;
        public int $variantId;
        public int $quantity;
        public float $unitPrice;              // snapshot at add time
        public string $productName;           // snapshot
        public ?string $variantLabel;         // snapshot
        public ?string $sku;                  // snapshot
        public float $lineSubtotal;           // unitPrice * quantity

        public ?Product $liveProduct;         // current state
        public ?Inventory $liveInventory;

        public bool $priceChanged;            // livePrice != unitPrice
        public bool $outOfStock;              // can't sell requested qty
        public bool $productRemoved;          // product archived/deleted

        public function hasWarning(): bool;   // any of the above true
    }

The view shows:
- Normal line: image placeholder, name, variant, qty controls, price
- Warning line: same, plus a badge "Price changed" or "Out of stock"
- Removed line: same, plus a badge "No longer available"

The customer can still see and remove warned items but cannot
proceed to checkout with them (enforced at 008E).

---

## Routes

    GET  /cart                show the cart page
    POST /cart/add            add item (form or AJAX)
    POST /cart/update         change quantity
    POST /cart/remove         remove item
    POST /cart/clear          empty cart
    GET  /cart/count          JSON: {count, subtotal} for badge

All POST routes require CSRF.

`/cart/add` responds differently based on `Accept` header:
- Form POST (no `Accept: application/json`): flash + redirect back
- AJAX (`Accept: application/json`): JSON `{ok, count, subtotal, message}`

Same dual behavior for `/cart/update` and `/cart/remove`.

---

## Middleware

Cart routes use `['store']` only (public). CSRF on POST.
No auth required - guests can use the cart.

    GET  /cart         ['store']
    POST /cart/add     ['store', 'csrf']
    POST /cart/update  ['store', 'csrf']
    POST /cart/remove  ['store', 'csrf']
    POST /cart/clear   ['store', 'csrf']
    GET  /cart/count   ['store']

No rate limiting on cart operations. Rate limiting is for
unauthenticated abuse; the cart is a legitimate high-frequency
operation.

---

## The Merge on Login

`CustomerAuthService::login()` currently does:

    1. regenerate session
    2. set customer_id in session
    3. cache the customer

We add a step 4: call `CartService::mergeGuestCartOnLogin()`.

Algorithm:

    1. Read $_SESSION['cart']['items'].
    2. If empty, do nothing.
    3. Load or create the customer's DB cart.
    4. For each session item:
         - If a DB item with the same (product_id, variant_id)
           exists: sum quantities (respecting current stock).
         - Otherwise: create a new DB cart item with the
           snapshot fields from the session item.
    5. Clear $_SESSION['cart'].

The session's `added_at` ordering is preserved by using it as
the `created_at` value on new DB rows.

---

## Stock behavior

**Add to cart:** validate `Inventory::canSell($qty)`. Reject if
false (unless the product allows backorders). Do NOT reserve.

**Cart view:** re-check `canSell` on every render. Items that
fail get the `outOfStock` flag.

**Checkout (008E):** the transaction that creates the order
calls `InventoryRepository::reserve()` for each item. This is
where stock is actually held. If reservation fails, the whole
transaction rolls back.

The cart is a "soft hold" in spirit only. A customer leaving
items in their cart for 3 days does not lock the stock. If two
customers race to checkout the last unit, one succeeds and the
other gets a clear "out of stock" message.

---

## Mini-cart in header

The header partial currently has:

    $cartCount = 0;   // hardcoded until 008D wires real cart state

Replace with:

    $cartCount = $cartCount ?? 0;

The layout passes `$cartCount` from a shared context resolver.
The layout calls `CartService::count()` once per request and
passes it to the header.

The header markup stays as-is (cart icon + badge). The badge
shows the count. When count is 0, badge is hidden.

A small JS block in the layout:

    - After page load, fetch /cart/count
    - Update badge text and visibility
    - Listen for 'cart:updated' custom event dispatched by the
      AJAX add-to-cart handler
    - On event, refetch /cart/count and update

This keeps the badge accurate without a full page reload.

---

## Product detail page integration

The existing disabled button:

    <button type="button" class="..." disabled>
        Add to Cart &mdash; coming soon
    </button>

Becomes a real form:

    <form method="POST" action="/cart/add" class="sf-add-to-cart-form">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int) $product->id ?>">
        <input type="hidden" name="variant_id" value="0">
        <input type="hidden" name="quantity" value="1">
        <button type="submit" class="sf-btn sf-btn-primary sf-btn-lg sf-btn-block">
            <i class="bi bi-bag-plus me-2"></i> Add to Cart
        </button>
    </form>

For variable products (008F-adjacent work), the variant selector
will populate `variant_id`. For now, simple products only -
variable products show a "select options" button that scrolls to
the variant selector (which doesn't exist yet, so we may disable
add-to-cart for variable products until variants UI ships).

**Decision:** for 008D, add-to-cart works for simple products.
For variable products, the button is disabled with a tooltip
"Variant selection coming soon." The plan is explicit about
this so it doesn't get lost.

---

## Files to Create

**Migrations:**

    database/migrations/0300_create_carts.sql

**Models:**

    app/Commerce/Models/Cart.php
    app/Commerce/Models/CartItem.php
    app/Commerce/Models/CartLineItem.php   (view-model)
    app/Commerce/Exceptions/CartException.php

**Repositories:**

    app/Commerce/Repositories/CartRepository.php
    app/Commerce/Repositories/CartItemRepository.php

**Services:**

    app/Commerce/Services/CartService.php

**Controllers:**

    app/Commerce/Controllers/Storefront/CartController.php

**Views:**

    resources/views/storefront/cart/index.php
    resources/views/storefront/cart/_line.php     (partial per item)
    resources/views/storefront/cart/_empty.php    (empty state)

**Modified:**

    app/Commerce/CommerceServiceProvider.php    (bind CartService, CartRepository, CartItemRepository)
    app/Commerce/Services/CustomerAuthService.php  (call mergeGuestCartOnLogin after login)
    app/Commerce/Support/StorefrontController.php  (pass $cartCount to header)
    resources/views/storefront/partials/header.php  (wire $cartCount)
    resources/views/storefront/product/show.php     (real add-to-cart form)
    resources/views/storefront/layouts/main.php     (mini-cart JS)
    routes/storefront.php                            (5 new routes)
    public/assets/css/storefront-cart.css            (new stylesheet, cart page + warnings)

**Estimated: ~14 new files.**

---

## Test Protocol

After every file lands: `php bin/verify-file.php <path>` and
`php bin/lint.php`.

After the full sub-stage:

    T1   Add a simple product to cart as a guest
    T2   View /cart shows the item with correct subtotal
    T3   Update quantity and total updates
    T4   Remove item and cart empties
    T5   Header badge updates via AJAX without page reload
    T6   Add second item; badge shows combined count
    T7   Log in mid-session: guest cart merges into DB cart
    T8   Log out and back in: DB cart persists
    T9   Add same product twice: quantity sums, no duplicate row
    T10  Out-of-stock: cannot add beyond available
    T11  Price change: cart shows warning badge
    T12  Two sessions: guest cart in one, customer cart in another
    T13  Responsive at 375 / 768 / 1024
    T14  Cart persists in DB across browser sessions (customer only)

---

## Progress

| # | Item | Status |
|---|---|---|
| 1 | Plan document | This file |
| 2 | Migration: carts + cart_items | Pending |
| 3 | Models (Cart, CartItem, CartLineItem, CartException) | Pending |
| 4 | Repositories (CartRepository, CartItemRepository) | Pending |
| 5 | CartService + provider binding | Pending |
| 6 | CartController | Pending |
| 7 | Views (cart page, line partial, empty state) | Pending |
| 8 | Routes | Pending |
| 9 | Product detail page integration | Pending |
| 10 | Header mini-cart + AJAX count | Pending |
| 11 | CustomerAuthService merge hook | Pending |
| 12 | CSS | Pending |
| 13 | Full test pass + commit | Pending |

---

## See Also

  - docs/stages/008_storefront-plan.md
  - docs/stages/008c_customer-auth-plan.md
  - docs/BASELINE.md
  - docs/REMEMBER.md (Pattern 23 - cascade check before patches)
  - docs/DEBUGGING.md (CSS override pattern)
