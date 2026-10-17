<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Import;

use CorporateAid\Commerce\Repositories\AttributeRepository;
use CorporateAid\Commerce\Repositories\BrandRepository;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Repositories\CustomerGroupRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;

/**
 * Generates the example CSV that a merchant can download
 * for any entity. Stage 016.
 *
 * The template answers two questions the bare upload
 * screen cannot:
 *
 *   1. What are the exact column names for this entity?
 *   2. What format does each column expect?
 *
 * The first is the handler's fields() keys, in declaration
 * order. The second is a sample row per entity that uses
 * values the handler's validate() accepts. A merchant who
 * uploads the template unchanged sees a clean validate
 * pass on their first attempt.
 *
 * The sample values are entity-specific. Products and
 * variants reference real brands / categories / products
 * from the current store when those exist, so the sample
 * row validates cleanly even when the merchant's own
 * catalog is populated. If the store has none of an entity
 * yet, the sample uses an empty reference (which the
 * handler's validate() accepts for optional fields).
 *
 * See docs/stages/016_data-import-plan.md.
 */
final class ImportTemplateGenerator
{
    public function __construct(
        private BrandRepository $brands,
        private CategoryRepository $categories,
        private AttributeRepository $attributes,
        private ProductRepository $products,
        private CustomerGroupRepository $groups,
    ) {
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, string>}
     *         [header, sampleRow]
     */
    public function generate(ImportHandler $handler): array
    {
        $fields = $handler->fields();
        $header = array_keys($fields);
        $sample = $this->sampleFor($handler->entityKey());

        // Guarantee every header column has a sample value,
        // even if the entity's sample omits a field. An
        // omitted field renders as an empty cell.
        $row = [];
        foreach ($header as $col) {
            $row[$col] = (string) ($sample[$col] ?? '');
        }

        return [$header, $row];
    }

    /**
     * @return array<string, string>
     */
    private function sampleFor(string $entityKey): array
    {
        return match ($entityKey) {
            'brand'     => $this->sampleBrand(),
            'category'  => $this->sampleCategory(),
            'attribute' => $this->sampleAttribute(),
            'product'   => $this->sampleProduct(),
            'variant'   => $this->sampleVariant(),
            'inventory' => $this->sampleInventory(),
            'customer'  => $this->sampleCustomer(),
            default     => [],
        };
    }

    /** @return array<string, string> */
    private function sampleBrand(): array
    {
        return [
            'name' => 'Example Brand',
            'slug' => 'example-brand',
        ];
    }

    /** @return array<string, string> */
    private function sampleCategory(): array
    {
        return [
            'name'         => 'Example Category',
            'slug'         => 'example-category',
            'parent_slug'  => '',
            'description'  => '',
        ];
    }

    /** @return array<string, string> */
    private function sampleAttribute(): array
    {
        return [
            'name'            => 'Example Attribute',
            'slug'            => 'example-attribute',
            'input_type'      => 'select',
            'is_variant_axis' => '0',
        ];
    }

    /** @return array<string, string> */
    private function sampleProduct(): array
    {
        $brandSlug = '';
        $allBrands = $this->brands->listAll();
        if ($allBrands !== []) {
            $brandSlug = (string) $allBrands[0]->slug;
        }

        $categorySlugs = '';
        $firstCategory = $this->categories->findBySlug('example-category');
        if ($firstCategory === null) {
            $firstCategory = $this->categories->findBySlug('uncategorized');
        }
        if ($firstCategory !== null) {
            $categorySlugs = (string) $firstCategory->slug;
        }

        return [
            'name'              => 'Example Product',
            'sku'               => 'EXAMPLE-001',
            'slug'              => 'example-product',
            'price'             => '9.99',
            'brand_slug'        => $brandSlug,
            'category_slugs'    => $categorySlugs,
            'short_description' => 'A one-line summary.',
            'description'       => 'A longer description of the product.',
        ];
    }

    /** @return array<string, string> */
    private function sampleVariant(): array
    {
        $productSku = '';
        $firstProduct = $this->products->paginate([], 1, 1)['items'] ?? [];
        if ($firstProduct !== []) {
            $productSku = (string) ($firstProduct[0]->sku ?? '');
        }

        // If the store already has attributes, use their
        // slugs so the sample row validates. If not, use
        // the generic pair the plan names.
        $pairs = [];
        $allAttrs = $this->attributes->allWithValues();
        foreach ($allAttrs as $attr) {
            if (!empty($attr['is_variant_axis']) && !empty($attr['values'])) {
                $slug  = (string) $attr['slug'];
                $value = (string) $attr['values'][0]['slug'];
                if ($slug !== '' && $value !== '') {
                    $pairs[] = $slug . ':' . $value;
                }
            }
            if (count($pairs) >= 2) {
                break;
            }
        }
        if ($pairs === []) {
            $pairs = ['color:red', 'size:m'];
        }

        return [
            'product_sku'      => $productSku,
            'sku'              => 'EXAMPLE-001-V1',
            'price'            => '9.99',
            'attribute_values' => implode('|', $pairs),
        ];
    }

    /** @return array<string, string> */
    private function sampleInventory(): array
    {
        $sku = 'EXAMPLE-001';
        $firstProduct = $this->products->paginate([], 1, 1)['items'] ?? [];
        if ($firstProduct !== []) {
            $sku = (string) ($firstProduct[0]->sku ?? 'EXAMPLE-001');
        }
        return [
            'sku'      => $sku,
            'quantity' => '10',
        ];
    }

    /** @return array<string, string> */
    private function sampleCustomer(): array
    {
        $groupSlug = '';
        $firstGroup = $this->groups->listForSelect();
        if ($firstGroup !== []) {
            $groupSlug = (string) ($firstGroup[0]['slug'] ?? '');
        }
        return [
            'email'               => 'example@example.test',
            'first_name'          => 'Example',
            'last_name'           => 'Customer',
            'phone'               => '',
            'company'             => '',
            'tax_id'              => '',
            'group_slug'          => $groupSlug,
            'accepts_marketing'   => '0',
        ];
    }
}
