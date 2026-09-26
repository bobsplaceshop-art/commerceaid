<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Platform\Repositories\UserRepository;

/**
 * Account preferences for the currently authenticated admin user.
 * Reached via AJAX from the topbar theme toggle, and (later) from a
 * full account settings page.
 */
final class AccountController
{
    public function __construct(private Container $container)
    {
    }

    /**
     * POST /account/theme
     *
     * Persists the current user's theme preference. Called by the
     * topbar theme toggle in app.js. Returns 204 on success, 401
     * if not authenticated, 422 on invalid input.
     */
    public function setTheme(Request $request, array $params = []): Response
    {
        $auth = $this->container->get(Auth::class);
        $user = $auth->user();

        if ($user === null) {
            return Response::json(['error' => 'Not authenticated'], 401);
        }

        $theme = (string) $request->input('theme', '');

        if (!in_array($theme, ['dark', 'light', 'system'], true)) {
            return Response::json(['error' => 'Invalid theme'], 422);
        }

        $this->container->get(UserRepository::class)
            ->updateThemePreference($user->id, $theme);

        return Response::noContent();
    }
}
