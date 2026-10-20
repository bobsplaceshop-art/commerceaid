<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Commerce\Support\StorefrontController;
use CorporateAid\Commerce\Services\Sitemap\SitemapService;

/**
 * Public sitemap endpoint.
 *
 * Serves /sitemap.xml. Google Search Console and Bing
 * Webmaster Tools fetch this URL and use it to discover
 * every public page.
 */
final class SitemapController extends StorefrontController
{
    /** @param array<string,string> $params */
    public function xml(Request $request, array $params = []): Response
    {
        $store = $this->resolveStore();
        if ($store === null) {
            return Response::html('Store not found.', 404);
        }

        $baseUrl = $this->baseUrl();

        $service = $this->container->get(SitemapService::class);
        $xml     = $service->buildXml($store, $baseUrl);

        return new Response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function baseUrl(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        $proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($proto !== 'http' && $proto !== 'https') {
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        }

        return $proto . '://' . $host;
    }
}
