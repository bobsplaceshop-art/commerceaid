<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Repositories\NavigationRepository;
use CorporateAid\Commerce\Models\NavigationMenu;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

final class AdminNavigationController extends AdminController
{
    private function previewUrl(string $target = '1'): string
    {
        // Use the configured base URL so the iframe loads the correct
        // host/port, regardless of where the admin is mounted.
        $base = rtrim((string) config('app.url'), '/');
        return $base . '/?customize_preview=' . urlencode($target);
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(NavigationRepository::class);
        $menus = $repo->listMenus();

        return $this->render('admin.navigation.index', [
            'title' => 'Navigation',
            'menus' => $menus,
        ]);
    }

    /** @param array<string,string> $params */
    public function header(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(NavigationRepository::class);
        $menu = $repo->findMenuByLocation('header');
        $tree = $repo->getHeaderTree('header');
        
        $res = $this->container->get(\CorporateAid\Commerce\Repositories\NavigationResourceRepository::class);
        $resources = $res->allGrouped();

        $trustItems = $repo->getTrustBarItems('header');

        return $this->render('admin.navigation.header', [
            'title'      => 'Header Builder',
            'menu'       => $menu,
            'items'      => $tree,
            'trustItems' => $trustItems,
            'resources'  => $resources,
            'previewUrl' => $this->previewUrl('header'),
        ]);
    }

    /** @param array<string,string> $params */
    public function footer(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(NavigationRepository::class);
        $menu = $repo->findMenuByLocation('footer');
        $columns = $repo->getFooterColumns('footer');
        $contacts = $repo->getContactDetails();

        $res = $this->container->get(\CorporateAid\Commerce\Repositories\NavigationResourceRepository::class);
        $resources = $res->allGrouped();

        return $this->render('admin.navigation.footer', [
            'title'      => 'Footer Builder',
            'menu'       => $menu,
            'columns'    => $columns,
            'contacts'   => $contacts,
            'resources'  => $resources,
            'previewUrl' => $this->previewUrl('footer'),
        ]);
    }
}
