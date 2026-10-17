<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Import\Handlers;

use CorporateAid\Commerce\Models\Customer;
use CorporateAid\Commerce\Repositories\CustomerGroupRepository;
use CorporateAid\Commerce\Repositories\CustomerRepository;
use CorporateAid\Commerce\Services\Import\ImportHandler;
use CorporateAid\Platform\Support\Hasher;

/**
 * Import handler for commerce_customers. Stage 016.
 *
 * Eight fields:
 *
 *   email             required  - the natural key, unique per store
 *   first_name        required
 *   last_name         required
 *   phone             optional
 *   company           optional
 *   tax_id            optional
 *   group_slug        optional  - resolves against customer groups
 *   accepts_marketing optional  - only honoured if the merchant
 *                                 confirmed consent on the upload
 *                                 screen; the pipeline strips the
 *                                 field otherwise
 *
 * Natural key: the email, trimmed and lowercased. A CSV
 * with "Jane@Example.com" and "jane@example.com" resolves
 * to the same customer.
 *
 * Password: imported customers get a random 32-byte hex
 * string, hashed with the same Hasher the registration
 * flow uses. The customer does not know the password. The
 * forgot-password flow is the first login. The batch
 * detail warns the merchant.
 *
 * Approval status: 'approved' by default. Imported
 * customers are existing customers moving onto the
 * platform, not new sign-ups awaiting review. The
 * merchant can change a customer's status after the
 * import through the admin UI.
 *
 * DSGVO consent: the accepts_marketing field is only
 * honoured if the merchant has explicitly confirmed on
 * the upload screen that every customer in the file
 * consented to marketing. The pipeline is responsible
 * for that gate - it strips the field from the row
 * before the handler sees it, unless the merchant
 * confirmed. The handler trusts its input.
 *
 * Update semantics: first_name, last_name, phone,
 * company, tax_id, customer_group_id, accepts_marketing
 * are the fields the CSV touches. A missing field leaves
 * the existing value alone. Email cannot be changed - a
 * customer who needs a different email is a different
 * customer, and the admin UI is where that change
 * happens.
 *
 * Rollback: a create is archived (status = 'archived',
 * not deleted - the codebase never hard-deletes a
 * customer; order history references them). An update
 * restores the previous first_name, last_name, phone,
 * company, tax_id, customer_group_id, and
 * accepts_marketing.
 *
 * See docs/stages/016_data-import-plan.md and the open
 * questions section for the consent gate details.
 */
final class ImportCustomerHandler implements ImportHandler
{
    public function __construct(
        private CustomerRepository $customers,
        private CustomerGroupRepository $groups,
    ) {
    }

    public function entityKey(): string
    {
        return 'customer';
    }

    public function entityLabel(): string
    {
        return 'Customer';
    }

    /**
     * @return array<string, array{label: string, required: bool, hint: string}>
     */
    public function fields(): array
    {
        return [
            'email' => [
                'label'    => 'Email',
                'required' => true,
                'hint'     => 'Unique per store. Used as the natural key for idempotency.',
            ],
            'first_name' => [
                'label'    => 'First name',
                'required' => true,
                'hint'     => 'The customer\'s first name.',
            ],
            'last_name' => [
                'label'    => 'Last name',
                'required' => true,
                'hint'     => 'The customer\'s last name.',
            ],
            'phone' => [
                'label'    => 'Phone',
                'required' => false,
                'hint'     => 'Optional. International format is preferred.',
            ],
            'company' => [
                'label'    => 'Company',
                'required' => false,
                'hint'     => 'Optional. For B2B customers.',
            ],
            'tax_id' => [
                'label'    => 'Tax ID',
                'required' => false,
                'hint'     => 'Optional. VAT ID or company tax number.',
            ],
            'group_slug' => [
                'label'    => 'Group slug',
                'required' => false,
                'hint'     => 'Optional. The slug of an existing customer group.',
            ],
            'accepts_marketing' => [
                'label'    => 'Accepts marketing',
                'required' => false,
                'hint'     => 'Only honoured if the merchant confirmed consent on the upload screen. Otherwise forced to 0.',
            ],
        ];
    }

