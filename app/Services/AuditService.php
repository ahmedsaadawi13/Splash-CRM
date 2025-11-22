<?php

/**
 * Audit Service
 *
 * File: app/Services/AuditService.php
 *
 * Provides comprehensive audit logging for all CRM operations:
 * - Logs create, update, delete, restore actions
 * - Tracks old values vs new values
 * - Records changed fields
 * - Captures user, IP address, user agent
 * - Retrieves audit history for any entity
 *
 * @package App\Services
 * @version 1.0.0
 */

namespace App\Services;

use Illuminate\Database\Capsule\Manager as DB;

class AuditService
{
    /**
     * Log an audit entry
     *
     * @param string $auditableType Entity type (e.g., 'lead', 'contact')
     * @param int $auditableId Entity ID
     * @param string $action Action performed (created, updated, deleted, restored)
     * @param array $oldValues Old values (for updates/deletes)
     * @param array $newValues New values (for creates/updates)
     * @param int|null $userId User who performed the action
     * @param int $tenantId Tenant ID
     * @param string|null $ipAddress IP address
     * @param string|null $userAgent User agent string
     */
    public function log(
        string $auditableType,
        int $auditableId,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?int $tenantId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        // Determine changed fields
        $changedFields = [];
        if ($oldValues && $newValues) {
            foreach ($newValues as $key => $value) {
                if (isset($oldValues[$key]) && $oldValues[$key] !== $value) {
                    $changedFields[] = $key;
                }
            }
        }

        DB::table('audit_logs')->insert([
            'tenant_id' => $tenantId ?? $_ENV['CURRENT_TENANT_ID'] ?? 1,
            'uuid' => uuid(),
            'user_id' => $userId,
            'user_name' => $this->getUserName($userId),
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'action' => $action,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'changed_fields' => !empty($changedFields) ? json_encode($changedFields) : null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get user name for audit log
     */
    private function getUserName(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }

        $user = DB::table('users')->find($userId);

        if (!$user) {
            return null;
        }

        return trim("{$user->first_name} {$user->last_name}");
    }

    /**
     * Get audit history for an entity
     */
    public function getHistory(string $auditableType, int $auditableId, int $limit = 50): array
    {
        return DB::table('audit_logs')
            ->where('auditable_type', $auditableType)
            ->where('auditable_id', $auditableId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
