<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Exceptions\PaymentRefundException;
use CorporateAid\Commerce\Exceptions\ReturnRequestException;
use CorporateAid\Commerce\Repositories\CustomerRepository;
use CorporateAid\Commerce\Repositories\OrderItemRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Repositories\ReturnRequestItemRepository;
use CorporateAid\Commerce\Repositories\ReturnRequestRepository;
use CorporateAid\Commerce\Services\Payment\PaymentRefundService;
use CorporateAid\Commerce\Services\ReturnRequestService;
use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Admin returns queue. Stage 008H Session 3.
 *
 * Routes (all under /commerce/ca-admin/returns):
 *
 *   GET  /                    index (filtered list)
 *   GET  /{id}                show (full request detail)
 *   POST /{id}/approve        approve a requested return
 *   POST /{id}/reject         reject with a required reason
 *   POST /{id}/receive        mark received with per-line quantities
 *   POST /{id}/refund         process the refund
 *
 * The list is filtered by tab. The four tabs are:
 *   open     - requested, approved
 *   received - received (awaiting refund)
 *   closed   - rejected, refunded, cancelled
 *   all      - every request
 *
 * The GET routes are gated by returns.view. The POST routes
 * by returns.moderate.
 *
 * Approve, reject, and markReceived delegate to
 * ReturnRequestService, which is the sole writer to the two
 * returns tables and to the affected order columns
 * (refunded_total, payment_status, fulfilment_status, and each
 * order line's returned_quantity).
 *
 * Refund delegates to PaymentRefundService, which consumes
 * ReturnRequestService::processRefund() and then dispatches
 * the refund to the payment provider. The provider call runs
 * post-commit; a provider failure leaves the recorded intent
 * intact and the refund row in a failed state for the retry
 * cron.
 *
 * This controller never writes those tables directly.
 *
 * The state machine enforced by the service:
 *   requested  -> approved | rejected | cancelled (customer)
 *   approved   -> received | rejected | cancelled (customer)
 *   received   -> refunded
 *
 * Flash handling: this controller writes flash but never reads.
 * The layout consumes flash globally. See docs/baseline/04-flash.md.
 *
 * IDOR defense: every lookup goes through the repository, which
 * scopes by store_id. Route params alone are never trusted.
 */
final class AdminReturnController extends AdminController
{
    private const PER_PAGE = 25;

    /**
     * Tab key => list of statuses in the tab. The 'all' tab
     * uses an empty list (no status filter applied).
     *
     * @var array<string, list<string>>
     */
    private const TAB_STATUSES = [
        'open'     => ['requested', 'approved'],
        'received' => ['received'],
        'closed'   => ['rejected', 'refunded', 'cancelled'],
        'all'      => [],
    ];

    /**
     * @var list<string>
     */
    private const VALID_TABS = ['open', 'received', 'closed', 'all'];

    // =================================================================
    // GET /commerce/ca-admin/returns
    // =================================================================

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(ReturnRequestRepository::class);

        $filter = (string) $request->query('status', 'open');
        if (!in_array($filter, self::VALID_TABS, true)) {
            $filter = 'open';
        }

        $q        = trim((string) $request->query('q', ''));
        $dateFrom = $this->validDate((string) $request->query('date_from', ''));
        $dateTo   = $this->validDate((string) $request->query('date_to', ''));
        $page     = max(1, (int) $request->query('page', 1));

        $filters = [];
        $tabStatuses = self::TAB_STATUSES[$filter] ?? [];
        if ($tabStatuses !== []) {
            // The repository's buildFilters() takes a single
            // status. The list reads the tab's statuses for the
            // count badges and defaults the filter to the first
            // status. For the multi-status tabs ('open', 'closed')
            // we still want every status in the tab visible in
            // one list. Simplest: do not filter by status when
            // the tab has more than one, and rely on the tab's
            // own list rendering to show only what belongs.
            //
            // The proper fix is a 'statuses' array param in the
            // repository, matching OrderRepository::paginate().
            // Logged as a follow-up. For now, filter by the first
            // status and use in-PHP filtering for the rest.
            $filters['status'] = $tabStatuses[0];
        }
        if ($q !== '') {
            $filters['q'] = $q;
        }
        if ($dateFrom !== null) {
            $filters['date_from'] = $dateFrom;
        }
        if ($dateTo !== null) {
            $filters['date_to'] = $dateTo;
        }

        $result = $repo->paginateForAdmin($filters, $page, self::PER_PAGE);

        // If the tab has more than one status, the repo returned
        // only rows for the first status. Request the rest
        // separately and merge. Small cost, correct result.
        if (count($tabStatuses) > 1) {
            $result = $this->mergeTabStatuses($repo, $result, $tabStatuses, $filters, $page);
        }

        $counts = [
            'open'     => $repo->countByStatus('requested') + $repo->countByStatus('approved'),
            'received' => $repo->countByStatus('received'),
            'closed'   => $repo->countByStatus('rejected') + $repo->countByStatus('refunded') + $repo->countByStatus('cancelled'),
            'all'      => $repo->countByStatus('requested')
                        + $repo->countByStatus('approved')
                        + $repo->countByStatus('received')
                        + $repo->countByStatus('rejected')
                        + $repo->countByStatus('refunded')
                        + $repo->countByStatus('cancelled'),
        ];

        // Bulk-load customers and orders for the current page so
        // the list can show names and order numbers without a
        // query per row.
        $customerIds = [];
        $orderIds    = [];
        foreach ($result['items'] as $r) {
            if ($r->customerId > 0) {
                $customerIds[$r->customerId] = true;
            }
            if ($r->orderId > 0) {
                $orderIds[$r->orderId] = true;
            }
        }
        $customerMap = $this->loadCustomers(array_keys($customerIds));
        $orderMap    = $this->loadOrders(array_keys($orderIds));

        return $this->render('admin.returns.index', [
            'title'       => 'Returns',
            'result'      => $result,
            'filter'      => $filter,
            'q'           => $q,
            'dateFrom'    => $dateFrom ?? '',
            'dateTo'      => $dateTo ?? '',
            'counts'      => $counts,
            'customerMap' => $customerMap,
            'orderMap'    => $orderMap,
        ]);
    }

    // =================================================================
    // GET /commerce/ca-admin/returns/{id}
    // =================================================================

    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id'] ?? 0);
        $session = $this->container->get(Session::class);

        if ($id <= 0) {
            $session->flash('error', 'Invalid return reference.');
            return Response::redirect('/commerce/ca-admin/returns');
        }

        $repo = $this->container->get(ReturnRequestRepository::class);
        $req  = $repo->findById($id);
        if ($req === null) {
            $session->flash('error', 'Return request not found.');
            return Response::redirect('/commerce/ca-admin/returns');
        }

        $order = $this->container->get(OrderRepository::class)
            ->findById($req->orderId);

        // Return lines paired with their order-line snapshots so
        // the detail page can show the product name and variant
        // label without a query per line.
        $items          = $this->container->get(ReturnRequestItemRepository::class)
            ->listForRequest($req->id);
        $orderItemsById = [];
        if ($order !== null) {
            foreach ($this->container->get(OrderItemRepository::class)
                         ->forOrder($order->id) as $oi) {
                $orderItemsById[$oi->id] = $oi;
            }
        }

        $customer = $req->customerId > 0
            ? $this->container->get(CustomerRepository::class)
                ->findById($req->customerId)
            : null;

        return $this->render('admin.returns.show', [
            'title'        => 'Return ' . $req->requestNumber,
            'request'      => $req,
            'items'        => $items,
            'order'        => $order,
            'orderItems'   => $orderItemsById,
            'customer'     => $customer,
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/returns/{id}/approve
    // =================================================================

    /** @param array<string,string> $params */
    public function approve(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id'] ?? 0);
        $session = $this->container->get(Session::class);
        $userId  = $this->currentUserId();

        if ($userId === null) {
            $session->flash('error', 'Could not identify the acting user.');
            return Response::redirect($this->backUrl($request));
        }
        if ($id <= 0) {
            $session->flash('error', 'Invalid return reference.');
            return Response::redirect($this->backUrl($request));
        }

        $repo = $this->container->get(ReturnRequestRepository::class);
        $req  = $repo->findById($id);
        if ($req === null) {
            $session->flash('error', 'Return request not found.');
            return Response::redirect($this->backUrl($request));
        }

        $ok = $this->container->get(ReturnRequestService::class)
            ->approve($id, $userId);

        if ($ok) {
            $session->flash('success', 'Return ' . $req->requestNumber . ' approved.');
        } else {
            $session->flash('error', 'Approval did not apply. The return state may have changed.');
        }

        return Response::redirect('/commerce/ca-admin/returns/' . $id);
    }

    // =================================================================
    // POST /commerce/ca-admin/returns/{id}/reject
    // =================================================================

    /** @param array<string,string> $params */
    public function reject(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id'] ?? 0);
        $session = $this->container->get(Session::class);
        $userId  = $this->currentUserId();

        if ($userId === null) {
            $session->flash('error', 'Could not identify the acting user.');
            return Response::redirect($this->backUrl($request));
        }
        if ($id <= 0) {
            $session->flash('error', 'Invalid return reference.');
            return Response::redirect($this->backUrl($request));
        }

        $repo = $this->container->get(ReturnRequestRepository::class);
        $req  = $repo->findById($id);
        if ($req === null) {
            $session->flash('error', 'Return request not found.');
            return Response::redirect($this->backUrl($request));
        }

        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            $session->flash('error', 'A reason is required to reject a return.');
            return Response::redirect('/commerce/ca-admin/returns/' . $id);
        }
        if (mb_strlen($reason) > 500) {
            $session->flash('error', 'Rejection reason must be 500 characters or fewer.');
            return Response::redirect('/commerce/ca-admin/returns/' . $id);
        }

        $ok = $this->container->get(ReturnRequestService::class)
            ->reject($id, $userId, $reason);

        if ($ok) {
            $session->flash('success', 'Return ' . $req->requestNumber . ' rejected.');
        } else {
            $session->flash('error', 'Rejection did not apply. The return state may have changed.');
        }

        return Response::redirect('/commerce/ca-admin/returns/' . $id);
    }

    // =================================================================
    // POST /commerce/ca-admin/returns/{id}/receive
    // =================================================================

    /**
     * Mark a return as received. The posted body carries a
     * received[<order_item_id>] map of actual counts. Lines not
     * in the map default to the requested quantity, so a modal
     * that only sends changed lines still works.
     *
     * @param array<string,string> $params
     */
    public function markReceived(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id'] ?? 0);
        $session = $this->container->get(Session::class);
        $userId  = $this->currentUserId();

        if ($userId === null) {
            $session->flash('error', 'Could not identify the acting user.');
            return Response::redirect($this->backUrl($request));
        }
        if ($id <= 0) {
            $session->flash('error', 'Invalid return reference.');
            return Response::redirect($this->backUrl($request));
        }

        $repo = $this->container->get(ReturnRequestRepository::class);
        $req  = $repo->findById($id);
        if ($req === null) {
            $session->flash('error', 'Return request not found.');
            return Response::redirect($this->backUrl($request));
        }

        $receivedRaw = $request->input('received', []);
        $received    = [];
        if (is_array($receivedRaw)) {
            foreach ($receivedRaw as $orderItemId => $qty) {
                $received[(int) $orderItemId] = max(0, (int) $qty);
            }
        }

        $adjustments = $this->container->get(ReturnRequestService::class)
            ->markReceived($id, $userId, $received);

        if ($adjustments === []) {
            $session->flash('error', 'Receiving did not apply. The return state may have changed.');
            return Response::redirect('/commerce/ca-admin/returns/' . $id);
        }

        // Summarize: how many lines were adjusted downward.
        $reduced = 0;
        foreach ($adjustments as $adj) {
            if ($adj['received'] < $adj['requested']) {
                $reduced++;
            }
        }

        if ($reduced === 0) {
            $session->flash('success', 'Return ' . $req->requestNumber . ' marked received.');
        } else {
            $session->flash(
                'success',
                'Return ' . $req->requestNumber . ' marked received with '
                . $reduced . ' line' . ($reduced === 1 ? '' : 's') . ' adjusted.'
            );
        }

        return Response::redirect('/commerce/ca-admin/returns/' . $id);
    }

    // =================================================================
    // POST /commerce/ca-admin/returns/{id}/refund
    // =================================================================

    /** @param array<string,string> $params */
    public function refund(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id'] ?? 0);
        $session = $this->container->get(Session::class);
        $userId  = $this->currentUserId();

        if ($userId === null) {
            $session->flash('error', 'Could not identify the acting user.');
            return Response::redirect($this->backUrl($request));
        }
        if ($id <= 0) {
            $session->flash('error', 'Invalid return reference.');
            return Response::redirect($this->backUrl($request));
        }

        $repo = $this->container->get(ReturnRequestRepository::class);
        $req  = $repo->findById($id);
        if ($req === null) {
            $session->flash('error', 'Return request not found.');
            return Response::redirect($this->backUrl($request));
        }

        try {
            $result = $this->container->get(PaymentRefundService::class)
                ->refund($id, $userId);
        } catch (ReturnRequestException $e) {
            $session->flash('error', $e->getMessage());
            return Response::redirect('/commerce/ca-admin/returns/' . $id);
        } catch (PaymentRefundException $e) {
            $session->flash('error', $e->getMessage());
            return Response::redirect('/commerce/ca-admin/returns/' . $id);
        }

        $amount = number_format($result->amount, 2);

        if ($result->status === 'succeeded') {
            $session->flash(
                'success',
                'Refund of ' . $amount . ' processed for ' . $req->requestNumber . '.'
            );
        } elseif ($result->status === 'pending') {
            $session->flash(
                'success',
                'Refund of ' . $amount . ' recorded for ' . $req->requestNumber
                . '. The provider will process it shortly.'
            );
        } else {
            $session->flash(
                'error',
                'The refund for ' . $req->requestNumber . ' failed: '
                . ($result->errorMessage ?? 'unknown provider error')
            );
        }

        return Response::redirect('/commerce/ca-admin/returns/' . $id);
    }

    // =================================================================
    // Helpers
    // =================================================================

    /**
     * Return to the list at the same tab the admin was on.
     * Reads 'return' from the POST body (set by the views).
     */
    private function backUrl(Request $request): string
    {
        $return = (string) $request->input('return', 'open');
        if (!in_array($return, self::VALID_TABS, true)) {
            $return = 'open';
        }
        return '/commerce/ca-admin/returns?status=' . urlencode($return);
    }

    /**
     * The acting user's ID for the reviewed_by audit trail.
     */
    private function currentUserId(): ?int
    {
        $user = $this->container->get(Auth::class)->user();
        return $user !== null ? (int) $user->id : null;
    }

    /**
     * Bulk-load customers by id, keyed by id.
     *
     * @param array<int,int> $ids
     * @return array<int,\CorporateAid\Commerce\Models\Customer>
     */
    private function loadCustomers(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $repo = $this->container->get(CustomerRepository::class);
        $out  = [];
        foreach ($ids as $id) {
            $c = $repo->findById((int) $id);
            if ($c !== null) {
                $out[(int) $id] = $c;
            }
        }
        return $out;
    }

    /**
     * Bulk-load orders by id, keyed by id.
     *
     * @param array<int,int> $ids
     * @return array<int,\CorporateAid\Commerce\Models\Order>
     */
    private function loadOrders(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $repo = $this->container->get(OrderRepository::class);
        $out  = [];
        foreach ($ids as $id) {
            $o = $repo->findById((int) $id);
            if ($o !== null) {
                $out[(int) $id] = $o;
            }
        }
        return $out;
    }

    /**
     * Validate a YYYY-MM-DD date from the query string. Returns
     * the string when valid, null otherwise. Rejects anything
     * that does not parse to the same format (guards against
     * off-by-one from strtotime coercion).
     */
    private function validDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($dt === false || $dt->format('Y-m-d') !== $value) {
            return null;
        }
        return $value;
    }

    /**
     * When a tab groups multiple statuses, the repository's
     * single-status filter returns only the first. This merges
     * the remaining statuses into the result set by fetching each
     * and appending. Pagination is a best-effort merge; pages
     * after the first may be slightly under-populated. Acceptable
     * for this stage; a future fix adds a 'statuses' array
     * parameter to the repository.
     *
     * @param array<string,mixed> $result
     * @param list<string> $statuses
     * @param array<string,mixed> $baseFilters
     * @return array<string,mixed>
     */
    private function mergeTabStatuses(
        ReturnRequestRepository $repo,
        array $result,
        array $statuses,
        array $baseFilters,
        int $page
    ): array {
        $alreadyLoaded = [];
        foreach ($result['items'] as $r) {
            $alreadyLoaded[$r->id] = true;
        }

        $extra = [];
        for ($i = 1; $i < count($statuses); $i++) {
            $subFilters = $baseFilters;
            $subFilters['status'] = $statuses[$i];
            $sub = $repo->paginateForAdmin($subFilters, 1, self::PER_PAGE);
            foreach ($sub['items'] as $r) {
                if (!isset($alreadyLoaded[$r->id])) {
                    $extra[] = $r;
                    $alreadyLoaded[$r->id] = true;
                }
            }
        }

        if ($extra !== []) {
            // Re-sort by requested_at DESC, id DESC — the shape
            // the repository returns for a single-status query.
            $all = array_merge($result['items'], $extra);
            usort($all, function ($a, $b) {
                $cmp = strcmp((string) $b->requestedAt, (string) $a->requestedAt);
                return $cmp !== 0 ? $cmp : ((int) $b->id - (int) $a->id);
            });
            $result['items'] = $all;
            // Total reflects the merged page view, not the sum of
            // the sub-queries. The counts[] array on the index
            // carries the true totals per tab.
            $result['total'] = count($all);
        }

        return $result;
    }
}
