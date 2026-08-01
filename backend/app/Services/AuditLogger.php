<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public static function log(
        ?User $user,
        string $module,
        string $action,
        ?string $description = null,
        ?Model $record = null,
        ?Request $request = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $event = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'event' => $event ?? self::slugify($module).'.'.self::slugify($action),
            'module' => $module,
            'action' => $action,
            'description' => $description,
            'table_name' => $record?->getTable(),
            'record_id' => $record?->getKey(),
            'auditable_type' => $record?->getMorphClass(),
            'auditable_id' => $record?->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    private static function slugify(string $value): string
    {
        return strtolower((string) preg_replace('/[\s.\/]+/', '_', trim($value)));
    }
}