    /**
     * @param array<string, string> $row
     */
    public function validate(array $row): ?string
    {
        $email     = trim((string) ($row['email'] ?? ''));
        $firstName = trim((string) ($row['first_name'] ?? ''));
        $lastName  = trim((string) ($row['last_name'] ?? ''));

        if ($email === '') {
            return 'Email is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Email is not a valid address.';
        }
        if (mb_strlen($email) > 190) {
            return 'Email must be 190 characters or fewer.';
        }
        if ($firstName === '') {
            return 'First name is required.';
        }
        if (mb_strlen($firstName) > 80) {
            return 'First name must be 80 characters or fewer.';
        }
        if ($lastName === '') {
            return 'Last name is required.';
        }
        if (mb_strlen($lastName) > 80) {
            return 'Last name must be 80 characters or fewer.';
        }

        $groupSlug = strtolower(trim((string) ($row['group_slug'] ?? '')));
        if ($groupSlug !== '') {
            $group = $this->groups->findBySlug($groupSlug);
            if ($group === null) {
                return "Customer group '{$groupSlug}' not found.";
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $row
     */
    public function naturalKey(array $row): string
    {
        return strtolower(trim((string) ($row['email'] ?? '')));
    }

    public function exists(string $naturalKey): bool
    {
        return $this->customers->findByEmail($naturalKey) !== null;
    }

    /**
     * @param array<string, string> $row
     */
    public function create(array $row): int
    {
        $customer = new Customer();
        $customer->email       = strtolower(trim((string) ($row['email'] ?? '')));
        $customer->firstName   = trim((string) ($row['first_name'] ?? ''));
        $customer->lastName    = trim((string) ($row['last_name'] ?? ''));
        $customer->phone       = $this->nullIfBlank($row['phone'] ?? null);
        $customer->company     = $this->nullIfBlank($row['company'] ?? null);
        $customer->taxId       = $this->nullIfBlank($row['tax_id'] ?? null);
        $customer->customerGroupId = $this->resolveGroupId((string) ($row['group_slug'] ?? ''));
        $customer->acceptsMarketing = $this->parseBoolean($row['accepts_marketing'] ?? '');
        $customer->status       = 'active';
        $customer->approvalStatus = 'approved';
        $customer->emailVerifiedAt = date('Y-m-d H:i:s');

        $randomPassword = Hasher::randomToken(32);
        $customer->passwordHash = Hasher::hash($randomPassword);

        return $this->customers->create($customer);
    }

    /**
     * @param array<string, string> $row
     * @return array<string, mixed>
     */
    public function update(string $naturalKey, array $row): array
    {
        $existing = $this->customers->findByEmail($naturalKey);
        if ($existing === null) {
            return [];
        }

        $previous = [
            'first_name'        => $existing->firstName,
            'last_name'         => $existing->lastName,
            'phone'             => $existing->phone,
            'company'           => $existing->company,
            'tax_id'            => $existing->taxId,
            'customer_group_id' => $existing->customerGroupId,
            'accepts_marketing' => $existing->acceptsMarketing,
        ];

        if (array_key_exists('first_name', $row)) {
            $existing->firstName = trim((string) $row['first_name']);
        }
        if (array_key_exists('last_name', $row)) {
            $existing->lastName = trim((string) $row['last_name']);
        }
        if (array_key_exists('phone', $row)) {
            $existing->phone = $this->nullIfBlank($row['phone']);
        }
        if (array_key_exists('company', $row)) {
            $existing->company = $this->nullIfBlank($row['company']);
        }
        if (array_key_exists('tax_id', $row)) {
            $existing->taxId = $this->nullIfBlank($row['tax_id']);
        }
        if (array_key_exists('group_slug', $row)) {
            $existing->customerGroupId = $this->resolveGroupId((string) $row['group_slug']);
        }
        if (array_key_exists('accepts_marketing', $row)) {
            $existing->acceptsMarketing = $this->parseBoolean($row['accepts_marketing']);
        }

        $this->customers->update($existing);

        return $previous;
    }

    public function delete(int $entityId): void
    {
        // Archiving rather than deleting. The codebase
        // never hard-deletes a customer; order history
        // references the row. The rollback target for a
        // create is the 'archived' status.
        $existing = $this->customers->findById($entityId);
        if ($existing === null) {
            return;
        }
        $existing->status = 'archived';
        $this->customers->update($existing);
    }

    /**
     * @param array<string, mixed> $previousValues
     */
    public function restore(int $entityId, array $previousValues): void
    {
        $existing = $this->customers->findById($entityId);
        if ($existing === null) {
            return;
        }

        if (array_key_exists('first_name', $previousValues)) {
            $existing->firstName = $previousValues['first_name'] === null
                ? null
                : (string) $previousValues['first_name'];
        }
        if (array_key_exists('last_name', $previousValues)) {
            $existing->lastName = $previousValues['last_name'] === null
                ? null
                : (string) $previousValues['last_name'];
        }
        if (array_key_exists('phone', $previousValues)) {
            $existing->phone = $previousValues['phone'] === null
                ? null
                : (string) $previousValues['phone'];
        }
        if (array_key_exists('company', $previousValues)) {
            $existing->company = $previousValues['company'] === null
                ? null
                : (string) $previousValues['company'];
        }
        if (array_key_exists('tax_id', $previousValues)) {
            $existing->taxId = $previousValues['tax_id'] === null
                ? null
                : (string) $previousValues['tax_id'];
        }
        if (array_key_exists('customer_group_id', $previousValues)) {
            $existing->customerGroupId = $previousValues['customer_group_id'] === null
                ? null
                : (int) $previousValues['customer_group_id'];
        }
        if (array_key_exists('accepts_marketing', $previousValues)) {
            $existing->acceptsMarketing = (bool) $previousValues['accepts_marketing'];
        }

        $this->customers->update($existing);
    }

    public function findIdByNaturalKey(string $naturalKey): ?int
    {
        return $this->customers->findByEmail($naturalKey)?->id;
    }

    // -----------------------------------------------------------------

    private function resolveGroupId(string $groupSlug): ?int
    {
        $slug = strtolower(trim($groupSlug));
        if ($slug === '') {
            return null;
        }
        $group = $this->groups->findBySlug($slug);
        return $group !== null ? (int) $group->id : null;
    }

    private function nullIfBlank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function parseBoolean(string $value): bool
    {
        $value = strtolower(trim($value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}
