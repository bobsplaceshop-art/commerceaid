<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;

/**
 * Role and permission repository.
 *
 * Read methods are used across the platform (the admin user
 * surface, the notification rules engine). Write methods were
 * added in Stage 008K.4 for the admin roles editor.
 *
 * Roles are a Platform concern - they exist regardless of which
 * product is installed. The admin roles editor UI lives in
 * Commerce (AdminRoleController) because it renders inside the
 * Commerce admin shell.
 */
final class RoleRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // =================================================================
    // Reads
    // =================================================================

    /**
     * @param int|null $storeId  When set, return global roles
     *                           (store_id NULL) plus roles scoped
     *                           to this store. When null, return
     *                           every role regardless of scope.
     * @return array<int,array<string,mixed>>
     */
    public function all(?int $storeId = null): array
    {
        if ($storeId !== null) {
            return $this->db->select(
                "SELECT `id`, `name`, `slug`, `description`, `is_system`, `store_id`
                   FROM `core_roles`
                  WHERE `store_id` IS NULL OR `store_id` = ?
                  ORDER BY `name`",
                [$storeId]
            );
        }
        return $this->db->select(
            "SELECT `id`, `name`, `slug`, `description`, `is_system`, `store_id`
               FROM `core_roles`
              ORDER BY `name`"
        );
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `core_roles` WHERE `slug` = ? LIMIT 1",
            [$slug]
        );
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return $this->db->selectOne(
            "SELECT * FROM `core_roles` WHERE `id` = ? LIMIT 1",
            [$id]
        );
    }

    /**
     * Paginated list of roles with user-assignment counts.
     *
     * @param array<string,string> $filters
     *   q        - name or slug LIKE
     *   type     - 'all' | 'system' | 'custom'
     * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25, ?int $storeId = null): array
    {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildFilters($filters, $storeId);

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `core_roles` WHERE {$where}",
            $params
        );

        $rows = $this->db->select(
            "SELECT r.`id`, r.`name`, r.`slug`, r.`description`, r.`is_system`,
                    r.`store_id`, r.`created_at`,
                    (SELECT COUNT(*) FROM `core_user_roles` ur
                      WHERE ur.`role_id` = r.`id`) AS user_count,
                    (SELECT COUNT(*) FROM `core_role_permissions` rp
                      WHERE rp.`role_id` = r.`id`) AS permission_count
               FROM `core_roles` r
              WHERE {$where}
              ORDER BY r.`is_system` DESC, r.`store_id` IS NULL DESC, r.`name` ASC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items'    => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Group every permission by its module column. Used by the
     * permission matrix editor.
     *
     * @return array<string,array<int,array<string,mixed>>>
     */
    public function permissionsGroupedByModule(): array
    {
        $rows = $this->db->select(
            "SELECT `id`, `slug`, `module`, `description`
               FROM `core_permissions`
              ORDER BY `module`, `slug`"
        );
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['module']][] = $r;
        }
        return $grouped;
    }

    /**
     * Permission IDs currently attached to a role.
     *
     * @return array<int,int>
     */
    public function permissionIdsForRole(int $roleId): array
    {
        if ($roleId <= 0) {
            return [];
        }
        $rows = $this->db->select(
            "SELECT `permission_id` FROM `core_role_permissions`
              WHERE `role_id` = ?",
            [$roleId]
        );
        return array_map(static fn ($r) => (int) $r['permission_id'], $rows);
    }

    /**
     * How many active users have this role assigned.
     */
    public function countUsersForRole(int $roleId): int
    {
        if ($roleId <= 0) {
            return 0;
        }
        return (int) $this->db->scalar(
            "SELECT COUNT(*)
               FROM `core_user_roles` ur
               JOIN `core_users` u ON u.`id` = ur.`user_id`
              WHERE ur.`role_id` = ?
                AND u.`deleted_at` IS NULL",
            [$roleId]
        );
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId !== null && $excludeId > 0) {
            $row = $this->db->selectOne(
                "SELECT 1 FROM `core_roles`
                  WHERE `slug` = ? AND `id` <> ? LIMIT 1",
                [$slug, $excludeId]
            );
        } else {
            $row = $this->db->selectOne(
                "SELECT 1 FROM `core_roles` WHERE `slug` = ? LIMIT 1",
                [$slug]
            );
        }
        return $row !== null;
    }

    // =================================================================
    // Writes
    // =================================================================

    /**
     * Create a custom role. Returns the new role id.
     *
     * $isSystem is always false for controller-driven creates.
     * System roles are only created by seeders.
     */
    /**
     * Create a custom role.
     *
     * $storeId null creates a global role visible to every store.
     * $storeId set creates a role visible only to that store.
     *
     * The controller should pass the current store's id for
     * merchant-created roles. Global roles are reserved for
     * seeders and for super-admin-managed cross-store roles.
     */
    public function create(string $name, string $slug, ?string $description, ?int $storeId = null): int
    {
        return $this->db->insert(
            "INSERT INTO `core_roles` (`store_id`, `name`, `slug`, `description`, `is_system`)
             VALUES (?, ?, ?, ?, 0)",
            [$storeId, $name, $slug, $description]
        );
    }

    /**
     * Update name and description. Slug and is_system are
     * intentionally not updatable from the controller:
     *   - The slug is the stable identifier used by guards and
     *     by the user assignment code (attachRoleBySlug).
     *   - is_system marks roles that cannot be deleted or reduced.
     */
    public function update(int $id, string $name, ?string $description): void
    {
        $this->db->execute(
            "UPDATE `core_roles`
                SET `name` = ?, `description` = ?
              WHERE `id` = ?",
            [$name, $description, $id]
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute(
            "DELETE FROM `core_roles` WHERE `id` = ?",
            [$id]
        );
    }

    /**
     * Replace a role's permission set with the given list.
     * Empty list clears every permission.
     *
     * Wrapped in a transaction so a partial failure does not
     * leave the role with an inconsistent permission set.
     *
     * @param array<int,int> $permissionIds
     */
    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->execute(
                "DELETE FROM `core_role_permissions` WHERE `role_id` = ?",
                [$roleId]
            );

            if ($permissionIds !== []) {
                $values = [];
                $params = [];
                foreach ($permissionIds as $pid) {
                    $values[] = '(?, ?)';
                    $params[] = $roleId;
                    $params[] = $pid;
                }
                $this->db->execute(
                    "INSERT INTO `core_role_permissions` (`role_id`, `permission_id`)
                     VALUES " . implode(', ', $values),
                    $params
                );
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // =================================================================
    // Private
    // =================================================================

    /**
     * @param array<string,string> $filters
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildFilters(array $filters, ?int $storeId = null): array
    {
        $where  = ['1 = 1'];
        $params = [];

        // Store scope. Global roles (store_id IS NULL) always
        // visible. Store-scoped roles visible only when the
        // caller passes a storeId that matches.
        if ($storeId !== null) {
            $where[]  = "(`store_id` IS NULL OR `store_id` = ?)";
            $params[] = $storeId;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[]  = "(`name` LIKE ? OR `slug` LIKE ?)";
            $params[] = $like;
            $params[] = $like;
        }

        $type = (string) ($filters['type'] ?? 'all');
        if ($type === 'system') {
            $where[] = "`is_system` = 1";
        } elseif ($type === 'custom') {
            $where[] = "`is_system` = 0";
        }

        $scope = (string) ($filters['scope'] ?? 'all');
        if ($scope === 'global') {
            $where[] = "`store_id` IS NULL";
        } elseif ($scope === 'this_store') {
            if ($storeId !== null) {
                $where[]  = "`store_id` = ?";
                $params[] = $storeId;
            } else {
                // No store context: this_store scope matches nothing.
                $where[] = "1 = 0";
            }
        }

        return [implode(' AND ', $where), $params];
    }
}
