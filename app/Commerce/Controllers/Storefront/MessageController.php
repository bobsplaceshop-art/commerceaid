<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Commerce\Repositories\CustomerRepository;
use CorporateAid\Commerce\Services\CustomerAuthService;
use CorporateAid\Commerce\Services\CustomerNotificationService;
use CorporateAid\Commerce\Services\SubscriberService;
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

        // Current newsletter subscription state. Read from
        // commerce_subscribers via the service; the
        // preferences page does not write to that table.
        $newsletterState = $this->container->get(SubscriberService::class)
            ->stateFor($customer->email);

        return $this->renderAccount('storefront.account.message-preferences', [
            'title'             => 'Message Preferences',
            'customer'          => $customer,
            'flashOk'           => $session->getFlash('prefs_success'),
            'flashError'        => $session->getFlash('prefs_error'),
            'newsletterStatus'  => $newsletterState['status'],
            'newsletterToggleable' => $newsletterState['status'] !== 'suppressed',
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

        // Newsletter subscription. This writes to
        // commerce_subscribers, not commerce_customers; the
        // two are distinct systems on purpose.
        $newsletterResult = $this->applyNewsletterPreference(
            $customer->email,
            (bool) $request->input('notify_newsletter', false)
        );

        $session = $this->container->get(Session::class);
        if ($newsletterResult['ok']) {
            $session->flash('prefs_success', 'Preferences saved.');
        } else {
            $session->flash('prefs_error', $newsletterResult['message']);
        }

        return Response::redirect('/account/messages/preferences');
    }

    /**
     * Apply the newsletter toggle.
     *
     *   on,  no subscriber or unsubscribed  -> resubscribe()
     *   on,  pending                        -> no-op
     *   on,  confirmed                      -> no-op
     *   on,  suppressed                     -> refuse
     *   off, pending or confirmed           -> unsubscribe()
     *                                          + acknowledgement
     *                                          email
     *   off, unsubscribed or none           -> no-op
     *
     * Returns a result the caller can surface as a flash:
     *   ok = true  -> 'Preferences saved.'
     *   ok = false -> the service's message
     *
     * @return array{ok:bool, message:string}
     */
    private function applyNewsletterPreference(string $email, bool $enabled): array
    {
        $service = $this->container->get(SubscriberService::class);
        $state   = $service->stateFor($email);
        $status  = $state['status'];

        if ($enabled) {
            if ($status === 'confirmed' || $status === 'pending') {
                return ['ok' => true, 'message' => 'Preferences saved.'];
            }
            if ($status === 'suppressed') {
                return [
                    'ok'      => false,
                    'message' => 'This address is suppressed and cannot be re-subscribed. '
                               . 'Contact support if this is a mistake.',
                ];
            }

            // Unsubscribed or no row. resubscribe() handles
            // both: it delegates to subscribe() when there
            // is no row, and re-opens an unsubscribed row
            // with a fresh consent record.
            $result = $service->resubscribe($email, [
                'source'     => 'account',
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);

            return [
                'ok'      => (bool) $result['ok'],
                'message' => (string) $result['message'],
            ];
        }

        // Toggle off. Only act if the subscriber is in a
        // state where the toggle is meaningful.
        if ($status === 'confirmed' || $status === 'pending') {
            $subscriber = $state['subscriber'];
            if ($subscriber !== null) {
                $service->unsubscribe(
                    $subscriber->id,
                    'Unsubscribed from account preferences'
                );
                $this->sendUnsubscribeAcknowledgement($subscriber);
            }
        }

        return ['ok' => true, 'message' => 'Preferences saved.'];
    }

    /**
     * Send the unsubscribe acknowledgement email. Same
     * template the token-based unsubscribe uses. Kept
     * private to this controller; it is only called from
     * the authenticated preferences flow.
     */
    private function sendUnsubscribeAcknowledgement(
        \CorporateAid\Commerce\Models\Subscriber $subscriber
    ): void {
        try {
            $storeContext = $this->container->get(
                \CorporateAid\Commerce\Context\StoreContext::class
            );
            $stores = $this->container->get(
                \CorporateAid\Commerce\Repositories\StoreRepository::class
            );
            $store = $stores->findById($storeContext->id());
            $storeName = $store !== null && $store->name !== ''
                            ? $store->name
                            : 'Store';

            $urlBase = rtrim((string) config('app.url', ''), '/');

            $this->container->get(\CorporateAid\Platform\Support\EmailService::class)
                ->sendTemplate(
                    [$subscriber->email => $subscriber->name ?? $subscriber->email],
                    'You have been unsubscribed from ' . $storeName,
                    'newsletter/unsubscribe-confirm',
                    [
                        'storeName'       => $storeName,
                        'subscriberEmail' => $subscriber->email,
                        'resubscribeUrl'  => $urlBase . '/#newsletter',
                    ]
                );
        } catch (\Throwable $e) {
            // The unsubscribe itself succeeded; the
            // acknowledgement email is best-effort. Log
            // and continue.
            $this->container->get(\CorporateAid\Platform\Support\Logger::class)
                ->error('Unsubscribe acknowledgement email failed', [
                    'error' => $e->getMessage(),
                ]);
        }
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
