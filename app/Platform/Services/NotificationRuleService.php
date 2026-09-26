<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Database;

/**
 * Resolves notification rules into concrete user IDs.
 * Only generic recipient types are supported here - product-specific
 * types belong to that product's own resolver service.
 */
final class NotificationRuleService
{
    public function __construct(
        private Database $db,
        private ?Auth $auth = null
    ) {
    }

    /** @return int[] */
    public function resolveRecipients(string $eventType, array $context = [], bool $excludeActor = true): array
    {
        $rules = $this->activeRulesFor($eventType);
        if (empty($rules)) return [];

        $userIds = [];
        foreach ($rules as $rule) {
            foreach ($this->resolveOne($rule, $context) as $id) {
                $userIds[$id] = true;
            }
        }

        $ids = array_keys($userIds);

        if ($excludeActor && $this->auth !== null) {
            $actorId = $this->auth->user()?->id;
            if ($actorId !== null) {
                $ids = array_values(array_filter($ids, fn ($id) => $id !== $actorId));
            }
        }

        return array_values($ids);
    }

    /** @return array{inapp: bool, email: bool} */
    public function channelsFor(string $eventType): array
    {
        $rules = $this->activeRulesFor($eventType);
        $inapp = false;
        $email = false;
        foreach ($rules as $r) {
            if (!empty($r['channel_inapp'])) $inapp = true;
            if (!empty($r['channel_email'])) $email = true;
        }
        return ['inapp' => $inapp, 'email' => $email];
    }

    /** @return array<int, array<string,mixed>> */
    private function activeRulesFor(string $eventType): array
    {
        return $this->db->select(
            "SELECT * FROM `core_notification_rules`
             WHERE `event_type` = ?
               AND `is_active` = 1
               AND `deleted_at` IS NULL",
            [$eventType]
        );
    }

    /** @return int[] */
    private function resolveOne(array $rule, array $context): array
    {
        $type  = (string) $rule['recipient_type'];
        $value = $rule['recipient_value'] !== null ? (string) $rule['recipient_value'] : '';

        return match ($type) {
            'role'         => $this->usersWithRole($value),
            'permission'   => $this->usersWithPermission($value),
            'user'         => $this->explicitUser($value),
            'owner'        => $this->owner($context),
            'actor_except' => [],
            default        => [],
        };
    }

    /** @return int[] */
    private function usersWithRole(string $roleSlug): array
    {
        if ($roleSlug === '') return [];
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

    /** @return int[] */
    private function usersWithPermission(string $permissionSlug): array
    {
        if ($permissionSlug === '') return [];
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

    /** @return int[] */
    private function explicitUser(string $userId): array
    {
        $id = (int) $userId;
        if ($id <= 0) return [];
        $active = $this->db->scalar(
            "SELECT `id` FROM `core_users`
             WHERE `id` = ? AND `deleted_at` IS NULL AND `status` = 'active'",
            [$id]
        );
        return $active ? [(int) $active] : [];
    }

    /** @return int[] */
    private function owner(array $context): array
    {
        foreach (['owner_id', 'assigned_to', 'assignee_id'] as $key) {
            $id = (int) ($context[$key] ?? 0);
            if ($id > 0) return [$id];
        }
        return [];
    }
}
