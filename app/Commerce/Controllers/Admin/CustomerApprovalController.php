<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Repositories\CustomerRepository;

/**
 * Customer approval queue.
 *
 * B2B signups under a group with requires_approval = 1 land in
 * approval_status = 'pending'. This controller is the admin
 * interface for approving, rejecting, or resetting that state.
 *
 * Routes (all under /commerce/ca-admin/customer-approvals):
 *   GET  /                       list (filter + pagination)
 *   GET  /{id}/history           timeline JSON (for modal)
 *   POST /{id}/approve           approve a pending customer
 *   POST /{id}/reject            reject with required reason
 *   POST /{id}/reset             reset to pending (reason optional)
 *
 * Design decisions (Stage 007B):
 *   - Approval reasons are admin-only. Customers see a generic
 *     "pending review" message, never the internal reason.
 *   - The queue shows pending by default; filter to see approved,
 *     rejected, or all.
 *   - Approve and reject are idempotent: re-applying the same
 *     state is a no-op (the repository WHERE clause guards it).
 *   - Every state transition writes a history row. The controller
 *     never writes history directly; the repository owns that.
 *
 * Flash handling: this controller writes flash messages via
 * $session->flash(...) but never reads them. The layout
 * (resources/views/layouts/app.php) reads and consumes flash
 * globally. Reading in the controller would swallow the message
 * before the layout could display it.
 *
 * See docs/baseline/03-controllers.md and docs/baseline/04-flash.md
 * for the conventions this file follows.
 */
final class CustomerApprovalController extends AdminController
{
    private const PER_PAGE = 25;

    private const VALID_FILTERS = ['pending', 'approved', 'rejected', 'all'];

    // =================================================================
    // GET /commerce/ca-admin/customer-approvals
    // =================================================================

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(CustomerRepository::class);

        $filter = (string) $request->query('status', 'pending');
        if (!in_array($filter, self::VALID_FILTERS, true)) {
            $filter = 'pending';
        }

        $page = max(1, (int) $request->query('page', 1));

        $filters = [];
        if ($filter !== 'all') {
            $filters['approval_status'] = $filter;
        }

        $result = $repo->paginate($filters, $page, self::PER_PAGE);

