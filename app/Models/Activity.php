<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Activities\Domain\Enums\ActivityStatus;

#[Fillable(['activity_plan_id', 'creator_id', 'idempotency_key', 'activity_type_id', 'location', 'notes', 'status'])]
class Activity extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ActivityStatus::class,
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ActivityPlan::class, 'activity_plan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'activity_products');
    }

    public function captureSessions(): HasMany
    {
        return $this->hasMany(CaptureSession::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ActivityLocation::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ActivityPhoto::class);
    }
}
