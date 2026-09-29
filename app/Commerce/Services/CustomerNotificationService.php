<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Models\Customer;
use CorporateAid\Commerce\Models\CustomerNotification;
use CorporateAid\Commerce\Repositories\CustomerNotificationRepository;
use CorporateAid\Commerce\Repositories\CustomerRepository;

/**
 * Customer notification service.
 *
 * The sole producer for commerce_customer_notifications. No
 * code outside this service writes to the table directly.
 * Same discipline as the Platform NotificationService, which
 * is the sole producer for core_notifications.
 *
 * Every public write method is defensive. Failures are
 * swallowed. A broken notifier must never break the caller -
 * registration must succeed even if the welcome message
 * cannot be written, checkout must succeed even if the order
 * confirmation cannot be written.
 *
 * Preferences: the customer carries two boolean columns,
 * notify_order_updates and notify_promotions. This service
 * reads them before delivering the matching category. System
 * messages always deliver - no preference gates them, because
 * they are operational (welcome, password change, policy
 * updates), not marketing.
 *
 * See docs/stages/008g.3_message-center-plan.md.
 */
final class CustomerNotificationService
{
    public function __construct(
        private CustomerNotificationRepository $notifications,
        private CustomerRepository $customers,
    ) {
    }

    // =================================================================
    // Producers
    // =================================================================

    /**
     * Deliver a notification to a customer.
     *
     * The base method every other producer routes through. It
     * does not check preferences - preference gating is done by
     * the callers that deliver preference-gated categories
     * (orderPlaced for order_updates, a future promo method for
     * promotions).
     *
     * Returns the new notification ID, or 0 on failure.
     */
    public function notify(
        int $customerId,
        string $category,
        string $title,
        ?string $body = null,
        ?string $link = null
    ): int {
        if ($customerId <= 0 || $title === '') {
            return 0;
        }

        $allowed = ['system', 'order', 'promotion'];
        if (!in_array($category, $allowed, true)) {
            return 0;
        }

        try {
            return $this->notifications->create(
                $customerId,
                $category,
                $title,
                $body,
                $link
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Welcome message. Fires once on customer registration.
     * Category 'system' - always delivered, no preference gate.
     */
    public function welcome(int $customerId): int
    {
        return $this->notify(
            $customerId,
            'system',
            'Welcome to the store',
            'Thanks for creating an account. You can track orders, save products to your wishlist, and manage your addresses from your account.',
            '/account'
        );
    }

    /**
     * Order placed notification. Fires from CheckoutService
     * after the order and its initial status history row are
     * written.
     *
     * Respects the customer's notify_order_updates preference.
     * If the preference is off, nothing is delivered and the
     * method returns 0.
     *
     * The link points to the customer's order detail page.
     */
    public function orderPlaced(int $customerId, string $orderNumber, int $orderId): int
    {
        if ($customerId <= 0 || $orderNumber === '') {
            return 0;
        }

        if (!$this->wantsOrderUpdates($customerId)) {
            return 0;
        }

        // The customer account order detail route is
        // /account/orders/{orderNumber}. The orderId is passed
        // for future reference but the URL uses the number -
        // it is the stable public identifier.
        $link = '/account/orders/' . rawurlencode($orderNumber);

        return $this->notify(
            $customerId,
            'order',
            'Order ' . $orderNumber . ' received',
            'We have received your order. You can track its status from your account.',
            $link
        );
    }

    /**
     * Generic system message. Used by admin-authored
     * announcements and by any operational event that does not
     * belong to a customer action (password change
     * confirmation, email verification, and similar).
     *
     * Category 'system' - always delivered.
     */
    public function systemMessage(
        int $customerId,
        string $title,
        ?string $body = null,
        ?string $link = null
    ): int {
        return $this->notify($customerId, 'system', $title, $body, $link);
    }

    // =================================================================
    // Reads
    // =================================================================

    /**
     * @return array<int, CustomerNotification>
     */
    public function listFor(int $customerId, ?string $category = null): array
    {
        if ($customerId <= 0) {
            return [];
        }
        return $this->notifications->listFor($customerId, $category);
    }

    public function unreadCount(int $customerId): int
    {
        if ($customerId <= 0) {
            return 0;
        }
        return $this->notifications->unreadCount($customerId);
    }

    /**
     * @return array<string, int>
     */
    public function countsByCategory(int $customerId): array
    {
        if ($customerId <= 0) {
            return ['system' => 0, 'order' => 0, 'promotion' => 0];
        }
        return $this->notifications->countsByCategory($customerId);
    }

    /**
     * Most recent unread notifications for the customer. Used
     * by the header bell dropdown for a quick snapshot.
     *
     * @return array<int, CustomerNotification>
     */
    public function unreadFor(int $customerId, int $limit = 5): array
    {
        if ($customerId <= 0) {
            return [];
        }
        return $this->notifications->unreadFor($customerId, $limit);
    }

    /**
     * @return array<string, int>
     */
    public function unreadCountsByCategory(int $customerId): array
    {
        if ($customerId <= 0) {
            return ['system' => 0, 'order' => 0, 'promotion' => 0];
        }
        return $this->notifications->unreadCountsByCategory($customerId);
    }

    // =================================================================
    // Mutations on existing notifications
    // =================================================================

    public function markRead(int $notificationId, int $customerId): bool
    {
        if ($notificationId <= 0 || $customerId <= 0) {
            return false;
        }
        return $this->notifications->markRead($notificationId, $customerId);
    }

    public function markAllRead(int $customerId): int
    {
        if ($customerId <= 0) {
            return 0;
        }
        return $this->notifications->markAllRead($customerId);
    }

    // =================================================================
    // Preference helpers
    // =================================================================

    /**
     * Whether the customer wants order-update notifications.
     * Defaults to true if the customer row cannot be loaded.
     * Defaulting to true means a broken read does not silently
     * suppress order notifications - the customer would rather
     * see an extra message than miss an order confirmation.
     */
    private function wantsOrderUpdates(int $customerId): bool
    {
        try {
            $customer = $this->customers->findById($customerId);
            if ($customer === null) {
                return true;
            }
            return $customer->notifyOrderUpdates;
        } catch (\Throwable) {
            return true;
        }
    }
}