        return $this->render('admin.customer_approvals.index', [
            'title'         => 'Customer Approvals',
            'result'        => $result,
            'currentFilter' => $filter,
            'counts'        => $this->counts($repo),
        ]);
    }

    // =================================================================
    // GET /commerce/ca-admin/customer-approvals/{id}/history
    // =================================================================

    /** @param array<string,string> $params */
    public function history(Request $request, array $params = []): Response
    {
        $id   = (int) ($params['id'] ?? 0);
        $repo = $this->container->get(CustomerRepository::class);

        if ($id <= 0) {
            return Response::json(['error' => 'Invalid customer reference.'], 400);
        }

        $customer = $repo->findById($id);
        if ($customer === null) {
            return Response::json(['error' => 'Customer not found.'], 404);
        }

        $rows = $repo->approvalHistory($id, 100);

        $history = [];
        foreach ($rows as $h) {
            $history[] = [
                'id'                 => $h->id,
                'action'             => $h->actionLabel(),
                'badge_variant'      => $h->badgeVariant(),
                'from_status'        => $h->fromStatus,
                'to_status'          => $h->toStatus,
                'reason'             => $h->reason,
                'actor_name'         => $h->actorDisplay(),
                'created_at'         => $h->createdAt,
                'created_at_display' => $this->formatDateTime($h->createdAt),
            ];
        }

        return Response::json([
            'customer' => [
                'id'              => $customer->id,
                'name'            => $customer->fullName(),
                'email'           => $customer->email,
                'company'         => $customer->company,
                'approval_status' => $customer->approvalStatus,
            ],
            'history' => $history,
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/customer-approvals/{id}/approve
    // =================================================================

    /** @param array<string,string> $params */
    public function approve(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id'] ?? 0);
        $repo    = $this->container->get(CustomerRepository::class);
        $session = $this->container->get(Session::class);
        $userId  = $this->currentUserId();

        if ($userId === null) {
            $session->flash('error', 'Could not identify the acting user.');
            return Response::redirect('/commerce/ca-admin/customer-approvals');
        }

        if ($id <= 0) {
            $session->flash('error', 'Invalid customer reference.');
            return Response::redirect($this->backToFilter($request));
        }

        $customer = $repo->findById($id);
        if ($customer === null) {
            $session->flash('error', 'Customer not found.');
            return Response::redirect($this->backToFilter($request));
        }

        if ($customer->isApproved()) {
            $session->flash('success', $customer->fullName() . ' is already approved.');
            return Response::redirect($this->backToFilter($request));
        }

        $ok = $repo->approve($id, $userId);

        if ($ok) {
            $session->flash('success', $customer->fullName() . ' has been approved.');
        } else {
            $session->flash('error', 'Approval did not apply. The customer state may have changed.');
        }

        return Response::redirect($this->backToFilter($request));
    }

    // =================================================================
    // POST /commerce/ca-admin/customer-approvals/{id}/reject
    // =================================================================

    /** @param array<string,string> $params */
    public function reject(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id'] ?? 0);
        $repo    = $this->container->get(CustomerRepository::class);
        $session = $this->container->get(Session::class);
        $userId  = $this->currentUserId();

        if ($userId === null) {
            $session->flash('error', 'Could not identify the acting user.');
            return Response::redirect('/commerce/ca-admin/customer-approvals');
        }

        if ($id <= 0) {
            $session->flash('error', 'Invalid customer reference.');
            return Response::redirect($this->backToFilter($request));
        }

        $customer = $repo->findById($id);
        if ($customer === null) {
            $session->flash('error', 'Customer not found.');
            return Response::redirect($this->backToFilter($request));
        }

        $reason = trim((string) $request->input('reason', ''));

        if ($reason === '') {
            $session->flash('error', 'A reason is required to reject a customer.');
            return Response::redirect($this->backToFilter($request));
        }

        if (mb_strlen($reason) > 500) {
            $session->flash('error', 'Rejection reason must be 500 characters or fewer.');
            return Response::redirect($this->backToFilter($request));
        }

        $ok = $repo->reject($id, $userId, $reason);

        if ($ok) {
            $session->flash('success', $customer->fullName() . ' has been rejected.');
        } else {
            $session->flash('error', 'Rejection did not apply. The customer state may have changed.');
        }

        return Response::redirect($this->backToFilter($request));
    }

    // =================================================================
    // POST /commerce/ca-admin/customer-approvals/{id}/reset
    // =================================================================

    /** @param array<string,string> $params */
    public function reset(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id'] ?? 0);
        $repo    = $this->container->get(CustomerRepository::class);
        $session = $this->container->get(Session::class);

        if ($id <= 0) {
            $session->flash('error', 'Invalid customer reference.');
            return Response::redirect($this->backToFilter($request));
        }

        $customer = $repo->findById($id);
        if ($customer === null) {
            $session->flash('error', 'Customer not found.');
            return Response::redirect($this->backToFilter($request));
        }

        if ($customer->isPending()) {
            $session->flash('success', $customer->fullName() . ' is already pending review.');
            return Response::redirect($this->backToFilter($request));
        }

        // Reason is optional on reset. Empty string becomes null so
        // the history row stores NULL, not "".
        $reasonRaw = trim((string) $request->input('reason', ''));
        $reason    = $reasonRaw === '' ? null : $reasonRaw;

        if ($reason !== null && mb_strlen($reason) > 500) {
            $session->flash('error', 'Reset reason must be 500 characters or fewer.');
            return Response::redirect($this->backToFilter($request));
        }

        $ok = $repo->resetToPending($id, $reason);

        if ($ok) {
            $session->flash('success', $customer->fullName() . ' has been reset to pending review.');
        } else {
            $session->flash('error', 'Reset did not apply.');
        }

        return Response::redirect($this->backToFilter($request));
    }

    // =================================================================
    // Helpers
    // =================================================================

    /**
     * Return to the filter view the admin was on.
     * Reads 'return' from the POST body (set by the view's forms).
     */
    private function backToFilter(Request $request): string
    {
        $return = (string) $request->input('return', 'pending');
        if (!in_array($return, self::VALID_FILTERS, true)) {
            $return = 'pending';
        }
        return '/commerce/ca-admin/customer-approvals?status=' . $return;
    }

    /**
     * The acting user's ID for the approval audit trail.
     */
    private function currentUserId(): ?int
    {
        $auth = $this->container->get(Auth::class);
        $user = $auth->user();
        return $user !== null ? (int) $user->id : null;
    }

    /**
     * Counts for every filter tab. Key 'all' is the total across
     * every approval status.
     *
     * @return array{pending:int, approved:int, rejected:int, all:int}
     */
    private function counts(CustomerRepository $repo): array
    {
        return [
            'pending'  => $repo->countByApprovalStatus('pending'),
            'approved' => $repo->countByApprovalStatus('approved'),
            'rejected' => $repo->countByApprovalStatus('rejected'),
            'all'      => $repo->countByApprovalStatus(null),
        ];
    }

    /**
     * Format a DATETIME string for display in the history timeline.
     */
    private function formatDateTime(?string $dt): string
    {
        if ($dt === null || $dt === '') return '';
        $ts = strtotime($dt);
        if ($ts === false) return $dt;
        return date('M j, Y \a\t H:i', $ts);
    }
}
