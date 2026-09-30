<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Repositories\StoreRepository;
use CorporateAid\Commerce\Repositories\StoreSettingsRepository;
use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Repositories\MediaRepository;
use CorporateAid\Commerce\Support\MediaVariantSet;
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
            $target = (string) ($def['target'] ?? 'key_value');

            if ($target === 'column') {
                $this->saveColumnSetting($key, $def, $request);
                continue;
            }

            $field = $this->fieldName($key);
            $posted = $request->input($field, null);

            if (($def['type'] ?? 'string') === 'boolean') {
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

    /**
     * Save a setting whose target is a column on
     * commerce_stores rather than the key-value settings
     * table.
     *
     * Handles three field shapes:
     *   file      - multipart upload via MediaRepository
     *   boolean   - hidden input + checkbox pattern
     *   string    - plain text or hex color
     *
     * @param array<string,mixed> $def
     */
    private function saveColumnSetting(
        string $key,
        array $def,
        Request $request
    ): void {
        $column = (string) ($def['column'] ?? '');
        if ($column === '') {
            return;
        }

        $storeContext = $this->container->get(StoreContext::class);
        if (!$storeContext->has()) {
            return;
        }
        $storeId = $storeContext->id();

        $type = (string) ($def['type'] ?? 'string');

        // ---- File field ----
        if ($type === 'file') {
            $this->saveFileColumn($storeId, $column, $def, $key, $request);
            return;
        }

        // ---- Scalar (string, boolean) ----
        $field = $this->fieldName($key);
        $posted = $request->input($field, null);
        if ($posted === null) {
            return;
        }

        $value = trim((string) $posted);
        $this->container->get(StoreRepository::class)
            ->updateColumn($storeId, $column, $value === '' ? null : $value);
    }

    /**
     * Handle the upload flow for a file-typed column setting.
     *
     * Three possible actions based on the POST:
     *   - A new file was uploaded: process via MediaRepository,
     *     set the column to the new file ID.
     *   - The remove flag was set: clear the column.
     *   - Neither: leave the column unchanged.
     *
     * @param array<string,mixed> $def
     */
    private function saveFileColumn(
        int $storeId,
        string $column,
        array $def,
        string $key,
        Request $request
    ): void {
        $field     = $this->fieldName($key);
        $removeKey = $field . '__remove';
        $uploadKey = $field . '__upload';

        // Removal takes priority. If the operator clicks Remove,
        // they are not uploading a replacement in the same
        // request.
        if ($request->input($removeKey, null) !== null) {
            $this->container->get(StoreRepository::class)
                ->updateColumn($storeId, $column, null);
            return;
        }

        $file = $request->file($uploadKey);
        if ($file === null) {
            // Nothing to do.
            return;
        }

        $context = (string) ($def['media_context'] ?? MediaVariantSet::CONTEXT_GENERAL);
        $maxMb   = (int) ($def['max_mb'] ?? 5);

        $auth   = $this->container->get(\CorporateAid\Core\Auth::class);
        $userId = $auth->user()?->id;

        $media = $this->container->get(MediaRepository::class);

        // Category mapping. Store logo and favicon go to the
        // 'store-branding' bucket. Other contexts get their
        // context name.
        $category = match ($context) {
            MediaVariantSet::CONTEXT_STORE_LOGO,
            MediaVariantSet::CONTEXT_STORE_FAVICON => 'store-branding',
            default => 'general',
        };

        $result = $media->storeFromUpload(
            $file,
            $category,
            $context,
            $maxMb,
            $userId
        );

        if (is_array($result)) {
            // Validation or processing failed. Flash the errors
            // and leave the column unchanged.
            $session = $this->container->get(\CorporateAid\Core\Session::class);
            $session->flash('error', implode(' ', $result));
            return;
        }

        $this->container->get(StoreRepository::class)
            ->updateColumn($storeId, $column, $result->file->id);
    }
}
