<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Middleware;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;

/**
 * Guest-only middleware.
 *
 * Applied to routes that should only be reachable by unauthenticated
 * users (login, register, password reset). If the current user is
 * already authenticated, they are redirected to $redirectTo.
 *
 * The redirect target is injected by the container so this middleware
 * stays product-agnostic. A product that wants to send authenticated
 * users to "/commerce/ca-admin" binds it that way; a product that
 * wants "/dashboard" binds that. Platform does not assume.
 */
final class GuestMiddleware
{
    public function __construct(
        private Auth $auth,
        private string $redirectTo = '/'
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->check()) {
            return Response::redirect($this->redirectTo);
        }
        return $next($request);
    }
}
