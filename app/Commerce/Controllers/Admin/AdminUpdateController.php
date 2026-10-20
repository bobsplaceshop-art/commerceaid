<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Core\Migrator;
use CorporateAid\Commerce\Repositories\StoreSettingsRepository;

final class AdminUpdateController extends AdminController
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $migrator = $this->container->get(Migrator::class);
        $pending  = $migrator->getPendingMigrations();

        $settings = $this->container->get(StoreSettingsRepository::class);
        $scheduledAt = $settings->string('system.update.scheduled_at', '');
        $storeStatus = $settings->string('system.store_status', 'live');
        $statusMessage = $settings->string('system.store_status_message', '');

        return $this->render('admin.update.index', [
            'title'         => 'System Update',
            'pending'       => $pending,
            'scheduledAt'   => $scheduledAt,
            'storeStatus'   => $storeStatus,
            'statusMessage' => $statusMessage,
        ]);
    }

    /** @param array<string,string> $params */
    public function run(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $migrator = $this->container->get(Migrator::class);

        try {
            $ran = $migrator->run();

            if (empty($ran)) {
                $session->flash('info', 'No pending migrations to run.');
            } else {
                $count = count($ran);
                $session->flash('success', "Successfully applied {$count} migration(s).");

                // Clear the scheduled time since we just ran it manually
                $settings = $this->container->get(StoreSettingsRepository::class);
                $settings->set('system.update.scheduled_at', '');
                
                // Clear the pending flag for the redirect
                \CorporateAid\Core\App::$hasPendingUpdates = false;
            }
        } catch (\Throwable $e) {
            $session->flash('error', 'Migration failed: ' . $e->getMessage());
        }

        return Response::redirect('/commerce/ca-admin/update');
    }

    /** @param array<string,string> $params */
    public function saveStatus(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $status = (string) $request->input('store_status', 'live');
        $message = trim((string) $request->input('status_message', ''));

        if (!in_array($status, ['live', 'coming_soon', 'maintenance'], true)) {
            $status = 'live';
        }

        $settings = $this->container->get(StoreSettingsRepository::class);
        $settings->set('system.store_status', $status);
        $settings->set('system.store_status_message', $message);

        $session->flash('success', 'Storefront status updated successfully.');
        return Response::redirect('/commerce/ca-admin/update');
    }

    /** @param array<string,string> $params */
    public function schedule(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $scheduledAt = trim((string) $request->input('scheduled_at', ''));

        if ($scheduledAt !== '') {
            $ts = strtotime($scheduledAt);
            if ($ts === false) {
                $session->flash('error', 'Invalid date/time format.');
                return Response::redirect('/commerce/ca-admin/update');
            }
            // Ensure it's stored in the format the StorefrontController expects
            $scheduledAt = date('Y-m-d H:i:s', $ts);
        }

        $settings = $this->container->get(StoreSettingsRepository::class);
        $settings->set('system.update.scheduled_at', $scheduledAt);

        $session->flash('success', 'Update schedule saved.');
        return Response::redirect('/commerce/ca-admin/update');
    }
}
