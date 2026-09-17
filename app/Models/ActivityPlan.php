<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Planning\Domain\Enums\PlanStatus;

#[Fillable(['creator_id', 'idempotency_key', 'activity_type_id', 'location', 'planned_date', 'notes', 'status', 'realized_activity_id'])]
class ActivityPlan extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'planned_date' => 'date',
            'status' => PlanStatus::class,
        ];
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
        return $this->belongsToMany(Product::class, 'activity_plan_products');
    }

    public function realizedActivity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'realized_activity_id');
    }
}
