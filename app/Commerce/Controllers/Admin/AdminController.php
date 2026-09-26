<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Response;
use CorporateAid\Core\View;
use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Services\StoreService;

/**
 * Base controller for every admin page.
 *
 * Provides a single render() helper that injects the context every
 * admin view needs:
 *   - $user          (current user or null)
 *   - $title         (page title)
 *   - $setupComplete (bool - controls sidebar lock state)
 *
 * Subclasses extend this and call $this->render('admin.foo', [...]).
 */
abstract class AdminController
{
    public function __construct(protected Container $container)
    {
    }

    /**
     * Render a view inside the admin shell (layouts.app).
     *
     * @param string $view  dot-notation view name, e.g. 'admin.dashboard'
     * @param array  $data  view-specific data (title is optional; if
     *                      provided it's used for breadcrumb + tab)
     */
    protected function render(string $view, array $data = []): Response
    {
        $auth     = $this->container->get(Auth::class);
        $viewSvc  = $this->container->get(View::class);

        $data['user'] = $auth->user();
        $data['title'] = $data['title'] ?? 'Admin';
        $data['setupComplete'] = $this->isSetupComplete();

        return Response::html(
            $viewSvc->render($view, $data, 'layouts.app')
        );
    }

    /**
     * Whether the current store has completed mandatory setup.
     * Returns true if no store is resolved yet (so we don't block
     * rendering with a false lock state).
     */
    protected function isSetupComplete(): bool
    {
        $context = $this->container->get(StoreContext::class);
        if (!$context->has()) {
            return true;
        }
        $storeService = $this->container->get(StoreService::class);
        return $storeService->isSetupComplete($context->id());
    }
}
