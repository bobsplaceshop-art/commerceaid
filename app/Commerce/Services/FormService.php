<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Exceptions\NewsletterException;
use CorporateAid\Commerce\Models\Form;
use CorporateAid\Commerce\Repositories\FormFieldRepository;
use CorporateAid\Commerce\Repositories\FormRepository;

/**
 * Form CRUD. Stage 009.
 *
 * Session 2a scope: form metadata only. Field operations
 * (add, update, reorder) ship in Session 2b.
 *
 * The service owns every write to commerce_forms. The
 * controller is a thin HTTP adapter: it collects input,
 * calls the service, and translates the result into a
 * response.
 *
 * See docs/stages/009_forms-plan.md.
 */
final class FormService
{
    public function __construct(
        private FormRepository $forms,
        private FormFieldRepository $fields,
    ) {
    }

    /**
     * Create a draft form.
     *
     * @param array{name:string,slug:string,description?:?string,
     *              success_message?:?string,notify_email?:?string,
     *              honeypot_enabled?:bool,requires_auth?:bool} $data
     */
    public function createForm(array $data, ?int $userId = null): int
    {
        $slug = $this->normaliseSlug((string) ($data['slug'] ?? ''));

        if ($slug === '') {
            throw new NewsletterException('A slug is required.');
        }
        if ($this->forms->slugExists($slug)) {
            throw new NewsletterException('That slug is already in use.');
        }

        return $this->forms->create([
            'name'             => (string) $data['name'],
            'slug'             => $slug,
            'description'      => $this->nullable($data['description']      ?? null),
            'status'           => 'draft',
            'success_message'  => $this->nullable($data['success_message']  ?? null),
            'notify_email'     => $this->nullable($data['notify_email']     ?? null),
            'honeypot_enabled' => !empty($data['honeypot_enabled']) ? 1 : 0,
            'requires_auth'    => !empty($data['requires_auth'])    ? 1 : 0,
            'created_by'       => $userId,
        ]);
    }

    /**
     * Update form metadata. The slug is immutable once the
     * form is published, to avoid breaking public URLs.
     *
     * @param array<string,mixed> $data
     */
    public function updateForm(int $formId, array $data): void
    {
        $form = $this->forms->findById($formId);
        if ($form === null) {
            throw new NewsletterException('Form not found.');
        }

        $update = [
            'name'             => (string) $data['name'],
            'description'      => $this->nullable($data['description']      ?? null),
            'success_message'  => $this->nullable($data['success_message']  ?? null),
            'notify_email'     => $this->nullable($data['notify_email']     ?? null),
            'honeypot_enabled' => !empty($data['honeypot_enabled']) ? 1 : 0,
            'requires_auth'    => !empty($data['requires_auth'])    ? 1 : 0,
        ];

        // The slug is editable only while the form is a draft.
        // Once published, the URL is public: changing it would
        // break every external reference.
        if ($form->isDraft()) {
            $newSlug = $this->normaliseSlug((string) ($data['slug'] ?? $form->slug));
            if ($newSlug === '') {
                throw new NewsletterException('A slug is required.');
            }
            if ($newSlug !== $form->slug && $this->forms->slugExists($newSlug, $formId)) {
                throw new NewsletterException('That slug is already in use.');
            }
            $update['slug'] = $newSlug;
        }

        $this->forms->update($formId, $update);
    }

    public function publish(int $formId): void
    {
        $form = $this->forms->findById($formId);
        if ($form === null) {
            throw new NewsletterException('Form not found.');
        }

        $fieldCount = $this->fields->countForForm($formId);
        if ($fieldCount === 0) {
            throw new NewsletterException(
                'Add at least one field before publishing the form.'
            );
        }

        $this->forms->update($formId, ['status' => 'published']);
    }

    public function unpublish(int $formId): void
    {
        $this->forms->update($formId, ['status' => 'draft']);
    }

    public function archive(int $formId): void
    {
        $this->forms->update($formId, ['status' => 'archived']);
    }

    public function delete(int $formId): void
    {
        $this->forms->softDelete($formId);
    }

