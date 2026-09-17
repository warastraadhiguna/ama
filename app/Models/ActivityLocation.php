<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Integrity\Domain\Enums\IntegrityStatus;

#[Fillable([
    'activity_id', 'capture_session_id', 'latitude', 'longitude', 'accuracy', 'altitude', 'speed',
    'bearing', 'provider', 'is_mock_location', 'captured_at_device', 'received_at_server',
    'integrity_status', 'anomaly_reasons',
])]
class ActivityLocation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_mock_location' => 'boolean',
            'captured_at_device' => 'datetime',
            'received_at_server' => 'datetime',
            'integrity_status' => IntegrityStatus::class,
            'anomaly_reasons' => 'array',
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
}
