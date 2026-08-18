<?php

declare(strict_types=1);

namespace App\Services;

final class Audit
{
    public static function log(Database $db, ?int $userId, string $action, string $entityType, ?int $entityId, $before = null, $after = null): void
    {
        $db->insert('audit_logs', [
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $before === null ? null : json_encode($before),
            'new_values' => $after === null ? null : json_encode($after),
            'ip_address' => request()->ip(),
            'created_at' => now()->toDateTimeString(),
        ]);
    }
}
