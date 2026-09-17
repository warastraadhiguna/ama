<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'activity_id', 'capture_session_id', 'device_id', 'latitude', 'longitude', 'accuracy',
    'captured_at_device', 'received_at_server', 'source', 'sha256_hash', 'file_size',
    'mime_type', 'storage_path', 'integrity_status',
])]
class ActivityPhoto extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'captured_at_device' => 'datetime',
            'received_at_server' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function captureSession(): BelongsTo
    {
        return $this->belongsTo(CaptureSession::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
