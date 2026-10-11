<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Core\Database;
use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Exceptions\NewsletterException;
use CorporateAid\Commerce\Models\Subscriber;
use CorporateAid\Commerce\Repositories\SubscriberListMemberRepository;
use CorporateAid\Commerce\Repositories\SubscriberListRepository;
use CorporateAid\Commerce\Repositories\SubscriberRepository;
use CorporateAid\Commerce\Repositories\StoreRepository;
use CorporateAid\Commerce\Repositories\StoreSettingsRepository;
use CorporateAid\Platform\Support\EmailService;
use CorporateAid\Platform\Support\SignedToken;

/**
 * Newsletter subscription workflow.
 *
 * Two entry points, both driven by the storefront:
 *
 *   subscribe()  - captures the email + consent evidence,
 *                  writes a pending subscriber, and either
 *                  sends the double opt-in email (if the
 *                  store requires it) or confirms immediately.
 *   confirm()    - the tokenised link from the opt-in email.
 *
 * Two exit points:
 *
 *   unsubscribe()    - user-initiated, from a campaign email
 *                      or the account preferences page.
 *   suppress()       - system-initiated, on a hard bounce or
 *                      three consecutive soft bounces.
 *
 * The service is token-agnostic. Every state transition is
 * keyed on a subscriber id. Token verification happens in the
 * controller.
 *
 * See docs/stages/008i_newsletter-plan.md.
 */
final class SubscriberService
{
    private const CONFIRM_TTL_SECONDS = 86400; // 24 hours
    private const MAX_SOFT_BOUNCES = 3;

    public function __construct(
        private Database $db,
        private StoreContext $storeContext,
        private SubscriberRepository $subscribers,
        private SubscriberListRepository $lists,
        private SubscriberListMemberRepository $members,
        private StoreSettingsRepository $settings,
        private StoreRepository $stores,
        private EmailService $mail,
        private SignedToken $tokens,
    ) {
    }

    /**
     * Subscribe an email address.
     *
     * Idempotent on the email: a second subscribe with the same
     * address either re-sends the confirmation (if still
     * pending) or is a no-op (if already confirmed).
     *
     * @param array<string,mixed> $consent { source, ip, user_agent }
     * @return array{
     *   ok:bool, subscriber:?Subscriber,
     *   status:string, message:string
     * }
     */
    public function subscribe(
        string $email,
        ?string $name,
        array $consent,
        ?int $listId = null
    ): array {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new NewsletterException('Please enter a valid email address.');
        }

        $list = $listId !== null
            ? $this->lists->findById($listId)
            : $this->lists->defaultForStore();

        if ($list === null) {
            throw new NewsletterException('The newsletter list is not available.');
        }

        $existing = $this->subscribers->findByEmail($email);

        if ($existing !== null) {
            // Already confirmed: idempotent no-op.
            if ($existing->isConfirmed()) {
                $this->members->addMember($existing->id, $list->id);
                return [
                    'ok'        => true,
                    'subscriber' => $existing,
                    'status'    => 'confirmed',
                    'message'   => 'You are already subscribed.',
                ];
            }

            // Unsubscribed or suppressed: refuse. The user
            // must actively resubscribe (which triggers a new
            // confirm flow), not just submit the form again.
            if ($existing->isUnsubscribed() || $existing->isSuppressed()) {
                return [
                    'ok'        => false,
                    'subscriber' => $existing,
                    'status'    => $existing->status,
                    'message'   => 'This address is not receiving our newsletter. ' .
                                   'Contact us if you would like to re-subscribe.',
                ];
            }

            // Pending: refresh the token and resend the email.
            // The confirmation window restarts.
            return $this->sendConfirmation($existing, $list->id);
        }

        // Fresh subscribe.
        $requiresDoubleOptIn = $this->settings->bool('newsletter.double_opt_in', true);

        $now = date('Y-m-d H:i:s');
        $subscriberId = $this->subscribers->create([
            'email'              => $email,
            'name'               => $name,
            'status'             => 'pending',
            'consent_source'     => (string) ($consent['source'] ?? ''),
            'consent_ip'         => (string) ($consent['ip'] ?? ''),
            'consent_user_agent' => (string) ($consent['user_agent'] ?? ''),
            'consent_at'         => $now,
        ]);

        $this->members->addMember($subscriberId, $list->id);

        $subscriber = $this->subscribers->findById($subscriberId);
        if ($subscriber === null) {
            throw new NewsletterException('Could not create the subscription.');
        }

        if (!$requiresDoubleOptIn) {
            // Single opt-in (non-German store). Confirm
            // immediately. No email sent.
            $this->subscribers->update($subscriber->id, [
                'status'       => 'confirmed',
                'confirmed_at' => $now,
            ]);
            $subscriber->status      = 'confirmed';
            $subscriber->confirmedAt = $now;

            return [
                'ok'        => true,
                'subscriber' => $subscriber,
                'status'    => 'confirmed',
                'message'   => 'You are subscribed.',
            ];
        }

