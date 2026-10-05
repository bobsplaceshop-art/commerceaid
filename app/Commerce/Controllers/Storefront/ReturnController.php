<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Commerce\Exceptions\ReturnRequestException;
use CorporateAid\Commerce\Repositories\OrderItemRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Repositories\ReturnRequestItemRepository;
use CorporateAid\Commerce\Repositories\ReturnRequestRepository;
use CorporateAid\Commerce\Services\CustomerAuthService;
use CorporateAid\Commerce\Services\ReturnRequestService;
use CorporateAid\Commerce\Support\StorefrontController;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Storefront returns.
 *
 * Five endpoints:
 *
 *   GET  /account/returns                     index
 *   GET  /account/returns/new/{orderNumber}   create
 *   POST /account/returns                     store
 *   GET  /account/returns/{requestNumber}     show
 *   POST /account/returns/{id}/cancel         cancel
 *
 * Every endpoint requires a logged-in customer. Guests do not
 * have order history to return against; the controller gates
 * internally on CustomerAuthService::user(), same pattern as
 * MessageController, RecentViewController, and WishlistController.
 *
 * The 14-day Widerrufsrecht window is enforced by two
 * conditions combined in canReturnFor():
 *   - Order::isWithdrawalEligible()   the model's existing check
 *                                     (14 days from placed_at)
 *   - ReturnRequestService::isWithinWindow()  the service's check
 *                                     (windowDays from delivery,
 *                                     or unblocked when the store
 *                                     allows pre-delivery requests)
 * Both must agree for the return button to appear. This keeps
 * the withdrawal block and the return button on the same page
 * consistent with each other.
 *
 * IDOR defense: every lookup filters on customer_id. Route params
 * alone are never trusted.
 *
 * See docs/stages/008h_returns-refunds-plan.md.
 */
final class ReturnController extends StorefrontController
{
    /**
     * Tab whitelist for the index page. Anything else falls back
     * to 'apply'.
     *
     * @var array<int, string>
     */
    private const TABS = ['apply', 'processing', 'closed'];

    // =================================================================
    // Show
    // =================================================================

    /**
     * The returns landing page. Three tabs: apply, processing,
     * closed. The apply tab lists eligible orders plus a Start
     * a return button for each.
     *
     * @param array<string,string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $customer = $this->customer();
        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $tab = (string) $request->query('tab', 'apply');
        if (!in_array($tab, self::TABS, true)) {
            $tab = 'apply';
        }

        $requests = $this->container
            ->get(ReturnRequestRepository::class)
            ->listForCustomer($customer->id);

        $processing = [];
        $closed     = [];
        foreach ($requests as $r) {
            if ($r->isOpen()) {
                $processing[] = $r;
            } else {
                $closed[] = $r;
            }
        }

        $eligible = [];
        if ($tab === 'apply') {
            $eligible = $this->container
                ->get(OrderRepository::class)
                ->listEligibleForReturn($customer->id);
        }

        $counts = [
            'processing' => count($processing),
            'closed'     => count($closed),
        ];

        return $this->renderAccount('storefront.account.returns', [
            'title'      => 'Returns',
            'customer'   => $customer,
            'tab'        => $tab,
            'counts'     => $counts,
            'processing' => $processing,
            'closed'     => $closed,
            'eligible'   => $eligible,
        ]);
    }

    // =================================================================
    // Create
    // =================================================================

    /**
     * The return submission form for one order. Lists each order
     * line with a quantity input, then a reason picker.
     *
     * @param array<string,string> $params
     */
    public function create(Request $request, array $params = []): Response
    {
        $customer = $this->customer();
        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $orderNumber = (string) ($params['orderNumber'] ?? '');
        if ($orderNumber === '') {
            return Response::redirect('/account/returns');
        }

        $orders = $this->container->get(OrderRepository::class);
        $order  = $orders->findForCustomer($orderNumber, $customer->id);

        if ($order === null) {
            return Response::redirect('/account/returns');
        }

        $session = $this->container->get(Session::class);

        if (!$this->canReturnFor($order)) {
            $session->flash('error', 'The return window for this order has closed.');
            return Response::redirect('/account/orders/' . rawurlencode($orderNumber));
        }

        $requests = $this->container->get(ReturnRequestRepository::class);
        if ($requests->hasOpenRequest($order->id)) {
            $session->flash('error', 'You already have an open return for this order.');
            return Response::redirect('/account/returns?tab=processing');
        }

        $svc      = $this->container->get(ReturnRequestService::class);
        $items    = $this->container->get(OrderItemRepository::class)->forOrder($order->id);

        // Pair each order line with its outstanding returnable
        // quantity so the form's max attribute is correct.
        $lines = [];
        foreach ($items as $item) {
            $outstanding = $svc->outstandingQuantity($item);
            if ($outstanding > 0) {
                $lines[] = ['item' => $item, 'outstanding' => $outstanding];
            }
        }

        if ($lines === []) {
            $session->flash('error', 'There is nothing left to return on this order.');
            return Response::redirect('/account/orders/' . rawurlencode($orderNumber));
        }

        return $this->renderAccount('storefront.account.return-form', [
            'title'        => 'Return items from ' . $order->orderNumber,
            'customer'     => $customer,
            'order'        => $order,
            'lines'        => $lines,
            'windowCloses' => $svc->windowClosesAt($order),
        ]);
    }

    // =================================================================
    // Store
    // =================================================================

