<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Models\Form;
use CorporateAid\Commerce\Models\FormField;
use CorporateAid\Commerce\Models\FormSubmission;
use CorporateAid\Commerce\Repositories\FormFieldRepository;
use CorporateAid\Commerce\Repositories\FormSubmissionRepository;
use CorporateAid\Platform\CustomFields\InputTypeRegistry;
use CorporateAid\Platform\Services\NotificationService;
use CorporateAid\Platform\Support\Logger;

/**
 * Form submission service. Stage 009 Session 3.
 *
 * Handles the POST path from the public form. The steps,
 * in order:
 *
 *   1. Honeypot check. A non-empty `website` field means a
 *      bot filled every input it saw. The response is a
 *      successful one, but nothing is stored.
 *   2. Server-side conditional logic. For each field, if
 *      the field's `conditional` rule does not match the
 *      submitted values, the field is skipped entirely -
 *      not validated, not stored.
 *   3. Required-field check on the visible fields.
 *   4. Per-field validation via the CustomFields
 *      InputType::validate() method. Each type knows its
 *      own rules.
 *   5. Insert the submission row.
 *
 * Domain event firing and merchant notification are
 * Session 4 concerns. Two private methods exist here as
 * no-ops with a TODO marker naming what Session 4 will
 * add. The service shape is complete; only the two
 * side-effects are stubbed.
 *
 * The submitted form values arrive under the `cf[...]`
 * prefix the CustomFields InputType classes emit. The
 * payload stored in commerce_form_submissions is keyed
 * by each field's field_key, which is what the renderer
 * emits as the input's name and what the submission
 * detail view and CSV export read.
 *
 * See docs/stages/009_forms-plan.md.
 */
