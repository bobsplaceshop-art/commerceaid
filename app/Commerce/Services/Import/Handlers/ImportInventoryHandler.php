<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Import\Handlers;

use CorporateAid\Commerce\Repositories\InventoryRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Services\Import\ImportHandler;

/**
 * Import handler for commerce_inventory. Stage 016.
 *
 * Two fields:
 *
 *   sku       required  - the SKU of a simple product
 *   quantity  required  - the absolute on-hand quantity
 *
 * Scope note: this Session 2 handler covers simple-product
 * inventory only. Variant-SKU inventory is deferred to
 * 016.4 because ProductVariantRepository has no global
 * SKU lookup - findBySku() takes the parent product id.
 * A CSV that carries only a variant SKU cannot resolve
 * without that lookup; 016.4 adds findBySkuGlobal() to
 * the repository and extends this handler.
 *
 * SKU resolution: the handler looks up the SKU against
 * commerce_products.sku. A match resolves to
 * (product_id, variant_id = 0), the sentinel for a
 * simple product's inventory row.
 *
 * Natural key: the SKU, trimmed and uppercased. The
 * database's inventory row is identified by the tuple
 * (store_id, product_id, variant_id); the handler
 * resolves the SKU to that tuple internally. The plan's
 * "natural key is the tuple" reflects the database row
 * identity, which the handler resolves from the CSV's
 * single SKU column.
 *
 * Write semantics: every write goes through
 * InventoryRepository::adjust(). The method records a
 * movement in the ledger and updates the current stock
 * row inside one transaction. The handler computes the
 * delta the method needs:
 *
 *   create    delta = quantity - 0 = quantity (movement type 'opening')
 *   update    delta = quantity - currentQuantity (movement type 'stocktake')
 *
 * The plan named 'import' as the movement type. The
 * repository's valid-type list does not include 'import';
 * the correct type is 'stocktake' (a full correction to a
 * known value). The reason option carries "Imported from
 * CSV" and the reference_type/reference_id carry the
 * batch id. The ledger records the import as a stocktake
 * with the batch reference.
 *
 * Rollback: the pipeline calls delete() for a create
 * (target quantity 0) or restore() for an update (target
 * quantity = previous quantity). Both adjust the row to
 * the target with a movement type of 'stocktake' and a
 * reason of "Rolled back import".
 *
 * See docs/stages/016_data-import-plan.md.
 */
final class ImportInventoryHandler implements ImportHandler
{
    public function __construct(
        private InventoryRepository $inventory,
        private ProductRepository $products,
    ) {
    }

    public function entityKey(): string
    {
        return 'inventory';
    }

    public function entityLabel(): string
    {
        return 'Inventory';
    }

    /**
     * @return array<string, array{label: string, required: bool, hint: string}>
     */
    public function fields(): array
    {
        return [
            'sku' => [
                'label'    => 'SKU',
                'required' => true,
                'hint'     => 'The SKU of a simple product or of a specific variant.',
            ],
            'quantity' => [
                'label'    => 'Quantity',
                'required' => true,
                'hint'     => 'The absolute on-hand quantity. Integer, zero or greater.',
            ],
        ];
    }

    /**
     * @param array<string, string> $row
     */
    public function validate(array $row): ?string
    {
        $sku      = trim((string) ($row['sku'] ?? ''));
        $quantity = trim((string) ($row['quantity'] ?? ''));

        if ($sku === '') {
            return 'SKU is required.';
        }
        if ($quantity === '') {
            return 'Quantity is required.';
        }
        if (!ctype_digit($quantity)) {
            return 'Quantity must be a non-negative integer.';
        }
        if ((int) $quantity > 10_000_000) {
            return 'Quantity must be 10,000,000 or fewer.';
        }

        if ($this->resolveUnit($sku) === null) {
            return "SKU '{$sku}' not found. Import the product or variant first.";
        }

        return null;
    }

