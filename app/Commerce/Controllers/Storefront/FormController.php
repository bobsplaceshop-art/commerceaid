<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Commerce\Models\Customer;
use CorporateAid\Commerce\Models\Form;
use CorporateAid\Commerce\Repositories\FormFieldRepository;
use CorporateAid\Commerce\Repositories\FormRepository;
use CorporateAid\Commerce\Services\CustomerAuthService;
use CorporateAid\Commerce\Services\FormSubmissionService;
use CorporateAid\Commerce\Support\StorefrontController;
use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;

/**
 * Public form surface. Stage 009 Session 3.
 *
 * Two actions:
 *
 *   show()    GET /forms/{slug}
 *   submit()  POST /forms/{slug}
 *
 * Both resolve the form by slug and require it to be in
 * `published` status. A draft or archived form, or an
 * unknown slug, renders 404.
 *
 * The form's `requires_auth` flag gates the surface for
 * logged-in customers only. A guest who hits a
 * requires_auth form is sent to /account/login with the
 * form URL as `intended`, matching the pattern used by
 * the account surface.
 *
 * The controller reuses the CustomFields InputType
 * classes for rendering, validation, and casting. The
 * `cf[...]` name prefix that those classes emit is
 * accepted as-is; the submission service reads it and
 * maps it to the field keys.
 *
 * See docs/stages/009_forms-plan.md.
 */
final class FormController extends StorefrontController
{
    /**
     * @param array<string,string> $params
     */
    public function show(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        if ($slug === '') {
            throw HttpException::notFound();
        }

        $forms = $this->container->get(FormRepository::class);
        $form  = $forms->findPublishedBySlug($slug);
        if ($form === null) {
            throw HttpException::notFound();
        }

        $customer = $this->container->get(CustomerAuthService::class)->user();

        if ($form->requiresAuth && $customer === null) {
            return Response::redirect(
                '/account/login?intended=' . urlencode('/forms/' . $slug)
            );
        }

        return $this->renderForm($form, $customer, [], [], null);
    }

    /**
     * @param array<string,string> $params
     */
    public function submit(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        if ($slug === '') {
            throw HttpException::notFound();
        }

        $forms = $this->container->get(FormRepository::class);
        $form  = $forms->findPublishedBySlug($slug);
        if ($form === null) {
            throw HttpException::notFound();
        }

        $customer = $this->container->get(CustomerAuthService::class)->user();

        if ($form->requiresAuth && $customer === null) {
            return Response::redirect(
                '/account/login?intended=' . urlencode('/forms/' . $slug)
            );
        }

        $service = $this->container->get(FormSubmissionService::class);
        $posted  = $_POST;
        $context = [
            'ip'          => $this->clientIp($request),
            'user_agent'  => (string) ($request->header('User-Agent') ?? ''),
            'customer_id' => $customer?->id,
        ];

        $result = $service->submit($form, $posted, $context);

        if ($result['ok']) {
            // Silent honeypot trip or a real success. Both
            // render the success message. The stored value
            // of `silent` distinguishes them at the log
            // layer (a future addition); the customer sees
            // no difference, and neither does a bot.
            $successMessage = $form->successMessage !== null
                && trim($form->successMessage) !== ''
                ? $form->successMessage
                : 'Thank you. Your message has been sent.';

            return $this->renderForm($form, $customer, [], [], $successMessage);
        }

        // Validation failed. Re-render the form with the
        // posted values and the per-field error map. The
        // `$old` array is populated from the submitted cf[]
        // so each field renders with the value the customer
        // just typed.
        $errors = $result['errors'] ?? [];
        $old    = $this->collectOldValues($posted);

        return $this->renderForm($form, $customer, $old, $errors, null);
    }

    /**
     * Render the form. Single method for both GET and POST
     * failure paths so the view shape is identical.
     *
     * @param array<string,string> $old    field_key => value
     * @param array<string,string> $errors field_key => message
     */
    private function renderForm(
        Form $form,
        ?Customer $customer,
        array $old,
        array $errors,
        ?string $successMessage
    ): Response {
        $fields = $this->container->get(FormFieldRepository::class);
        $list   = $fields->listActiveForForm($form->id);

        $steps = [];
        foreach ($list as $field) {
            $steps[$field->step] = true;
        }
        $stepNumbers = array_keys($steps);
        sort($stepNumbers);

        return $this->render('storefront.forms.show', [
            'form'           => $form,
            'fields'         => $list,
            'steps'          => $stepNumbers,
            'old'            => $old,
            'errors'         => $errors,
            'successMessage' => $successMessage,
            'customer'       => $customer,
        ]);
    }

    /**
     * Flatten $_POST['cf'] into a field_key => value map
     * for the view's `old` input repopulation. Multi-value
     * fields keep their array shape so the option-bearing
     * types render the right selected state.
     *
     * @param array<string,mixed> $posted
     * @return array<string,mixed>
     */
    private function collectOldValues(array $posted): array
    {
        $cf = $posted['cf'] ?? [];
        if (!is_array($cf)) return [];

        $out = [];
        foreach ($cf as $key => $value) {
            $out[(string) $key] = $value;
        }
        return $out;
    }

    /**
     * The best-effort client IP. Trusts X-Forwarded-For when
     * present (reverse proxy). Falls back to REMOTE_ADDR.
     */
    private function clientIp(Request $request): ?string
    {
        $forwarded = (string) ($request->header('X-Forwarded-For') ?? '');
        if ($forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);
            if ($first !== '') return $first;
        }
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return $remote !== '' ? $remote : null;
    }
}
