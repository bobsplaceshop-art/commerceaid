<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * A form submission. Stage 009.
 *
 * payload is the submitted values keyed by field_key.
 * It is decoded from the JSON column and exposed as an
 * associative array. Keys are the field_key values of
 * the form at the moment of submission; the values are
 * the raw strings the customer typed (or empty strings
 * for empty fields).
 *
 * status lifecycle:
 *   new       - unreviewed
 *   read      - reviewed by the merchant
 *   spam      - flagged as spam
 *   archived  - hidden from the default list, preserved
 *
 * submitterEmail and submitterName are denormalised from
 * the payload for the common filter case. Both may be
 * null; the form may not have an email or name field.
 *
 * See docs/stages/009_forms-plan.md.
 */
final class FormSubmission
{
    public int $id = 0;
    public int $storeId = 0;
    public int $formId = 0;
    public ?int $customerId = null;
    public ?string $submitterEmail = null;
    public ?string $submitterName = null;
    public ?string $submitterIp = null;
    public ?string $userAgent = null;

    /** @var array<string,string> */
    public array $payload = [];

    public string $status = 'new';
    public ?string $notes = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $s = new self();
        $s->id             = (int) $row['id'];
        $s->storeId        = (int) $row['store_id'];
        $s->formId         = (int) $row['form_id'];
        $s->customerId     = $row['customer_id']     !== null ? (int) $row['customer_id']         : null;
        $s->submitterEmail = $row['submitter_email'] !== null ? (string) $row['submitter_email']  : null;
        $s->submitterName  = $row['submitter_name']  !== null ? (string) $row['submitter_name']   : null;
        $s->submitterIp    = $row['submitter_ip']    !== null ? (string) $row['submitter_ip']     : null;
        $s->userAgent      = $row['user_agent']      !== null ? (string) $row['user_agent']       : null;

        // payload_json shape: {"field_key":"value", ...}
        $s->payload = [];
        if (!empty($row['payload_json'])) {
            $decoded = is_array($row['payload_json'])
                ? $row['payload_json']
                : json_decode((string) $row['payload_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $key => $value) {
                    // Payload values are always strings. Arrays
                    // (multi-select) are joined by comma; the
                    // renderer will re-split if it needs to.
                    if (is_array($value)) {
                        $s->payload[(string) $key] = implode(', ', array_map(
                            static fn ($v) => (string) $v,
                            $value
                        ));
                    } else {
                        $s->payload[(string) $key] = (string) $value;
                    }
                }
            }
        }

        $s->status    = (string) $row['status'];
        $s->notes     = $row['notes']      !== null ? (string) $row['notes']      : null;
        $s->createdAt = $row['created_at'] !== null ? (string) $row['created_at'] : null;
        $s->updatedAt = $row['updated_at'] !== null ? (string) $row['updated_at'] : null;

        return $s;
    }

    public function isNew(): bool      { return $this->status === 'new'; }
    public function isRead(): bool     { return $this->status === 'read'; }
    public function isSpam(): bool     { return $this->status === 'spam'; }
    public function isArchived(): bool { return $this->status === 'archived'; }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'new'      => 'New',
            'read'     => 'Read',
            'spam'     => 'Spam',
            'archived' => 'Archived',
            default    => ucfirst($this->status),
        };
    }

    public function statusBadgeVariant(): string
    {
        return match ($this->status) {
            'new'      => 'info',
            'read'     => 'success',
            'spam'     => 'danger',
            'archived' => 'muted',
            default    => 'muted',
        };
    }

    /**
     * The value for a field_key, or an empty string. Used
     * by the detail view and the CSV export so neither has
     * to guard every access.
     */
    public function value(string $fieldKey): string
    {
        return $this->payload[$fieldKey] ?? '';
    }
}
