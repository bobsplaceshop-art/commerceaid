<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Commerce\Exceptions\CartException;
use CorporateAid\Commerce\Services\CartService;
use CorporateAid\Commerce\Support\StorefrontController;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Storefront cart.
 *
 * Works for both guests and logged-in customers. CartService
 * resolves the storage layer internally; the controller doesn't
 * care which one it is.
 *
 * Most methods accept both form POST and AJAX POST. The
 * distinction is the Accept header:
 *
 *   Accept: application/json  -> JSON response
 *   anything else             -> flash + redirect
 *
 * This lets the same endpoint power the "Add to Cart" button on
 * the product page (form POST, redirect back) and the AJAX call
 * from the mini-cart JS (JSON response, live badge update).
 *
 * All POST routes carry CSRF middleware at the route level.
 * CartException carries a customer-safe message; other
 * exceptions propagate to the error handler.
 *
 * See docs/stages/008d_cart-plan.md.
 */
final class CartController extends StorefrontController
{
    // =================================================================
    // Show
    // =================================================================

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $cart = $this->container->get(CartService::class);

        return $this->render('storefront.cart.index', [
            'title'      => 'Your Cart',
            'lines'      => $cart->items(),
            'subtotal'   => $cart->subtotal(),
            'count'      => $cart->count(),
            'isEmpty'    => $cart->isEmpty(),
        ]);
    }

    // =================================================================
    // Add
    // =================================================================

    /** @param array<string,string> $params */
    public function add(Request $request, array $params = []): Response
    {
        $cart = $this->container->get(CartService::class);

        $productId = (int) $request->input('product_id', 0);
        $variantId = (int) $request->input('variant_id', 0);
        $quantity  = (int) $request->input('quantity', 1);

        try {
            $cart->add($productId, $variantId, $quantity);
        } catch (CartException $e) {
            return $this->respondToCartAction($request, false, $e->getMessage());
        }

        return $this->respondToCartAction($request, true, 'Added to cart.');
    }

    // =================================================================
    // Update quantity
    // =================================================================

    /** @param array<string,string> $params */
    public function update(Request $request, array $params = []): Response
    {
        $cart = $this->container->get(CartService::class);

        $productId = (int) $request->input('product_id', 0);
        $variantId = (int) $request->input('variant_id', 0);
        $quantity  = (int) $request->input('quantity', 0);

        try {
            $cart->updateQuantity($productId, $variantId, $quantity);
        } catch (CartException $e) {
            return $this->respondToCartAction($request, false, $e->getMessage());
        }

        return $this->respondToCartAction($request, true, 'Cart updated.');
    }

    // =================================================================
    // Remove
    // =================================================================

    /** @param array<string,string> $params */
    public function remove(Request $request, array $params = []): Response
    {
        $cart = $this->container->get(CartService::class);

        $productId = (int) $request->input('product_id', 0);
        $variantId = (int) $request->input('variant_id', 0);

        $cart->remove($productId, $variantId);

        return $this->respondToCartAction($request, true, 'Item removed.');
    }

    // =================================================================
    // Clear
    // =================================================================

    /** @param array<string,string> $params */
    public function clear(Request $request, array $params = []): Response
    {
        $cart = $this->container->get(CartService::class);
        $cart->clear();

        return $this->respondToCartAction($request, true, 'Cart emptied.');
    }

    // =================================================================
    // Count (JSON only, for the mini-cart badge)
    // =================================================================

    /** @param array<string,string> $params */
    public function count(Request $request, array $params = []): Response
    {
        $cart = $this->container->get(CartService::class);

        return Response::json([
            'count'    => $cart->count(),
            'subtotal' => $cart->subtotal(),
        ]);
    }

    // =================================================================
    // Response shaping
    // =================================================================

    /**
     * Emit the response for a cart mutation. Form POST gets a
     * flash + redirect back. AJAX POST gets JSON.
     *
     * @param string $message customer-facing message
     */
    private function respondToCartAction(Request $request, bool $ok, string $message): Response
    {
        if ($this->wantsJson($request)) {
            return Response::json($this->jsonPayload($ok, $message));
        }

        $session = $this->container->get(Session::class);
        $session->flash($ok ? 'success' : 'error', $message);

        return Response::redirect($this->safeBackUrl($request));
    }

    /**
     * @return array<string,mixed>
     */
    private function jsonPayload(bool $ok, string $message): array
    {
        $cart = $this->container->get(CartService::class);
        return [
            'ok'       => $ok,
            'message'  => $message,
            'count'    => $cart->count(),
            'subtotal' => $cart->subtotal(),
        ];
    }

    private function wantsJson(Request $request): bool
    {
        $accept = (string) $request->header('Accept', '');
        return str_contains($accept, 'application/json');
    }

    /**
     * The URL to redirect to after a form POST. Uses the Referer
     * header if it's a same-origin path, falls back to /cart.
     *
     * Referer is attacker-controllable in principle, but the
     * browser enforces it as an absolute URL of the page that
     * triggered the form. We validate only the path portion and
     * require it to be a safe internal path. RedirectGuard is
     * not needed here because we're using path parsing, not the
     * raw header.
     */
    private function safeBackUrl(Request $request): string
    {
        $referer = (string) $request->header('Referer', '');
        if ($referer === '') {
            return '/cart';
        }

        $path = parse_url($referer, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || $path[0] !== '/') {
            return '/cart';
        }
        if (str_starts_with($path, '//')) {
            return '/cart';
        }

        return $path;
    }
}
