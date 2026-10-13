<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Platform\Repositories\ActivityRepository;
use CorporateAid\Platform\Repositories\AuditLogRepository;
use CorporateAid\Platform\Support\LogReader;

/**
 * Logs hub. Stage 014.
 *
 * One screen, four tabs. The merchant reads the Activity Log
 * tab and never opens the other three. The developer reads
 * whichever tab matches the question.
 *
 *   Overview     counts from every stream; no data table
 *   Activity     core_activities - business timeline
 *   System       storage/logs/app.log - developer log
 *   Audit        core_audit_logs - raw HTTP audit
 *
 * Routes:
 *   GET /commerce/ca-admin/logs           -> overview
 *   GET /commerce/ca-admin/logs/{tab}     -> the named tab
 *
 * Permission: core.audit.view on both routes.
 *
 * The three data streams are distinct and are not merged:
 *
 *   core_activities       human sentences ("Esther published
 *                         Product #42"). Written by services
 *                         that call ActivityLogger::log().
 *
 *   storage/logs/app.log  structured developer log. Written by
 *                         Logger for every INFO, WARNING,
 *                         ERROR, EXCEPTION line.
 *
 *   core_audit_logs       raw HTTP audit rows. Written
 *                         automatically by
 *                         AuditLoggerMiddleware for every
 *                         state-changing request.
 *
 * See docs/REMEMBER.md for the three-way split.
 */
final class LogController extends AdminController
{
    /**
     * Tab metadata - order, label, icon. Rendered by the sub-nav
     * partial. Extend by adding a key here and a handler to the
     * match statement in index().
     *
     * @var array<string,array{label:string, icon:string}>
     */
    private const TAB_META = [
        'overview' => ['label' => 'Overview',     'icon' => 'bi-speedometer2'],
        'activity' => ['label' => 'Activity Log', 'icon' => 'bi-list-check'],
        'system'   => ['label' => 'System Logs',  'icon' => 'bi-journal-text'],
        'audit'    => ['label' => 'Audit Trail',  'icon' => 'bi-shield-check'],
    ];

    private const PER_PAGE = 50;

    /**
     * @param array<string,string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $tab = (string) ($params['tab'] ?? 'overview');
        if (!isset(self::TAB_META[$tab])) {
            throw HttpException::notFound();
        }

        return match ($tab) {
            'overview' => $this->showOverview($request),
            'activity' => $this->showActivity($request),
            'system'   => $this->showSystem($request),
            'audit'    => $this->showAudit($request),
        };
    }

    // =================================================================
    // Overview tab
    // =================================================================

    private function showOverview(Request $request): Response
    {
        $activities = $this->container->get(ActivityRepository::class);
        $audit      = $this->container->get(AuditLogRepository::class);

        $activityCounts = $activities->counts();
        $auditCounts    = $audit->counts();
        $systemCounts   = $this->systemLogCounts();

        // The last activity, if any, so the merchant sees the
        // most recent action at a glance.
        $lastActivity = null;
        $recent = $activities->paginate([], 1, 1)['items'] ?? [];
        if (!empty($recent)) {
            $lastActivity = $recent[0];
        }

        return $this->renderTab('overview', [
            'title'          => 'Logs',
            'activityCounts' => $activityCounts,
            'auditCounts'    => $auditCounts,
            'systemCounts'   => $systemCounts,
            'lastActivity'   => $lastActivity,
        ]);
    }

    /**
     * Summary counts from app.log. The System Logs view and
     * its reader already produce this shape when the level
     * filter is present; here we just count rows in a small
     * time window.
     *
     * @return array{exceptions_today:int, errors_today:int, total_today:int}
     */
    private function systemLogCounts(): array
    {
        $today = LogReader::readFiltered(
            ['date_from' => date('Y-m-d')],
            1,
            500
        );

        $exceptions = 0;
        $errors     = 0;
        foreach ($today['items'] as $entry) {
            $lvl = (string) ($entry['level'] ?? '');
            if ($lvl === 'exception') $exceptions++;
            elseif ($lvl === 'error')  $errors++;
        }

        return [
            'exceptions_today' => $exceptions,
            'errors_today'     => $errors,
            'total_today'      => (int) $today['total'],
        ];
    }

