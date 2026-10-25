<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

/**
 * Lists selectable resources for the admin menu-item picker.
 *
 * This is the read side of the resource pair. The picker renders
 * groups (categories, products, pages, legal pages) and each row
 * carries (id, label). Selecting a row stores resource_type +
 * resource_id on a navigation item. The storefront resolves that
 * pair to an href at render time via NavigationRepository.
 *
 * No write methods. Picker only.
 */
final class NavigationResourceRepository extends CommerceRepository
{
    /** @return array<int, array{id:int,label:string}> */
    public function categories(): array
    {
        $rows = $this->db->select(
            'SELECT `id`, `name` FROM `commerce_categories`
              WHERE `store_id` = ? AND `deleted_at` IS NULL AND `status` = \'active\'
              ORDER BY `name` ASC',
            [$this->storeId()]
        );
        return array_map(
            fn ($r) => ['id' => (int) $r['id'], 'label' => (string) $r['name']],
            $rows
        );
    }

    /** @return array<int, array{id:int,label:string}> */
    public function products(): array
    {
        $rows = $this->db->select(
            'SELECT `id`, `name`, `sku` FROM `commerce_products`
              WHERE `store_id` = ? AND `deleted_at` IS NULL AND `status` = \'published\'
              ORDER BY `name` ASC',
            [$this->storeId()]
        );
        return array_map(function ($r) {
            $label = (string) $r['name'];
            $sku = (string) ($r['sku'] ?? '');
            if ($sku !== '') {
                $label .= ' (' . $sku . ')';
            }
            return ['id' => (int) $r['id'], 'label' => $label];
        }, $rows);
    }

    /** @return array<int, array{id:int,label:string}> */
    public function pages(): array
    {
        $rows = $this->db->select(
            'SELECT `id`, `title` FROM `commerce_store_pages`
              WHERE `store_id` = ? AND `status` = \'published\'
              ORDER BY `title` ASC',
            [$this->storeId()]
        );
        return array_map(
            fn ($r) => ['id' => (int) $r['id'], 'label' => (string) $r['title']],
            $rows
        );
    }

    /** @return array<int, array{id:int,label:string}> */
    public function legalPages(): array
    {
        $rows = $this->db->select(
            'SELECT `id`, `title` FROM `commerce_legal_pages`
              WHERE `store_id` = ? AND `is_published` = 1
              ORDER BY `title` ASC',
            [$this->storeId()]
        );
        return array_map(
            fn ($r) => ['id' => (int) $r['id'], 'label' => (string) $r['title']],
            $rows
        );
    }

    /**
     * Every resource type in one call, keyed by type. Convenience for
     * the admin builder that renders a single grouped picker.
     *
     * @return array<string, array<int, array{id:int,label:string}>>
     */
    public function allGrouped(): array
    {
        return [
            'category'   => $this->categories(),
            'product'    => $this->products(),
            'page'       => $this->pages(),
            'legal_page' => $this->legalPages(),
        ];
    }
}
