<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Repositories\NavigationRepository;
use CorporateAid\Commerce\Models\NavigationItem;
use CorporateAid\Commerce\Models\StoreContactDetail;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

final class AdminNavigationItemController extends AdminController
{
    /** @param array<string,string> $params */
    public function store(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(NavigationRepository::class);

        $menuId = (int) $request->input('menu_id');
        $menu = $repo->findMenuById($menuId);

        if (!$menu) {
            $session->flash('error', 'Invalid menu.');
            return Response::redirect('/commerce/ca-admin/navigation');
        }

        $redirect = $menu->location === 'custom'
            ? '/commerce/ca-admin/navigation/menu/' . $menu->slug
            : '/commerce/ca-admin/navigation/' . $menu->location;

        $item = new NavigationItem();
        $item->menuId       = $menuId;
        $item->columnId     = $request->input('column_id') ? (int) $request->input('column_id') : null;
        $item->parentId     = $request->input('parent_id') ? (int) $request->input('parent_id') : null;
        $item->label        = trim((string) $request->input('label'));
        $item->icon         = trim((string) $request->input('icon')) ?: null;
        $item->subtitle     = trim((string) $request->input('subtitle')) ?: null;
        $item->url          = trim((string) $request->input('url')) ?: null;
        $item->resourceType = $request->input('resource_type') ?: null;
        $item->resourceId   = $request->input('resource_id') ? (int) $request->input('resource_id') : null;
        $item->sortOrder    = (int) $request->input('sort_order', 0);
        $item->isHighlight  = (bool) $request->input('is_highlight', false);

        if ($item->label === '') {
            $session->flash('error', 'Label is required.');
            return Response::redirect($redirect);
        }

        // Ensure exactly one destination is set.
        if ($item->resourceType && $item->url) {
            $item->url = null;
        }
        if (!$item->resourceType && !$item->url) {
            $item->url = '#';
        }

        $repo->createItem($item);
        $session->flash('success', 'Item added.');

        return Response::redirect($redirect);
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(NavigationRepository::class);
        $id      = (int) ($params['id'] ?? 0);

        $menuId = (int) $request->input('menu_id');
        $menu = $repo->findMenuById($menuId);

        if (!$menu) {
            $session->flash('error', 'Invalid menu.');
            return Response::redirect('/commerce/ca-admin/navigation');
        }

        $redirect = $menu->location === 'custom'
            ? '/commerce/ca-admin/navigation/menu/' . $menu->slug
            : '/commerce/ca-admin/navigation/' . $menu->location;

        $item = new NavigationItem();
        $item->id           = $id;
        $item->menuId       = $menuId;
        $item->label        = trim((string) $request->input('label'));
        $item->icon         = trim((string) $request->input('icon')) ?: null;
        $item->subtitle     = trim((string) $request->input('subtitle')) ?: null;
        $item->url          = trim((string) $request->input('url')) ?: null;
        $item->resourceType = $request->input('resource_type') ?: null;
        $item->resourceId   = $request->input('resource_id') ? (int) $request->input('resource_id') : null;
        $item->sortOrder    = (int) $request->input('sort_order', 0);
        $item->isHighlight  = (bool) $request->input('is_highlight', false);

        if ($item->resourceType && $item->url) {
            $item->url = null;
        }
        if (!$item->resourceType && !$item->url) {
            $item->url = '#';
        }

        $repo->updateItem($item);
        $session->flash('success', 'Item updated.');

        return Response::redirect($redirect);
    }

    /** @param array<string,string> $params */
    public function destroy(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(NavigationRepository::class);
        $id      = (int) ($params['id'] ?? 0);
        $menuId  = (int) $request->input('menu_id');

        $repo->deleteItem($id, $menuId);
        $session->flash('success', 'Item deleted.');

        $menu = $repo->findMenuById($menuId);
        $redirect = '/commerce/ca-admin/navigation';
        if ($menu !== null) {
            $redirect = $menu->location === 'custom'
                ? '/commerce/ca-admin/navigation/menu/' . $menu->slug
                : '/commerce/ca-admin/navigation/' . $menu->location;
        }
        return Response::redirect($redirect);
    }

    /** @param array<string,string> $params */
    public function storeContact(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(NavigationRepository::class);

        $contact = new StoreContactDetail();
        $contact->type      = (string) $request->input('type', 'custom');
        $contact->label     = trim((string) $request->input('label'));
        $contact->value     = trim((string) $request->input('value'));
        $contact->sortOrder = (int) $request->input('sort_order', 0);

        if ($contact->label === '' || $contact->value === '') {
            $session->flash('error', 'Label and value are required for contact details.');
            return Response::redirect('/commerce/ca-admin/navigation/footer');
        }

        $repo->createContact($contact);
        $session->flash('success', 'Contact detail added.');

        return Response::redirect('/commerce/ca-admin/navigation/footer');
    }

    /** @param array<string,string> $params */
    public function updateContact(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(NavigationRepository::class);

        $contact = new StoreContactDetail();
        $contact->id        = (int) ($params['id'] ?? 0);
        $contact->type      = (string) $request->input('type', 'custom');
        $contact->label     = trim((string) $request->input('label'));
        $contact->value     = trim((string) $request->input('value'));
        $contact->sortOrder = (int) $request->input('sort_order', 0);

        $repo->updateContact($contact);
        $session->flash('success', 'Contact detail updated.');

        return Response::redirect('/commerce/ca-admin/navigation/footer');
    }

    /** @param array<string,string> $params */
    public function destroyContact(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(NavigationRepository::class);
        $id      = (int) ($params['id'] ?? 0);

        $repo->deleteContact($id);
        $session->flash('success', 'Contact detail deleted.');

        return Response::redirect('/commerce/ca-admin/navigation/footer');
    }
}
