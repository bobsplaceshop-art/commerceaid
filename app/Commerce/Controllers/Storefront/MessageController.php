<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Commerce\Repositories\CustomerRepository;
use CorporateAid\Commerce\Services\CustomerAuthService;
use CorporateAid\Commerce\Services\CustomerNotificationService;
use CorporateAid\Commerce\Support\StorefrontController;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Storefront message center.
 *
 * Five endpoints:
 *
 *   GET  /account/messages                   index
 *   POST /account/messages/mark-read         markRead
 *   POST /account/messages/mark-all-read     markAllRead
 *   GET  /account/messages/preferences       preferences
 *   POST /account/messages/preferences       savePreferences
 *
 * Every endpoint requires a logged-in customer. The message
 * center is not available to guests - they have no persistent
 * identity to receive notifications against.
 *
 * See docs/stages/008g.3_message-center-plan.md.
 */
final class MessageController extends StorefrontController
{
    /**
     * Category whitelist for the tab filter. Anything else
     * falls back to 'system'.
     *
     * @var array<int, string>
     */
    private const CATEGORIES = ['system', 'order', 'promotion'];

    // =================================================================
    // Show
    // =================================================================

    /**
     * The message center page. Shows the requested tab.
     *
     * @param array<string,string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $customer = $customerAuth->user();
        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $notifications = $this->container->get(CustomerNotificationService::class);
        $session       = $this->container->get(Session::class);

        $tab = (string) $request->query('tab', 'system');
        if (!in_array($tab, self::CATEGORIES, true)) {
            $tab = 'system';
        }

        return $this->renderAccount('storefront.account.messages', [
            'title'             => 'Messages',
            'customer'          => $customer,
            'tab'               => $tab,
            'messages'          => $notifications->listFor($customer->id, $tab),
            'unreadCount'       => $notifications->unreadCount($customer->id),
            'counts'            => $notifications->countsByCategory($customer->id),
            'unreadCounts'      => $notifications->unreadCountsByCategory($customer->id),
            'flashOk'           => $session->getFlash('message_success'),
        ]);
    }

    // =================================================================
    // Mark read
    // =================================================================

    /**
     * Mark a single notification read. Form POST only. Redirects
     * back to the same tab.
     *
     * @param array<string,string> $params
     */
    public function markRead(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $customer = $customerAuth->user();
        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $notificationId = (int) $request->input('notification_id', 0);
        $tab = $this->safeTab((string) $request->input('tab', 'system'));

        if ($notificationId > 0) {
            $this->container->get(CustomerNotificationService::class)
                ->markRead($notificationId, $customer->id);
        }

        return Response::redirect('/account/messages?tab=' . $tab);
    }

    /**
     * Mark every unread notification read. Form POST only.
     * Redirects back to the same tab with a flash.
     *
     * @param array<string,string> $params
     */
    public function markAllRead(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $customer = $customerAuth->user();
        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $tab   = $this->safeTab((string) $request->input('tab', 'system'));
        $count = $this->container->get(CustomerNotificationService::class)
            ->markAllRead($customer->id);

        $session = $this->container->get(Session::class);
        $session->flash(
            'message_success',
            $count === 0
                ? 'Nothing to mark read.'
                : 'Marked ' . $count . ' ' . ($count === 1 ? 'message' : 'messages') . ' as read.'
        );

        return Response::redirect('/account/messages?tab=' . $tab);
    }

    // =================================================================
    // Preferences
    // =================================================================

    /**
     * The preferences page.
     *
     * @param array<string,string> $params
     */
    public function preferences(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $customer = $customerAuth->user();
        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $session = $this->container->get(Session::class);

        return $this->renderAccount('storefront.account.message-preferences', [
            'title'    => 'Message Preferences',
            'customer' => $customer,
            'flashOk'  => $session->getFlash('prefs_success'),
        ]);
    }

    /**
     * Save preferences. Form POST only.
     *
     * @param array<string,string> $params
     */
    public function savePreferences(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $customer = $customerAuth->user();
        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $orderUpdates = (bool) $request->input('notify_order_updates', false);
        $promotions   = (bool) $request->input('notify_promotions', false);

        $this->container->get(CustomerRepository::class)
            ->updateNotificationPrefs($customer->id, $orderUpdates, $promotions);

        $session = $this->container->get(Session::class);
        $session->flash('prefs_success', 'Preferences saved.');

        return Response::redirect('/account/messages/preferences');
    }

    // =================================================================
    // Internals
    // =================================================================

    private function safeTab(string $tab): string
    {
        return in_array($tab, self::CATEGORIES, true) ? $tab : 'system';
    }

    /**
     * Render a page inside the account shell.
     *
     * @param array<string,mixed> $data
     */
    private function renderAccount(string $view, array $data): Response
    {
        $data['_account_inner_view'] = $view;
        return parent::render('storefront.account._shell', $data);
    }
}
