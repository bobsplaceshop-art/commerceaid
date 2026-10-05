<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Services\CustomerAuthService;
use CorporateAid\Commerce\Services\ProductReviewService;
use CorporateAid\Commerce\Support\StorefrontController;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Storefront review submission and pagination.
 *
 * Two actions:
 *
 *   submit()    POST /products/{slug}/review
 *               Creates or updates the current customer's review.
 *               Login required (checked here, redirects to
 *               /account/login with a flash). Responds with a
 *               flash + redirect back to the PDP.
 *
 *   paginate()  GET  /products/{slug}/reviews?offset=N
 *               Returns a JSON page of approved reviews. Public.
 *               Used by the "Load more reviews" button on the
 *               Reviews tab.
 *
 * The submission is rate-limited: one review per customer per
 * product per 60 seconds. The rate-limit check reads the
 * existing review's updated_at timestamp. If the customer is
 * editing a review that was touched less than 60 seconds ago,
 * the request is rejected with a flash.
 *
 * See docs/stages/008g.5_reviews-plan.md.
 */
final class ProductReviewController extends StorefrontController
{
    private const PAGE_SIZE   = 10;
    private const RATE_LIMIT_SECONDS = 60;

    /** @param array<string,string> $params */
    public function submit(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        if ($slug === '') {
            return Response::redirect('/products');
        }

        $session = $this->container->get(Session::class);

        // Require login.
        $customerId = $this->container->get(CustomerAuthService::class)->id();
        if ($customerId === null || $customerId <= 0) {
            $session->flash('error', 'Please sign in to submit a review.');
            return Response::redirect('/account/login?intended='
                . urlencode('/products/' . $slug . '#reviews'));
        }

        // Resolve the product.
        $products = $this->container->get(ProductRepository::class);
        $product  = $products->findBySlug($slug);
        if ($product === null) {
            $session->flash('error', 'That product is no longer available.');
            return Response::redirect('/products');
        }

        $reviews = $this->container->get(ProductReviewService::class);

        // Rate-limit check. If the customer already has a review
        // for this product that was touched in the last 60
        // seconds, reject the submission. This blocks accidental
        // double-submits and rapid-fire edits.
        $existing = $reviews->customerReview($product->id, $customerId);
        if ($existing !== null && $existing->updatedAt !== null) {
            $age = time() - (int) strtotime($existing->updatedAt);
            if ($age >= 0 && $age < self::RATE_LIMIT_SECONDS) {
                $session->flash('info', 'Please wait a moment before submitting again.');
                return Response::redirect('/products/' . $slug . '#reviews');
            }
        }

        // Read the form.
        $rating = (int) $request->input('rating', 0);
        $title  = $request->input('title', null);
        $body   = (string) $request->input('body', '');

        $result = $reviews->submit(
            productId: $product->id,
            customerId: $customerId,
            rating: $rating,
            body: $body,
            title: is_string($title) && trim($title) !== '' ? trim($title) : null
        );

        if (!$result['ok']) {
            $errors = $result['errors'] ?? ['Could not save your review.'];
            $session->flash('error', implode(' ', $errors));
            return Response::redirect('/products/' . $slug . '#reviews');
        }

        $session->flash(
            'success',
            !empty($result['is_edit'])
                ? 'Your review has been updated and is pending moderation.'
                : 'Thank you. Your review is pending moderation.'
        );

        return Response::redirect('/products/' . $slug . '#reviews');
    }

    /** @param array<string,string> $params */
    public function paginate(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        if ($slug === '') {
            return Response::json(['reviews' => [], 'has_more' => false]);
        }

        $products = $this->container->get(ProductRepository::class);
        $product  = $products->findBySlug($slug);
        if ($product === null) {
            return Response::json(['reviews' => [], 'has_more' => false]);
        }

        $offset = max(0, (int) $request->query('offset', 0));
        $limit  = self::PAGE_SIZE;

        $reviews = $this->container->get(ProductReviewService::class);

        // Fetch one extra row to know whether there is more.
        $page = $reviews->forProduct($product->id, $limit + 1, $offset);

        $rows = $page['reviews'];
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->renderReviewJson($r);
        }

        return Response::json([
            'reviews'  => $out,
            'has_more' => $hasMore,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function renderReviewJson(\CorporateAid\Commerce\Models\ProductReview $r): array
    {
        return [
            'id'                => $r->id,
            'rating'            => $r->rating,
            'title'             => $r->title,
            'body'              => $r->body,
            'verified_purchase' => $r->verifiedPurchase,
            'created_at'        => $r->createdAt,
            'author_name'       => $this->authorName($r),
            'is_anonymous'      => $r->customerId === null,
        ];
    }

    /**
     * Resolve the display name for a review's author. "Anonymous"
     * when the customer was deleted. The customer's full name
     * when available, falling back to the email.
     */
    private function authorName(\CorporateAid\Commerce\Models\ProductReview $r): string
    {
        if ($r->customerId === null) {
            return 'Anonymous';
        }

        try {
            $customers = $this->container->get(
                \CorporateAid\Commerce\Repositories\CustomerRepository::class
            );
            $c = $customers->findById($r->customerId);
            if ($c === null) {
                return 'Anonymous';
            }

            // The Customer model has fullName(). It falls back to
            // the email internally when the name is empty, so we
            // never fall through to an empty string here.
            $name = (string) $c->fullName();
            if ($name !== '') {
                return $name;
            }

            return (string) $c->email;
        } catch (\Throwable) {
            return 'Anonymous';
        }
    }
}
