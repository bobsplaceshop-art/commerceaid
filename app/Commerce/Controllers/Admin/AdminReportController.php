<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Commerce\Repositories\ReportRepository;
use Mpdf\Mpdf;

final class AdminReportController extends AdminController
{
    /**
     * The interactive reports page. Accepts ?days=N (7|30|90|365).
     */
    public function index(Request $request, array $params = []): Response
    {
        $days = (int) $request->query('days', 30);
        if (!in_array($days, [7, 30, 90, 365], true)) {
            $days = 30;
        }

        $data = $this->buildReportData($days);
        $data['title'] = 'Reports';
        $data['days']  = $days;

        return $this->render('admin.reports.index', $data);
    }

    /**
     * A stripped-down print view. No sidebar, no topbar, no buttons.
     * The browser's "Save as PDF" turns this into a clean PDF.
     */
    public function print(Request $request, array $params = []): Response
    {
        $days = (int) $request->query('days', 30);
        if (!in_array($days, [7, 30, 90, 365], true)) {
            $days = 30;
        }

        $data = $this->buildReportData($days);
        $data['title'] = 'Reports';
        $data['days']  = $days;

        $viewSvc = $this->container->get(\CorporateAid\Core\View::class);
        $store   = $this->resolveStore();
        $data['store'] = $store;

        return Response::html(
            $viewSvc->render('admin.reports.print', $data)
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function buildReportData(int $days): array
    {
        $reports = $this->container->get(ReportRepository::class);

        $series  = $reports->salesOverTime($days);
        $revenue = array_sum(array_column($series, 'revenue'));
        $orders  = array_sum(array_column($series, 'orders'));
        $aov     = $orders > 0 ? $revenue / $orders : 0.0;

        return [
            'salesSeries'     => $series,
            'totalRevenue'    => $revenue,
            'totalOrders'     => $orders,
            'averageOrder'    => $aov,
            'topProducts'     => $reports->topProducts($days, 20),
            'salesByCategory' => $reports->salesByCategory($days),
            'statusBreakdown' => $reports->orderStatusBreakdown(),
            'customerStats'   => $reports->customerStats($days),
            'slowMoving'      => $reports->slowMovingStock($days, 20),
        ];
    }

    /**
     * Generates a true PDF of the report using mPDF.
     * Streams it to the browser as a file download.
     *
     * mPDF is a pure-PHP library. No system binaries, no exec(),
     * no cPanel restrictions. Works identically on any host.
     */
    public function downloadPdf(Request $request, array $params = []): Response
    {
        $days = (int) $request->query('days', 30);
        if (!in_array($days, [7, 30, 90, 365], true)) {
            $days = 30;
        }

        $data = $this->buildReportData($days);
        $data['title'] = 'Reports';
        $data['days']  = $days;

        $store = $this->resolveStore();
        $data['store'] = $store;
        $storeName = $store !== null ? $store->name : 'Store';

        $viewSvc = $this->container->get(\CorporateAid\Core\View::class);
        $html = $viewSvc->render('admin.reports.pdf', $data);

        $filename = preg_replace('/[^a-z0-9]+/i', '-', $storeName);
        $filename = strtolower(trim($filename, '-')) . '-reports-' . date('Y-m-d') . '.pdf';

        @ini_set('memory_limit', '256M');
        @set_time_limit(60);

        $mpdf = new Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'margin_left'       => 15,
            'margin_right'      => 15,
            'margin_top'        => 15,
            'margin_bottom'     => 15,
            'default_font'      => 'dejavusans',
            'default_font_size' => 10,
        ]);

        $mpdf->WriteHTML($html);
        $pdfBytes = $mpdf->Output('', 'S');

        return new \CorporateAid\Core\Response($pdfBytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => (string) strlen($pdfBytes),
        ]);
    }
}
