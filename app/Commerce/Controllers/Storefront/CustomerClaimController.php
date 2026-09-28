<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Services\CustomerAuthService;
use CorporateAid\Commerce\Support\StorefrontController;

/**
 * Guest order claim. Customer-facing reconciliation.
 *
 * Routes (all under /account/claims, guarded by customer.auth):
 *   GET  /account/claims             list claimable orders
 *   POST /account/claims/link        link all claimable orders
 *   POST /account/claims/dismiss     hide notification for session
 *
 * The claimable orders are those whose claimable_by_customer_id
 * matches the logged-in customer and whose claimed_at is NULL.
 * See docs/stages/008e.2_guest-order-claim-plan.md.
 */
final class CustomerClaimController extends StorefrontController
{
    private const DISMISS_KEY = 'guest_claim_dismissed';

    /** @param array<string,string> $params */
    public function list(Request $request, array $params = []): Response
    {
        $customer = $this->currentCustomer();
        if ($customer === null) {
            return Response::redirect('/account/login?intended=' . urlencode('/account/claims'));
        }

        $orders = $this->container->get(OrderRepository::class);
        $claimable = $orders->listClaimableForCustomer($customer->id);

        return $this->render('storefront.account.claims', [
            'title'     => 'Claim your orders',
            'customer'  => $customer,
            'claimable' => $claimable,
        ]);
    }

    /** @param array<string,string> $params */
    public function link(Request $request, array $params = []): Response
    {
        $customer = $this->currentCustomer();
        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $orders = $this->container->get(OrderRepository::class);
        $session = $this->customerSession();

        $claimable = $orders->listClaimableForCustomer($customer->id);
        if ($claimable === []) {
            $session->flash('info', 'No orders to link.');
            return Response::redirect('/account/claims');
        }

        $ids = array_map(fn ($o) => $o->id, $claimable);
        $count = $orders->claimOrders($ids, $customer->id);

        // Clear the dismiss flag now that the customer has decided.
        $session->forget(self::DISMISS_KEY);

        $session->flash(
            'success',
            $count === 1
                ? '1 order linked to your account.'
                : $count . ' orders linked to your account.'
        );

        return Response::redirect('/account/orders');
    }

    /** @param array<string,string> $params */
    public function dismiss(Request $request, array $params = []): Response
    {
        $customer = $this->currentCustomer();
        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        // Session-level dismissal. The orders stay claimable.
        // The customer can still visit /account/claims to link them.
        $this->customerSession()->set(self::DISMISS_KEY, true);

        return Response::redirect('/account');
    }

    /**
     * The logged-in customer, or null.
     */
    private function currentCustomer(): ?\CorporateAid\Commerce\Models\Customer
    {
        try {
            return $this->container
                ->get(CustomerAuthService::class)
                ->user();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The customer session. Not Session::class (that is the admin
     * session). See docs/stages/008c_customer-auth-plan.md.
     */
    private function customerSession(): Session
    {
        return $this->container->get('customer_session');
    }
}
