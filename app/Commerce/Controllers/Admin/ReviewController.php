<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Repositories\ProductReviewRepository;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Admin review moderation.
 *
 * Routes (all under /commerce/ca-admin/reviews), each
 * guarded by the permission middleware with
 * review.moderate:
 *
 *   GET  /                    index (filtered list)
 *   POST /{id}/approve        approve and redirect
 *   POST /{id}/reject         reject and redirect
 *   POST /{id}/delete         hard delete and redirect
 *
 * The list is filtered by status. Default: pending. The
 * filter is a query parameter:
 *   ?status=pending      awaiting moderation (default)
 *   ?status=approved     already live
 *   ?status=rejected     rejected by a moderator
 *
 * Pagination is simple: page number in the query string,
 * 25 rows per page.
 */
final class ReviewController extends AdminController
{
    private const PER_PAGE = 25;

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $reviews = $this->container->get(ProductReviewRepository::class);

        $status = (string) $request->query('status', 'pending');
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = 'pending';
        }

        $page   = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $rows        = $reviews->listByStatus($status, self::PER_PAGE, $offset);
        $total       = $reviews->countByStatus($status);
        $pendingCnt  = $reviews->countPending();

        $totalPages = (int) ceil($total / self::PER_PAGE);

        // Resolve display names and product names for each row.
        // One bulk query each. Keeps the list rendering from
        // N+1 querying.
        $customerIds = [];
        $productIds  = [];
        foreach ($rows as $r) {
            if ($r->customerId !== null) {
                $customerIds[$r->customerId] = true;
            }
            $productIds[$r->productId] = true;
        }

        $customerMap = $this->loadCustomers(array_keys($customerIds));
        $productMap  = $this->loadProducts(array_keys($productIds));

        return $this->render('admin.reviews.index', [
            'title'      => 'Reviews',
            'reviews'    => $rows,
            'status'     => $status,
            'page'       => $page,
            'total'      => $total,
            'totalPages' => $totalPages,
            'pendingCnt' => $pendingCnt,
            'customerMap'=> $customerMap,
            'productMap' => $productMap,
        ]);
    }

    /** @param array<string,string> $params */
    public function approve(Request $request, array $params = []): Response
    {
        return $this->mutateStatus($request, $params, 'approved');
    }

    /** @param array<string,string> $params */
    public function reject(Request $request, array $params = []): Response
    {
        return $this->mutateStatus($request, $params, 'rejected');
    }

    /** @param array<string,string> $params */
    public function destroy(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $id      = (int) ($params['id'] ?? 0);

        if ($id <= 0) {
            $session->flash('error', 'Invalid review id.');
            return Response::redirect('/commerce/ca-admin/reviews');
        }

        $deleted = $this->container
            ->get(ProductReviewRepository::class)
            ->delete($id);

        $session->flash(
            $deleted ? 'success' : 'error',
            $deleted ? 'Review deleted.' : 'Review not found.'
        );

        $returnTo = (string) $request->input('return_status', 'pending');
        if (!in_array($returnTo, ['pending', 'approved', 'rejected'], true)) {
            $returnTo = 'pending';
        }

        return Response::redirect('/commerce/ca-admin/reviews?status=' . urlencode($returnTo));
    }

    /**
     * Shared logic for approve / reject. Sets the status,
     * flashes a message, and returns to the list at the same
     * filter the moderator was viewing.
     *
     * @param array<string,string> $params
     */
    private function mutateStatus(Request $request, array $params, string $targetStatus): Response
    {
        $session = $this->container->get(Session::class);
        $id      = (int) ($params['id'] ?? 0);

        if ($id <= 0) {
            $session->flash('error', 'Invalid review id.');
            return Response::redirect('/commerce/ca-admin/reviews');
        }

        $ok = $this->container
            ->get(ProductReviewRepository::class)
            ->updateStatus($id, $targetStatus);

        if ($ok) {
            $session->flash(
                'success',
                $targetStatus === 'approved' ? 'Review approved.' : 'Review rejected.'
            );
        } else {
            $session->flash('error', 'Review not found or already in that state.');
        }

        $returnTo = (string) $request->input('return_status', 'pending');
        if (!in_array($returnTo, ['pending', 'approved', 'rejected'], true)) {
            $returnTo = 'pending';
        }

        return Response::redirect('/commerce/ca-admin/reviews?status=' . urlencode($returnTo));
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

        $repo = $this->container->get(
            \CorporateAid\Commerce\Repositories\CustomerRepository::class
        );

        $out = [];
        foreach ($ids as $id) {
            $c = $repo->findById((int) $id);
            if ($c !== null) {
                $out[(int) $id] = $c;
            }
        }
        return $out;
    }

    /**
     * Bulk-load products by id, keyed by id.
     *
     * @param array<int,int> $ids
     * @return array<int,\CorporateAid\Commerce\Models\Product>
     */
    private function loadProducts(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $repo = $this->container->get(
            \CorporateAid\Commerce\Repositories\ProductRepository::class
        );

        $out = [];
        foreach ($ids as $id) {
            $p = $repo->findById((int) $id);
            if ($p !== null) {
                $out[(int) $id] = $p;
            }
        }
        return $out;
    }
}