    // =================================================================
    // Activity tab (core_activities)
    // =================================================================

    private function showActivity(Request $request): Response
    {
        $repo = $this->container->get(ActivityRepository::class);

        $userId = (int) $request->query('user_id', 0);
        $module = trim((string) $request->query('module', ''));
        $search = trim((string) $request->query('q', ''));
        $page   = max(1, (int) $request->query('page', 1));

        // Default range: today. A merchant wants to know what
        // happened today. An explicit date_from or date_to
        // overrides.
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo   = trim((string) $request->query('date_to', ''));
        if ($dateFrom === '' && $dateTo === '') {
            $dateFrom = date('Y-m-d');
        }

        $filters = [
            'user_id'   => $userId,
            'module'    => $module,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'search'    => $search,
        ];

        $result = $repo->paginate($filters, $page, self::PER_PAGE);

        return $this->renderTab('activity', [
            'title'       => 'Logs - Activity',
            'result'      => $result,
            'counts'      => $repo->counts(),
            'activeModules' => $repo->activeModules(),
            'userId'      => $userId,
            'module'      => $module,
            'dateFrom'    => $dateFrom,
            'dateTo'      => $dateTo,
            'q'           => $search,
        ]);
    }

    // =================================================================
    // System tab (storage/logs/app.log)
    // =================================================================

    private function showSystem(Request $request): Response
    {
        $level = strtolower(trim((string) $request->query('level', 'exception')));
        if (!in_array($level, ['all', 'exception', 'error', 'warning', 'info'], true)) {
            $level = 'exception';
        }

        $dateFrom = trim((string) $request->query('date_from', ''));
        if ($dateFrom === '') {
            $dateFrom = date('Y-m-d', time() - 86400);
        }

        $dateTo = trim((string) $request->query('date_to', ''));
        $search = trim((string) $request->query('q', ''));
        $page   = max(1, (int) $request->query('page', 1));

        $filters = [
            'level'     => $level,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'search'    => $search,
        ];

        $result = LogReader::readFiltered($filters, $page, self::PER_PAGE);

        return $this->renderTab('system', [
            'title'    => 'Logs - System',
            'result'   => $result,
            'level'    => $level,
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
            'q'        => $search,
            'logPath'  => LogReader::resolvePath(),
        ]);
    }

    // =================================================================
    // Audit tab (core_audit_logs)
    // =================================================================

    private function showAudit(Request $request): Response
    {
        $repo = $this->container->get(AuditLogRepository::class);

        $userId = (int) $request->query('user_id', 0);
        $method = strtoupper(trim((string) $request->query('method', '')));
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $method = '';
        }
        $path   = trim((string) $request->query('path', ''));
        $search = trim((string) $request->query('q', ''));
        $page   = max(1, (int) $request->query('page', 1));

        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo   = trim((string) $request->query('date_to', ''));
        if ($dateFrom === '' && $dateTo === '') {
            $dateFrom = date('Y-m-d');
        }

        $filters = [
            'user_id'   => $userId,
            'method'    => $method,
            'path'      => $path,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'search'    => $search,
        ];

        $result = $repo->paginate($filters, $page, self::PER_PAGE);

        return $this->renderTab('audit', [
            'title'       => 'Logs - Audit Trail',
            'result'      => $result,
            'counts'      => $repo->counts(),
            'activeUsers' => $repo->activeUsers(),
            'userId'      => $userId,
            'method'      => $method,
            'path'        => $path,
            'dateFrom'    => $dateFrom,
            'dateTo'      => $dateTo,
            'q'           => $search,
        ]);
    }

    // =================================================================
    // Shell
    // =================================================================

    /**
     * Render a tab into the Logs shell. The shell includes the
     * sub-nav partial and then the tab's own view file
     * (admin/logs/{tab}.php).
     *
     * @param array<string,mixed> $data
     */
    private function renderTab(string $tab, array $data = []): Response
    {
        $data['tabMeta']    = self::TAB_META;
        $data['currentTab'] = $tab;
        $data['title']      = $data['title'] ?? 'Logs';

        return $this->render('admin.logs.index', $data);
    }
}
