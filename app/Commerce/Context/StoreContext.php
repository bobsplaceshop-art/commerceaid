<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Context;

use CorporateAid\Commerce\Exceptions\StoreContextMissingException;
use CorporateAid\Platform\Context\StoreContextInterface;

/**
 * Single source of truth for "which store is the current request serving".
 *
 * Resolution order (populated by StoreResolutionMiddleware):
 *   1. Explicit  - admin session switcher or /commerce/ca-admin/stores/{id}/...
 *   2. Domain    - commerce_store_domains.hostname lookup
 *   3. Path      - /s/{slug}/... fallback for dev / staging
 *   4. Default   - the single store row if only one exists
 *   5. Failure   - throws StoreContextMissingException
 */
final class StoreContext implements StoreContextInterface
{
    private ?int $storeId = null;
    private ?string $storeSlug = null;
    private ?string $resolutionSource = null;

    public function set(int $storeId, string $storeSlug, string $source): void
    {
        $this->storeId = $storeId;
        $this->storeSlug = $storeSlug;
        $this->resolutionSource = $source;
    }

    public function setId(?int $storeId): void
    {
        $this->storeId = $storeId;
    }

    public function id(): int
    {
        if ($this->storeId === null) {
            throw new StoreContextMissingException(
                'No store has been resolved for this request.'
            );
        }
        return $this->storeId;
    }

    public function idOrNull(): ?int
    {
        return $this->storeId;
    }

    public function slug(): ?string
    {
        return $this->storeSlug;
    }

    public function source(): ?string
    {
        return $this->resolutionSource;
    }

    public function has(): bool
    {
        return $this->storeId !== null;
    }

    public function clear(): void
    {
        $this->storeId = null;
        $this->storeSlug = null;
        $this->resolutionSource = null;
    }
}
