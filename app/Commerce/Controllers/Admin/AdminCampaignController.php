<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Exceptions\NewsletterException;
use CorporateAid\Commerce\Repositories\EmailCampaignRepository;
use CorporateAid\Commerce\Repositories\SubscriberListRepository;
use CorporateAid\Commerce\Services\CampaignService;
use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Admin campaign management. Stage 008I.
 *
 * Routes (all under /commerce/ca-admin/campaigns):
 *
 *   GET  /                    index
 *   GET  /create              showCreate
 *   POST /                    store
 *   GET  /{id}/edit           showEdit
 *   POST /{id}                update
 *   POST /{id}/send           send
 *   POST /{id}/delete         destroy
 *   GET  /{id}                show (detail + stats after send)
 *   GET  /{id}/preview        preview (renders the campaign body)
 *
 * Permissions:
 *   newsletter.campaigns.view    index, show, showEdit, preview
 *   newsletter.campaigns.manage  showCreate, store, update,
 *                                send, destroy
 *
 * See docs/stages/008i_newsletter-plan.md.
 */
final class AdminCampaignController extends AdminController
{
    private const PER_PAGE = 25;
    private const VALID_TABS = ['all', 'draft', 'sending', 'sent', 'cancelled'];

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(EmailCampaignRepository::class);

        $tab = (string) $request->query('status', 'all');
        if (!in_array($tab, self::VALID_TABS, true)) {
            $tab = 'all';
        }

