<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Pwa;

use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;

/**
 * Renders /manifest.json - tenant-aware PWA manifest.
 * Pulls branding from config. When a store is resolved, store-level
 * branding overrides deployment defaults.
 */
final class ManifestController
{
    public function __construct(private Container $container)
    {
    }

    /** @param array<string,string> $params */
    public function __invoke(Request $request, array $params = []): Response
    {
        $config = $this->container->get('config');
        $branding = (array) $config->get('branding', []);

        $manifest = [
            'name'             => $branding['pwa_name']        ?? $branding['product_name'] ?? 'Store',
            'short_name'       => $branding['pwa_short_name']  ?? $branding['product_short'] ?? 'Store',
            'description'      => $branding['tagline']         ?? '',
            'start_url'        => '/',
            'scope'            => '/',
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => $branding['pwa_bg_color']    ?? '#ffffff',
            'theme_color'      => $branding['pwa_theme_color'] ?? $branding['primary_color'] ?? '#000000',
            'icons' => [
                ['src' => '/assets/img/pwa/icon-192.png',         'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/assets/img/pwa/icon-512.png',         'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/assets/img/pwa/icon-maskable-512.png','sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];

        return Response::json($manifest)
            ->withHeader('Content-Type', 'application/manifest+json; charset=utf-8');
    }
}