    /**
     * Submit a return request. Form POST only. On validation
     * failure, flashes the message and old input and redirects
     * back to the form.
     *
     * @param array<string,string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $customer = $this->customer();
        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $session = $this->container->get(Session::class);

        $orderNumber = (string) $request->input('order_number', '');
        $reasonCode  = (string) $request->input('reason_code', '');
        $reasonNote  = trim((string) $request->input('reason_note', ''));
        $lineInput   = $request->input('lines', []);

        $orders = $this->container->get(OrderRepository::class);
        $order  = $orders->findForCustomer($orderNumber, $customer->id);
        if ($order === null) {
            return Response::redirect('/account/returns');
        }

        // Parse posted lines: lines[<orderItemId>][qty] = int.
        $lineQuantities = [];
        if (is_array($lineInput)) {
            foreach ($lineInput as $orderItemId => $row) {
                $qty = (int) (is_array($row) ? ($row['qty'] ?? 0) : 0);
                if ($qty > 0) {
                    $lineQuantities[(int) $orderItemId] = $qty;
                }
            }
        }

        try {
            $result = $this->container
                ->get(ReturnRequestService::class)
                ->open(
                    $order->id,
                    $customer->id,
                    $lineQuantities,
                    $reasonCode,
                    $reasonNote !== '' ? $reasonNote : null
                );

            $session->flash(
                'success',
                'Return request ' . $result['requestNumber'] . ' created.'
            );
            return Response::redirect('/account/returns/' . rawurlencode($result['requestNumber']));
        } catch (ReturnRequestException $e) {
            $session->flash('error', $e->getMessage());
            $session->flashOldInput([
                'order_number' => $orderNumber,
                'reason_code'  => $reasonCode,
                'reason_note'  => $reasonNote,
                'lines'        => $lineInput,
            ]);
            return Response::redirect('/account/returns/new/' . rawurlencode($orderNumber));
        }
    }

    // =================================================================
    // Show one
    // =================================================================

    /**
     * Single return request view. Ownership-guarded: the request
     * must belong to the logged-in customer. Same IDOR defense
     * as AccountController::orderDetail().
     *
     * @param array<string,string> $params
     */
    public function show(Request $request, array $params = []): Response
    {
        $customer = $this->customer();
        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $requestNumber = (string) ($params['requestNumber'] ?? '');
        if ($requestNumber === '') {
            return Response::redirect('/account/returns');
        }

        $requests = $this->container->get(ReturnRequestRepository::class);
        $req      = $requests->findByNumber($requestNumber);

        if ($req === null || $req->customerId !== $customer->id) {
            return Response::redirect('/account/returns');
        }

        $order = $this->container->get(OrderRepository::class)->findById($req->orderId);

        // Attach the order line snapshots (product name, variant
        // label) to each return line for display.
        $orderItemsById = [];
        if ($order !== null) {
            foreach ($this->container->get(OrderItemRepository::class)->forOrder($order->id) as $oi) {
                $orderItemsById[$oi->id] = $oi;
            }
        }

        $svc   = $this->container->get(ReturnRequestService::class);
        $items = $this->container->get(ReturnRequestItemRepository::class)->listForRequest($req->id);

        return $this->renderAccount('storefront.account.return-detail', [
            'title'        => 'Return ' . $req->requestNumber,
            'customer'     => $customer,
            'request'      => $req,
            'items'        => $items,
            'order'        => $order,
            'orderItems'   => $orderItemsById,
            'canCancel'    => in_array($req->status, ['requested', 'approved'], true),
            'windowCloses' => $order !== null ? $svc->windowClosesAt($order) : null,
        ]);
    }

    // =================================================================
    // Cancel
    // =================================================================

    /**
     * Customer-side cancel for a still-open return request.
     * Form POST only. Allowed while the request is in requested
     * or approved status. Redirects to the processing tab.
     *
     * @param array<string,string> $params
     */
    public function cancel(Request $request, array $params = []): Response
    {
        $customer = $this->customer();
        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $id = (int) ($params['id'] ?? 0);

        $session = $this->container->get(Session::class);
        $ok = $this->container
            ->get(ReturnRequestService::class)
            ->cancel($id, $customer->id);

        if ($ok) {
            $session->flash('success', 'Return request cancelled.');
        } else {
            $session->flash('error', 'That return could not be cancelled.');
        }

        return Response::redirect('/account/returns?tab=processing');
    }

    // =================================================================
    // Internals
    // =================================================================

    /**
     * The logged-in customer, or null when no customer session
     * exists. Every public method resolves this at the top and
     * redirects on null.
     *
     * @return \CorporateAid\Commerce\Models\Customer|null
     */
    private function customer(): ?\CorporateAid\Commerce\Models\Customer
    {
        return $this->container
            ->get(CustomerAuthService::class)
            ->user();
    }

    /**
     * Whether the customer can start a new return for this order.
     *
     * Both checks must agree:
     *   - the order is within its withdrawal window per the
     *     Order model (14 days from placed_at)
     *   - the order is within the store's return window per
     *     ReturnRequestService (windowDays from delivery, or
     *     unblocked before delivery when the store allows it)
     *
     * Combining the two keeps the order detail withdrawal
     * block and the return page eligibility display consistent.
     */
    private function canReturnFor(\CorporateAid\Commerce\Models\Order $order): bool
    {
        if (!$order->isWithdrawalEligible()) {
            return false;
        }
        return $this->container
            ->get(ReturnRequestService::class)
            ->isWithinWindow($order);
    }

    /**
     * Render a page inside the account shell.
     *
     * @param array<string,mixed> $data
     */
    private function renderAccount(string $view, array $data): Response
    {
        $data['_account_inner_view'] = $view;
        return parent::render('storefront.account._shell', $data);
    }
}
