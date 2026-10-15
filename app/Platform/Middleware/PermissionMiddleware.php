<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Middleware;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;

/**
 * Blocks users who do not have the given permission.
 * Routed via: ['permission:vacancies.create']
 *
 * Middleware model migration 2026-09-24 - see AuthMiddleware for notes.
 */
final class PermissionMiddleware
{
    private Auth $auth;
    private string $permission;

    public function __construct(Auth $auth, string $permission = '')
    {
        $this->auth = $auth;
        $this->permission = $permission;
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return Response::redirect('/login');
        }

        if ($this->permission === '' || $user->can($this->permission)) {
            return $next($request);
        }

        // Logged in but lacks permission. Throw so App::renderException
        // renders resources/views/errors/403.php through the same path
        // the router's 404 uses. The themed view replaces the inline
        // plain-HTML body that used to ship here.
        throw HttpException::forbidden();
    }
}
