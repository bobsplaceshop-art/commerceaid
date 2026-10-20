<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Commerce\Support\StorefrontController;
use CorporateAid\Commerce\Services\Feed\MerchantFeedService;

/**
 * Public feed endpoints.
 *
 * These routes are NOT rendered through the storefront layout.
 * They return raw XML or other machine-readable output for
 * external crawlers (Google Merchant Center, etc.).
 *
 * Auth is not required: the feed URL is intended for external
 * consumption and contains only public catalog data.
 */
final class FeedController extends StorefrontController
{
    /**
     * GET /feed/google-merchant.xml
     *
     * The Google Merchant Center product feed. One entry per
     * product (simple) or per variant (variable). Google
     * fetches this URL on its own schedule and displays the
     * products in the Shopping tab.
     *
     * @param array<string,string> $params
     */
    public function googleMerchant(Request $request, array $params = []): Response
    {
        $store = $this->resolveStore();
        if ($store === null) {
            return Response::html('Store not found.', 404);
        }

        $baseUrl = $this->baseUrl($request);

        $service = $this->container->get(MerchantFeedService::class);
        $xml     = $service->buildXml($store, $baseUrl);

        return new Response($xml, 200, [
            'Content-Type'           => 'application/xml; charset=utf-8',
            'Cache-Control'          => 'public, max-age=3600',
            'X-Robots-Tag'           => 'noindex',
        ]);
    }

    /**
     * Resolve the absolute base URL of the current request.
     * Reads X-Forwarded-Proto when present (reverse proxy),
     * falls back to Apache's own HTTPS flag.
     */
    private function baseUrl(Request $request): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        $proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($proto !== 'http' && $proto !== 'https') {
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        }

        return $proto . '://' . $host;
    }
}
