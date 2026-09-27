<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\CustomerAddress;

/**
 * Customer address repository.
 *
 * Every method is tenant-scoped via storeId(). Every method that
 * operates on a specific address also scopes by customer_id, so
 * a controller cannot accidentally let customer A edit customer
 * B's address by passing a different id.
 *
 * Default semantics: at most one default billing and one default
 * shipping per customer. Setting a new default clears the old one
 * for the same type. An address of type 'both' becomes the default
 * for both billing and shipping. The customer row's
 * default_billing_address_id and default_shipping_address_id
 * columns are kept in sync.
 *
 * See docs/stages/008c_customer-auth-plan.md.
 */
final class CustomerAddressRepository extends CommerceRepository
{
    /** @return array<int, CustomerAddress> */
    public function listForCustomer(int $customerId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_customer_addresses`
              WHERE `store_id` = ? AND `customer_id` = ?
              ORDER BY `is_default` DESC, `id` ASC",
            [$this->storeId(), $customerId]
        );
        return array_map(fn ($r) => CustomerAddress::fromRow($r), $rows);
    }

    public function findById(int $id): ?CustomerAddress
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_customer_addresses`
              WHERE `id` = ? AND `store_id` = ?
              LIMIT 1",
            [$id, $this->storeId()]
        );
        return $row !== null ? CustomerAddress::fromRow($row) : null;
    }

    /**
     * Explicitly scoped by customer. Used by edit/delete/set-default
     * so a controller cannot touch another customer's address.
     */
    public function findForCustomer(int $id, int $customerId): ?CustomerAddress
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_customer_addresses`
              WHERE `id` = ? AND `customer_id` = ? AND `store_id` = ?
              LIMIT 1",
            [$id, $customerId, $this->storeId()]
        );
        return $row !== null ? CustomerAddress::fromRow($row) : null;
    }

    public function countForCustomer(int $customerId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_customer_addresses`
              WHERE `store_id` = ? AND `customer_id` = ?",
            [$this->storeId(), $customerId]
        );
    }

    public function create(CustomerAddress $address): int
    {
        return (int) $this->db->insert(
            "INSERT INTO `commerce_customer_addresses`
                (`store_id`, `customer_id`, `label`, `type`,
                 `first_name`, `last_name`, `company`,
                 `address_line_1`, `address_line_2`,
                 `city`, `state`, `postal_code`, `country_code`,
                 `phone`, `email`, `is_default`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $address->storeId > 0 ? $address->storeId : $this->storeId(),
                $address->customerId,
                $address->label,
                $address->type,
                $address->firstName,
                $address->lastName,
                $address->company,
                $address->addressLine1,
                $address->addressLine2,
                $address->city,
                $address->state,
                $address->postalCode,
                $address->countryCode,
                $address->phone,
                $address->email,
                $address->isDefault ? 1 : 0,
            ]
        );
    }

    public function update(CustomerAddress $address): void
    {
        $this->db->execute(
            "UPDATE `commerce_customer_addresses`
                SET `label` = ?, `type` = ?,
                    `first_name` = ?, `last_name` = ?, `company` = ?,
                    `address_line_1` = ?, `address_line_2` = ?,
                    `city` = ?, `state` = ?, `postal_code` = ?,
                    `country_code` = ?, `phone` = ?, `email` = ?
              WHERE `id` = ? AND `customer_id` = ? AND `store_id` = ?",
            [
                $address->label,
                $address->type,
                $address->firstName,
                $address->lastName,
                $address->company,
                $address->addressLine1,
                $address->addressLine2,
                $address->city,
                $address->state,
                $address->postalCode,
                $address->countryCode,
                $address->phone,
                $address->email,
                $address->id,
                $address->customerId,
                $this->storeId(),
            ]
        );
    }

    /**
     * Hard delete. Addresses have no audit value - unlike products
     * or orders - so there is no soft-delete state. A deleted
     * address is gone. The FK on customer_id has ON DELETE CASCADE
     * so a full customer delete cleans up automatically.
     */
    public function delete(int $id, int $customerId): void
    {
        $this->db->execute(
            "DELETE FROM `commerce_customer_addresses`
              WHERE `id` = ? AND `customer_id` = ? AND `store_id` = ?",
            [$id, $customerId, $this->storeId()]
        );
    }

    /**
     * Make this address the default for its type(s).
     *
     * Within a transaction:
     *   1. Clear is_default on the customer's other addresses of
     *      the same type(s).
     *   2. Set is_default = 1 on this address.
     *   3. Update the customer row's default_billing_address_id
     *      and/or default_shipping_address_id.
     *
     * A 'both' address sets both customer columns. A 'billing'
     * address sets only the billing column, etc.
     */
    public function setDefault(CustomerAddress $address): void
    {
        $this->db->beginTransaction();
        try {
            // Determine which slots this address fills.
            $forBilling  = $address->isBilling();
            $forShipping = $address->isShipping();

            // 1. Clear existing defaults for this customer on the
            //    same type(s). An address is "of type billing" if
            //    its type is 'billing' or 'both'. Same for shipping.
            if ($forBilling) {
                $this->db->execute(
                    "UPDATE `commerce_customer_addresses`
                        SET `is_default` = 0
                      WHERE `customer_id` = ? AND `store_id` = ?
                        AND `type` IN ('billing','both')
                        AND `id` <> ?",
                    [$address->customerId, $this->storeId(), $address->id]
                );
            }
            if ($forShipping) {
                $this->db->execute(
                    "UPDATE `commerce_customer_addresses`
                        SET `is_default` = 0
                      WHERE `customer_id` = ? AND `store_id` = ?
                        AND `type` IN ('shipping','both')
                        AND `id` <> ?",
                    [$address->customerId, $this->storeId(), $address->id]
                );
            }

            // 2. Set this address as default.
            $this->db->execute(
                "UPDATE `commerce_customer_addresses`
                    SET `is_default` = 1
                  WHERE `id` = ? AND `customer_id` = ? AND `store_id` = ?",
                [$address->id, $address->customerId, $this->storeId()]
            );

            // 3. Sync the customer row's default id columns.
            $billingId  = $forBilling  ? $address->id : null;
            $shippingId = $forShipping ? $address->id : null;

            if ($billingId !== null && $shippingId !== null) {
                $this->db->execute(
                    "UPDATE `commerce_customers`
                        SET `default_billing_address_id` = ?,
                            `default_shipping_address_id` = ?
                      WHERE `id` = ? AND `store_id` = ?",
                    [$billingId, $shippingId, $address->customerId, $this->storeId()]
                );
            } elseif ($billingId !== null) {
                $this->db->execute(
                    "UPDATE `commerce_customers`
                        SET `default_billing_address_id` = ?
                      WHERE `id` = ? AND `store_id` = ?",
                    [$billingId, $address->customerId, $this->storeId()]
                );
            } elseif ($shippingId !== null) {
                $this->db->execute(
                    "UPDATE `commerce_customers`
                        SET `default_shipping_address_id` = ?
                      WHERE `id` = ? AND `store_id` = ?",
                    [$shippingId, $address->customerId, $this->storeId()]
                );
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Clear the customer's default for a specific type. Called when
     * the address that was default gets deleted.
     */
    public function clearCustomerDefault(int $customerId, string $type): void
    {
        $column = match ($type) {
            'billing'  => 'default_billing_address_id',
            'shipping' => 'default_shipping_address_id',
            default    => null,
        };
        if ($column === null) {
            return;
        }
        $this->db->execute(
            "UPDATE `commerce_customers`
                SET `{$column}` = NULL
              WHERE `id` = ? AND `store_id` = ?",
            [$customerId, $this->storeId()]
        );
    }
}
