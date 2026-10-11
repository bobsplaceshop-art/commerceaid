<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Core\Database;
use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Models\EmailQueueEntry;
use CorporateAid\Commerce\Repositories\EmailCampaignRepository;
use CorporateAid\Commerce\Repositories\EmailEventRepository;
use CorporateAid\Commerce\Repositories\EmailQueueRepository;
use CorporateAid\Commerce\Repositories\StoreRepository;
use CorporateAid\Commerce\Repositories\StoreSettingsRepository;
use CorporateAid\Platform\Support\EmailService;

/**
 * The queue drain worker.
 *
 * One public entry point: drain(). Called from the CLI
 * script bin/send-emails.php. Takes a batch of pending
 * rows, sends each one through EmailService, records a
 * 'sent' event, and handles failures with the retry /
 * bounce / suppression rules from the plan doc.
 *
 * Never throws on a per-row failure. A failed row is
 * recorded and the loop continues so a single dead
 * address cannot stall a campaign.
 *
 * See docs/stages/008i_newsletter-plan.md.
 */
final class QueueService
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private Database $db,
        private StoreContext $storeContext,
        private EmailQueueRepository $queue,
        private EmailCampaignRepository $campaigns,
        private EmailEventRepository $events,
        private StoreRepository $stores,
        private StoreSettingsRepository $settings,
        private EmailService $mail,
        private SubscriberService $subscribers,
        private CampaignService $campaignService,
    ) {
    }

    /**
     * Drain a batch of pending queue rows.
     *
     * @return array{
     *   claimed:int, sent:int, failed:int, retried:int,
     *   suppressed:int
     * }
     */
    public function drain(int $batch = 100): array
    {
        // The caller (bin/send-emails.php) sets the store
        // context before calling drain(). The repositories
        // resolve their store scope from the context, so no
        // store id argument is needed here.
        if (!$this->storeContext->has()) {
            throw new \RuntimeException(
                'QueueService::drain() called without a store context. '
                . 'The CLI worker must call StoreContext::set() first.'
            );
        }

        $storeId = $this->storeContext->id();

        $entries = $this->queue->claimBatch($storeId, $batch);

        $sent       = 0;
        $failed     = 0;
        $retried    = 0;
        $suppressed = 0;

        $rate = max(1, $this->settings->int('newsletter.rate_limit_per_minute', 60));
        $sleepMicros = (int) floor(60_000_000 / $rate);

        foreach ($entries as $entry) {
            $result = $this->sendOne($entry);

            if ($result === 'sent') {
                $sent++;
            } elseif ($result === 'retry') {
                $retried++;
            } elseif ($result === 'failed') {
                $failed++;
            } elseif ($result === 'suppressed') {
                $suppressed++;
            }

            // Rate limit between sends.
            usleep($sleepMicros);
        }

        return [
            'claimed'    => count($entries),
            'sent'       => $sent,
            'failed'     => $failed,
            'retried'    => $retried,
            'suppressed' => $suppressed,
        ];
    }

    /**
     * Send one queue row. Returns a status string:
     *   'sent'       - delivered to SMTP
     *   'retry'      - a transient failure, will retry
     *   'failed'     - exhausted attempts, marked failed
     *   'suppressed' - subscriber is now suppressed
     *
     * Never throws.
     */
    private function sendOne(EmailQueueEntry $entry): string
    {
        $campaign = $this->campaigns->findById($entry->campaignId);
        if ($campaign === null) {
            $this->queue->markAttemptFailed($entry->id, 'Campaign no longer exists', 1);
            return 'failed';
        }

        $storeName    = $this->storeName();
        $storeAddress = $this->storeAddress();
        $urlBase      = rtrim((string) config('app.url', ''), '/');
        $unsubUrl     = $urlBase . '/newsletter/unsubscribe/' . urlencode($entry->unsubscribeToken);

        try {
            $ok = $this->mail->sendTemplate(
                [$entry->toEmail => $entry->toName ?? $entry->toEmail],
                $campaign->subject,
                'newsletter/campaign',
                [
                    'storeName'       => $storeName,
                    'storeAddress'    => $storeAddress,
                    'subject'         => $campaign->subject,
                    'preheader'       => $campaign->preheader ?? '',
                    'bodyHtml'        => $campaign->bodyHtml,
                    'unsubscribeUrl'  => $unsubUrl,
                    'subscriberEmail' => $entry->toEmail,
                    'trackingPixelUrl' => null,
                ]
            );
        } catch (\Throwable $e) {
            $ok = false;
        }

        if ($ok) {
            $this->queue->markSent($entry->id);
            $this->events->record($entry->campaignId, $entry->subscriberId, 'sent');
            $this->subscribers->resetSoftBounce($entry->subscriberId);
            $this->campaignService->markSentIfComplete($entry->campaignId);
            return 'sent';
        }

        // Failure. Treat every failed send as a soft bounce
        // for now. Async hard-bounce detection (parsing a
        // bounce mailbox) is deferred - see the plan doc.
        $this->queue->markAttemptFailed($entry->id, 'SMTP rejected or unavailable', self::MAX_ATTEMPTS);

        $after = $this->subscribers->recordSoftBounce($entry->subscriberId);

        if ($after !== null && $after->isSuppressed()) {
            $this->events->record($entry->campaignId, $entry->subscriberId, 'bounce_hard');
            return 'suppressed';
        }

        $fresh = $this->queue->findById($entry->id);
        if ($fresh !== null && $fresh->status === 'failed') {
            $this->events->record($entry->campaignId, $entry->subscriberId, 'bounce_soft');
            return 'failed';
        }

        $this->events->record($entry->campaignId, $entry->subscriberId, 'bounce_soft');
        return 'retry';
    }

    /**
     * Called from a future CLI command to process a bounce
     * mailbox reply. Not yet wired.
     */
    public function recordHardBounce(int $subscriberId, string $reason): void
    {
        $this->subscribers->suppress($subscriberId, $reason);
    }

    private function storeName(): string
    {
        $store = $this->stores->findById($this->storeContext->id());
        return $store !== null && $store->name !== '' ? $store->name : 'Store';
    }

    /**
     * The physical address shown in the campaign footer.
     * Required by German UWG § 7 for marketing emails.
     * Read from the store setting; falls back to the store
     * legal name if the setting is empty.
     */
    private function storeAddress(): string
    {
        $address = trim($this->settings->string('newsletter.footer_address', ''));
        if ($address !== '') {
            return $address;
        }

        $store = $this->stores->findById($this->storeContext->id());
        if ($store === null || $store->legalName === null) {
            return '';
        }
        return (string) $store->legalName;
    }
}
