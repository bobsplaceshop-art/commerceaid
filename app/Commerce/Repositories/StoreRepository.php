<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Core\Database;

/**
 * Store lookup. Unlike other Commerce repositories, this one CANNOT
 * extend CommerceRepository - that base requires a store_id to already
 * be resolved, and this is the class that finds it.
 *
 * Reads from `commerce_stores` and `commerce_store_domains`.
 * Writes use raw SQL via Database::insert() and Database::execute().
 */
final class StoreRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByHostname(string $hostname): ?array
    {
        $hostname = strtolower(trim($hostname));
        if ($hostname === '') return null;

        return $this->db->selectOne(
            "SELECT s.*
               FROM `commerce_stores` s
               JOIN `commerce_store_domains` d ON d.`store_id` = s.`id`
              WHERE d.`hostname` = ?
                AND s.`status` = 'active'
              ORDER BY d.`is_primary` DESC
              LIMIT 1",
            [$hostname]
        );
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') return null;

        return $this->db->selectOne(
            "SELECT * FROM `commerce_stores`
              WHERE `slug` = ? AND `status` = 'active'
              LIMIT 1",
            [$slug]
        );
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `commerce_stores` WHERE `id` = ? LIMIT 1",
            [$id]
        );
    }

    /**
     * The single active store, if exactly one exists.
     * @return array<string,mixed>|null
     */
    public function findDefault(): ?array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_stores`
              WHERE `status` = 'active'
              ORDER BY `id` ASC
              LIMIT 2"
        );
        return count($rows) === 1 ? $rows[0] : null;
    }

    /** @return array<int, array<string,mixed>> */
    public function allActive(): array
    {
        return $this->db->select(
            "SELECT `id`, `slug`, `name`, `theme_slug`
               FROM `commerce_stores`
              WHERE `status` = 'active'
              ORDER BY `name` ASC"
        );
    }

    /** Insert a store. Returns the new id. */
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

    /** Update a store row. */
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

    /** @return array<int, array<string,mixed>> */
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
