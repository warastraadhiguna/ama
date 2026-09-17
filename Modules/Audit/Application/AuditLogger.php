<?php

namespace Modules\Audit\Application;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

/**
 * docs section 30: "Sistem wajib memiliki audit log untuk aksi penting."
 * A plain injectable service rather than a full Domain/Application/
 * Presentation module — every call site just needs to record one row, no
 * business rule lives here worth a use-case layer of its own.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        ?User $actor,
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
        ]);
    }
}
