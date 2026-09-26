<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Middleware;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Services\StoreService;

/**
 * Keeps the admin aware that setup is incomplete.
 *
 * Behaviour:
 *   - First admin login after store creation: redirect once to the wizard.
 *   - After that: allowed into the dashboard, but a banner persists
 *     on every admin page until all mandatory fields are set.
 *   - The banner is session-hideable ("remind me later"); it returns
 *     next login.
 *
 * Only runs on admin routes. Storefront is never gated.
 */
final class SetupGateMiddleware
{
    private const SETUP_PATH          = '/commerce/ca-admin/setup';
    private const SKIP_PATH           = '/commerce/ca-admin/setup/skip';
    private const FIRST_VISIT_FLAG    = 'ca_setup_first_visit_done';
    private const BANNER_HIDE_FLAG    = 'ca_setup_banner_hidden_until';

    public function __construct(
        private Auth $auth,
        private StoreService $stores,
        private StoreContext $context,
        private Session $session
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        // Only relevant for authenticated admins on admin routes.
        if ($this->auth->user() === null) {
            return $next($request);
        }

        $path = (string) ($request->path() ?? '');
        if (strpos($path, '/commerce/ca-admin') !== 0) {
            return $next($request);
        }

        // Allow the setup wizard itself, its skip action, and logout.
        if ($path === self::SETUP_PATH
            || $path === self::SKIP_PATH
            || strpos($path, '/commerce/ca-admin/setup/') === 0
            || $path === '/logout'
            || strpos($path, '/api/') === 0
        ) {
            return $next($request);
        }

        // Requires a resolved store.
        if (!$this->context->has()) {
            return $next($request);
        }

        $storeId = $this->context->id();
        if ($this->stores->isSetupComplete($storeId)) {
            return $next($request);
        }

        // Setup is incomplete. On the very first visit, redirect to wizard once.
        if (!$this->session->get(self::FIRST_VISIT_FLAG, false)) {
            $this->session->set(self::FIRST_VISIT_FLAG, true);
            return Response::redirect(self::SETUP_PATH);
        }

        // Subsequent visits: allow through, but mark for banner display.
        $hideUntil = (int) $this->session->get(self::BANNER_HIDE_FLAG, 0);
        if ($hideUntil < time()) {
            $this->session->set('ca_setup_banner_active', true);
            $this->session->set(
                'ca_setup_missing_fields',
                $this->stores->mandatoryFieldsMissing($storeId)
            );
        } else {
            $this->session->set('ca_setup_banner_active', false);
        }

        return $next($request);
    }
}
