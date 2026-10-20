<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Commerce\Models\Store;

/**
 * Store lookup. Unlike other Commerce repositories, this one CANNOT
 * extend CommerceRepository - that base requires a store_id to already
 * be resolved, and this is the class that finds it.
 *
 * Read methods return Store models. Write methods accept plain
 * arrays (mass-assignment is guarded by StoreService::updateStore).
 *
 * Reads from `commerce_stores` and `commerce_store_domains`.
 * Writes use raw SQL via Database::insert() and Database::execute().
 */
final class StoreRepository
{
    public function __construct(private Database $db)
    {
    }

    // =================================================================
    // Reads
    // =================================================================

    public function findByHostname(string $hostname): ?Store
    {
        $hostname = strtolower(trim($hostname));
        if ($hostname === '') return null;

        $row = $this->db->selectOne(
            "SELECT s.*
               FROM `commerce_stores` s
               JOIN `commerce_store_domains` d ON d.`store_id` = s.`id`
              WHERE d.`hostname` = ?
                AND s.`status` = 'active'
              ORDER BY d.`is_primary` DESC
              LIMIT 1",
            [$hostname]
        );

        return $row ? Store::fromRow($row) : null;
    }

    public function findBySlug(string $slug): ?Store
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') return null;

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_stores`
              WHERE `slug` = ? AND `status` = 'active'
              LIMIT 1",
            [$slug]
        );

        return $row ? Store::fromRow($row) : null;
    }

    public function findById(int $id): ?Store
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_stores` WHERE `id` = ? LIMIT 1",
            [$id]
        );

        return $row ? Store::fromRow($row) : null;
    }

    /**
     * The single active store, if exactly one exists.
     * Returns null when zero or more than one active store is present.
     */
    public function findDefault(): ?Store
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_stores`
              WHERE `status` = 'active'
              ORDER BY `id` ASC
              LIMIT 2"
        );

        if (count($rows) !== 1) {
            return null;
        }

        return Store::fromRow($rows[0]);
    }

    /**
     * The ISO currency code for a store. Joins the store's
     * default_currency_id against core_currencies and returns
     * the code (EUR, KES, USD, ...).
     *
     * The Store model carries the currency id, not the code.
     * This method is the resolution step. Callers that need the
     * code (CheckoutService writes it on every order row) call
     * this rather than reading the model and looking up the
     * code separately.
     *
     * Falls back to 'EUR' when the store has no currency set
     * or the id does not resolve. The fallback matches the
     * default on the Order model and core_currencies, so an
     * unconfigured store degrades to the platform default
     * rather than to an empty string.
     */
    public function currencyCodeForStore(int $storeId): string
    {
        if ($storeId <= 0) {
            return 'EUR';
        }

        $row = $this->db->selectOne(
            "SELECT c.`code`
               FROM `commerce_stores` s
               JOIN `core_currencies` c ON c.`id` = s.`default_currency_id`
              WHERE s.`id` = ?
              LIMIT 1",
            [$storeId]
        );

        if ($row === null) {
            return 'EUR';
        }

        $code = strtoupper(trim((string) ($row['code'] ?? '')));
        return $code !== '' ? $code : 'EUR';
    }

    /** @return array<int, Store> */
    /**
     * Stores this user can access. Super-admins get every active
     * store; other users get only what they're linked to via
     * commerce_store_users.
     *
     * @return array<int, Store>
     */
    public function forUser(int $userId, bool $isSuperAdmin): array
    {
        if ($isSuperAdmin) {
            return $this->allActive();
        }
        
        $rows = $this->db->select(
            "SELECT s.* 
             FROM `commerce_stores` s
             JOIN `commerce_store_users` su ON su.store_id = s.id
             WHERE su.user_id = ?
             ORDER BY s.name ASC",
            [$userId]
        );
        
        return array_map([Store::class, 'fromRow'], $rows);
    }

    /** @return array<int, Store> */
    public function allActive(): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_stores`
              WHERE `status` = 'active'
              ORDER BY `name` ASC"
        );

        return array_map(fn ($r) => Store::fromRow($r), $rows);
    }

    // =================================================================
    // Writes
    //
    // These accept plain arrays and return ints. The read side
    // returns Store models. Write side stays array-based because
    // callers pass partial column sets (mass-assignment). Modeling
    // that as a DTO would require a separate write DTO.
    // =================================================================

    /** Insert a store. Returns the new id. */
    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn ($c) => ':' . $c, $columns);
        $sql = 'INSERT INTO `commerce_stores` (`'
             . implode('`, `', $columns) . '`) VALUES ('
             . implode(', ', $placeholders) . ')';

        $params = [];
        foreach ($data as $k => $v) {
            $params[':' . $k] = $v;
        }

        return $this->db->insert($sql, $params);
    }

    /** Update a store row. Returns affected row count. */
    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): int
    {
        if ($data === []) return 0;

        $assignments = [];
        $params = [':id' => $id];
        foreach ($data as $k => $v) {
            $assignments[] = "`{$k}` = :{$k}";
            $params[':' . $k] = $v;
        }

        $sql = 'UPDATE `commerce_stores` SET '
             . implode(', ', $assignments)
             . ' WHERE `id` = :id';

        return $this->db->execute($sql, $params);
    }

    /**
     * Update a single column on the store row.
     *
     * Whitelisted to a fixed set of columns. Never accepts an
     * arbitrary column name from the caller - the whitelist is
     * the security boundary.
     *
     * Used by the Store Settings page for fields that write to
     * commerce_stores columns (logo_file_id, favicon_file_id,
     * pwa_name, pwa_short_name, pwa_theme_color,
     * pwa_background_color) rather than to the key-value
     * settings table.
     *
     * @param mixed $value null clears a nullable column
     */
    public function updateColumn(int $storeId, string $column, mixed $value): void
    {
        $allowed = [
            'logo_file_id',
            'favicon_file_id',
            'pwa_name',
            'pwa_short_name',
            'pwa_theme_color',
            'pwa_background_color',
            'font_body_slug',
            'font_display_slug',
            'font_script_slug',
            'theme_slug',
        ];

        if (!in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException(
                "Column {$column} is not updatable via updateColumn()."
            );
        }

        if ($storeId <= 0) {
            return;
        }

        $this->db->execute(
            "UPDATE `commerce_stores` SET `{$column}` = ? WHERE `id` = ?",
            [$value, $storeId]
        );
    }

    /** Attach a hostname to a store. Returns the new id. */
    public function addDomain(int $storeId, string $hostname, bool $isPrimary = false): int
    {
        return $this->db->insert(
            "INSERT INTO `commerce_store_domains`
                (`store_id`, `hostname`, `is_primary`, `ssl_verified`)
             VALUES (:store_id, :hostname, :is_primary, 0)",
            [
                ':store_id'   => $storeId,
                ':hostname'   => strtolower(trim($hostname)),
                ':is_primary' => $isPrimary ? 1 : 0,
            ]
        );
    }

    /**
     * Return raw domain rows for a store.
     * Domains are not modeled as DTOs - they carry few columns and
     * are always consumed as a list in the admin UI.
     *
     * @return array<int, array<string,mixed>>
     */
    public function domainsFor(int $storeId): array
    {
        return $this->db->select(
            "SELECT * FROM `commerce_store_domains`
              WHERE `store_id` = ?
              ORDER BY `is_primary` DESC, `id` ASC",
            [$storeId]
        );
    }
}
