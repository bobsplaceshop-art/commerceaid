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
use CorporateAid\Platform\Support\RateLimiter;
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
    private const CONFIRM_TTL_SECONDS     = 86400;   // 24 hours
    private const UNSUBSCRIBE_TTL_SECONDS = 7776000; // 90 days
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
        private RateLimiter $limiter,
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
     * Read the current subscription state for an email
     * address without changing it. Used by the account
     * preferences page to render the newsletter toggle.
     *
     * Returns 'none' when no subscriber row exists at all.
     * That state is distinct from 'unsubscribed' because a
     * customer who has never signed up and a customer who
     * has opted out are shown different copy.
     *
     * @return array{status:string, subscriber:?Subscriber}
     */
    public function stateFor(string $email): array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return ['status' => 'none', 'subscriber' => null];
        }

        $subscriber = $this->subscribers->findByEmail($email);
        if ($subscriber === null) {
            return ['status' => 'none', 'subscriber' => null];
        }

        return [
            'status'     => $subscriber->status,
            'subscriber' => $subscriber,
        ];
    }

    /**
     * Re-subscribe an existing subscriber who is currently
     * unsubscribed. Called only from the authenticated
     * account preferences toggle - never from a public
     * route.
     *
     * This is deliberately separate from subscribe(). The
     * public subscribe() path refuses to re-subscribe an
     * unsubscribed address, because a public form can be
     * filled in by anyone: allowing it would let a third
     * party undo a subscriber's opt-out. The account
     * preferences path is different - the customer is
     * authenticated and toggling their own account - so the
     * refusal does not apply.
     *
     * Suppressed addresses cannot be re-subscribed even
     * here. A suppression is a hard bounce or a spam
     * complaint; the operator must clear it deliberately.
     *
     * @param array<string,mixed> $consent { source, ip, user_agent }
     * @return array{ok:bool, subscriber:?Subscriber, status:string, message:string}
     */
    public function resubscribe(string $email, array $consent): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new NewsletterException('Please enter a valid email address.');
        }

        $existing = $this->subscribers->findByEmail($email);

        // No row: this is not a re-subscribe, it is a fresh
        // subscribe. Delegate.
        if ($existing === null) {
            return $this->subscribe($email, null, $consent, null);
        }

        // Already subscribed or already waiting on a
        // confirmation. Nothing to do; the row is in the
        // right state.
        if ($existing->isConfirmed() || $existing->isPending()) {
            return [
                'ok'         => true,
                'subscriber' => $existing,
                'status'     => $existing->status,
                'message'    => $existing->isConfirmed()
                                    ? 'You are already subscribed.'
                                    : 'Check your inbox to confirm your subscription.',
            ];
        }

        // Suppressed: refuse. The operator must clear it.
        if ($existing->isSuppressed()) {
            return [
                'ok'         => false,
                'subscriber' => $existing,
                'status'     => 'suppressed',
                'message'    => 'This address cannot be re-subscribed automatically. '
                              . 'Contact support if this is a mistake.',
            ];
        }

        // Unsubscribed: re-open. The consent evidence is
        // refreshed because the customer is giving consent
        // again, now. The old consent record is not erased;
        // consent_at is updated, and the flow records the
        // new moment of consent.
        $list = $this->lists->defaultForStore();
        if ($list === null) {
            throw new NewsletterException('The newsletter list is not available.');
        }

        $requiresDoubleOptIn = $this->settings->bool('newsletter.double_opt_in', true);
        $now = date('Y-m-d H:i:s');

        if (!$requiresDoubleOptIn) {
            $this->subscribers->update($existing->id, [
                'status'             => 'confirmed',
                'confirmed_at'       => $now,
                'unsubscribed_at'    => null,
                'unsubscribe_reason' => null,
                'consent_source'     => (string) ($consent['source'] ?? 'account'),
                'consent_ip'         => (string) ($consent['ip'] ?? ''),
                'consent_user_agent' => (string) ($consent['user_agent'] ?? ''),
                'consent_at'         => $now,
            ]);

            $this->members->addMember($existing->id, $list->id);

            $fresh = $this->subscribers->findById($existing->id);

            return [
                'ok'         => true,
                'subscriber' => $fresh,
                'status'     => 'confirmed',
                'message'    => 'You are subscribed.',
            ];
        }

        // Double opt-in: reopen, clear the unsubscribe
        // timestamp, and send a fresh confirmation email.
        // sendConfirmation() writes the token and updates
        // status to 'pending'.
        $this->subscribers->update($existing->id, [
            'unsubscribed_at'    => null,
            'unsubscribe_reason' => null,
            'consent_source'     => (string) ($consent['source'] ?? 'account'),
            'consent_ip'         => (string) ($consent['ip'] ?? ''),
            'consent_user_agent' => (string) ($consent['user_agent'] ?? ''),
            'consent_at'         => $now,
        ]);

        $refreshed = $this->subscribers->findById($existing->id);
        if ($refreshed === null) {
            throw new NewsletterException('Could not update the subscription.');
        }

        return $this->sendConfirmation($refreshed, $list->id);
    }

    /**
     * Sign an unsubscribe token for a subscriber and return
     * the full unsubscribe URL. Called by the campaign queue
     * when rendering the campaign email, and by the
     * preferences page when showing the current state.
     *
     * The token is scoped 'unsubscribe' and expires in 90
     * days. Unsubscribe tokens are long-lived because a
     * subscriber may open an old campaign months after it
     * was sent.
     */
    public function unsubscribeUrlFor(int $subscriberId): string
    {
        $token = $this->tokens->sign(
            ['id' => $subscriberId, 'scope' => 'unsubscribe'],
            self::UNSUBSCRIBE_TTL_SECONDS
        );

        $urlBase = rtrim((string) config('app.url', ''), '/');

        return $urlBase . '/newsletter/unsubscribe/' . urlencode($token);
    }

    /**
     * Confirm a subscriber by signed token. The token is the
     * only authorisation. The controller passes the raw token
     * from the URL and receives a result the view can render
     * without further branching.
     *
     * Returns ok = false on any failure: malformed token,
     * wrong scope, expired, missing subscriber, or a
     * subscriber that is not in 'pending' state. The caller
     * is expected to render the same neutral message for
     * every failure.
     *
     * @return array{ok:bool, message:string, subscriber:?Subscriber}
     */
    public function confirmByToken(string $token): array
    {
        $claims = $this->tokens->verify($token, 'confirm');
        if ($claims === null) {
            return [
                'ok'         => false,
                'subscriber' => null,
                'message'    => 'This confirmation link is no longer valid.',
            ];
        }

        $subscriberId = (int) ($claims['id'] ?? 0);
        if ($subscriberId <= 0) {
            return [
                'ok'         => false,
                'subscriber' => null,
                'message'    => 'This confirmation link is no longer valid.',
            ];
        }

        $subscriber = $this->confirm($subscriberId);
        if ($subscriber === null) {
            return [
                'ok'         => false,
                'subscriber' => null,
                'message'    => 'This confirmation link is no longer valid.',
            ];
        }

        return [
            'ok'         => true,
            'subscriber' => $subscriber,
            'message'    => 'Thank you. Your subscription is confirmed.',
        ];
    }

    /**
     * Unsubscribe a subscriber by signed token, and send the
     * acknowledgement email.
     *
     * Idempotent on an already-unsubscribed subscriber: the
     * second call still returns ok = true. That way a
     * mail-scanner double-click does not flip the page to an
     * error.
     *
     * @return array{ok:bool, message:string, subscriber:?Subscriber}
     */
    public function unsubscribeByToken(string $token, ?string $reason = null): array
    {
        $claims = $this->tokens->verify($token, 'unsubscribe');
        if ($claims === null) {
            return [
                'ok'         => false,
                'subscriber' => null,
                'message'    => 'This unsubscribe link is no longer valid.',
            ];
        }

        $subscriberId = (int) ($claims['id'] ?? 0);
        if ($subscriberId <= 0) {
            return [
                'ok'         => false,
                'subscriber' => null,
                'message'    => 'This unsubscribe link is no longer valid.',
            ];
        }

        $subscriber = $this->subscribers->findById($subscriberId);
        if ($subscriber === null) {
            return [
                'ok'         => false,
                'subscriber' => null,
                'message'    => 'This unsubscribe link is no longer valid.',
            ];
        }

        $alreadyUnsubscribed = $subscriber->isUnsubscribed();

        // Only perform the state change if the subscriber is
        // not already unsubscribed. This keeps the audit trail
        // honest: unsubscribed_at is the moment the user
        // actually unsubscribed, not the moment a scanner
        // re-clicked the link.
        if (!$alreadyUnsubscribed) {
            $this->unsubscribe($subscriberId, $reason);
        }

        // Send the acknowledgement email only on the first
        // unsubscribe, not on subsequent hits.
        if (!$alreadyUnsubscribed) {
            $fresh = $this->subscribers->findById($subscriberId);
            if ($fresh !== null) {
                $storeName = $this->storeName();
                $urlBase   = rtrim((string) config('app.url', ''), '/');

                $this->mail->sendTemplate(
                    [$fresh->email => $fresh->name ?? $fresh->email],
                    'You have been unsubscribed from ' . $storeName,
                    'newsletter/unsubscribe-confirm',
                    [
                        'storeName'       => $storeName,
                        'subscriberEmail' => $fresh->email,
                        'resubscribeUrl'  => $urlBase . '/#newsletter',
                    ]
                );
            }
        }

        $result = $this->subscribers->findById($subscriberId);

        return [
            'ok'         => true,
            'subscriber' => $result,
            'message'    => 'You have been unsubscribed. You will receive no further campaigns.',
        ];
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
        // Per-email rate limit. The IP-level limiter on the
        // route stops a single-source burst. This second
        // layer stops a distributed attack (botnet, one IP
        // per request) that would otherwise send unlimited
        // confirmation emails to a single victim address.
        //
        // Key: sha256(email), which does not reveal the
        // address in the rate-limit table and is stable
        // across requests.
        //
        // Cap: 3 sends per 24 hours. Far below what any
        // legitimate user needs; a victim of a subscription
        // bomb sees at most three emails from this store,
        // regardless of how many distinct IPs submit the
        // form.
        //
        // When the cap is hit, the response is identical to
        // a successful send. The attacker learns nothing.
        // The subscriber keeps its existing token and
        // remains pending; the store simply does not send
        // another confirmation.
        $emailKey = 'newsletter_confirm:' . hash('sha256', $subscriber->email);
        $state    = $this->limiter->check($emailKey, 'global', 3, 1440);

        if (!$state['allowed']) {
            return [
                'ok'         => true,
                'subscriber' => $subscriber,
                'status'     => 'pending',
                'message'    => 'Check your inbox to confirm your subscription.',
            ];
        }

        $this->limiter->hit($emailKey, 'global', 1440);

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
