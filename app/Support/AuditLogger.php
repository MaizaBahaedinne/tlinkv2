<?php

namespace App\Support;

use App\Models\AuditLog;

class AuditLogger
{
    public static function record(?int $actorId, string $action, ?string $targetType = null, ?int $targetId = null, array $metadata = [], ?string $ip = null): void
    {
        AuditLog::query()->create([
            'actor_id' => $actorId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => $metadata,
            'ip_address' => $ip,
        ]);
    }
}
