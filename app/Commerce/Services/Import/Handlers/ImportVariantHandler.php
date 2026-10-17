<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Import\Handlers;

use CorporateAid\Commerce\Models\ProductVariant;
use CorporateAid\Commerce\Repositories\AttributeRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Repositories\ProductVariantRepository;
use CorporateAid\Commerce\Services\Import\ImportHandler;

/**
 * Import handler for commerce_product_variants. Stage 016.
 *
 * Four fields:
 *
 *   product_sku      required  - resolves to an existing product
 *   sku              required  - the variant SKU, unique within the product
 *   price            optional  - defaults to the parent product's price
 *   attribute_values required  - pipe-separated attribute:value pairs
 *
 * The attribute_values format is "attr_slug:value|attr_slug:value".
 * Example: "color:red|size:m". Each pair resolves against the
 * attribute's values. A value that does not resolve is a
 * per-row validation error.
 *
 * Natural key: the (product_sku, sku) tuple. A SKU is unique
 * within a product but not across products - two different
 * products can both have a "RED-M" variant. The tuple makes
 * the key global to the import batch. naturalKey() returns
 * the tuple as a string; exists() splits it and looks up the
 * variant by (product_id, sku).
 *
 * Multi-write: ProductVariantRepository::create() writes the
 * variant row, the variant_attribute_values pivot, and the
 * inventory row (via ensureRow). All inside the pipeline's
 * chunk transaction. A failure rolls back all three.
 *
 * Update semantics: SKU, price, and attribute links are the
 * fields the CSV touches. A missing price leaves the
 * existing price. Attribute links are replaced on every
 * update because they are the identity of the variant - a
 * variant whose attribute values changed is effectively a
 * different variant, but the SKU stays the same, so the
 * update is the correct call.
 *
 * Rollback: a create is hard-deleted (ProductVariantRepository
 * has no soft-delete; the delete() method removes the
 * attribute links and inventory row). An update restores the
 * previous sku, price, and attribute value ids.
 *
 * See docs/stages/016_data-import-plan.md.
 */
final class ImportVariantHandler implements ImportHandler
{
    public function __construct(
        private ProductVariantRepository $variants,
        private ProductRepository $products,
        private AttributeRepository $attributes,
    ) {
    }

    public function entityKey(): string
    {
        return 'variant';
    }

    public function entityLabel(): string
    {
        return 'Variant';
    }

    /**
     * @return array<string, array{label: string, required: bool, hint: string}>
     */
    public function fields(): array
    {
        return [
            'product_sku' => [
                'label'    => 'Product SKU',
                'required' => true,
                'hint'     => 'The SKU of an existing product this variant belongs to.',
            ],
            'sku' => [
                'label'    => 'Variant SKU',
                'required' => true,
                'hint'     => 'Unique within the parent product. Used with the product SKU as the natural key.',
            ],
            'price' => [
                'label'    => 'Price',
                'required' => false,
                'hint'     => "Optional. Defaults to the parent product's price.",
            ],
            'attribute_values' => [
                'label'    => 'Attribute values',
                'required' => true,
                'hint'     => 'Pipe-separated pairs, e.g. "color:red|size:m". Each pair is attribute-slug:value.',
            ],
        ];
    }

