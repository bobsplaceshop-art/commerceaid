<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Core\Database;
use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Exceptions\NewsletterException;
use CorporateAid\Commerce\Models\EmailCampaign;
use CorporateAid\Commerce\Repositories\EmailCampaignRepository;
use CorporateAid\Commerce\Repositories\EmailEventRepository;
use CorporateAid\Commerce\Repositories\EmailQueueRepository;
use CorporateAid\Commerce\Repositories\StoreSettingsRepository;
use CorporateAid\Commerce\Repositories\SubscriberRepository;
use CorporateAid\Commerce\Repositories\SubscriberListRepository;
use CorporateAid\Platform\Support\SignedToken;

/**
 * Newsletter campaigns.
 *
 * A campaign targets one subscriber list. Sending enqueues
 * one row per confirmed subscriber with a fresh signed
 * unsubscribe token, then marks the campaign 'sending'.
 * The CLI worker drains the queue.
 *
 * See docs/stages/008i_newsletter-plan.md.
 */
final class CampaignService
{
    public function __construct(
        private Database $db,
        private StoreContext $storeContext,
        private EmailCampaignRepository $campaigns,
        private EmailQueueRepository $queue,
        private EmailEventRepository $events,
        private SubscriberRepository $subscribers,
        private SubscriberListRepository $lists,
        private StoreSettingsRepository $settings,
        private SignedToken $tokens,
    ) {
    }

    /**
     * Create a draft campaign.
     *
     * @param array<string,mixed> $data
     */
    public function createDraft(array $data, ?int $userId = null): int
    {
        $subject = trim((string) ($data['subject'] ?? ''));
        if ($subject === '') {
            throw new NewsletterException('A subject is required.');
        }

        $listId = (int) ($data['list_id'] ?? 0);
        $list   = $this->lists->findById($listId);
        if ($list === null) {
            throw new NewsletterException('The selected list was not found.');
        }

        $bodyHtml = (string) ($data['body_html'] ?? '');
        if (trim($bodyHtml) === '') {
            throw new NewsletterException('The email body cannot be empty.');
        }

        return $this->campaigns->create([
            'list_id'   => $list->id,
            'subject'   => $subject,
            'preheader' => $data['preheader'] ?? null,
            'body_html' => $bodyHtml,
            'body_text' => $data['body_text'] ?? null,
            'status'    => 'draft',
            'created_by' => $userId,
        ]);
    }

    /**
     * Update a draft campaign.
     *
     * @param array<string,mixed> $data
     */
    public function updateDraft(int $campaignId, array $data): void
    {
        $campaign = $this->campaigns->findById($campaignId);
        if ($campaign === null) {
            throw new NewsletterException('Campaign not found.');
        }
        if (!$campaign->canEdit()) {
            throw new NewsletterException('A campaign that has been sent cannot be edited.');
        }

        $patch = [];
        if (array_key_exists('subject', $data)) {
            $s = trim((string) $data['subject']);
            if ($s === '') throw new NewsletterException('A subject is required.');
            $patch['subject'] = $s;
        }
        if (array_key_exists('preheader', $data)) {
            $patch['preheader'] = $data['preheader'];
        }
        if (array_key_exists('body_html', $data)) {
            $b = (string) $data['body_html'];
            if (trim($b) === '') throw new NewsletterException('The email body cannot be empty.');
            $patch['body_html'] = $b;
        }
        if (array_key_exists('body_text', $data)) {
            $patch['body_text'] = $data['body_text'];
        }
        if (array_key_exists('list_id', $data)) {
            $lid = (int) $data['list_id'];
            $list = $this->lists->findById($lid);
            if ($list === null) throw new NewsletterException('The selected list was not found.');
            $patch['list_id'] = $list->id;
        }

        if ($patch !== []) {
            $this->campaigns->update($campaign->id, $patch);
        }
    }

    /**
     * Send a campaign. Enqueues one queue row per confirmed
     * subscriber in the target list, then flips the campaign
     * to 'sending'.
     *
     * @return array{enqueued:int}
     */
    public function send(int $campaignId): array
    {
        $campaign = $this->campaigns->findById($campaignId);
        if ($campaign === null) {
            throw new NewsletterException('Campaign not found.');
        }
        if (!$campaign->canSend()) {
            throw new NewsletterException('This campaign cannot be sent from its current status.');
        }

        $recipients = $this->subscribers->confirmedOnList($campaign->listId);
        if ($recipients === []) {
            throw new NewsletterException('The selected list has no confirmed subscribers.');
        }

        $tokenDays = max(1, $this->settings->int('newsletter.unsubscribe_token_days', 90));
        $ttlSeconds = $tokenDays * 86400;

        $entries = [];
        foreach ($recipients as $subscriber) {
            $token = $this->tokens->sign(
                [
                    'id'       => $subscriber->id,
                    'campaign' => $campaign->id,
                    'scope'    => 'unsubscribe',
                ],
                $ttlSeconds
            );

            $entries[] = [
                'campaign_id'       => $campaign->id,
                'subscriber_id'     => $subscriber->id,
                'to_email'          => $subscriber->email,
                'to_name'           => $subscriber->name,
                'unsubscribe_token' => $token,
            ];
        }

        $this->db->beginTransaction();
        try {
            $enqueued = $this->queue->enqueue($entries);
            $this->campaigns->update($campaign->id, ['status' => 'sending']);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return ['enqueued' => $enqueued];
    }

    /**
     * Mark a campaign sent. Called by the queue worker when
     * the last pending row for the campaign has drained.
     */
    public function markSentIfComplete(int $campaignId): void
    {
        $pending = $this->queue->countByStatusForCampaign($campaignId, 'pending')
                 + $this->queue->countByStatusForCampaign($campaignId, 'sending');

        if ($pending === 0) {
            $this->campaigns->markSent($campaignId);
        }
    }

    /**
     * Per-campaign stats: sent, failed, opens, clicks, bounces.
     *
     * @return array{sent:int, failed:int, pending:int, opens:int, clicks:int, bounces:int, unsubscribes:int}
     */
    public function stats(int $campaignId): array
    {
        return [
            'sent'         => $this->events->countByTypeForCampaign($campaignId, 'sent'),
            'failed'       => $this->queue->countByStatusForCampaign($campaignId, 'failed'),
            'pending'      => $this->queue->countByStatusForCampaign($campaignId, 'pending'),
            'opens'        => $this->events->countByTypeForCampaign($campaignId, 'open'),
            'clicks'       => $this->events->countByTypeForCampaign($campaignId, 'click'),
            'bounces'      => $this->events->countByTypeForCampaign($campaignId, 'bounce_hard')
                            + $this->events->countByTypeForCampaign($campaignId, 'bounce_soft'),
            'unsubscribes' => $this->events->countByTypeForCampaign($campaignId, 'unsubscribe'),
        ];
    }

    /**
     * Delete a draft or cancelled campaign.
     */
    public function deleteDraft(int $campaignId): bool
    {
        $campaign = $this->campaigns->findById($campaignId);
        if ($campaign === null) return false;
        if (!$campaign->canEdit()) return false;

        $this->campaigns->delete($campaign->id);
        return true;
    }
}
