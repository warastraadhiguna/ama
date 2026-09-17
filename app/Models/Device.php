<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Integrity\Domain\Enums\IntegrityStatus;

#[Fillable(['user_id', 'device_uuid', 'app_version', 'os_version', 'manufacturer', 'model', 'integrity_status', 'last_active_at', 'revoked_at'])]
class Device extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'integrity_status' => IntegrityStatus::class,
            'last_active_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
