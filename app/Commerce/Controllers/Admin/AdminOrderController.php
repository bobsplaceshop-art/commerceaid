<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Repositories\CustomerRepository;
use CorporateAid\Commerce\Repositories\OrderItemRepository;
use CorporateAid\Commerce\Repositories\OrderNoteRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Repositories\OrderStatusHistoryRepository;
use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Admin orders queue.
 *
 * Routes (all under /commerce/ca-admin/orders):
 *
 *   GET  /                    index (filtered list)
 *   GET  /{id}                show (full order detail)
 *   POST /{id}/status         updateStatus (transition)
 *   POST /{id}/note           addNote (internal note)
 *
 * The list is filtered by tab. The five tabs are:
 *   new        - pending_payment, paid
 *   processing - processing, packed, on_hold
 *   shipped    - shipped, delivered
 *   done       - completed, cancelled, failed,
 *                refunded, partially_refunded
 *   all        - every order
 *
 * The GET routes are gated by orders.view. The POST routes
 * by orders.manage.
 *
 * Status transitions are validated against
 * Order::canTransitionTo() before the repository is called.
 * The repository writes the order row and the history row
 * inside one transaction.
 *
 * Flash handling: this controller writes flash messages but
 * never reads them. The admin layout reads and consumes
 * flash globally.
 *
 * See docs/baseline/03-controllers.md and docs/baseline/04-flash.md
 * for the conventions this file follows.
 */
final class AdminOrderController extends AdminController
{
    private const PER_PAGE = 25;

    /**
     * Tab key => list of statuses in the tab. The 'all' tab
     * uses an empty list (no status filter applied).
     *
     * @var array<string, list<string>>
     */
    private const TAB_STATUSES = [
        'new'        => ['pending_payment', 'paid'],
        'processing' => ['processing', 'packed', 'on_hold'],
        'shipped'    => ['shipped', 'delivered'],
        'done'       => ['completed', 'cancelled', 'failed', 'refunded', 'partially_refunded'],
        'all'        => [],
    ];

    /**
     * @var list<string>
     */
    private const VALID_TABS = ['new', 'processing', 'shipped', 'done', 'all'];

    // =================================================================
    // GET /commerce/ca-admin/orders
    // =================================================================

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(OrderRepository::class);

        $filter = (string) $request->query('status', 'new');
        if (!in_array($filter, self::VALID_TABS, true)) {
            $filter = 'new';
        }

