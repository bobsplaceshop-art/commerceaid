<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Repositories\StoreRepository;
use CorporateAid\Commerce\Repositories\StoreSettingsRepository;
use CorporateAid\Commerce\Repositories\FontRepository;
use CorporateAid\Commerce\Services\Fonts\FontDownloader;
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

        // Populate dynamic options for the theme selector.
        // The definitions array is static, but the themes are
        // discovered at runtime from themes/.
        if ($activeTab === StoreSettingDefinitions::GROUP_BRANDING
            && isset($definitions['store.theme_slug'])
        ) {
            try {
                $registry = $this->container->get(
                    \CorporateAid\Commerce\Services\Theme\ThemeRegistry::class
                );
                $themeOptions = [];
                foreach ($registry->all() as $theme) {
                    $slug = (string) ($theme['slug'] ?? '');
                    $name = (string) ($theme['name'] ?? $slug);
                    if ($slug !== '') {
                        $themeOptions[$slug] = $name;
                    }
                }
                $definitions['store.theme_slug']['options'] = $themeOptions;
            } catch (\Throwable $e) {
                // If the registry fails, leave options empty.
                // The select will just render blank.
            }
        }

        // Column-target fields read their current value from
        // commerce_stores, not from the key-value table.
        // Merge those into $values so the view sees them under
        // the same key the definition uses.
        if ($activeTab !== 'advanced') {
            $values = $this->mergeColumnValues($activeTab, $values, $definitions);
        }

        // Advanced tab: custom settings only.
        $custom = $activeTab === 'advanced'
            ? $settings->customSettings()
            : [];

        // Payments tab needs a provider list on top of the
        // credential fields. The providers come from the
        // registry; the enabled state comes from the payment
        // methods table. Both are read here so the view stays
        // a pure renderer.
        $paymentProviders = [];
        $paymentMethods   = [];
        if ($activeTab === StoreSettingDefinitions::GROUP_PAYMENTS) {
            $registry = $this->container->get(
                \CorporateAid\Commerce\Services\Payment\PaymentProviderRegistry::class
            );
            $paymentProviders = $registry->labels();

            $methodRepo = $this->container->get(
                \CorporateAid\Commerce\Repositories\PaymentMethodRepository::class
            );
            foreach ($methodRepo->listForStore() as $row) {
                $paymentMethods[(string) $row['provider_key']] = [
                    'is_enabled' => (bool) $row['is_enabled'],
                    'sort_order' => (int) $row['sort_order'],
                ];
            }
        }

        return $this->render('admin.settings.index', [
            'title'            => 'Store Settings',
            'groups'           => $groups,
            'activeTab'        => $activeTab,
            'definitions'      => $definitions,
            'values'           => $values,
            'custom'           => $custom,
            'paymentProviders' => $paymentProviders,
            'paymentMethods'   => $paymentMethods,
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

        // -------------------------------------------------------------
        // Pre-save validation for the Popup tab. The ends_at date
        // must not be earlier than starts_at. Reject before any
        // write so the merchant sees the error on the same page.
        // -------------------------------------------------------------
        if ($activeTab === StoreSettingDefinitions::GROUP_POPUP) {
            $startsRaw = (string) $request->input($this->fieldName('popup.starts_at'), '');
            $endsRaw   = (string) $request->input($this->fieldName('popup.ends_at'), '');

            $starts = trim($startsRaw);
            $ends   = trim($endsRaw);

            if ($starts !== '' && $ends !== '' && $ends < $starts) {
                $session->flash('error', 'The popup end date must be on or after the start date.');
                return Response::redirect('/commerce/ca-admin/settings?tab=' . urlencode($activeTab));
            }
        }

        $savedCount = 0;
        $fileErrors = [];

        foreach ($definitions as $key => $def) {
            $target = (string) ($def['target'] ?? 'key_value');

            if ($target === 'column') {
                $outcome = $this->saveColumnSetting($key, $def, $request);
                if ($outcome === 'saved') {
                    $savedCount++;
                } elseif ($outcome === 'skipped') {
                    // Nothing to do for this field. Not an
                    // error. Do not flash.
                } else {
                    // Any other string is an error message.
                    $fileErrors[] = $outcome;
                }
                continue;
            }

            $field = $this->fieldName($key);
            $posted = $request->input($field, null);

            if (($def['type'] ?? 'string') === 'boolean') {
                // Unchecked checkbox sends nothing. Absence means
                // false. Presence (any value) means true.
                $settings->set($key, $posted === null ? '0' : '1', $userId);
                $savedCount++;
                continue;
            }

            // File fields on the key-value target: the hidden
            // input carries the file ID from the media picker.
            // We just save it as a plain string, like any other.
            if (($def['type'] ?? 'string') === 'file') {
                $removeKey = $field . '__remove';

                if ($request->input($removeKey, null) !== null) {
                    $settings->set($key, '', $userId);
                    $savedCount++;
                    continue;
                }

                // Fall through to the standard string save.
                // The picker writes the ID into the main input.
            }

            if ($posted === null) {
                // Non-boolean, non-file field was not in the POST. Skip.
                continue;
            }

            $settings->set($key, (string) $posted, $userId);
            $savedCount++;
        }

        // Payments tab has a side effect: the provider
        // enable toggles live in commerce_store_payment_methods,
        // not in the key-value settings table. Read them
        // from the POST and write each one through the
        // repository. A provider not present in the POST is
        // disabled - the checkbox is unchecked.
        if ($activeTab === StoreSettingDefinitions::GROUP_PAYMENTS) {
            $registry = $this->container->get(
                \CorporateAid\Commerce\Services\Payment\PaymentProviderRegistry::class
            );
            $methodRepo = $this->container->get(
                \CorporateAid\Commerce\Repositories\PaymentMethodRepository::class
            );

            $existing = [];
            foreach ($methodRepo->listForStore() as $row) {
                $existing[(string) $row['provider_key']] = (int) $row['sort_order'];
            }

            $sortOrder = 0;
            foreach ($registry->knownKeys() as $providerKey) {
                $fieldName = 'payments__' . $providerKey . '__enabled';
                $isEnabled = $request->input($fieldName) !== null;

                // Preserve existing sort_order when present;
                // assign fresh position for new keys in
                // registry order.
                $sort = $existing[$providerKey] ?? $sortOrder;
                $methodRepo->set($providerKey, $isEnabled, $sort);
                $sortOrder++;
                $savedCount++;
            }
        }

        // Fonts tab has a side effect: after the slug
        // selections are written, fetch any missing WOFF2
        // files. Downloads happen once per slug per store
        // install. A failure here does NOT roll back the
        // selection - the merchant keeps their choice and
        // sees a warning. The storefront falls back to the
        // platform font stack for any slot that did not
        // download.
        if ($activeTab === StoreSettingDefinitions::GROUP_FONTS) {
            foreach ($this->downloadSelectedFonts() as $fontError) {
                $fileErrors[] = $fontError;
            }
        }

        // Flash a message that reflects what actually happened.
        if ($fileErrors !== []) {
            $session->flash('error', implode(' ', $fileErrors));
        } elseif ($savedCount > 0) {
            $session->flash('success', 'Settings saved.');
        } else {
            $session->flash('info', 'No changes to save.');
        }

        return Response::redirect('/commerce/ca-admin/settings?tab=' . urlencode($activeTab));
    }

    /**
     * Test a single payment provider's connection.
     *
     * Route: POST /commerce/ca-admin/settings/payments/test
     *
     * A "connection test" for a payment provider has no
     * single network call that proves it works. What it
     * proves here:
     *
     *   1. The provider class resolves through the registry.
     *   2. The provider reports isConfigured() = true for
     *      this store.
     *
     * Both are local checks. A full sandbox round trip is a
     * separate concern - the merchant makes one real test
     * payment. This button answers "are the credentials in
     * the DB and readable by the provider", which is the
     * question that matters when the merchant has just
     * pasted in a new key.
     *
     * @param array<string,string> $params
     */
    public function testPaymentProvider(Request $request, array $params = []): Response
    {
        $session  = $this->container->get(Session::class);
        $providerKey = trim((string) $request->input('provider_key', ''));

        if ($providerKey === '') {
            $session->flash('error', 'No provider selected.');
            return Response::redirect('/commerce/ca-admin/settings?tab=payments');
        }

        $registry = $this->container->get(
            \CorporateAid\Commerce\Services\Payment\PaymentProviderRegistry::class
        );
        $provider = $registry->resolve($providerKey);
        if ($provider === null) {
            $session->flash(
                'error',
                'The provider class for "' . $providerKey . '" is not available in this build.'
            );
            return Response::redirect('/commerce/ca-admin/settings?tab=payments');
        }

        $ctx = $this->container->get(\CorporateAid\Commerce\Context\StoreContext::class);
        if (!$provider->isConfigured($ctx)) {
            $session->flash(
                'error',
                $provider->label() . ' is not fully configured. '
                . 'Fill in the credential fields below and save before testing.'
            );
            return Response::redirect('/commerce/ca-admin/settings?tab=payments');
        }

        // Real outbound probe. The provider issues one
        // read-only API call with the stored credentials
        // and returns a merchant-readable result.
        $result = $provider->testConnection();
        $ok     = (bool) ($result['ok'] ?? false);
        $message = (string) ($result['message'] ?? 'No result.');

        $session->flash($ok ? 'success' : 'error', $message);

        return Response::redirect('/commerce/ca-admin/settings?tab=payments');
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
     * @return string 'saved' on success, 'skipped' if nothing
     *                was posted, or an error message string
     */
    private function saveColumnSetting(
        string $key,
        array $def,
        Request $request
    ): string {
        $column = (string) ($def['column'] ?? '');
        if ($column === '') {
            return 'skipped';
        }

        $storeContext = $this->container->get(StoreContext::class);
        if (!$storeContext->has()) {
            return 'skipped';
        }
        $storeId = $storeContext->id();

        $type = (string) ($def['type'] ?? 'string');

        // ---- File field ----
        if ($type === 'file') {
            return $this->saveFileColumn($storeId, $column, $def, $key, $request);
        }

        // ---- Scalar (string, boolean) ----
        $field = $this->fieldName($key);
        $posted = $request->input($field, null);
        if ($posted === null) {
            return 'skipped';
        }

        $value = trim((string) $posted);
        $this->container->get(StoreRepository::class)
            ->updateColumn($storeId, $column, $value === '' ? null : $value);
        return 'saved';
    }

    /**
     * Download every WOFF2 weight for the store's currently
     * selected fonts. Called after the Fonts tab save has
     * written the slug columns.
     *
     * Idempotent: files already on disk are skipped inside
     * FontDownloader::download(). A second save of the same
     * slug is free.
     *
     * The return value is a list of human-readable error
     * strings, empty on success. The caller adds them to
     * the save flash. Failures do not roll back the slug
     * selection.
     *
     * @return array<int,string>
     */
    private function downloadSelectedFonts(): array
    {
        $storeContext = $this->container->get(StoreContext::class);
        if (!$storeContext->has()) {
            return ['Font downloads skipped: no store context.'];
        }

        try {
            $fontRepo = $this->container->get(FontRepository::class);
            $downloader = $this->container->get(FontDownloader::class);
        } catch (\Throwable $e) {
            return ['Font downloader unavailable: ' . $e->getMessage()];
        }

        $selections = $fontRepo->getSelections($storeContext->id());
        $errors = [];

        foreach ($selections as $slot => $slug) {
            if ($slug === null || $slug === '') {
                continue;
            }

            try {
                $result = $downloader->download($slug);
            } catch (\Throwable $e) {
                $errors[] = "Font '{$slug}' ({$slot}): " . $e->getMessage();
                continue;
            }

            foreach ($result['errors'] as $err) {
                $errors[] = "Font '{$slug}' ({$slot}): {$err}";
            }
        }

        return $errors;
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
     * @return string 'saved' on success, 'skipped' if nothing
     *                was posted, or an error message string
     */
    private function saveFileColumn(
        int $storeId,
        string $column,
        array $def,
        string $key,
        Request $request
    ): string {
        $field     = $this->fieldName($key);
        $removeKey = $field . '__remove';
        $uploadKey = $field . '__upload';

        // Removal takes priority. If the operator clicks Remove,
        // they are not uploading a replacement in the same
        // request.
        if ($request->input($removeKey, null) !== null) {
            $this->container->get(StoreRepository::class)
                ->updateColumn($storeId, $column, null);
            return 'saved';
        }

        $file = $request->file($uploadKey);
        if ($file === null) {
            // Nothing was uploaded and nothing was removed.
            // This is the common case for a save that only
            // touched text fields on the same tab.
            return 'skipped';
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
            $storeId,
            $category,
            $context,
            $maxMb,
            $userId
        );

        if (is_array($result)) {
            // Validation or processing failed. Return the
            // first error as the status. The caller flashes it.
            return (string) ($result[0] ?? 'The file could not be processed.');
        }

        $this->container->get(StoreRepository::class)
            ->updateColumn($storeId, $column, $result->file->id);
        return 'saved';
    }

    /**
     * Merge commerce_stores column values into the $values map
     * so the settings view sees them under the definition key.
     *
     * Only runs when the active tab contains at least one
     * column-target field. Reads the current store row once.
     *
     * @param array<string,string> $values
     * @param array<string,array<string,mixed>> $definitions
     * @return array<string,string>
     */
    private function mergeColumnValues(
        string $activeTab,
        array $values,
        array $definitions
    ): array {
        $hasColumnField = false;
        foreach ($definitions as $def) {
            if (($def['target'] ?? 'key_value') === 'column') {
                $hasColumnField = true;
                break;
            }
        }
        if (!$hasColumnField) {
            return $values;
        }

        $storeContext = $this->container->get(StoreContext::class);
        if (!$storeContext->has()) {
            return $values;
        }

        $store = $this->container->get(StoreRepository::class)
            ->findById($storeContext->id());
        if ($store === null) {
            return $values;
        }

        foreach ($definitions as $key => $def) {
            if (($def['target'] ?? 'key_value') !== 'column') {
                continue;
            }
            $column = (string) ($def['column'] ?? '');
            if ($column === '') {
                continue;
            }

            // Map the column name to the Store model property.
            // $column is snake_case; the model is camelCase.
            $property = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $column))));
            if (!property_exists($store, $property)) {
                continue;
            }
            $value = $store->{$property};
            $values[$key] = $value === null ? '' : (string) $value;
        }

        return $values;
    }
}
