# 9. Model (DTO)

Section of: [BASELINE](../BASELINE.md)

## Template

    <?php
    declare(strict_types=1);

    namespace CorporateAid\Commerce\Models;

    final class Foo
    {
        public int $id = 0;
        public int $storeId = 0;
        public string $name = '';
        public ?string $slug = null;
        public ?string $description = null;
        public bool $isActive = false;
        public ?string $createdAt = null;
        public ?string $updatedAt = null;

        /** @param array<string,mixed> $row */
        public static function fromRow(array $row): self
        {
            $f = new self();
            $f->id          = (int) $row['id'];
            $f->storeId     = (int) $row['store_id'];
            $f->name        = (string) $row['name'];
            $f->slug        = $row['slug'] ?? null;
            $f->description = $row['description'] ?? null;
            $f->isActive    = (bool) ($row['is_active'] ?? false);
            $f->createdAt   = $row['created_at'] ?? null;
            $f->updatedAt   = $row['updated_at'] ?? null;
            return $f;
        }

        public function isActive(): bool
        {
            return $this->isActive;
        }
    }

## Rules

1. final class. Public typed properties. No getters or setters.

2. Every property has a default. This is non-negotiable.

   Wrong:

       public int $id;
       public string $name;

   Right:

       public int $id = 0;
       public string $name = '';
       public ?string $description = null;

   PHP 8 throws "must not be accessed before initialization" when a
   fresh instance is created and a property is read before being
   written. Defaults prevent this. This was a bug class we hit in
   Stage 006 and fixed across six models.

3. Static fromRow(array): self is the only hydration path. No
   constructors. No named constructors. No ::make().

4. fromRow casts every value explicitly:

       (int) $row['id']
       (string) $row['name']
       (bool) ($row['is_active'] ?? false)

   Nullable columns use the ?? null guard:

       $row['description'] ?? null

5. Business logic methods live on the model. Examples:

       $customer->isPending()
       $customer->isApproved()
       $customer->canCheckout()
       $product->isOnSale()
       $product->discountPercent()
       $inventory->isLowStock()

   Those are model methods, not repository methods. A repository
   returns data. A model decides what the data means.

6. No constructor promotion. No magic methods. No active record
   pattern. Models are pure DTOs.

## Hydration From Rows

Repositories fetch rows from the database as associative arrays and
map them to models:

    $rows = $this->db->select("SELECT * FROM `commerce_foos` WHERE ...", $params);
    return array_map(fn ($r) => Foo::fromRow($r), $rows);

fromRow is the only place column-to-property mapping happens. If a
column is renamed, fromRow is the single place that changes.

## Column Name to Property Name

Database columns are snake_case. Model properties are camelCase.

    id              -> $id
    store_id        -> $storeId
    customer_group_id  -> $customerGroupId
    created_at      -> $createdAt
    is_active       -> $isActive

The conversion happens inside fromRow, not in the repository. Views
never see snake_case on a model property.

## Default Values by Type

    int       -> 0
    float     -> 0.0
    bool      -> false
    string    -> ''
    ?int      -> null
    ?string   -> null
    ?bool     -> null
    array     -> []

For a nullable type the default is always null, because null is a
meaningful value (not set, or the underlying column is NULL).

## Business Logic Example

    final class Customer
    {
        public string $status = 'active';
        public string $approvalStatus = 'approved';

        public function isActive(): bool
        {
            return $this->status === 'active';
        }

        public function isApproved(): bool
        {
            return $this->approvalStatus === 'approved';
        }

        public function canCheckout(): bool
        {
            return $this->isActive() && $this->isApproved();
        }
    }

The controller calls $customer->canCheckout(). The controller does
not check $customer->status and $customer->approvalStatus itself.
The rule lives on the model.

## What Belongs on the Model vs the Repository

Belongs on the model:

    isActive()
    isPublished()
    canCheckout()
    discountPercent()
    isLowStock()
    fullName()
    initials()

Belongs on the repository:

    findById()
    findByEmail()
    paginate()
    count()
    approve()
    reject()

The model answers "what does this data mean". The repository answers
"how do I get or store this data". Never mix.

## When a Model Needs an Extra Field Not in the Table

Do not add a property that has no column. Instead, add a domain
method:

    public function isExpiring(): bool
    {
        return $this->expiresAt !== null
            && strtotime($this->expiresAt) < time() + 86400;
    }

The property stays a direct mirror of the column. Derived state is
always computed, never stored.