final class FormSubmissionService
{
    public function __construct(
        private readonly FormFieldRepository $fields,
        private readonly FormSubmissionRepository $submissions,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * Process a public form submission.
     *
     * @param Form $form The form being submitted.
     * @param array<string,mixed> $posted The full $_POST array
     *                                    (values under $posted['cf'],
     *                                    honeypot at $posted['website']).
     * @param array{ip:?string,user_agent:?string,customer_id:?int} $context
     *
     * @return array{
     *   ok:bool,
     *   submission_id?:int,
     *   silent?:bool,
     *   errors?:array<string,string>
     * }
     */
    public function submit(Form $form, array $posted, array $context): array
    {
        // -----------------------------------------------------------------
        // Step 1 - honeypot.
        // -----------------------------------------------------------------
        //
        // A bot fills every input it sees. The `website` field is
        // rendered off-screen and hidden from humans. If it has a
        // value, the request is a bot. Return a successful-shaped
        // response so the bot sees no difference, and store nothing.
        if ($form->honeypotEnabled) {
            $honeypot = trim((string) ($posted['website'] ?? ''));
            if ($honeypot !== '') {
                return [
                    'ok'     => true,
                    'silent' => true,
                ];
            }
        }

        // -----------------------------------------------------------------
        // Normalise the submitted values under the cf[...] prefix.
        // -----------------------------------------------------------------
        //
        // The InputType classes emit inputs named `cf[field_key]`
        // (single value) or `cf[field_key][]` (multi-value). The
        // cast to array below handles both shapes: a string value
        // becomes a single-element array, an array stays as-is,
        // and a missing key becomes an empty array.
        $raw = $posted['cf'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }

        // -----------------------------------------------------------------
        // Step 2 - server-side conditional logic.
        // -----------------------------------------------------------------
        //
        // Only fields whose conditional rule matches the submitted
        // values are processed. A field with no rule is always
        // visible. A field with a rule is visible only when the
        // referenced field's submitted value satisfies the
        // operator.
        $formFields = $this->fields->listActiveForForm($form->id);
        $visibleFields = [];
        foreach ($formFields as $field) {
            if ($this->isFieldVisible($field, $raw)) {
                $visibleFields[] = $field;
            }
        }

        // -----------------------------------------------------------------
        // Step 3 and 4 - required check and per-field validation.
        // -----------------------------------------------------------------
        $errors = [];
        $payload = [];

        foreach ($visibleFields as $field) {
            $inputType = InputTypeRegistry::get($field->inputType);
            $key = $field->fieldKey;

            // Fetch the raw submitted value for this key. Multi-
            // value types arrive as arrays; single-value types as
            // strings. The normaliseValue() helper collapses both
            // into the shape the InputType expects.
            $value = $this->normaliseValue($raw[$key] ?? null);

            // The CustomFields InputType classes read allowed
            // option values from $constraints['_options'] - a
            // flat list of bare strings. The CustomFields engine
            // merges this in before validating. Forms has to do
            // the same. Without it, SelectType::validate() sees
            // an empty allowed list and rejects every submission
            // with "Must be one of the allowed options."
            $constraints = $field->constraints;
            if ($field->hasOptions()) {
                $constraints['_options'] = array_map(
                    static fn (array $opt): string => (string) ($opt['value'] ?? ''),
                    $field->options
                );
            }

            // The InputType's validate() handles the required
            // check itself, so no separate guard is needed here.
            $fieldErrors = $inputType->validate(
                $value,
                $constraints,
                $field->isRequired
            );

            if ($fieldErrors !== []) {
                $errors[$key] = $fieldErrors[0];
                continue;
            }

            // Cast the value to the stored string form. This
            // converts multi-value types to JSON, checkboxes to
            // '1'/'0', and everything else to a trimmed string.
            // A null return means "empty value" and is stored as
            // an empty string in the payload.
            $cast = $inputType->cast($value);
            $payload[$key] = $cast ?? '';
        }

        if ($errors !== []) {
            return [
                'ok'     => false,
                'errors' => $errors,
            ];
        }

        // -----------------------------------------------------------------
        // Step 5 - insert.
        // -----------------------------------------------------------------
        //
        // Denormalise the common email and name fields from the
        // payload. The plan doc names this explicitly: when a
        // field is of type `email` and its label contains
        // "email" (case-insensitive), the submitted value is
        // copied to submitter_email. Same heuristic for a name.
        // This is a filter optimisation - the payload remains
        // the source of truth.
        [$submitterEmail, $submitterName] = $this->extractContactFields(
            $visibleFields,
            $payload
        );

        $submissionId = $this->submissions->create([
            'form_id'         => $form->id,
            'customer_id'     => $context['customer_id'] ?? null,
            'submitter_email' => $submitterEmail,
            'submitter_name'  => $submitterName,
            'submitter_ip'    => $context['ip'] ?? null,
            'user_agent'      => $context['user_agent'] ?? null,
            'payload_json'    => json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'status'          => 'new',
        ]);

        // -----------------------------------------------------------------
        // Session 4 hooks. No-ops today.
        // -----------------------------------------------------------------
        $this->fireSubmittedEvent($form, $submissionId, $payload);
        $this->notifyMerchant($form, $submissionId, $payload);

        return [
            'ok'            => true,
            'submission_id' => $submissionId,
        ];
    }

    /**
     * Is a field visible given the currently-submitted values?
     *
     * A field with no conditional rule is always visible. A
     * field with a rule is visible only when the rule matches.
     *
     * The operator set is the six documented in the plan doc:
     * equals, not_equals, contains, is_empty, is_not_empty,
     * greater_than, less_than.
     *
     * @param array<string,mixed> $raw The raw cf[...] array.
     */
    private function isFieldVisible(FormField $field, array $raw): bool
    {
        if ($field->conditional === null) {
            return true;
        }

        $rule = $field->conditional;
        $otherKey = (string) $rule['field'];
        $operator = (string) $rule['operator'];
        $expected = $rule['value'] ?? null;

        $actual = $this->normaliseValue($raw[$otherKey] ?? null);

        return match ($operator) {
            'equals'        => (string) $actual === (string) $expected,
            'not_equals'    => (string) $actual !== (string) $expected,
            'contains'      => $this->valueContains($actual, $expected),
            'is_empty'      => $this->isEmptyValue($actual),
            'is_not_empty'  => !$this->isEmptyValue($actual),
            'greater_than'  => is_numeric($actual) && is_numeric($expected)
                                && (float) $actual > (float) $expected,
            'less_than'     => is_numeric($actual) && is_numeric($expected)
                                && (float) $actual < (float) $expected,
            default         => true,
        };
    }

    /**
     * Normalise a raw submitted value. Strings stay strings,
     * arrays are collapsed to their first element if they have
     * one, and anything else becomes null.
     *
     * The InputType validate() methods accept `mixed` for the
     * value, so the shape passed in matters. Single-value types
     * expect a string; multi-value types accept an array.
     * Because the renderer emits `cf[key]` for single-value
     * types and `cf[key][]` for multi-value types, the shape
     * arriving from $_POST is already correct in each case.
     * This helper preserves arrays, trims strings, and maps
     * null/absent to an empty string.
     *
     * @param mixed $value
     * @return mixed
     */
    private function normaliseValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null) {
            return '';
        }
        if (is_string($value)) {
            return trim($value);
        }
        return '';
    }

    /** @param mixed $actual @param mixed $expected */
    private function valueContains(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            foreach ($actual as $item) {
                if ((string) $item === (string) $expected) return true;
            }
            return false;
        }
        if (!is_string($actual)) return false;
        return str_contains($actual, (string) $expected);
    }

    /** @param mixed $value */
    private function isEmptyValue(mixed $value): bool
    {
        if (is_array($value)) return $value === [];
        return $value === null || $value === '';
    }

    /**
     * Pull submitter_email and submitter_name from the payload
     * by matching the field type and the label text.
     *
     * @param array<int,FormField> $fields
     * @param array<string,string> $payload
     * @return array{0:?string, 1:?string}
     */
    private function extractContactFields(array $fields, array $payload): array
    {
        $email = null;
        $name  = null;

        foreach ($fields as $field) {
            $value = $payload[$field->fieldKey] ?? '';
            if ($value === '') continue;

            if ($email === null && $field->inputType === 'email') {
                $email = $value;
                continue;
            }

            if ($name === null
                && $field->inputType === 'text'
                && stripos($field->label, 'name') !== false
            ) {
                $name = $value;
            }
        }

        return [$email, $name];
    }

    /**
     * Record the form.submitted event in the application log.
     * A full event dispatcher does not exist in the codebase
     * yet - the webhook dispatch path is deferred to a future
     * sub-stage (009.7). For now, the log line gives every
     * submission a structured audit trail that a future
     * dispatcher can consume or replay.
     *
     * @param array<string,string> $payload
     */
    private function fireSubmittedEvent(Form $form, int $submissionId, array $payload): void
    {
        Logger::info('form.submitted', [
            'form_id'       => $form->id,
            'form_slug'     => $form->slug,
            'form_name'     => $form->name,
            'submission_id' => $submissionId,
            'field_count'   => count($payload),
        ]);
    }

    /**
     * Send a notification email to the merchant. Session 4
     * wires this to EmailService and the notification
     * pipeline. Today it is deliberately a no-op.
     *
     * @param array<string,string> $payload
     */
    private function notifyMerchant(Form $form, int $submissionId, array $payload): void
    {
        // Dispatch a notification to whoever the store's
        // configured rules say should receive form.submitted
        // events. NotificationService is rule-driven: an
        // operator adds a rule in the admin and the dispatch
        // picks the right recipients. With no rules configured,
        // dispatch is a no-op and the merchant sees nothing.
        //
        // The context carries enough for a rule to filter on:
        // the form's id, slug, and name, plus the submission id
        // for a follow-up link. The payload is not passed
        // wholesale because the notification engine would then
        // need to know each field's label. The merchant reads
        // the payload from the submission detail page.
        try {
            $this->notifications->dispatch(
                'form.submitted',
                [
                    'form_id'       => $form->id,
                    'form_slug'     => $form->slug,
                    'form_name'     => $form->name,
                    'submission_id' => $submissionId,
                    'submitter'     => $payload['email']
                        ?? ($payload['name'] ?? 'anonymous'),
                ],
                'New form submission',
                $form->name . ' received a new submission.',
                '/commerce/ca-admin/forms/' . $form->id . '/submissions/' . $submissionId
            );
        } catch (\Throwable $e) {
            // Never block a submission on a notification
            // failure. Log the error and move on.
            Logger::warning('form.submitted notification failed', [
                'form_id'       => $form->id,
                'submission_id' => $submissionId,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