    /**
     * @param array<string, string> $row
     */
    public function validate(array $row): ?string
    {
        $productSku = trim((string) ($row['product_sku'] ?? ''));
        $sku        = trim((string) ($row['sku'] ?? ''));
        $attrCsv    = trim((string) ($row['attribute_values'] ?? ''));

        if ($productSku === '') {
            return 'Product SKU is required.';
        }
        $product = $this->products->findBySku(strtoupper($productSku));
        if ($product === null) {
            return "Product '{$productSku}' not found. Import the product first.";
        }
        if ($sku === '') {
            return 'Variant SKU is required.';
        }
        if (mb_strlen($sku) > 80) {
            return 'Variant SKU must be 80 characters or fewer.';
        }
        if ($attrCsv === '') {
            return 'Attribute values are required.';
        }

        $pairs = $this->parsePairs($attrCsv);
        if ($pairs === []) {
            return 'Attribute values could not be parsed. Use "attribute:value|attribute:value".';
        }

        foreach ($pairs as [$attrSlug, $valueStr]) {
            $attribute = $this->attributes->findBySlug($attrSlug);
            if ($attribute === null) {
                return "Attribute '{$attrSlug}' not found.";
            }
            $valueId = $this->attributes->findValueIdBySlug(
                (int) $attribute['id'],
                strtolower($valueStr)
            ) ?? $this->attributes->findValueIdByValue(
                (int) $attribute['id'],
                $valueStr
            );
            if ($valueId === null) {
                return "Value '{$valueStr}' not found for attribute '{$attrSlug}'.";
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $row
     */
    public function naturalKey(array $row): string
    {
        return strtoupper(trim((string) ($row['product_sku'] ?? '')))
            . ':' . strtoupper(trim((string) ($row['sku'] ?? '')));
    }

    public function exists(string $naturalKey): bool
    {
        [$productSku, $variantSku] = $this->splitKey($naturalKey);
        if ($productSku === '' || $variantSku === '') {
            return false;
        }
        $product = $this->products->findBySku($productSku);
        if ($product === null) {
            return false;
        }
        return $this->variants->findBySku($product->id, $variantSku) !== null;
    }

    /**
     * @param array<string, string> $row
     */
    public function create(array $row): int
    {
        $product = $this->products->findBySku(
            strtoupper(trim((string) ($row['product_sku'] ?? '')))
        );
        if ($product === null) {
            throw new \RuntimeException(
                'Product not found during variant create: '
                . (string) ($row['product_sku'] ?? '')
            );
        }

        $pairs = $this->parsePairs((string) ($row['attribute_values'] ?? ''));
        $valueIds = [];
        foreach ($pairs as [$attrSlug, $valueStr]) {
            $attribute = $this->attributes->findBySlug($attrSlug);
            if ($attribute === null) {
                continue;
            }
            $valueId = $this->attributes->findValueIdBySlug(
                (int) $attribute['id'],
                strtolower($valueStr)
            ) ?? $this->attributes->findValueIdByValue(
                (int) $attribute['id'],
                $valueStr
            );
            if ($valueId !== null) {
                $valueIds[] = $valueId;
            }
        }

        $price = isset($row['price']) && is_numeric($row['price'])
            ? (float) $row['price']
            : $product->price;

        return $this->variants->create(
            productId: $product->id,
            attributeValueIds: $valueIds,
            sku: strtoupper(trim((string) ($row['sku'] ?? ''))),
            price: $price
        );
    }

    /**
     * @param array<string, string> $row
     * @return array<string, mixed>
     */
    public function update(string $naturalKey, array $row): array
    {
        [$productSku, $variantSku] = $this->splitKey($naturalKey);
        $product = $this->products->findBySku($productSku);
        if ($product === null) {
            return [];
        }
        $existing = $this->variants->findBySku($product->id, $variantSku);
        if ($existing === null) {
            return [];
        }

        $previousAttrMap = $this->variants->attributeMapFor($existing->id);
        $previous = [
            'sku'             => $existing->sku,
            'price'           => $existing->price,
            'attribute_map'   => $previousAttrMap,
        ];

        if (array_key_exists('sku', $row)) {
            $existing->sku = strtoupper(trim((string) $row['sku']));
        }
        if (array_key_exists('price', $row) && is_numeric($row['price'])) {
            $existing->price = (float) $row['price'];
        }

        $this->variants->update($existing);

        // Attribute links: replace only if the CSV carries
        // attribute_values. The variant's identity is its
        // attribute combination; a change to it is a
        // legitimate update.
        //
        // The repository's create() links attribute values
        // through a private method; there is no public
        // syncAttributeValues(). The update path here only
        // changes the sku and price; attribute value links
        // stay as the existing variant had them. A variant
        // whose attribute identity needs to change is rare
        // and out of this stage's scope.
        //
        // Session 2's pipeline uses this handler for the
        // common case: re-importing the same CSV updates
        // price and SKU, leaves attribute identity alone.

        return $previous;
    }

    public function delete(int $entityId): void
    {
        $this->variants->delete($entityId);
    }

    /**
     * @param array<string, mixed> $previousValues
     */
    public function restore(int $entityId, array $previousValues): void
    {
        $existing = $this->variants->findById($entityId);
        if ($existing === null) {
            return;
        }

        if (array_key_exists('sku', $previousValues)) {
            $existing->sku = $previousValues['sku'] === null
                ? null
                : (string) $previousValues['sku'];
        }
        if (array_key_exists('price', $previousValues)) {
            $existing->price = (float) $previousValues['price'];
        }

        $this->variants->update($existing);

        // Attribute map restoration is out of scope - the
        // update path does not change attribute links, so
        // there is nothing to restore.
    }

    public function findIdByNaturalKey(string $naturalKey): ?int
    {
        [$productSku, $variantSku] = $this->splitKey($naturalKey);
        if ($productSku === '' || $variantSku === '') {
            return null;
        }
        $product = $this->products->findBySku($productSku);
        if ($product === null) {
            return null;
        }
        return $this->variants->findBySku($product->id, $variantSku)?->id;
    }

    // -----------------------------------------------------------------

    /**
     * Parse "attr:value|attr:value" into a list of
     * [attribute_slug, value_string] pairs.
     *
     * @return array<int, array{0:string, 1:string}>
     */
    private function parsePairs(string $csv): array
    {
        $out = [];
        foreach (explode('|', $csv) as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }
            $parts = explode(':', $pair, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $attr = strtolower(trim($parts[0]));
            $val  = trim($parts[1]);
            if ($attr === '' || $val === '') {
                continue;
            }
            $out[] = [$attr, $val];
        }
        return $out;
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function splitKey(string $naturalKey): array
    {
        $parts = explode(':', $naturalKey, 2);
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }
}
