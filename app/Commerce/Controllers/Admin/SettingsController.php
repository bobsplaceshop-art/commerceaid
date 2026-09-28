<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Repositories\StoreSettingsRepository;
use CorporateAid\Commerce\Support\StoreSettingDefinitions;

/**
 * Store settings.
 *
 * Route: /commerce/ca-admin/settings
 *
 * A tabbed page that renders every setting declared in
 * StoreSettingDefinitions::DEFINITIONS plus an Advanced tab for
 * custom key-value settings the platform does not yet know about.
 *
 * Adding a new setting is one line in the definitions array.
 * This controller does not change. The view does not change.
 * No migration. No form edit. That is the whole point.
 *
 * Routes:
 *   GET  /settings                       index (tabbed page)
 *   POST /settings                       save (per-tab form)
 *   POST /settings/advanced              add a custom setting
 *   POST /settings/advanced/delete       remove a custom setting
 *
 * See docs/stages/008e.1_german-compliance-plan.md.
 */
final class SettingsController extends AdminController
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $settings = $this->container->get(StoreSettingsRepository::class);

        $groups     = StoreSettingDefinitions::groups();
        $firstGroup = $groups[0] ?? StoreSettingDefinitions::GROUP_GENERAL;

        $activeTab = (string) $request->query('tab', $firstGroup);
        if (!in_array($activeTab, $groups, true) && $activeTab !== 'advanced') {
            $activeTab = $firstGroup;
        }

        // Values for the active tab (or empty for advanced).
        $values = $settings->all();
        $definitions = $activeTab === 'advanced'
            ? []
            : StoreSettingDefinitions::forGroup($activeTab);

        // Advanced tab: custom settings only.
        $custom = $activeTab === 'advanced'
            ? $settings->customSettings()
            : [];

        return $this->render('admin.settings.index', [
            'title'       => 'Store Settings',
            'groups'      => $groups,
            'activeTab'   => $activeTab,
            'definitions' => $definitions,
            'values'      => $values,
            'custom'      => $custom,
        ]);
    }

    /** @param array<string,string> $params */
    public function save(Request $request, array $params = []): Response
    {
        $settings = $this->container->get(StoreSettingsRepository::class);
        $auth     = $this->container->get(Auth::class);
        $session  = $this->container->get(Session::class);

        $userId = $auth->user()?->id ?? null;

        $activeTab = (string) $request->input('_tab', StoreSettingDefinitions::GROUP_GENERAL);
        $definitions = StoreSettingDefinitions::forGroup($activeTab);

        if ($definitions === []) {
            $session->flash('error', 'Nothing to save on that tab.');
            return Response::redirect('/commerce/ca-admin/settings?tab=' . urlencode($activeTab));
        }

        foreach ($definitions as $key => $def) {
            $field = $this->fieldName($key);
            $posted = $request->input($field, null);

            if ($def['type'] === 'boolean') {
                // Unchecked checkbox sends nothing. Absence means
                // false. Presence (any value) means true.
                $settings->set($key, $posted === null ? '0' : '1', $userId);
                continue;
            }

            if ($posted === null) {
                // Non-boolean field was not in the POST. Skip.
                continue;
            }

            $settings->set($key, (string) $posted, $userId);
        }

        $session->flash('success', 'Settings saved.');
        return Response::redirect('/commerce/ca-admin/settings?tab=' . urlencode($activeTab));
    }

    /** @param array<string,string> $params */
    public function addCustom(Request $request, array $params = []): Response
    {
        $settings = $this->container->get(StoreSettingsRepository::class);
        $auth     = $this->container->get(Auth::class);
        $session  = $this->container->get(Session::class);

        $userId = $auth->user()?->id ?? null;
        $key    = trim((string) $request->input('custom_key', ''));
        $value  = (string) $request->input('custom_value', '');

        if ($key === '') {
            $session->flash('error', 'Setting key is required.');
            return Response::redirect('/commerce/ca-admin/settings?tab=advanced');
        }

        if (StoreSettingDefinitions::has($key)) {
            $session->flash(
                'error',
                'That key is a built-in setting. Edit it on the '
                . StoreSettingDefinitions::groupLabel(
                    (string) StoreSettingDefinitions::DEFINITIONS[$key]['group']
                )
                . ' tab instead.'
            );
            return Response::redirect('/commerce/ca-admin/settings?tab=advanced');
        }

        $settings->set($key, $value, $userId);
        $session->flash('success', 'Custom setting saved.');
        return Response::redirect('/commerce/ca-admin/settings?tab=advanced');
    }

    /** @param array<string,string> $params */
    public function deleteCustom(Request $request, array $params = []): Response
    {
        $settings = $this->container->get(StoreSettingsRepository::class);
        $session  = $this->container->get(Session::class);

        $key = trim((string) $request->input('custom_key', ''));

        if ($key === '' || StoreSettingDefinitions::has($key)) {
            $session->flash('error', 'That key cannot be deleted from here.');
            return Response::redirect('/commerce/ca-admin/settings?tab=advanced');
        }

        $settings->delete($key);
        $session->flash('success', 'Custom setting removed.');
        return Response::redirect('/commerce/ca-admin/settings?tab=advanced');
    }

    /**
     * Convert a dotted setting key into a safe form field name.
     * Dots become double underscores so POST keys remain
     * URL-safe and match typical HTML form conventions.
     */
    private function fieldName(string $key): string
    {
        return str_replace('.', '__', $key);
    }
}