    /**
     * Clone a form and its fields. The copy is a draft;
     * its slug is "{original-slug}-copy" and is suffixed
     * with a counter until unique.
     */
    public function duplicate(int $formId, ?int $userId = null): int
    {
        $form = $this->forms->findById($formId);
        if ($form === null) {
            throw new NewsletterException('Form not found.');
        }

        $newSlug = $this->uniqueCopySlug($form->slug);

        $newId = $this->forms->create([
            'name'             => $form->name . ' (copy)',
            'slug'             => $newSlug,
            'description'      => $form->description,
            'status'           => 'draft',
            'success_message'  => $form->successMessage,
            'notify_email'     => $form->notifyEmail,
            'honeypot_enabled' => $form->honeypotEnabled ? 1 : 0,
            'requires_auth'    => $form->requiresAuth    ? 1 : 0,
            'created_by'       => $userId,
        ]);

        $fields = $this->fields->listForForm($formId);
        foreach ($fields as $field) {
            $this->fields->create([
                'form_id'          => $newId,
                'field_key'        => $field->fieldKey,
                'label'            => $field->label,
                'input_type'       => $field->inputType,
                'placeholder'      => $field->placeholder,
                'help_text'        => $field->helpText,
                'is_required'      => $field->isRequired ? 1 : 0,
                'options_json'     => $field->options     !== [] ? json_encode($field->options)     : null,
                'constraints_json' => $field->constraints !== [] ? json_encode($field->constraints) : null,
                'default_value'    => $field->defaultValue,
                'step'             => $field->step,
                'sort_order'       => $field->sortOrder,
                'conditional_json' => $field->conditional !== null ? json_encode($field->conditional) : null,
                'is_active'        => $field->isActive ? 1 : 0,
            ]);
        }

        return $newId;
    }

    // -----------------------------------------------------------------
    // Fields
    // -----------------------------------------------------------------

    /**
     * Add a field to a form.
     *
     * @param array<string,mixed> $data  {field_key, label, input_type, ...}
     */
    public function addField(int $formId, array $data): int
    {
        $form = $this->forms->findById($formId);
        if ($form === null) {
            throw new NewsletterException('Form not found.');
        }

        $key = $this->normaliseFieldKey((string) ($data['field_key'] ?? ''));
        if ($key === '') {
            throw new NewsletterException('A field key is required.');
        }
        if ($this->fields->fieldKeyExists($formId, $key)) {
            throw new NewsletterException('That field key is already used on this form.');
        }

        $step      = max(1, (int) ($data['step'] ?? 1));
        $sortOrder = $this->fields->nextSortOrder($formId, $step);

        return $this->fields->create([
            'form_id'          => $formId,
            'field_key'        => $key,
            'label'            => (string) ($data['label'] ?? ''),
            'input_type'       => (string) ($data['input_type'] ?? 'text'),
            'placeholder'      => $this->nullable($data['placeholder'] ?? null),
            'help_text'        => $this->nullable($data['help_text'] ?? null),
            'is_required'      => !empty($data['is_required']) ? 1 : 0,
            'options_json'     => !empty($data['options'])     ? json_encode($data['options'])     : null,
            'constraints_json' => !empty($data['constraints']) ? json_encode($data['constraints']) : null,
            'default_value'    => $this->nullable($data['default_value'] ?? null),
            'step'             => $step,
            'sort_order'       => $sortOrder,
            'conditional_json' => !empty($data['conditional']) ? json_encode($data['conditional']) : null,
            'is_active'        => 1,
        ]);
    }

    /**
     * Update a field. The field_key may be changed while the
     * form is a draft; once published, the key is immutable
     * because the payload uses it as the column name.
     *
     * @param array<string,mixed> $data
     */
    public function updateField(int $fieldId, array $data): void
    {
        $field = $this->fields->findById($fieldId);
        if ($field === null) {
            throw new NewsletterException('Field not found.');
        }

        $form = $this->forms->findById($field->formId);
        $canChangeKey = $form !== null && $form->isDraft();

        $update = [
            'label'            => (string) ($data['label'] ?? $field->label),
            'input_type'       => (string) ($data['input_type'] ?? $field->inputType),
            'placeholder'      => $this->nullable($data['placeholder'] ?? null),
            'help_text'        => $this->nullable($data['help_text'] ?? null),
            'is_required'      => !empty($data['is_required']) ? 1 : 0,
            'options_json'     => !empty($data['options'])     ? json_encode($data['options'])     : null,
            'constraints_json' => !empty($data['constraints']) ? json_encode($data['constraints']) : null,
            'default_value'    => $this->nullable($data['default_value'] ?? null),
            'step'             => max(1, (int) ($data['step'] ?? $field->step)),
            'conditional_json' => !empty($data['conditional']) ? json_encode($data['conditional']) : null,
        ];

        if ($canChangeKey) {
            $newKey = $this->normaliseFieldKey((string) ($data['field_key'] ?? $field->fieldKey));
            if ($newKey === '') {
                throw new NewsletterException('A field key is required.');
            }
            if ($newKey !== $field->fieldKey
                && $this->fields->fieldKeyExists($field->formId, $newKey, $fieldId)
            ) {
                throw new NewsletterException('That field key is already used on this form.');
            }
            $update['field_key'] = $newKey;
        }

        $this->fields->update($fieldId, $update);
    }

