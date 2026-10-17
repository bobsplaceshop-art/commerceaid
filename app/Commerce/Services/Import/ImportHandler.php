<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Import;

/**
 * The contract every entity handler implements. Stage 016.
 *
 * A handler is the entity-specific half of the import.
 * It declares the entity's natural key (the field that
 * identifies a row for idempotency), its required
 * fields, and its validation rules. The generic pipeline
 * (parse, map, validate, preview, commit) calls the
 * handler, and the handler knows how to turn one CSV row
 * into one database write.
 *
 * The interface is deliberately small. It does not know
 * about transactions; the pipeline wraps every chunk.
 * It does not know about storage; the pipeline holds the
 * file. It does not know about the batch; the pipeline
 * owns the batch record.
 *
 * A handler:
 *
 *   - validates a row, returning an error message or
 *     null;
 *   - resolves a row's natural key from the mapped data;
 *   - creates the entity row when the natural key is new;
 *   - updates the entity row when the natural key exists;
 *   - returns the previous values for a rollback of an
 *     update, or null for a create.
 *
 * The handler never manages transactions itself. If a
 * create needs multiple related writes (a product plus
 * its category associations), the handler does them in
 * one method call and the pipeline commits the chunk.
 *
 * See docs/stages/016_data-import-plan.md.
 */
interface ImportHandler
{
    /**
     * The entity key the batch record stores. Matches the
     * `entity` column on commerce_import_batches.
     *
     * Examples: 'brand', 'category', 'product', 'customer'.
     */
    public function entityKey(): string;

    /**
     * The human label the admin shows in the entity picker.
     */
    public function entityLabel(): string;

    /**
     * The handler's field definitions, keyed by field name.
     * The map UI uses this to render one select per field.
     *
     * Each field:
     *   - label:  the human label shown on the map screen
     *   - required: true if the CSV must supply a value
     *   - hint:  short explanation shown below the select
     *
     * @return array<string, array{label: string, required: bool, hint: string}>
     */
    public function fields(): array;

    /**
     * Validate one mapped row.
     *
     * The row is keyed by the handler's field names
     * (already mapped from the CSV's header names by the
     * pipeline). Every required field has a value.
     *
     * Returns null on pass, or the error message on fail.
     * The message is shown in the failed-row export and
     * the preview screen.
     *
     * @param array<string, string> $row
     */
    public function validate(array $row): ?string;

    /**
     * Extract the entity's natural key from a mapped row.
     * Used for idempotency: the row is created if the key
     * is new, updated if the key exists.
     *
     * The key is normalised (trimmed, lowercased where
     * appropriate) so two semantically identical values
     * resolve to the same key.
     *
     * @param array<string, string> $row
     */
    public function naturalKey(array $row): string;

    /**
     * Does the entity row for this natural key already
     * exist in the current store?
     */
    public function exists(string $naturalKey): bool;

    /**
     * Insert the entity row.
     *
     * Returns the new row's id.
     *
     * @param array<string, string> $row
     */
    public function create(array $row): int;

    /**
     * Update the entity row identified by the natural key.
     *
     * Returns the previous values of every column the
     * update overwrote, keyed by column name, so the
     * rollback can restore them exactly. Returns an empty
     * array if the update wrote nothing (a re-import of a
     * row that has not changed).
     *
     * @param array<string, string> $row
     * @return array<string, mixed>
     */
    public function update(string $naturalKey, array $row): array;

    /**
     * Delete the entity row created by a batch (rollback
     * of a create). Does not run for updates - those are
     * restored from the previous values in update().
     */
    public function delete(int $entityId): void;

    /**
     * Restore the previous values for an updated row
     * (rollback of an update).
     *
     * @param array<string, mixed> $previousValues
     */
    public function restore(int $entityId, array $previousValues): void;

    /**
     * The entity row's id for a given natural key, or null
     * when the key does not resolve to an entity in the
     * current store. Used by the committer to distinguish
     * create from update, and to fill the entity_id on
     * commerce_import_rows::markCommitted() for updates.
     *
     * A handler that also has a findById-style method on
     * its repository delegates to that. A handler whose
     * natural key is a tuple (variant) resolves the
     * tuple first.
     */
    public function findIdByNaturalKey(string $naturalKey): ?int;
}