        $q    = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));

        $filters = [];
        $tabStatuses = self::TAB_STATUSES[$filter] ?? [];
        if ($tabStatuses !== []) {
            $filters['statuses'] = $tabStatuses;
        }
        if ($q !== '') {
            $filters['q'] = $q;
        }

        $result = $repo->paginate($filters, $page, self::PER_PAGE);
        $counts = $repo->countByStatusGroups();

        // Bulk-load customers for the current page so the list
        // can show names without one query per row.
        $customerIds = [];
        foreach ($result['items'] as $o) {
            if ($o->customerId !== null) {
                $customerIds[$o->customerId] = true;
            }
        }
        $customerMap = $this->loadCustomers(array_keys($customerIds));

        return $this->render('admin.orders.index', [
            'title'       => 'Orders',
            'result'      => $result,
            'filter'      => $filter,
            'q'           => $q,
            'counts'      => $counts,
            'customerMap' => $customerMap,
        ]);
    }

    // =================================================================
    // GET /commerce/ca-admin/orders/{id}
    // =================================================================

    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $this->container->get(Session::class)
                ->flash('error', 'Invalid order reference.');
            return Response::redirect('/commerce/ca-admin/orders');
        }

        $orders = $this->container->get(OrderRepository::class);
        $order  = $orders->findById($id);
        if ($order === null) {
            $this->container->get(Session::class)
                ->flash('error', 'Order not found.');
            return Response::redirect('/commerce/ca-admin/orders');
        }

        $items         = $this->container->get(OrderItemRepository::class)
            ->forOrder($order->id);
        $statusHistory = $this->container->get(OrderStatusHistoryRepository::class)
            ->forOrder($order->id);
        $notes         = $this->container->get(OrderNoteRepository::class)
            ->forOrder($order->id);

        $customer = null;
        if ($order->customerId !== null) {
            $customer = $this->container->get(CustomerRepository::class)
                ->findById($order->customerId);
        }

        return $this->render('admin.orders.show', [
            'title'              => 'Order ' . $order->orderNumber,
            'order'              => $order,
            'items'              => $items,
            'statusHistory'      => $statusHistory,
            'notes'              => $notes,
            'customer'           => $customer,
            'allowedTransitions' => $order->allowedTransitions(),
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/orders/{id}/status
    // =================================================================

    /** @param array<string,string> $params */
    public function updateStatus(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id'] ?? 0);
        $session = $this->container->get(Session::class);

        if ($id <= 0) {
            $session->flash('error', 'Invalid order reference.');
            return Response::redirect($this->backUrl($request));
        }

        $orders = $this->container->get(OrderRepository::class);
        $order  = $orders->findById($id);
        if ($order === null) {
            $session->flash('error', 'Order not found.');
            return Response::redirect($this->backUrl($request));
        }

        $newStatus = (string) $request->input('status', '');
        if ($newStatus === '' || !$order->canTransitionTo($newStatus)) {
            $session->flash(
                'error',
                'That status transition is not allowed from ' . $order->status . '.'
            );
            return Response::redirect('/commerce/ca-admin/orders/' . $order->id);
        }

        $note   = trim((string) $request->input('note', ''));
        $noteVal = $note === '' ? null : $note;
        if ($noteVal !== null && mb_strlen($noteVal) > 500) {
            $session->flash('error', 'Transition note must be 500 characters or fewer.');
            return Response::redirect('/commerce/ca-admin/orders/' . $order->id);
        }

        $userId = $this->currentUserId();

        $orders->transitionStatus(
            $order->id,
            $order->status,
            $newStatus,
            $userId,
            $noteVal
        );

        $session->flash(
            'success',
            'Order moved to ' . str_replace('_', ' ', $newStatus) . '.'
        );

        return Response::redirect('/commerce/ca-admin/orders/' . $order->id);
    }

    // =================================================================
    // POST /commerce/ca-admin/orders/{id}/note
    // =================================================================

    /** @param array<string,string> $params */
    public function addNote(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id'] ?? 0);
        $session = $this->container->get(Session::class);

        if ($id <= 0) {
            $session->flash('error', 'Invalid order reference.');
            return Response::redirect($this->backUrl($request));
        }

        $orders = $this->container->get(OrderRepository::class);
        $order  = $orders->findById($id);
        if ($order === null) {
            $session->flash('error', 'Order not found.');
            return Response::redirect($this->backUrl($request));
        }

        $note = trim((string) $request->input('note', ''));
        if ($note === '') {
            $session->flash('error', 'A note cannot be empty.');
            return Response::redirect('/commerce/ca-admin/orders/' . $order->id);
        }
        if (mb_strlen($note) > 5000) {
            $session->flash('error', 'A note must be 5000 characters or fewer.');
            return Response::redirect('/commerce/ca-admin/orders/' . $order->id);
        }

        $userId = $this->currentUserId();

        $this->container->get(OrderNoteRepository::class)
            ->append($order->id, $note, $userId, false);

        $session->flash('success', 'Note added.');

        return Response::redirect('/commerce/ca-admin/orders/' . $order->id);
    }

    // =================================================================
    // Helpers
    // =================================================================

    /**
     * Return to the list at the same tab the admin was on.
     * Reads 'return' from the POST body (set by the view).
     */
    private function backUrl(Request $request): string
    {
        $return = (string) $request->input('return', 'new');
        if (!in_array($return, self::VALID_TABS, true)) {
            $return = 'new';
        }
        return '/commerce/ca-admin/orders?status=' . urlencode($return);
    }

    /**
     * The acting user's ID for the status history actor column.
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

        $out = [];
        foreach ($ids as $id) {
            $c = $repo->findById((int) $id);
            if ($c !== null) {
                $out[(int) $id] = $c;
            }
        }
        return $out;
    }
}
