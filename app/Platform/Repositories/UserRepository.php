<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;
use CorporateAid\Platform\Models\User;
use CorporateAid\Platform\Support\Hasher;

/**
 * All reads/writes against the `core_users` table.
 * Nothing else in the app should contain SQL for users.
 */
final class UserRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?User
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_users` WHERE `id` = ? AND `deleted_at` IS NULL LIMIT 1",
            [$id]
        );
        return $row ? User::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_users` WHERE `email` = ? AND `deleted_at` IS NULL LIMIT 1",
            [strtolower(trim($email))]
        );
        return $row ? User::fromRow($row) : null;
    }

    /**
     * Create a new user with a hashed password. Returns the new user's ID.
     *
     * @param array{name:string,email:string,password:string,status?:string,is_client_user?:bool,organisation_id?:?int,phone?:?string,timezone?:string} $data
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO `core_users`
                (`name`, `email`, `password_hash`, `phone`, `timezone`, `status`, `is_client_user`, `organisation_id`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        return $this->db->insert($sql, [
            $data['name'],
            strtolower(trim($data['email'])),
            Hasher::hash($data['password']),
            $data['phone'] ?? null,
            $data['timezone'] ?? 'Africa/Nairobi',
            $data['status'] ?? 'active',
            !empty($data['is_client_user']) ? 1 : 0,
            $data['organisation_id'] ?? null,
        ]);
    }

    public function updatePassword(int $userId, string $newPassword): void
    {
        $this->db->execute(
            "UPDATE `core_users` SET `password_hash` = ? WHERE `id` = ?",
            [Hasher::hash($newPassword), $userId]
        );
    }

    public function updateLastLogin(int $userId): void
    {
        $this->db->execute(
            "UPDATE `core_users` SET `last_login_at` = NOW(), `failed_logins` = 0, `locked_until` = NULL WHERE `id` = ?",
            [$userId]
        );
    }

    /**
     * Update the user's theme preference.
     * Accepts: 'dark', 'light', 'system'.
     */
    public function updateThemePreference(int $userId, string $preference): void
    {
        if (!in_array($preference, ['dark', 'light', 'system'], true)) {
            $preference = 'system';
        }
        $this->db->execute(
            "UPDATE `core_users` SET `theme_preference` = ? WHERE `id` = ?",
            [$preference, $userId]
        );
    }

    public function incrementFailedLogins(int $userId, int $lockThreshold = 5, int $lockMinutes = 15): void
    {
        $this->db->execute(
            "UPDATE `core_users` SET `failed_logins` = `failed_logins` + 1 WHERE `id` = ?",
            [$userId]
        );

        $failed = (int) $this->db->scalar("SELECT `failed_logins` FROM `core_users` WHERE `id` = ?", [$userId]);
        if ($failed >= $lockThreshold) {
            $this->db->execute(
                "UPDATE `core_users` SET `locked_until` = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE `id` = ?",
                [$lockMinutes, $userId]
            );
        }
    }

    public function setStatus(int $userId, string $status): void
    {
        $this->db->execute("UPDATE `core_users` SET `status` = ? WHERE `id` = ?", [$status, $userId]);
    }

    /**
     * Attach a role to a user by role slug. Idempotent.
     */
    public function attachRoleBySlug(int $userId, string $roleSlug): void
    {
        $this->db->execute(
            "INSERT IGNORE INTO `core_user_roles` (`user_id`, `role_id`)
             SELECT ?, `id` FROM `core_roles` WHERE `slug` = ?",
            [$userId, $roleSlug]
        );
    }

    /**
     * Load a user's role slugs into ->roleSlugs (mutates the passed object).
     */
    public function loadRoles(User $user): User
    {
        $rows = $this->db->select(
            "SELECT r.`slug`
             FROM `core_roles` r
             JOIN `core_user_roles` ur ON ur.`role_id` = r.`id`
             WHERE ur.`user_id` = ?",
            [$user->id]
        );
        $user->roleSlugs = array_map(static fn ($r) => (string) $r['slug'], $rows);
        return $user;
    }

    /**
     * Load a user's permission slugs into ->permissionSlugs (mutates the passed object).
     */
    public function loadPermissions(User $user): User
    {
        $rows = $this->db->select(
            "SELECT DISTINCT p.`slug`
             FROM `core_permissions` p
             JOIN `core_role_permissions` rp ON rp.`permission_id` = p.`id`
             JOIN `core_user_roles` ur ON ur.`role_id` = rp.`role_id`
             WHERE ur.`user_id` = ?",
            [$user->id]
        );
        $user->permissionSlugs = array_map(static fn ($r) => (string) $r['slug'], $rows);
        return $user;
    }

    /**
     * Count all non-deleted users.
     */
    public function count(): int
    {
        return (int) $this->db->scalar("SELECT COUNT(*) FROM `core_users` WHERE `deleted_at` IS NULL");
    }

    /**
     * Return active staff users suitable for recruiter assignment.
     * Excludes client users. Returns minimal fields for dropdowns.
     *
     * @return array<int, array<string,mixed>>
     */
    public function listForAssignment(): array
    {
        return $this->db->select(
            "SELECT u.`id`, u.`name`, u.`email`,
                    GROUP_CONCAT(r.`slug` ORDER BY r.`slug` SEPARATOR ',') AS role_slugs
             FROM `core_users` u
             LEFT JOIN `core_user_roles` ur ON ur.`user_id` = u.`id`
             LEFT JOIN `core_roles` r ON r.`id` = ur.`role_id`
             WHERE u.`deleted_at` IS NULL
               AND u.`status` = 'active'
               AND u.`is_client_user` = 0
             GROUP BY u.`id`
             ORDER BY u.`name` ASC"
        );
    }

    /**
     * Return IDs of active users who have the given permission.
     * @return int[]
     */
    public function findActiveUserIdsByPermission(string $permissionSlug): array
    {
        $rows = $this->db->select(
            "SELECT DISTINCT u.`id`
             FROM `core_users` u
             JOIN `core_user_roles` ur ON ur.`user_id` = u.`id`
             JOIN `core_role_permissions` rp ON rp.`role_id` = ur.`role_id`
             JOIN `core_permissions` p ON p.`id` = rp.`permission_id`
             WHERE u.`deleted_at` IS NULL AND u.`status` = 'active'
               AND p.`slug` = ?",
            [$permissionSlug]
        );
        return array_map(fn ($r) => (int) $r['id'], $rows);
    }

    /**
     * Return IDs of active users with a given role slug.
     * @return int[]
     */
    public function findActiveUserIdsByRole(string $roleSlug): array
    {
        $rows = $this->db->select(
            "SELECT DISTINCT u.`id`
             FROM `core_users` u
             JOIN `core_user_roles` ur ON ur.`user_id` = u.`id`
             JOIN `core_roles` r ON r.`id` = ur.`role_id`
             WHERE u.`deleted_at` IS NULL AND u.`status` = 'active'
               AND r.`slug` = ?",
            [$roleSlug]
        );
        return array_map(fn ($r) => (int) $r['id'], $rows);
    }

    // =================================================================
    // Admin user management (Stage 008K.3)
    // =================================================================

    /**
     * Paginated list of users with optional filters.
     *
     * @param array<string,string> $filters
     *   q      - name or email LIKE
     *   status - active | pending | suspended | disabled | all
     * @return array{items:array<int,User>,total:int,page:int,per_page:int,pages:int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildUserFilters($filters);

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `core_users` WHERE {$where}",
            $params
        );

        // Fetch the users (paginated)
        $rows = $this->db->select(
            "SELECT * FROM `core_users`
              WHERE {$where}
              ORDER BY `name` ASC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $items = array_map(static fn ($r) => User::fromRow($r), $rows);

        // Bulk-load role slugs and primary role display name for each
        // user, avoiding N+1. Two queries: one for all role links,
        // one to fetch the roles.
        if ($items !== []) {
            $ids = array_map(static fn (User $u) => $u->id, $items);
            $in  = implode(',', array_fill(0, count($ids), '?'));

            $roleRows = $this->db->select(
                "SELECT ur.`user_id`, r.`slug`, r.`name`
                   FROM `core_user_roles` ur
                   JOIN `core_roles` r ON r.`id` = ur.`role_id`
                  WHERE ur.`user_id` IN ({$in})
                  ORDER BY r.`name` ASC",
                $ids
            );

            $byUser = [];
            foreach ($roleRows as $rr) {
                $uid = (int) $rr['user_id'];
                $byUser[$uid][] = [
                    'slug' => (string) $rr['slug'],
                    'name' => (string) $rr['name'],
                ];
            }
            foreach ($items as $u) {
                $u->roleSlugs = array_map(
                    static fn (array $r) => $r['slug'],
                    $byUser[$u->id] ?? []
                );
            }
            // Stash the display names on the users as a temporary
            // property map for the view to read. The User model has
            // no property for this, so we rely on the view computing
            // a display name from the user's roles via a separate
            // helper. Since we're adding nothing to the model, the
            // view reads roleSlugs only. That is enough for the
            // list — role display names can be joined via the roles
            // map the controller passes.
        }

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Update a user's editable fields (name, phone, timezone).
     * Email is NOT changed here — it is the login identifier.
     */
    public function update(User $user): void
    {
        $this->db->execute(
            "UPDATE `core_users`
                SET `name` = ?, `phone` = ?, `timezone` = ?
              WHERE `id` = ? AND `deleted_at` IS NULL",
            [
                $user->name,
                $user->phone,
                $user->timezone,
                $user->id,
            ]
        );
    }

    /**
     * Remove every role from a user. Used before re-attaching a
     * single role so the user has exactly one.
     */
    public function detachAllRoles(int $userId): void
    {
        $this->db->execute(
            "DELETE FROM `core_user_roles` WHERE `user_id` = ?",
            [$userId]
        );
    }

    /**
     * Return the user's roles with their numeric IDs. The admin edit
     * form uses the id as the value of the role <select>; the slug is
     * for display.
     *
     * @return array<int,array{id:int,slug:string,name:string}>
     */
    public function rolesWithIdsForUser(int $userId): array
    {
        $rows = $this->db->select(
            "SELECT r.`id`, r.`slug`, r.`name`
               FROM `core_roles` r
               JOIN `core_user_roles` ur ON ur.`role_id` = r.`id`
              WHERE ur.`user_id` = ?
              ORDER BY r.`name` ASC",
            [$userId]
        );
        return array_map(static fn ($r) => [
            'id'   => (int) $r['id'],
            'slug' => (string) $r['slug'],
            'name' => (string) $r['name'],
        ], $rows);
    }

    /**
     * Build the WHERE clause for the admin user list.
     *
     * @param array<string,string> $filters
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildUserFilters(array $filters): array
    {
        $where  = ["`deleted_at` IS NULL"];
        $params = [];

        $status = (string) ($filters['status'] ?? 'all');
        if ($status !== 'all' && $status !== '') {
            $where[]  = "`status` = ?";
            $params[] = $status;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[]  = "(`name` LIKE ? OR `email` LIKE ?)";
            $params[] = $like;
            $params[] = $like;
        }

        return [implode(' AND ', $where), $params];
    }
}
