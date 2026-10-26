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
        // Relative path so the iframe stays same-origin with the
        // admin. Matches the Pages builder pattern (AdminPageController
        // line 121). The admin's CSP default-src 'self' allows it,
        // and the .htaccess exception allows framing for
        // ?customize_preview=*.
        return '/?customize_preview=' . urlencode($target);
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

    /** @param array<string,string> $params */
    public function storeColumn(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(NavigationRepository::class);

        $menuId    = (int) $request->input('menu_id');
        $columnKey = trim((string) $request->input('column_key'));
        $heading   = trim((string) $request->input('heading'));
        $sortOrder = (int) $request->input('sort_order', 0);
        $location  = (string) $request->input('menu_location', 'footer');

        if ($columnKey === '' || $heading === '') {
            $session->flash('error', 'Column key and heading are required.');
            return Response::redirect('/commerce/ca-admin/navigation/' . $location);
        }

        $repo->createColumn($menuId, $columnKey, $heading, $sortOrder);
        $session->flash('success', 'Column created.');

        return Response::redirect('/commerce/ca-admin/navigation/' . $location);
    }

    /** @param array<string,string> $params */
    public function updateColumn(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(NavigationRepository::class);

        $columnId  = (int) ($params['id'] ?? 0);
        $menuId    = (int) $request->input('menu_id');
        $heading   = trim((string) $request->input('heading'));
        $sortOrder = (int) $request->input('sort_order', 0);
        $location  = (string) $request->input('menu_location', 'footer');

        if ($heading === '') {
            $session->flash('error', 'Heading is required.');
            return Response::redirect('/commerce/ca-admin/navigation/' . $location);
        }

        $repo->updateColumn($columnId, $menuId, $heading, $sortOrder);
        $session->flash('success', 'Column updated.');

        return Response::redirect('/commerce/ca-admin/navigation/' . $location);
    }

    /** @param array<string,string> $params */
    public function destroyColumn(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(NavigationRepository::class);

        $columnId = (int) ($params['id'] ?? 0);
        $menuId   = (int) $request->input('menu_id');
        $location = (string) $request->input('menu_location', 'footer');

        $repo->deleteColumn($columnId, $menuId);
        $session->flash('success', 'Column deleted.');

        return Response::redirect('/commerce/ca-admin/navigation/' . $location);
    }

    /** @param array<string,string> $params */
    public function storeMenu(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(NavigationRepository::class);

        $name = trim((string) $request->input('name'));
        $slug = trim((string) $request->input('slug'));

        if ($name === '' || $slug === '') {
            $session->flash('error', 'Name and slug are required.');
            return Response::redirect('/commerce/ca-admin/navigation');
        }

        $slug = strtolower((string) preg_replace('/[^a-z0-9-]+/i', '-', $slug));
        $existing = $repo->findMenuBySlug($slug);
        if ($existing !== null) {
            $session->flash('error', 'A menu with that slug already exists.');
            return Response::redirect('/commerce/ca-admin/navigation');
        }

        $repo->createMenu($name, $slug, 'custom');
        $session->flash('success', 'Menu created.');
        return Response::redirect('/commerce/ca-admin/navigation');
    }

    /** @param array<string,string> $params */
    public function destroyMenu(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(NavigationRepository::class);

        $menuId = (int) ($params['id'] ?? 0);
        $repo->deleteMenu($menuId);

        $session->flash('success', 'Menu deleted.');
        return Response::redirect('/commerce/ca-admin/navigation');
    }

    /** @param array<string,string> $params */
    public function customMenu(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(NavigationRepository::class);
        $slug = (string) ($params['slug'] ?? '');
        $menu = $repo->findMenuBySlug($slug);

        if ($menu === null || $menu->location !== 'custom') {
            return Response::redirect('/commerce/ca-admin/navigation');
        }

        $tree = $repo->getMenuTree($slug);

        $res = $this->container->get(\CorporateAid\Commerce\Repositories\NavigationResourceRepository::class);
        $resources = $res->allGrouped();

        return $this->render('admin.navigation.menu', [
            'title'     => $menu->name,
            'menu'      => $menu,
            'items'     => $tree,
            'resources' => $resources,
        ]);
    }
}
