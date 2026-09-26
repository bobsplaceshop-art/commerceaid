<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Middleware;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;

/**
 * Requires a logged-in user. Redirects to $loginPath otherwise,
 * preserving the intended URL as ?intended=<path> so the login
 * controller can send the user back after authentication.
 *
 * The login path is injected by the container so this middleware
 * stays product-agnostic. A product that uses /auth/sign-in binds
 * it that way; a product that uses /login binds that.
 *
 * SECURITY: The intended URL is URL-encoded when appended. The
 * login controller is responsible for validating the intended
 * value before redirecting - see AuthController::isSafeInternalRedirect().
 * Never trust ?intended= blindly; it is attacker-controllable.
 */
final class AuthMiddleware
{
    public function __construct(
        private Auth $auth,
        private string $loginPath = '/login'
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->check()) {
            return $next($request);
        }

        $intended = $request->path();
        $url = $this->loginPath;

        if ($intended !== '/' && $intended !== $this->loginPath) {
            $separator = str_contains($this->loginPath, '?') ? '&' : '?';
            $url .= $separator . 'intended=' . rawurlencode($intended);
        }

        return Response::redirect($url);
    }
}
