<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Customer DTO.
 *
 * Customers are separate from core_users (admins). Different
 * lifecycle, different login endpoint, different session namespace.
 *
 * approval_status gates B2B checkout:
 *   'pending'  - registered, awaiting admin review
 *   'approved' - normal (default for retail signups)
 *   'rejected' - admin declined; can be reset to pending
 */
final class Customer
{
    public int $id = 0;
    public int $storeId = 0;
    public string $email = '';
    public ?string $passwordHash = null;
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $phone = null;
    public ?string $company = null;
    public ?string $taxId = null;
    public ?string $dateOfBirth = null;
    public ?string $emailVerifiedAt = null;
    public bool $acceptsMarketing = false;
    public bool $notifyOrderUpdates = true;
    public bool $notifyPromotions = false;
    public ?int $customerGroupId = null;
    public ?int $defaultBillingAddressId = null;
    public ?int $defaultShippingAddressId = null;
    public string $status = 'active';
    public string $approvalStatus = 'approved';
    public ?string $approvalReason = null;
    public ?string $approvedAt = null;
    public ?int $approvedBy = null;
    public ?string $rejectedAt = null;
    public ?string $lastLoginAt = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $c = new self();
        $c->id                       = (int) $row['id'];
        $c->storeId                  = (int) $row['store_id'];
        $c->email                    = (string) $row['email'];
        $c->passwordHash             = $row['password_hash'] ?? null;
        $c->firstName                = $row['first_name'] ?? null;
        $c->lastName                 = $row['last_name'] ?? null;
        $c->phone                    = $row['phone'] ?? null;
        $c->company                  = $row['company'] ?? null;
        $c->taxId                    = $row['tax_id'] ?? null;
        $c->dateOfBirth              = $row['date_of_birth'] ?? null;
        $c->emailVerifiedAt          = $row['email_verified_at'] ?? null;
        $c->acceptsMarketing         = (bool) ($row['accepts_marketing'] ?? false);
        $c->notifyOrderUpdates       = (bool) ($row['notify_order_updates'] ?? true);
        $c->notifyPromotions         = (bool) ($row['notify_promotions'] ?? false);
        $c->customerGroupId          = isset($row['customer_group_id']) ? (int) $row['customer_group_id'] : null;
        $c->defaultBillingAddressId  = isset($row['default_billing_address_id']) ? (int) $row['default_billing_address_id'] : null;
        $c->defaultShippingAddressId = isset($row['default_shipping_address_id']) ? (int) $row['default_shipping_address_id'] : null;
        $c->status                   = (string) ($row['status'] ?? 'active');
        $c->approvalStatus           = (string) ($row['approval_status'] ?? 'approved');
        $c->approvalReason           = $row['approval_reason'] ?? null;
        $c->approvedAt               = $row['approved_at'] ?? null;
        $c->approvedBy               = isset($row['approved_by']) ? (int) $row['approved_by'] : null;
        $c->rejectedAt               = $row['rejected_at'] ?? null;
        $c->lastLoginAt              = $row['last_login_at'] ?? null;
        $c->createdAt                = $row['created_at'] ?? null;
        $c->updatedAt                = $row['updated_at'] ?? null;
        return $c;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDisabled(): bool
    {
        return $this->status === 'disabled';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function isPending(): bool
    {
        return $this->approvalStatus === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->approvalStatus === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->approvalStatus === 'rejected';
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function isGuest(): bool
    {
        return $this->passwordHash === null;
    }

    /**
     * Can this customer place an order?
     */
    public function canCheckout(): bool
    {
        return $this->isActive() && $this->isApproved();
    }

    public function fullName(): string
    {
        $parts = array_filter([$this->firstName, $this->lastName]);
        if (empty($parts)) {
            return $this->email;
        }
        return implode(' ', $parts);
    }

    public function initials(): string
    {
        $first = $this->firstName !== null && $this->firstName !== ''
            ? strtoupper(substr($this->firstName, 0, 1))
            : strtoupper(substr($this->email, 0, 1));
        return $first;
    }
}
