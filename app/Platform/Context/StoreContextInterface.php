<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Context;

/**
 * Platform-facing view of the current store context.
 *
 * Commerce owns StoreContext; Platform cannot import it.
 * This interface declares the subset Platform needs so the
 * dependency direction stays one-way (Core < Platform <
 * Commerce).
 *
 * Returns null when no store is resolved - CLI scripts,
 * early request lifecycle, or a failed resolution. Callers
 * treat null as "no store context" and behave accordingly.
 * Logging writes NULL for store_id rather than throwing.
 */
interface StoreContextInterface
{
    /** The resolved store id, or null if none resolved. */
    public function idOrNull(): ?int;

    /** True when a store has been resolved. */
    public function has(): bool;
}
