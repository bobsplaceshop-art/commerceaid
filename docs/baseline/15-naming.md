# 15. Naming

Section of: [BASELINE](../BASELINE.md)

## Table of Conventions

Controller
  Format: {Entity}Controller
  Example: CustomerApprovalController

View file
  Format: resources/views/admin/{snake_plural}/{action}.php
  Example: admin/customer_approvals/index.php

View reference
  Format: dot-notation
  Example: admin.customer_approvals.index

Partial
  Format: leading underscore
  Example: _reject-modal.php

Repository
  Format: {Entity}Repository
  Example: CustomerRepository

Model
  Format: {Entity}
  Example: Customer

Route path
  Format: /commerce/ca-admin/{kebab-plural}
  Example: /commerce/ca-admin/customer-approvals

Route param
  Format: {camelCase or snake_case}
  Example: {id}, {productId}, {tab}

Permission slug
  Format: {module}.{entity}.{action}
  Example: commerce.customers.approve

Flash key
  Format: fixed
  Example: 'success' or 'error'

Migration file
  Format: {4-digit sequence}_{snake_description}.sql
  Example: 0205_customer_and_order_tables.sql

Seeder file
  Format: {4-digit sequence}_{snake_description}.sql
  Example: 0006_seed_test_b2b_customer.sql

DB table
  Format: {module_prefix}_{snake_plural}
  Example: commerce_customers, core_users

DB column
  Format: snake_case
  Example: customer_group_id, approval_status, created_at

DB index
  Format: idx_{module_short}_{entity_short}_{purpose}
  Example: idx_com_cust_approval

DB foreign key
  Format: fk_{child_module}_{child_entity}_{parent_entity}
  Example: fk_com_cust_approved_by

CSS class
  Format: ra-{kebab-case}
  Example: ra-card, ra-btn-primary, ra-hide-sm

JS global
  Format: RA_{Name}
  Example: RA_DeleteProduct, RA_AdjustStock

## Model Property Naming

Database columns are snake_case. Model properties are camelCase.

    store_id            ->  $storeId
    customer_group_id   ->  $customerGroupId
    approval_status     ->  $approvalStatus
    created_at          ->  $createdAt
    is_active           ->  $isActive

The conversion happens inside Model::fromRow(). No other layer
translates between the two.

## Route Path vs Controller Method

Route path         /commerce/ca-admin/customer-approvals
Controller method  index

Route path         /commerce/ca-admin/customer-approvals/{id}/approve
Controller method  approve

Route path         /commerce/ca-admin/customer-approvals/{id}/reject
Controller method  reject

Path uses kebab-case and HTTP verb mapping. Method names use
camelCase and describe the action. The mapping is not automatic;
the developer picks the method name.

## When in Doubt

Ask: what would the sibling look like? Every existing convention
has an example in the codebase. Match the sibling.

If a name does not match any existing pattern, the naming convention
may need to be extended. Update the naming convention document
before inventing a new pattern.

## See Also

Full suite-wide naming rules are in docs/NAMING-CONVENTION.md. This
section covers only the subset that applies to admin Commerce work.
