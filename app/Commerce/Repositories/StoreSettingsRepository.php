<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\Store;

/**
 * Per-store key-value settings.
 *
 * Every setting is a row in commerce_store_settings keyed by
 * (store_id, key_name). Reads go through one of the typed
 * accessors (bool, string, int) with a required default. If a
 * row is missing, the default is returned - this is what makes
 * "no migration needed" possible for new settings. The code
 * declares what it expects; the row appears only when the
 * merchant changes the value.
 *
 * Settings are loaded once per request and cached in-memory.
 * A call to set() or delete() invalidates the cache so
 * subsequent reads within the same request see the change.
 *
 * See docs/stages/008e.1_german-compliance-plan.md.
 */
final class StoreSettingsRepository extends CommerceRepository
{
    /** @var array<string, string>|null */
    private ?array $cache = null;

    /**
     * All settings for the current store, keyed by key_name.
     * Value is the raw string from the database.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $rows = $this->db->select(
            "SELECT `key_name`, `value`
               FROM `commerce_store_settings`
              WHERE `store_id` = ?",
            [$this->storeId()]
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['key_name']] = (string) ($row['value'] ?? '');
        }

        $this->cache = $map;
        return $map;
    }

    /**
     * Read one setting as a bool. Recognized truthy values are
     * '1', 'true', 'on', 'yes' (case-insensitive). Everything
     * else is false. Missing key returns $default.
     */
    public function bool(string $key, bool $default = false): bool
    {
        $all = $this->all();
        if (!array_key_exists($key, $all)) {
            return $default;
        }
        $raw = strtolower(trim($all[$key]));
        return in_array($raw, ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * Read one setting as a string. Missing key returns $default.
     */
    public function string(string $key, string $default = ''): string
    {
        $all = $this->all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * Read one setting as an int. Missing or non-numeric returns
     * $default.
     */
    public function int(string $key, int $default = 0): int
    {
        $all = $this->all();
        if (!array_key_exists($key, $all)) {
            return $default;
        }
        $raw = trim($all[$key]);
        return is_numeric($raw) ? (int) $raw : $default;
    }

    /**
     * Write one setting. Creates the row if it does not exist.
     * Records the actor if given. Invalidates the cache.
     */
    public function set(string $key, string $value, ?int $userId = null): void
    {
        $this->db->execute(
            "INSERT INTO `commerce_store_settings`
                (`store_id`, `key_name`, `value`, `updated_by`)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                `value`      = VALUES(`value`),
                `updated_by` = VALUES(`updated_by`)",
            [$this->storeId(), $key, $value, $userId]
        );

        $this->cache = null;
    }

    /**
     * Delete one setting. A no-op if the row is missing.
     * Invalidates the cache.
     */
    public function delete(string $key): void
    {
        $this->db->execute(
            "DELETE FROM `commerce_store_settings`
              WHERE `store_id` = ? AND `key_name` = ?",
            [$this->storeId(), $key]
        );

        $this->cache = null;
    }

    /**
     * Custom settings - rows in the table that are not declared
     * in StoreSettingDefinitions. Used by the Advanced panel.
     *
     * @return array<string, string>
     */
    public function customSettings(): array
    {
        $all = $this->all();
        $known = array_keys(\CorporateAid\Commerce\Support\StoreSettingDefinitions::DEFINITIONS);

        $custom = [];
        foreach ($all as $key => $value) {
            if (!in_array($key, $known, true)) {
                $custom[$key] = $value;
            }
        }
        return $custom;
    }

    /**
     * Count of custom settings. Cheap check for the Advanced
     * tab badge.
     */
    public function countCustom(): int
    {
        return count($this->customSettings());
    }
}