        $q    = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));

        $filters = [];
        if ($tab !== 'all') {
            $filters['status'] = $tab;
        }
        if ($q !== '') {
            $filters['q'] = $q;
        }

        $result = $repo->paginate($filters, $page, self::PER_PAGE);

        $counts = [
            'all'       => $repo->paginate([],                        1, 1)['total'],
            'draft'     => $repo->paginate(['status' => 'draft'],     1, 1)['total'],
            'sending'   => $repo->paginate(['status' => 'sending'],   1, 1)['total'],
            'sent'      => $repo->paginate(['status' => 'sent'],      1, 1)['total'],
            'cancelled' => $repo->paginate(['status' => 'cancelled'], 1, 1)['total'],
        ];

        return $this->render('admin.campaigns.index', [
            'title'  => 'Campaigns',
            'result' => $result,
            'tab'    => $tab,
            'q'      => $q,
            'counts' => $counts,
        ]);
    }

    /** @param array<string,string> $params */
    public function showCreate(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $lists   = $this->container->get(SubscriberListRepository::class)->listForStore();

        return $this->render('admin.campaigns.edit', [
            'title'  => 'New campaign',
            'mode'   => 'create',
            'campaign' => null,
            'lists'  => $lists,
            'errors' => $session->getFlash('campaign_errors', []),
            'old'    => $_SESSION['_old'] ?? [],
        ]);
    }

    /** @param array<string,string> $params */
    public function store(Request $request, array $params = []): Response
    {
        $svc     = $this->container->get(CampaignService::class);
        $session = $this->container->get(Session::class);
        $userId  = $this->currentUserId();

        $data   = $this->collectInput($request);
        $errors = $this->validate($data, true);

        if ($errors !== []) {
            $session->flash('campaign_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/campaigns/create');
        }

        try {
            $id = $svc->createDraft($data, $userId);
        } catch (NewsletterException $e) {
            $session->flash('error', $e->getMessage());
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/campaigns/create');
        }

        $session->clearOldInput();
        $session->flash('success', 'Campaign saved as draft.');
        return Response::redirect('/commerce/ca-admin/campaigns/' . $id . '/edit');
    }

    /** @param array<string,string> $params */
    public function showEdit(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid campaign reference.');
        }

        $repo = $this->container->get(EmailCampaignRepository::class);
        $campaign = $repo->findById($id);
        if ($campaign === null) {
            return $this->fail('Campaign not found.');
        }

        $session = $this->container->get(Session::class);
        $lists   = $this->container->get(SubscriberListRepository::class)->listForStore();

        return $this->render('admin.campaigns.edit', [
            'title'    => 'Edit ' . $campaign->subject,
            'mode'     => 'edit',
            'campaign' => $campaign,
            'lists'    => $lists,
            'errors'   => $session->getFlash('campaign_errors', []),
            'old'      => $_SESSION['_old'] ?? [],
        ]);
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid campaign reference.');
        }

        $repo = $this->container->get(EmailCampaignRepository::class);
        $campaign = $repo->findById($id);
        if ($campaign === null) {
            return $this->fail('Campaign not found.');
        }
        if (!$campaign->canEdit()) {
            $session = $this->container->get(Session::class);
            $session->flash('error', 'A campaign that has been sent cannot be edited.');
            return Response::redirect('/commerce/ca-admin/campaigns/' . $id);
        }

        $svc     = $this->container->get(CampaignService::class);
        $session = $this->container->get(Session::class);

        $data   = $this->collectInput($request);
        $errors = $this->validate($data, false);

        if ($errors !== []) {
            $session->flash('campaign_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/campaigns/' . $id . '/edit');
        }

        try {
            $svc->updateDraft($id, $data);
        } catch (NewsletterException $e) {
            $session->flash('error', $e->getMessage());
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/campaigns/' . $id . '/edit');
        }

        $session->clearOldInput();
        $session->flash('success', 'Campaign updated.');
        return Response::redirect('/commerce/ca-admin/campaigns/' . $id . '/edit');
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid campaign reference.');
        }

        $repo     = $this->container->get(EmailCampaignRepository::class);
        $svc      = $this->container->get(CampaignService::class);
        $campaign = $repo->findById($id);
        if ($campaign === null) {
            return $this->fail('Campaign not found.');
        }

        $list = $this->container->get(SubscriberListRepository::class)->findById($campaign->listId);

        return $this->render('admin.campaigns.show', [
            'title'    => $campaign->subject,
            'campaign' => $campaign,
            'list'     => $list,
            'stats'    => $svc->stats($campaign->id),
        ]);
    }

    /**
     * Send a campaign. Enqueues one row per confirmed subscriber
     * and flips the campaign to 'sending'. The CLI worker
     * (bin/send-emails.php) drains the queue.
     *
     * @param array<string,string> $params
     */
    public function send(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid campaign reference.');
        }

        $repo = $this->container->get(EmailCampaignRepository::class);
        $svc  = $this->container->get(CampaignService::class);
        $session = $this->container->get(Session::class);

        $campaign = $repo->findById($id);
        if ($campaign === null) {
            return $this->fail('Campaign not found.');
        }

        try {
            $result = $svc->send($id);
        } catch (NewsletterException $e) {
            $session->flash('error', $e->getMessage());
            return Response::redirect('/commerce/ca-admin/campaigns/' . $id);
        }

        $session->flash(
            'success',
            'Campaign queued for delivery. ' . $result['enqueued']
            . ' email' . ($result['enqueued'] === 1 ? '' : 's')
            . ' will send when the queue worker runs.'
        );

        return Response::redirect('/commerce/ca-admin/campaigns/' . $id);
    }

    /** @param array<string,string> $params */
    public function destroy(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid campaign reference.');
        }

        $repo    = $this->container->get(EmailCampaignRepository::class);
        $svc     = $this->container->get(CampaignService::class);
        $session = $this->container->get(Session::class);

        $campaign = $repo->findById($id);
        if ($campaign === null) {
            return $this->fail('Campaign not found.');
        }
        if (!$campaign->canEdit()) {
            $session->flash('error', 'Only a draft or cancelled campaign can be deleted.');
            return Response::redirect('/commerce/ca-admin/campaigns/' . $id);
        }

        $svc->deleteDraft($id);
        $session->flash('success', 'Campaign deleted.');
        return Response::redirect('/commerce/ca-admin/campaigns');
    }

    /**
     * Preview the campaign body in the admin. Renders the same
     * template the email uses but without the unsubscribe link.
     *
     * @param array<string,string> $params
     */
    public function preview(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid campaign reference.');
        }

        $repo = $this->container->get(EmailCampaignRepository::class);
        $campaign = $repo->findById($id);
        if ($campaign === null) {
            return $this->fail('Campaign not found.');
        }

        // ?raw=1 returns a bare email document with no admin
        // shell. This is what the sandboxed iframe on the
        // campaign detail page loads. The merchant's design
        // renders on the recipient's email-client defaults,
        // fully isolated from the admin theme.
        if ((string) $request->query('raw', '') === '1') {
            $store = $this->container->get(\CorporateAid\Commerce\Repositories\StoreRepository::class)
                ->findById($this->container->get(\CorporateAid\Commerce\Context\StoreContext::class)->id());
            $storeName = $store !== null && $store->name !== '' ? $store->name : 'Store';

            $body = $this->container->get(\CorporateAid\Core\View::class)
                ->render('admin.campaigns._preview-raw', [
                    'campaign'  => $campaign,
                    'storeName' => $storeName,
                ]);

            return Response::html($body);
        }

        return $this->render('admin.campaigns.preview', [
            'title'    => 'Preview: ' . $campaign->subject,
            'campaign' => $campaign,
        ]);
    }

    // =================================================================
    // Helpers
    // =================================================================

    private function fail(string $message): Response
    {
        $this->container->get(Session::class)->flash('error', $message);
        return Response::redirect('/commerce/ca-admin/campaigns');
    }

    private function currentUserId(): ?int
    {
        $user = $this->container->get(Auth::class)->user();
        return $user !== null ? (int) $user->id : null;
    }

    /**
     * Read the form and normalize.
     *
     * @return array<string,mixed>
     */
    private function collectInput(Request $request): array
    {
        return [
            'subject'   => trim((string) $request->input('subject', '')),
            'preheader' => trim((string) $request->input('preheader', '')),
            'body_html' => (string) $request->input('body_html', ''),
            'body_text' => trim((string) $request->input('body_text', '')),
            'list_id'   => (int) $request->input('list_id', 0),
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function validate(array $data, bool $requireList): array
    {
        $errors = [];

        $subject = (string) ($data['subject'] ?? '');
        if ($subject === '') {
            $errors['subject'] = 'Subject is required.';
        } elseif (mb_strlen($subject) > 255) {
            $errors['subject'] = 'Subject must be 255 characters or fewer.';
        }

        $preheader = (string) ($data['preheader'] ?? '');
        if (mb_strlen($preheader) > 255) {
            $errors['preheader'] = 'Preheader must be 255 characters or fewer.';
        }

        $body = (string) ($data['body_html'] ?? '');
        if (trim(strip_tags($body)) === '' && strpos($body, '<img') === false) {
            $errors['body_html'] = 'Body cannot be empty.';
        }

        if ($requireList) {
            $listId = (int) ($data['list_id'] ?? 0);
            if ($listId <= 0) {
                $errors['list_id'] = 'Select a list.';
            } else {
                $list = $this->container->get(SubscriberListRepository::class)->findById($listId);
                if ($list === null) {
                    $errors['list_id'] = 'The selected list was not found.';
                }
            }
        }

        return $errors;
    }
}
