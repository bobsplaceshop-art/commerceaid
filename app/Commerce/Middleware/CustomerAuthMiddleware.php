<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Middleware;

use CorporateAid\Commerce\Services\CustomerAuthService;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;

/**
 * Requires a logged-in customer. Redirects to the customer login
 * page otherwise, preserving the intended URL as ?intended=<path>
 * so the login controller can send them back after authentication.
 *
 * Parallel to Platform\Middleware\AuthMiddleware but operating on
 * the customer session via CustomerAuthService. The two guards are
 * independent:
 *
 *   auth           -> AuthMiddleware          (admin)
 *   customer.auth  -> CustomerAuthMiddleware  (storefront)
 *
 * A route that needs an admin uses 'auth'. A route that needs a
 * customer uses 'customer.auth'. Neither implies the other.
 *
 * SECURITY: The intended URL is URL-encoded when appended. The
 * login controller is responsible for validating the intended
 * value before redirecting - see
 * Storefront\AuthController::isSafeInternalRedirect(). Never trust
 * ?intended= blindly; it is attacker-controllable.
 *
 * Routes registered with 'customer.auth' should also declare
 * 'store' (so StoreContext is populated before any repository
 * read) but should NOT declare 'auth' or 'setup.gate' - those
 * are admin concerns.
 *
 * See docs/stages/008c_customer-auth-plan.md.
 */
final class CustomerAuthMiddleware
{
    public function __construct(
        private CustomerAuthService $auth,
        private string $loginPath = '/account/login'
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->check()) {
            return $next($request);
        }

        $intended = $request->path();
        $url = $this->loginPath;

        // Do not loop the user back to the login page after a redirect
        // from the login page itself. Same rule as AuthMiddleware.
        if ($intended !== '/' && $intended !== $this->loginPath) {
            $separator = str_contains($this->loginPath, '?') ? '&' : '?';
            $url .= $separator . 'intended=' . rawurlencode($intended);
        }

        return Response::redirect($url);
    }
}
