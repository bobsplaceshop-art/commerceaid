<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Middleware;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\View;
use CorporateAid\Commerce\Repositories\StoreSettingsRepository;
use CorporateAid\Platform\Support\Logger;

final class MaintenanceMiddleware
{
    public function __construct(
        private Auth $auth,
        private StoreSettingsRepository $settings,
        private View $view,
        private Logger $logger,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $status = $this->settings->string('system.store_status', 'live');
        
        // Log the actual string status
        $this->logger->info("MW GATE: path " . $request->path() . " - status is " . $status);

        if ($status === 'live') {
            return $next($request);
        }

        // Allow logged-in admins to browse the site
        if ($this->auth->user() !== null && $this->auth->user()->can('core.settings.manage')) {
            return $next($request);
        }

        $message = $this->settings->string('system.store_status_message', '');
        
        // Fetch the store's contact email
        $contactEmail = $this->settings->string('general.contact_email', '');
        if ($contactEmail === '') {
            $contactEmail = 'admin@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }

        if ($status === 'coming_soon') {
            $body = $this->view->render('errors.coming-soon', [
                'title' => 'Coming Soon',
                'message' => $message ?: 'We are working hard to launch our new store. Please check back soon!',
                'contactEmail' => $contactEmail,
            ]);
            return Response::html($body, 200);
        }

        // Fallback to maintenance (503)
        $body = $this->view->render('errors.503', [
            'title' => 'Maintenance',
            'message' => $message ?: 'The store is currently undergoing scheduled maintenance. Please check back shortly.',
            'contactEmail' => $contactEmail,
        ]);
        return Response::html($body, 503);
    }
}
