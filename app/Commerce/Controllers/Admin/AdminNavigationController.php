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
    private function previewUrl(): string
    {
        // The admin and storefront share the same host.
        // A relative path to the root is the safest way to load the preview.
        return '/?customize_preview=1';
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
            'previewUrl' => $this->previewUrl(),
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
            'previewUrl' => $this->previewUrl(),
        ]);
    }
}