        return $this->sendConfirmation($subscriber, $list->id);
    }

    /**
     * Confirm a pending subscriber by id. Called by the
     * controller after the token has been verified.
     */
    public function confirm(int $subscriberId): ?Subscriber
    {
        $subscriber = $this->subscribers->findById($subscriberId);
        if ($subscriber === null) {
            return null;
        }

        if ($subscriber->isConfirmed()) {
            return $subscriber;
        }

        if (!$subscriber->isPending()) {
            // Unsubscribed or suppressed. Do not resurrect on
            // a stale click.
            return null;
        }

        $now = date('Y-m-d H:i:s');
        $this->subscribers->update($subscriber->id, [
            'status'              => 'confirmed',
            'confirmed_at'        => $now,
            'confirmation_token'  => null,
            'soft_bounce_count'   => 0,
        ]);

        return $this->subscribers->findById($subscriber->id);
    }

    /**
     * Unsubscribe by subscriber id. User-initiated.
     *
     * Removes the subscriber from every list. Keeps the row
     * so the record of consent-and-withdrawal is preserved.
     */
    public function unsubscribe(int $subscriberId, ?string $reason = null): bool
    {
        $subscriber = $this->subscribers->findById($subscriberId);
        if ($subscriber === null) return false;

        $this->subscribers->update($subscriber->id, [
            'status'             => 'unsubscribed',
            'unsubscribed_at'    => date('Y-m-d H:i:s'),
            'unsubscribe_reason' => $reason,
        ]);

        $this->members->removeAllForSubscriber($subscriber->id);

        return true;
    }

    /**
     * Suppress by subscriber id. System-initiated.
     *
     * Called on a hard bounce or after the third consecutive
     * soft bounce. Distinct from unsubscribe so the audit
     * trail shows the difference.
     */
    public function suppress(int $subscriberId, string $reason): bool
    {
        $subscriber = $this->subscribers->findById($subscriberId);
        if ($subscriber === null) return false;

        $this->subscribers->update($subscriber->id, [
            'status'             => 'suppressed',
            'unsubscribed_at'    => date('Y-m-d H:i:s'),
            'unsubscribe_reason' => $reason,
        ]);

        $this->members->removeAllForSubscriber($subscriber->id);

        return true;
    }

    /**
     * Increment the soft bounce counter. At the configured
     * threshold (3), moves the subscriber to suppressed.
     *
     * Returns the resulting subscriber (with its new status)
     * so the caller can act accordingly.
     */
    public function recordSoftBounce(int $subscriberId): ?Subscriber
    {
        $subscriber = $this->subscribers->findById($subscriberId);
        if ($subscriber === null) return null;

        $next = $subscriber->softBounceCount + 1;

        if ($next >= self::MAX_SOFT_BOUNCES) {
            $this->suppress($subscriber->id, 'Repeated soft bounces');
            return $this->subscribers->findById($subscriber->id);
        }

        $this->subscribers->update($subscriber->id, [
            'soft_bounce_count' => $next,
        ]);

        return $this->subscribers->findById($subscriber->id);
    }

    /**
     * Reset the soft bounce counter. Called by the queue
     * worker after a successful send.
     */
    public function resetSoftBounce(int $subscriberId): void
    {
        $this->subscribers->update($subscriberId, ['soft_bounce_count' => 0]);
    }

    /**
     * The tenant's display name for outbound emails.
     */
    private function storeName(): string
    {
        $storeId = $this->storeContext->id();
        $store   = $this->stores->findById($storeId);
        if ($store === null) return 'Store';
        return $store->name !== '' ? $store->name : 'Store';
    }

    /**
     * Send the double opt-in confirmation email and store the
     * token on the subscriber row.
     *
     * @return array{ok:bool, subscriber:?Subscriber, status:string, message:string}
     */
    private function sendConfirmation(Subscriber $subscriber, int $listId): array
    {
        $token = $this->tokens->sign(
            ['id' => $subscriber->id, 'scope' => 'confirm'],
            self::CONFIRM_TTL_SECONDS
        );

        $this->subscribers->update($subscriber->id, [
            'confirmation_token'   => $token,
            'confirmation_sent_at' => date('Y-m-d H:i:s'),
            'status'               => 'pending',
        ]);

        $this->members->addMember($subscriber->id, $listId);

        $storeName = $this->storeName();
        $urlBase   = (string) config('app.url', '');
        $confirmUrl = rtrim($urlBase, '/') . '/newsletter/confirm/' . urlencode($token);

        $this->mail->sendTemplate(
            [$subscriber->email => $subscriber->name ?? $subscriber->email],
            'Confirm your subscription to ' . $storeName,
            'newsletter/opt-in-confirmation',
            [
                'storeName'       => $storeName,
                'confirmUrl'      => $confirmUrl,
                'subscriberEmail' => $subscriber->email,
                'unsubscribeUrl'  => '',
            ]
        );

        $subscriber = $this->subscribers->findById($subscriber->id);

        return [
            'ok'        => true,
            'subscriber' => $subscriber,
            'status'    => 'pending',
            'message'   => 'Check your inbox to confirm your subscription.',
        ];
    }
}
