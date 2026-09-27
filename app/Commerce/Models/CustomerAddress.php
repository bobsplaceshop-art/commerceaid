<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Customer address DTO.
 *
 * A customer can have multiple addresses, each typed as billing,
 * shipping, or both. At most one address per type is the default.
 * Setting a new default clears the previous one for the same type.
 *
 * Default IDs are also stored on the customer row
 * (default_billing_address_id, default_shipping_address_id) so
 * checkout and other flows can read them without a JOIN.
 *
 * See docs/stages/008c_customer-auth-plan.md.
 */
final class CustomerAddress
{
    public int $id = 0;
    public int $storeId = 0;
    public int $customerId = 0;
    public ?string $label = null;
    public string $type = 'both';
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $company = null;
    public string $addressLine1 = '';
    public ?string $addressLine2 = null;
    public string $city = '';
    public ?string $state = null;
    public ?string $postalCode = null;
    public string $countryCode = '';
    public ?string $phone = null;
    public ?string $email = null;
    public bool $isDefault = false;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $a = new self();
        $a->id            = (int) $row['id'];
        $a->storeId       = (int) $row['store_id'];
        $a->customerId    = (int) $row['customer_id'];
        $a->label         = $row['label'] ?? null;
        $a->type          = (string) ($row['type'] ?? 'both');
        $a->firstName     = $row['first_name'] ?? null;
        $a->lastName      = $row['last_name'] ?? null;
        $a->company       = $row['company'] ?? null;
        $a->addressLine1  = (string) $row['address_line_1'];
        $a->addressLine2  = $row['address_line_2'] ?? null;
        $a->city          = (string) $row['city'];
        $a->state         = $row['state'] ?? null;
        $a->postalCode    = $row['postal_code'] ?? null;
        $a->countryCode   = (string) $row['country_code'];
        $a->phone         = $row['phone'] ?? null;
        $a->email         = $row['email'] ?? null;
        $a->isDefault     = (bool) ($row['is_default'] ?? false);
        $a->createdAt     = $row['created_at'] ?? null;
        $a->updatedAt     = $row['updated_at'] ?? null;
        return $a;
    }

    public function isBilling(): bool
    {
        return $this->type === 'billing' || $this->type === 'both';
    }

    public function isShipping(): bool
    {
        return $this->type === 'shipping' || $this->type === 'both';
    }

    public function recipientName(): string
    {
        $parts = array_filter([$this->firstName, $this->lastName], fn ($p) => $p !== null && $p !== '');
        return $parts !== [] ? implode(' ', $parts) : '';
    }

    /**
     * Single-line display of the address. Used in lists and
     * anywhere a compact representation is needed.
     */
    public function oneLine(): string
    {
        $parts = array_filter([
            $this->addressLine1,
            $this->addressLine2,
            $this->city,
            $this->state,
            $this->postalCode,
            $this->countryCode,
        ], fn ($p) => $p !== null && $p !== '');
        return implode(', ', $parts);
    }
}