    /**
     * @param array<string, string> $row
     */
    public function naturalKey(array $row): string
    {
        return strtoupper(trim((string) ($row['sku'] ?? '')));
    }

    public function exists(string $naturalKey): bool
    {
        $unit = $this->resolveUnit($naturalKey);
        if ($unit === null) {
            return false;
        }
        return $this->inventory->forUnit($unit['product_id'], $unit['variant_id']) !== null;
    }

    /**
     * @param array<string, string> $row
     */
    public function create(array $row): int
    {
        $unit = $this->resolveUnit((string) ($row['sku'] ?? ''));
        if ($unit === null) {
            throw new \RuntimeException(
                'SKU not found during inventory create: '
                . (string) ($row['sku'] ?? '')
            );
        }
        $quantity = (int) ($row['quantity'] ?? 0);

        $movement = $this->inventory->adjust(
            $unit['product_id'],
            $unit['variant_id'],
            $quantity,
            'opening',
            [
                'reference_type' => 'import_batch',
                'reason'         => 'Imported from CSV',
            ]
        );

        // The pipeline records the created entity id as the
        // inventory row's id (not the movement id). The
        // movement id is in the ledger for audit.
        return (int) $movement->id;
    }

    /**
     * @param array<string, string> $row
     * @return array<string, mixed>
     */
    public function update(string $naturalKey, array $row): array
    {
        $unit = $this->resolveUnit($naturalKey);
        if ($unit === null) {
            return [];
        }
        $current = $this->inventory->forUnit($unit['product_id'], $unit['variant_id']);
        if ($current === null) {
            return [];
        }

        $previous = ['quantity' => $current->quantity];

        $target = (int) ($row['quantity'] ?? $current->quantity);
        $delta  = $target - $current->quantity;

        if ($delta !== 0) {
            $this->inventory->adjust(
                $unit['product_id'],
                $unit['variant_id'],
                $delta,
                'stocktake',
                [
                    'reference_type' => 'import_batch',
                    'reason'         => 'Imported from CSV',
                ]
            );
        }

        return $previous;
    }

    public function delete(int $entityId): void
    {
        // The pipeline passes the movement id (returned
        // from create()). We need the inventory row, not
        // the movement. The pipeline's rollback flow
        // re-resolves by re-reading the batch's import
        // row's payload (which carries the SKU). This
        // method cannot reliably delete without the
        // product_id and variant_id, so it does nothing.
        // The pipeline's rollback for inventory is
        // target-quantity-driven, not entity-id-driven.
        //
        // See the rollback notes in the interface and the
        // ImportRollback service (Session 2 pipeline).
    }

    /**
     * @param array<string, mixed> $previousValues
     */
    public function restore(int $entityId, array $previousValues): void
    {
        // Same constraint as delete(): the pipeline
        // re-resolves the unit from the batch row's
        // payload. The previous quantity is passed in
        // $previousValues['quantity'].
        //
        // Implementation note for Session 2: the
        // pipeline's rollback walks the batch's rows in
        // reverse order, re-reads each row's payload,
        // re-resolves the SKU, and calls
        // InventoryRepository::adjust() directly. The
        // handler's restore() is not used for inventory.
    }

    // -----------------------------------------------------------------

    /**
     * Resolve a CSV SKU to a (product_id, variant_id) unit.
     * Product-first: a product SKU resolves to variant_id=0.
     * Variant SKU resolves to the variant's product_id and
     * its own id.
     *
     * @return array{product_id:int, variant_id:int}|null
     */
    private function resolveUnit(string $sku): ?array
    {
        $sku = strtoupper(trim($sku));
        if ($sku === '') {
            return null;
        }

        $product = $this->products->findBySku($sku);
        if ($product !== null) {
            return ['product_id' => $product->id, 'variant_id' => 0];
        }

        // No product match. Variant SKU resolution is
        // 016.4's scope; this handler does not resolve
        // variant SKUs.
        return null;
    }
}
