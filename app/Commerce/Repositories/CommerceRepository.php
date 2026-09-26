<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Commerce\Context\StoreContext;

/**
 * Base class for every Commerce repository.
 *
 * All tenant-scoped tables carry a `store_id` column. This base:
 *   - reads `store_id` from StoreContext at query time
 *   - refuses to run if no store is resolved
 *   - stamps `store_id` on every INSERT / UPDATE
 *
 * Every Commerce repository MUST extend this class. Direct use of
 * `Database` outside a repository is a code-review failure.
 */
abstract class CommerceRepository
{
    public function __construct(
        protected Database $db,
        protected StoreContext $storeContext
    ) {
    }

    /**
     * The store_id for the current request. Throws if unresolved.
     */
    protected function storeId(): int
    {
        return $this->storeContext->id();
    }

    /**
     * Scope a SQL snippet to the current store. Prefixes the store
     * column with the given table alias if provided.
     */
    protected function scoped(string $column = 'store_id', ?string $alias = null): string
    {
        $prefix = $alias !== null ? "{$alias}." : '';
        return "{$prefix}{$column} = " . $this->storeId();
    }

    /**
     * Guard - call at the top of any method that must run inside a store.
     */
    protected function requireStore(): void
    {
        $this->storeContext->id(); // throws if missing
    }
}