    public function deleteField(int $fieldId): void
    {
        $field = $this->fields->findById($fieldId);
        if ($field === null) {
            throw new NewsletterException('Field not found.');
        }
        $this->fields->delete($fieldId);
    }

    /**
     * Move a field up or down within its step. Swaps the
     * sort_order of the target field with the adjacent one.
     *
     * @param string $direction  'up' or 'down'. Anything else is refused.
     */
    public function moveField(int $fieldId, string $direction): void
    {
        if (!in_array($direction, ['up', 'down'], true)) {
            throw new NewsletterException('Invalid move direction.');
        }

        $field = $this->fields->findById($fieldId);
        if ($field === null) {
            throw new NewsletterException('Field not found.');
        }

        $siblings = $this->fields->listForForm($field->formId);
        // Filter to the same step; the up/down move is
        // scoped to a step, not to the whole form.
        $inStep = array_values(array_filter(
            $siblings,
            static fn ($f) => $f->step === $field->step
        ));

        $index = -1;
        foreach ($inStep as $i => $f) {
            if ($f->id === $field->id) { $index = $i; break; }
        }
        if ($index < 0) return;

        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if (!isset($inStep[$target])) return; // Already at the boundary.

        $neighbour = $inStep[$target];

        // Swap the two sort_order values. If they are equal
        // (rare, from a bad import) we still produce a
        // deterministic order by renumbering the whole step
        // by 10.
        if ($field->sortOrder === $neighbour->sortOrder) {
            $this->renumberStep($field->formId, $field->step, $inStep, $index, $target);
            return;
        }

        $this->fields->update($field->id,     ['sort_order' => $neighbour->sortOrder]);
        $this->fields->update($neighbour->id, ['sort_order' => $field->sortOrder]);
    }

    /**
     * Fallback for the equal-sort_order edge case. Renumber
     * the whole step sequentially with the target order.
     *
     * @param array<int,\CorporateAid\Commerce\Models\FormField> $inStep
     */
    private function renumberStep(int $formId, int $step, array $inStep, int $from, int $to): void
    {
        // Reorder the array by swapping the two positions.
        $reordered = $inStep;
        $tmp = $reordered[$from];
        $reordered[$from] = $reordered[$to];
        $reordered[$to] = $tmp;

        $ids = array_map(static fn ($f) => $f->id, $reordered);
        $this->fields->reorder($formId, $ids);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function normaliseFieldKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?? $key;
        $key = trim($key, '_');
        // MySQL column-safe: must start with a letter.
        if ($key !== '' && !preg_match('/^[a-z]/', $key)) {
            $key = 'f_' . $key;
        }
        return $key;
    }

    private function normaliseSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?? $slug;
        $slug = trim($slug, '-');
        return $slug;
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) return null;
        $s = trim((string) $value);
        return $s === '' ? null : $s;
    }

    /**
     * Find a slug of the form "foo-copy", "foo-copy-2", etc.
     * that is not yet in use.
     */
    private function uniqueCopySlug(string $baseSlug): string
    {
        $base = $this->normaliseSlug($baseSlug) . '-copy';
        if (!$this->forms->slugExists($base)) {
            return $base;
        }
        for ($i = 2; $i <= 100; $i++) {
            $candidate = $base . '-' . $i;
            if (!$this->forms->slugExists($candidate)) {
                return $candidate;
            }
        }
        // Extremely unlikely. Fall back to a random suffix so
        // the caller never sees a "slug exists" error from a
        // duplicate action.
        return $base . '-' . bin2hex(random_bytes(3));
    }
}
