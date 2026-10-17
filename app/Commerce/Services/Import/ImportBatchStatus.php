<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Import;

/**
 * The lifecycle states of a commerce_import_batches row.
 *
 * A final class of string constants rather than a PHP
 * enum. The codebase's style is constants
 * (AdminSubscriberController::VALID_TABS, ::VALID_SOURCES,
 * the payment method keys). The constants are the values
 * the ENUM column carries. Adding a new state means
 * editing both this class and the ENUM.
 *
 * See docs/stages/016_data-import-plan.md.
 */
final class ImportBatchStatus
{
    /** File uploaded, no mapping yet. */
    public const UPLOADED = 'uploaded';

    /** Columns mapped, not validated. */
    public const MAPPED = 'mapped';

    /** Rows validated. No database write yet. */
    public const VALIDATED = 'validated';

    /** Commit in progress. */
    public const COMMITTING = 'committing';

    /** Commit finished cleanly. */
    public const COMMITTED = 'committed';

    /** Commit finished with at least one rolled-back chunk. */
    public const PARTIALLY_COMMITTED = 'partially_committed';

    /** A committed batch was reversed. */
    public const ROLLED_BACK = 'rolled_back';

    /** The merchant abandoned the batch before commit. */
    public const CANCELLED = 'cancelled';

    /**
     * Every valid state. Used by the repository's status
     * filter and by the controller's status validation.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::UPLOADED,
            self::MAPPED,
            self::VALIDATED,
            self::COMMITTING,
            self::COMMITTED,
            self::PARTIALLY_COMMITTED,
            self::ROLLED_BACK,
            self::CANCELLED,
        ];
    }

    /**
     * The states that count as "in flight" (a batch that is
     * not yet terminal). Used by the admin list's default
     * filter and by the cleanup cron's scope.
     */
    public static function isTerminal(string $status): bool
    {
        return in_array($status, [
            self::COMMITTED,
            self::PARTIALLY_COMMITTED,
            self::ROLLED_BACK,
            self::CANCELLED,
        ], true);
    }
}
