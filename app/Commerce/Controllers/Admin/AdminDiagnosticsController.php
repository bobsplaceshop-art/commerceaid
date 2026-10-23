<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Services\Diagnostics\PerformanceCheckService;

final class AdminDiagnosticsController extends AdminController
{
    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $auth = $this->container->get(\CorporateAid\Core\Auth::class);
        if (!$auth->user()?->can('core.settings.manage')) {
            throw \CorporateAid\Core\Exceptions\HttpException::forbidden();
        }

        $session = $this->container->get(Session::class);
        $results = $session->getFlash('perf_results', null);

        return $this->render('admin.system.performance', [
            'title'   => 'Performance Check',
            'results' => $results,
        ]);
    }

    /** @param array<string,string> $params */
    public function run(Request $request, array $params = []): Response
    {
        $auth = $this->container->get(\CorporateAid\Core\Auth::class);
        if (!$auth->user()?->can('core.settings.manage')) {
            throw \CorporateAid\Core\Exceptions\HttpException::forbidden();
        }

        $session = $this->container->get(Session::class);

        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $baseUrl = $proto . '://' . $host;

        $service = $this->container->get(PerformanceCheckService::class);
        $result  = $service->run($baseUrl);

        $session->flash('perf_results', $result);
        return Response::redirect('/commerce/ca-admin/system/performance');
    }
}
