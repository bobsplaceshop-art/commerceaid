<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Pwa;

use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Models\Store;
use CorporateAid\Commerce\Repositories\StoreRepository;

/**
 * Renders /manifest.json - the tenant-aware web app manifest.
 *
 * Serves both the storefront and the admin. The manifest fields
 * come from the store's PWA settings (commerce_stores columns),
 * falling back per field to config('branding') and then to
 * hardcoded defaults. The store's uploaded favicon supplies the
 * 192x192 and 512x512 icons; when none has been uploaded, three
 * placeholder PNGs at /assets/img/pwa/ are used.
 *
 * Route: GET /manifest.json (registered in routes/commerce.php)
 *
 * The route is public. No auth, no CSRF. It is fetched by the
 * browser on every page load to discover installability.
 */
final class ManifestController
{
    public function __construct(private Container $container)
    {
    }

    /** @param array<string,string> $params */
    public function __invoke(Request $request, array $params = []): Response
    {
        $branding = $this->resolveBranding();
        $store    = $this->resolveStore();

        $manifest = [
            'name'             => $this->pick($store?->pwaName,    $branding['pwa_name']        ?? null, $branding['product_name'] ?? 'Store'),
            'short_name'       => $this->pick($store?->pwaShortName, $branding['pwa_short_name']  ?? null, $branding['product_short'] ?? 'Store'),
            'description'      => (string) ($branding['tagline'] ?? ''),
            'start_url'        => '/',
            'scope'            => '/',
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => $this->pick($store?->pwaBackgroundColor, $branding['pwa_bg_color'] ?? null, '#ffffff'),
            'theme_color'      => $this->pick($store?->pwaThemeColor,      $branding['pwa_theme_color'] ?? null, $branding['primary_color'] ?? '#000000'),
            'icons'            => $this->resolveIcons($store),
        ];

        return Response::json($manifest)
            ->withHeader('Content-Type', 'application/manifest+json; charset=utf-8');
    }

    /**
     * Pick the first non-empty value from the given list.
     * Skips nulls and empty strings. Returns the final fallback
     * if nothing else matches.
     */
    private function pick(?string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                return $candidate;
            }
        }
        return '';
    }

    /**
     * Read the deployment-level branding config. Always an array.
     *
     * @return array<string,mixed>
     */
    private function resolveBranding(): array
    {
        try {
            return (array) $this->container->get('config')->get('branding', []);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Resolve the current store from the StoreContext. Null when
     * no store is bound (rare in practice - the middleware runs
     * before every storefront and admin request).
     */
    private function resolveStore(): ?Store
    {
        try {
            $context = $this->container->get(StoreContext::class);
            if (!$context->has()) {
                return null;
            }
            $repo = $this->container->get(StoreRepository::class);
            return $repo->findById($context->id());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Build the icons array.
     *
     * Preference order per slot:
     *   1. The store's uploaded favicon at the required size
     *      (lg=512, md=192, both PNG).
     *   2. The deployment placeholder at /assets/img/pwa/.
     *
     * @return array<int,array<string,string>>
     */
    private function resolveIcons(?Store $store): array
    {
        $icon192 = store_favicon_url($store, 'md',  'png') ?? '/assets/img/pwa/icon-192.png';
        $icon512 = store_favicon_url($store, 'lg',  'png') ?? '/assets/img/pwa/icon-512.png';
        $mask    = store_favicon_url($store, 'lg',  'png') ?? '/assets/img/pwa/icon-maskable-512.png';

        return [
            [
                'src'   => $icon192,
                'sizes' => '192x192',
                'type'  => 'image/png',
            ],
            [
                'src'   => $icon512,
                'sizes' => '512x512',
                'type'  => 'image/png',
            ],
            [
                'src'     => $mask,
                'sizes'   => '512x512',
                'type'    => 'image/png',
                'purpose' => 'maskable',
            ],
        ];
    }
}
