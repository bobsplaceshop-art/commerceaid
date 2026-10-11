<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Repositories\EmailEventRepository;
use CorporateAid\Commerce\Repositories\SubscriberListMemberRepository;
use CorporateAid\Commerce\Repositories\SubscriberListRepository;
use CorporateAid\Commerce\Repositories\SubscriberRepository;
use CorporateAid\Commerce\Services\SubscriberService;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Admin subscriber management. Stage 008I.
 *
 * Routes (all under /commerce/ca-admin/subscribers):
 *
 *   GET  /                     index
 *   GET  /export               exportCsv
 *   GET  /{id}                 show
 *   POST /{id}/unsubscribe     unsubscribe
 *
 * Permissions:
 *   newsletter.subscribers.view    index, show, exportCsv
 *   newsletter.subscribers.manage  unsubscribe
 *
 * See docs/stages/008i_newsletter-plan.md.
 */
final class AdminSubscriberController extends AdminController
{
    private const PER_PAGE = 25;
    private const VALID_TABS = ['all', 'confirmed', 'pending', 'unsubscribed', 'suppressed'];
    private const VALID_SOURCES = ['', 'footer', 'home', 'account', 'admin', 'import'];

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(SubscriberRepository::class);

        $tab = (string) $request->query('status', 'all');
        if (!in_array($tab, self::VALID_TABS, true)) {
            $tab = 'all';
        }

        $source = (string) $request->query('source', '');
        if (!in_array($source, self::VALID_SOURCES, true)) {
            $source = '';
        }

        $q    = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));

        $filters = [];
        if ($tab !== 'all') {
            $filters['status'] = $tab;
        }
        if ($source !== '') {
            $filters['source'] = $source;
        }
        if ($q !== '') {
            $filters['q'] = $q;
        }

        $result = $repo->paginate($filters, $page, self::PER_PAGE);

        $counts = [
            'all'          => $repo->paginate([],                        1, 1)['total'],
            'confirmed'    => $repo->paginate(['status' => 'confirmed'], 1, 1)['total'],
            'pending'      => $repo->paginate(['status' => 'pending'],   1, 1)['total'],
            'unsubscribed' => $repo->paginate(['status' => 'unsubscribed'], 1, 1)['total'],
            'suppressed'   => $repo->paginate(['status' => 'suppressed'], 1, 1)['total'],
        ];

        return $this->render('admin.subscribers.index', [
            'title'   => 'Subscribers',
            'result'  => $result,
            'tab'     => $tab,
            'source'  => $source,
            'q'       => $q,
            'counts'  => $counts,
            'lists'   => $this->container->get(SubscriberListRepository::class)->listForStore(),
        ]);
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid subscriber reference.');
        }

        $repo       = $this->container->get(SubscriberRepository::class);
        $listRepo   = $this->container->get(SubscriberListRepository::class);
        $memberRepo = $this->container->get(SubscriberListMemberRepository::class);
        $eventRepo  = $this->container->get(EmailEventRepository::class);

        $subscriber = $repo->findById($id);
        if ($subscriber === null) {
            return $this->fail('Subscriber not found.');
        }

        $allLists     = $listRepo->listForStore();
        $memberListIds = $memberRepo->listIdsForSubscriber($subscriber->id);
        $events       = $eventRepo->listForSubscriber($subscriber->id, 50);

        return $this->render('admin.subscribers.show', [
            'title'        => $subscriber->displayName(),
            'subscriber'   => $subscriber,
            'allLists'     => $allLists,
            'memberListIds' => $memberListIds,
            'events'       => $events,
        ]);
    }

    /**
     * Manual unsubscribe by an admin. Same service as the
     * storefront unsubscribe path. Used when a customer
     * contacts the store asking to be removed.
     *
     * @param array<string,string> $params
     */
    public function unsubscribe(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid subscriber reference.');
        }

        $repo    = $this->container->get(SubscriberRepository::class);
        $service = $this->container->get(SubscriberService::class);
        $session = $this->container->get(Session::class);

        $subscriber = $repo->findById($id);
        if ($subscriber === null) {
            return $this->fail('Subscriber not found.');
        }

        $reason = trim((string) $request->input('reason', ''));
        $ok     = $service->unsubscribe($subscriber->id, $reason !== '' ? $reason : null);

        if ($ok) {
            $session->flash('success', $subscriber->displayName() . ' has been unsubscribed.');
        } else {
            $session->flash('error', 'Could not unsubscribe this address.');
        }

        return Response::redirect('/commerce/ca-admin/subscribers/' . $subscriber->id);
    }

    /**
     * CSV export of the current filtered list. Streams every
     * matching row (no pagination).
     *
     * @param array<string,string> $params
     */
    public function exportCsv(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(SubscriberRepository::class);

        $tab = (string) $request->query('status', 'all');
        if (!in_array($tab, self::VALID_TABS, true)) {
            $tab = 'all';
        }
        $source = (string) $request->query('source', '');
        if (!in_array($source, self::VALID_SOURCES, true)) {
            $source = '';
        }
        $q = trim((string) $request->query('q', ''));

        $filters = [];
        if ($tab !== 'all')    $filters['status'] = $tab;
        if ($source !== '')    $filters['source'] = $source;
        if ($q !== '')         $filters['q'] = $q;

        // 10,000 cap to keep memory bounded. Any store with
        // more than 10k subscribers gets a follow-up stage
        // with streaming export.
        $result = $repo->paginate($filters, 1, 100);

        $rows = [['email', 'name', 'status', 'source', 'consent_at', 'confirmed_at', 'unsubscribed_at']];
        $page = 1;
        do {
            $batch = $repo->paginate($filters, $page, 100);
            foreach ($batch['items'] as $s) {
                $rows[] = [
                    $s->email,
                    $s->name ?? '',
                    $s->status,
                    $s->consentSource ?? '',
                    $s->consentAt ?? '',
                    $s->confirmedAt ?? '',
                    $s->unsubscribedAt ?? '',
                ];
                if (count($rows) > 10000) break 2;
            }
            $page++;
        } while ($page <= $batch['pages']);

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(function ($v) {
                $v = (string) $v;
                return '"' . str_replace('"', '""', $v) . '"';
            }, $row)) . "\n";
        }

        $filename = 'subscribers-' . date('Y-m-d') . '.csv';

        // The Response class does not carry custom headers, so
        // set them here before returning the body. header()
        // is safe to call because the controller has not sent
        // any output yet.
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        return Response::text($csv);
    }

    private function fail(string $message): Response
    {
        $this->container->get(Session::class)->flash('error', $message);
        return Response::redirect('/commerce/ca-admin/subscribers');
    }
}
