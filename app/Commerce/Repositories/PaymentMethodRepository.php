<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

/**
 * Reads and writes commerce_store_payment_methods.
 *
 * One row per provider per store. The row carries
 * is_enabled and sort_order. Missing row = provider is
 * not configured for this store.
 *
 * The registry (PaymentProviderRegistry) consults this
 * repository through enabledForStore(). The admin
 * settings page (Session 5) writes through set().
 *
 * Does not know about PaymentProvider instances. It
 * reads and writes rows of { provider_key, is_enabled,
 * sort_order }.
 *
 * Stage 010.
 */
final class PaymentMethodRepository extends CommerceRepository
{
    /**
     * Every configured provider for the current store,
     * enabled or not. Ordered by sort_order then key.
     *
     * @return array<int, array{
     *     id: int,
     *     provider_key: string,
     *     is_enabled: bool,
     *     sort_order: int
     * }>
     */
    public function listForStore(): array
    {
        $rows = $this->db->select(
            "SELECT `id`, `provider_key`, `is_enabled`, `sort_order`
               FROM `commerce_store_payment_methods`
              WHERE `store_id` = ?
              ORDER BY `sort_order` ASC, `provider_key` ASC",
            [$this->storeId()]
        );

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id'           => (int) $row['id'],
                'provider_key' => (string) $row['provider_key'],
                'is_enabled'   => (bool) $row['is_enabled'],
                'sort_order'   => (int) $row['sort_order'],
            ];
        }
        return $out;
    }

    /**
     * The provider keys enabled for the current store,
     * in sort order. The shape the registry needs.
     *
     * @return array<int, string>
     */
    public function enabledKeysForStore(): array
    {
        $rows = $this->db->select(
            "SELECT `provider_key`
               FROM `commerce_store_payment_methods`
              WHERE `store_id` = ? AND `is_enabled` = 1
              ORDER BY `sort_order` ASC, `provider_key` ASC",
            [$this->storeId()]
        );

        $out = [];
        foreach ($rows as $row) {
            $out[] = (string) $row['provider_key'];
        }
        return $out;
    }

    /**
     * Whether a single provider is enabled for the
     * current store. False when the row is missing or
     * is_enabled is 0.
     */
    public function isEnabled(string $providerKey): bool
    {
        if ($providerKey === '') {
            return false;
        }
        $row = $this->db->selectOne(
            "SELECT `is_enabled`
               FROM `commerce_store_payment_methods`
              WHERE `store_id` = ? AND `provider_key` = ?
              LIMIT 1",
            [$this->storeId(), $providerKey]
        );
        if ($row === null) {
            return false;
        }
        return (bool) $row['is_enabled'];
    }

    /**
     * Insert-or-update a single provider's row.
     *
     * The upsert pattern matches StoreSettingsRepository::set()
     * and the nine other ON DUPLICATE KEY UPDATE sites in the
     * codebase. The unique key on (store_id, provider_key)
     * triggers the update branch.
     *
     * Called from the admin settings page (Session 5).
     */
    public function set(string $providerKey, bool $isEnabled, int $sortOrder = 0): void
    {
        if ($providerKey === '') {
            return;
        }
        $this->db->execute(
            "INSERT INTO `commerce_store_payment_methods`
                (`store_id`, `provider_key`, `is_enabled`, `sort_order`)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                `is_enabled` = VALUES(`is_enabled`),
                `sort_order` = VALUES(`sort_order`)",
            [
                $this->storeId(),
                $providerKey,
                $isEnabled ? 1 : 0,
                max(0, $sortOrder),
            ]
        );
    }

    /**
     * Bulk-write the enabled state and ordering of every
     * provider in one pass. Used by the settings page save.
     *
     * Rows present in the input are upserted. Rows already
     * in the table but absent from the input are disabled
     * (is_enabled = 0) — not deleted, so their sort_order
     * history survives a toggle.
     *
     * @param array<int, array{key: string, enabled: bool,
     *                        sort_order: int}> $rows
     */
    public function sync(array $rows): void
    {
        foreach ($rows as $row) {
            $key = (string) ($row['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $this->set(
                $key,
                (bool) ($row['enabled'] ?? false),
                (int) ($row['sort_order'] ?? 0)
            );
        }
    }

    /**
     * Delete a single row. Rarely used — the settings page
     * disables rather than deletes. Kept for admin tooling
     * and tests.
     */
    public function delete(string $providerKey): void
    {
        if ($providerKey === '') {
            return;
        }
        $this->db->execute(
            "DELETE FROM `commerce_store_payment_methods`
              WHERE `store_id` = ? AND `provider_key` = ?",
            [$this->storeId(), $providerKey]
        );
    }
}
